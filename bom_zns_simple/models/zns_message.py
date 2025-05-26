# -*- coding: utf-8 -*-

import json
import logging
import requests
from odoo import models, fields, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class ZnsMessage(models.Model):
    _name = 'zns.message'
    _description = 'ZNS Message History'
    _order = 'create_date desc'
    _rec_name = 'display_name'

    display_name = fields.Char('Display Name', compute='_compute_display_name', store=True)
    template_id = fields.Many2one('zns.template', string='Template', required=True)
    connection_id = fields.Many2one('zns.connection', string='Connection', required=True)
    phone = fields.Char('Phone Number', required=True)
    message_id = fields.Char('Message ID', readonly=True, help='Message ID from BOM API')
    parameters = fields.Text('Parameters', help='JSON parameters sent')
    status = fields.Selection([
        ('draft', 'Draft'),
        ('sent', 'Sent'),
        ('failed', 'Failed')
    ], string='Status', default='draft')
    error_message = fields.Text('Error Message')
    sent_date = fields.Datetime('Sent Date', readonly=True)
    
    # Relations
    partner_id = fields.Many2one('res.partner', string='Contact')
    sale_order_id = fields.Many2one('sale.order', string='Sale Order')
    invoice_id = fields.Many2one('account.move', string='Invoice')
    
    @api.depends('template_id', 'phone', 'create_date')
    def _compute_display_name(self):
        for record in self:
            if record.template_id and record.phone:
                record.display_name = f"{record.template_id.name} - {record.phone}"
            else:
                record.display_name = f"ZNS Message #{record.id}"
    
    def send_zns_message(self):
        """Send ZNS message using correct endpoint"""
        if self.status == 'sent':
            raise UserError("Message already sent")
        
        connection = self.connection_id
        if not connection:
            raise UserError("No connection configured")
        
        access_token = connection._get_access_token()
        # Use correct endpoint from Postman collection
        url = f"{connection.api_base_url}/send-zns-by-template"
        headers = {
            'Content-Type': 'application/json',
            'Authorization': f'Bearer {access_token}'
        }
        
        # Parse parameters
        try:
            params = json.loads(self.parameters) if self.parameters else {}
        except json.JSONDecodeError:
            params = {}
        
        data = {
            'phone': self.phone,
            'template_id': self.template_id.template_id,
            'params': params
        }
        
        try:
            response = requests.post(url, headers=headers, json=data, timeout=30)
            response.raise_for_status()
            
            result = response.json()
            if result.get('error') == 0:
                message_data = result.get('data', {})
                self.write({
                    'status': 'sent',
                    'message_id': message_data.get('message_id'),
                    'sent_date': fields.Datetime.now()
                })
                self.env.user.notify_success(message="ZNS message sent successfully!")
            else:
                self.write({
                    'status': 'failed',
                    'error_message': result.get('message', 'Unknown error')
                })
                raise UserError(f"Send failed: {result.get('message', 'Unknown error')}")
                
        except requests.exceptions.RequestException as e:
            self.write({
                'status': 'failed',
                'error_message': str(e)
            })
            raise UserError(f"Send failed: {str(e)}")