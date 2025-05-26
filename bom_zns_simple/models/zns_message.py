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
        """Send ZNS message using correct BOM API format from Postman collection"""
        if self.status == 'sent':
            raise UserError("Message already sent")
        
        connection = self.connection_id
        if not connection:
            raise UserError("No connection configured")
        
        if not connection.api_key:
            raise UserError("No API key configured")
        
        try:
            # Get access token
            access_token = connection._get_access_token()
        except Exception as e:
            self.write({
                'status': 'failed',
                'error_message': f"Failed to get access token: {str(e)}"
            })
            raise UserError(f"❌ Token error: {str(e)}")
        
        # Parse parameters
        try:
            params = json.loads(self.parameters) if self.parameters else {}
        except json.JSONDecodeError:
            params = {}
        
        # Format phone number (remove leading 0, don't add country code if already present)
        phone = self.phone
        if phone.startswith('0'):
            phone = phone  # Keep as is for Vietnamese numbers
        
        # Prepare request exactly as shown in Postman collection
        url = f"{connection.api_base_url}/send-zns-by-template"
        headers = {
            'Authorization': f'Bearer {access_token}',
            'Content-Type': 'application/json'
        }
        
        # Data structure from Postman collection
        data = {
            "phone": phone,
            "params": params,
            "template_id": self.template_id.template_id
        }
        
        try:
            _logger.info(f"Sending ZNS message to: {url}")
            _logger.info(f"Headers: {headers}")
            _logger.info(f"Data: {data}")
            
            response = requests.post(url, headers=headers, json=data, timeout=30)
            
            _logger.info(f"Response status: {response.status_code}")
            _logger.info(f"Response body: {response.text}")
            
            response.raise_for_status()
            result = response.json()
            
            # Check for success (BOM API returns error: 0 for success)
            if result.get('error') == 0:
                message_data = result.get('data', {})
                self.write({
                    'status': 'sent',
                    'message_id': message_data.get('message_id', str(response.status_code)),
                    'sent_date': fields.Datetime.now(),
                    'error_message': False
                })
                
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': 'ZNS Message Sent',
                        'message': f"✅ ZNS message sent successfully!\nMessage ID: {self.message_id}",
                        'type': 'success',
                        'sticky': False,
                    }
                }
            else:
                error_msg = result.get('message', 'Unknown API error')
                self.write({
                    'status': 'failed',
                    'error_message': f"API Error: {error_msg}"
                })
                raise UserError(f"❌ Send failed: {error_msg}")
                
        except requests.exceptions.RequestException as e:
            error_msg = f"Connection error: {str(e)}"
            self.write({
                'status': 'failed',
                'error_message': error_msg
            })
            raise UserError(f"❌ Send failed: {error_msg}")
        except Exception as e:
            error_msg = f"Unexpected error: {str(e)}"
            self.write({
                'status': 'failed',
                'error_message': error_msg
            })
            raise UserError(f"❌ Send failed: {error_msg}")
    
    def test_send_dummy(self):
        """Test send functionality with dummy data"""
        # Create dummy parameters based on Postman collection example
        dummy_params = {
            "customer_name": "Test User",
            "product_name": "Test Product", 
            "so_no": "TEST123",
            "amount": "1"
        }
        
        # Temporarily set parameters for test
        original_params = self.parameters
        self.parameters = json.dumps(dummy_params)
        
        try:
            result = self.send_zns_message()
            return result
        finally:
            # Restore original parameters
            self.parameters = original_params