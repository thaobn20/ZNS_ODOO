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
        """Send ZNS message using correct BOM API format with enhanced error handling"""
        if self.status == 'sent':
            raise UserError("Message already sent")
        
        connection = self.connection_id
        if not connection:
            raise UserError("No connection configured")
        
        if not connection.api_key:
            raise UserError("No API key configured")
        
        _logger.info(f"=== SENDING ZNS MESSAGE ID {self.id} ===")
        _logger.info(f"Template: {self.template_id.name} ({self.template_id.template_id})")
        _logger.info(f"Phone: {self.phone}")
        _logger.info(f"Connection: {connection.name}")
        
        try:
            # Get access token
            _logger.info("Getting access token...")
            access_token = connection._get_access_token()
            _logger.info(f"✅ Access token obtained: {access_token[:30]}...")
        except Exception as e:
            error_msg = f"Failed to get access token: {str(e)}"
            _logger.error(f"❌ {error_msg}")
            self.write({
                'status': 'failed',
                'error_message': error_msg
            })
            raise UserError(f"❌ Token error: {error_msg}")
        
        # Parse parameters
        try:
            params = json.loads(self.parameters) if self.parameters else {}
            _logger.info(f"Parameters: {params}")
        except json.JSONDecodeError as e:
            error_msg = f"Invalid parameters JSON: {str(e)}"
            _logger.error(f"❌ {error_msg}")
            self.write({
                'status': 'failed',
                'error_message': error_msg
            })
            raise UserError(f"❌ {error_msg}")
        
        # Format phone number
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
            _logger.info(f"🚀 Sending ZNS request...")
            _logger.info(f"URL: {url}")
            _logger.info(f"Headers: {headers}")
            _logger.info(f"Data: {data}")
            
            response = requests.post(url, headers=headers, json=data, timeout=30)
            
            _logger.info(f"📨 Response received:")
            _logger.info(f"Status: {response.status_code}")
            _logger.info(f"Body: {response.text}")
            
            response.raise_for_status()
            result = response.json()
            
            # Check for success (BOM API returns error: 0 for success)
            if result.get('error') == 0 or result.get('error') == '0':
                message_data = result.get('data', {})
                message_id = message_data.get('message_id', str(response.status_code))
                
                self.write({
                    'status': 'sent',
                    'message_id': message_id,
                    'sent_date': fields.Datetime.now(),
                    'error_message': False
                })
                
                _logger.info(f"✅ ZNS message sent successfully! Message ID: {message_id}")
                
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': '✅ ZNS Message Sent',
                        'message': f"ZNS message sent successfully!\nMessage ID: {message_id}",
                        'type': 'success',
                        'sticky': False,
                    }
                }
            else:
                error_code = result.get('error', 'unknown')
                error_msg = result.get('message', 'Unknown API error')
                full_error = f"API Error {error_code}: {error_msg}"
                
                _logger.error(f"❌ {full_error}")
                
                self.write({
                    'status': 'failed',
                    'error_message': full_error
                })
                raise UserError(f"❌ Send failed: {full_error}")
                
        except requests.exceptions.RequestException as e:
            error_msg = f"Connection error: {str(e)}"
            _logger.error(f"❌ {error_msg}")
            self.write({
                'status': 'failed',
                'error_message': error_msg
            })
            raise UserError(f"❌ Send failed: {error_msg}")
        except Exception as e:
            error_msg = f"Unexpected error: {str(e)}"
            _logger.error(f"❌ {error_msg}")
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