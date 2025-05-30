# -*- coding: utf-8 -*-

import json
import logging
from odoo import models, fields, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class ResPartner(models.Model):
    _inherit = 'res.partner'
    
    zns_message_ids = fields.One2many('zns.message', 'partner_id', string='ZNS Messages')
    zns_message_count = fields.Integer('ZNS Message Count', compute='_compute_zns_message_count')
    
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for partner in self:
            partner.zns_message_count = len(partner.zns_message_ids)
    
    def action_send_zns(self):
        """Open ZNS send wizard"""
        return {
            'type': 'ir.actions.act_window',
            'name': 'Send ZNS Message',
            'res_model': 'zns.send.wizard',
            'view_mode': 'form',
            'target': 'new',
            'context': {
                'default_partner_id': self.id,
                'default_phone': self.mobile or self.phone,
            }
        }


class SaleOrder(models.Model):
    _inherit = 'sale.order'
    
    # Simple ZNS Integration Fields - No mappings, just direct template
    zns_message_ids = fields.One2many('zns.message', 'sale_order_id', string='ZNS Messages')
    zns_message_count = fields.Integer('ZNS Message Count', compute='_compute_zns_message_count')
    zns_auto_send = fields.Boolean('Auto Send ZNS', default=True, help="Automatically send ZNS when order is confirmed")
    zns_template_id = fields.Many2one('zns.template', string='ZNS Template', 
                                     domain="[('active', '=', True)]",
                                     help="Template will be auto-selected if not specified")
            
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for order in self:
            order.zns_message_count = len(order.zns_message_ids)
    
    @api.onchange('partner_id')
    def _onchange_zns_template(self):
        """Auto-select first available template"""
        if self.partner_id and not self.zns_template_id:
            # Find first active template
            template = self.env['zns.template'].search([
                ('active', '=', True),
                ('template_type', '=', 'transaction')
            ], limit=1)
            if template:
                self.zns_template_id = template
                _logger.info(f"Auto-selected template: {template.name} for SO")
    
    def action_confirm(self):
        """Override to send ZNS automatically when order is confirmed"""
        _logger.info(f"=== CONFIRMING SALE ORDER {self.name} ===")
        
        # Call original confirm method first
        result = super(SaleOrder, self).action_confirm()
        
        # Send ZNS automatically if enabled
        for order in self:
            _logger.info(f"Processing ZNS for order {order.name}")
            
            # Check if auto-send is enabled
            if not order.zns_auto_send:
                _logger.info(f"Auto-send disabled for SO {order.name}")
                continue
                
            # Check if customer and phone exist
            if not order.partner_id:
                _logger.warning(f"No customer for SO {order.name}")
                continue
                
            phone = order.partner_id.mobile or order.partner_id.phone
            if not phone:
                _logger.warning(f"No phone number for customer {order.partner_id.name} in SO {order.name}")
                continue
            
            try:
                _logger.info(f"Attempting to send ZNS for SO {order.name}")
                order._send_confirmation_zns()
                _logger.info(f"✅ ZNS sent successfully for SO {order.name}")
                
            except Exception as e:
                _logger.error(f"❌ Failed to send ZNS for SO {order.name}: {e}")
                _logger.error(f"Error details: {type(e).__name__}: {str(e)}")
                # Don't block the confirmation if ZNS fails, just log the error
        
        return result

    def action_test_auto_send_zns(self):
        """Test the auto-send ZNS functionality - simplified"""
        _logger.info(f"=== TESTING AUTO SEND ZNS FOR SO {self.name} ===")
        
        try:
            # Check basic requirements
            if not self.partner_id:
                raise UserError("❌ No customer found")
            
            phone = self.partner_id.mobile or self.partner_id.phone
            if not phone:
                raise UserError("❌ No phone number found for customer")
            
            _logger.info(f"✅ Customer: {self.partner_id.name}")
            _logger.info(f"✅ Raw phone: {phone}")
            
            # Test phone formatting
            formatted_phone = self.env['zns.helper'].format_phone_number(phone)
            if not formatted_phone:
                raise UserError(f"❌ Cannot format phone number: {phone}. Please use Vietnamese format (0987654321) or international (84987654321)")
            
            _logger.info(f"✅ Formatted phone: {formatted_phone}")
            
            # Check auto-send setting
            if not self.zns_auto_send:
                _logger.warning("⚠️ Auto-send is disabled for this order")
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': '⚠️ Auto-send Disabled',
                        'message': 'ZNS auto-send is disabled for this order. Enable it in the ZNS Configuration section.',
                        'type': 'warning',
                        'sticky': True,
                    }
                }
            
            # Get template - auto-select if not specified
            template = self.zns_template_id
            if not template:
                _logger.info("🔍 No template selected, finding first available template...")
                template = self.env['zns.template'].search([
                    ('active', '=', True),
                    ('template_type', '=', 'transaction')
                ], limit=1)
                
                if template:
                    # Auto-assign for future use
                    self.zns_template_id = template
            
            if not template:
                raise UserError("❌ No ZNS template found. Please create a template in Templates → Template List")
            
            _logger.info(f"✅ Using template: {template.name} (BOM ID: {template.template_id})")
            
            # Test connection
            if not template.connection_id or not template.connection_id.active:
                raise UserError(f"❌ Template '{template.name}' has no active connection")
            
            _logger.info(f"✅ Connection: {template.connection_id.name}")
            
            # Test parameter building
            try:
                params = self.env['zns.helper'].build_sale_order_params(self, template)
                _logger.info(f"✅ Parameters built: {len(params)} parameters")
                for key, value in params.items():
                    _logger.info(f"   • {key}: {value}")
            except Exception as param_error:
                _logger.error(f"❌ Parameter building failed: {param_error}")
                raise UserError(f"❌ Parameter building failed: {param_error}")
            
            # Test connection and token
            try:
                access_token = template.connection_id._get_access_token()
                _logger.info(f"✅ Access token obtained: {access_token[:30]}...")
            except Exception as token_error:
                raise UserError(f"❌ Connection test failed: {token_error}")
            
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '✅ Auto Send Test Successful',
                    'message': f"Auto-send test completed successfully!\n\n"
                             f"Customer: {self.partner_id.name}\n"
                             f"Phone: {phone} → {formatted_phone}\n"
                             f"Template: {template.name}\n"
                             f"Parameters: {len(params)} found\n\n"
                             f"The message will be sent when order is confirmed.",
                    'type': 'success',
                    'sticky': True,
                }
            }
                
        except Exception as e:
            _logger.error(f"❌ Auto-send test failed: {e}")
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '❌ Auto Send Test Failed',
                    'message': f"Test failed: {str(e)}\n\nCheck the logs for more details.",
                    'type': 'danger',
                    'sticky': True,
                }
            }
    
    def _send_confirmation_zns(self):
        """Send ZNS notification for order confirmation - simplified"""
        _logger.info(f"=== SENDING CONFIRMATION ZNS FOR SO {self.name} ===")
        
        try:
            # Get template - auto-select if not specified
            template = self.zns_template_id
            if not template:
                _logger.info("No template selected, finding first available template...")
                template = self.env['zns.template'].search([
                    ('active', '=', True),
                    ('template_type', '=', 'transaction')
                ], limit=1)
                
                if template:
                    self.zns_template_id = template
            
            if not template:
                raise Exception("No ZNS template found")
            
            _logger.info(f"✅ Using template: {template.name} (BOM ID: {template.template_id})")
            
            # Check connection
            if not template.connection_id or not template.connection_id.active:
                raise Exception("No active connection found for template")
            
            _logger.info(f"✅ Using connection: {template.connection_id.name}")
            
            # Build parameters
            try:
                params = self.env['zns.helper'].build_sale_order_params(self, template)
                _logger.info(f"✅ Built {len(params)} parameters: {params}")
            except Exception as param_error:
                _logger.error(f"❌ Failed to build parameters: {param_error}")
                raise Exception(f"Failed to build parameters: {param_error}")
            
            # Format phone number
            phone = self.env['zns.helper'].format_phone_number(
                self.partner_id.mobile or self.partner_id.phone
            )
            
            if not phone:
                raise Exception("No valid phone number found")
            
            _logger.info(f"✅ Formatted phone: {phone}")
            
            # Create and send ZNS message
            message_vals = {
                'template_id': template.id,
                'connection_id': template.connection_id.id,
                'phone': phone,
                'parameters': json.dumps(params),
                'partner_id': self.partner_id.id,
                'sale_order_id': self.id,
            }
            
            _logger.info(f"Creating ZNS message with values: {message_vals}")
            
            message = self.env['zns.message'].create(message_vals)
            _logger.info(f"✅ Created ZNS message record: {message.id}")
            
            # Send immediately
            try:
                message.send_zns_message()
                _logger.info(f"✅ ZNS sent successfully for SO {self.name}")
            except Exception as send_error:
                _logger.error(f"❌ Failed to send ZNS message: {send_error}")
                raise Exception(f"Failed to send ZNS message: {send_error}")
                
        except Exception as e:
            _logger.error(f"❌ _send_confirmation_zns failed for SO {self.name}: {e}")
            raise  # Re-raise the exception so the caller can handle it
 
    def action_send_zns(self):
        """Open ZNS send wizard - Direct method for button calls"""
        return self.action_send_zns_manual()
    
    def action_send_zns_manual(self):
        """Manual ZNS sending with template selection"""
        return {
            'type': 'ir.actions.act_window',
            'name': 'Send ZNS Message',
            'res_model': 'zns.send.wizard',
            'view_mode': 'form',
            'target': 'new',
            'context': {
                'default_partner_id': self.partner_id.id,
                'default_phone': self.partner_id.mobile or self.partner_id.phone,
                'default_sale_order_id': self.id,
                'default_template_id': self.zns_template_id.id if self.zns_template_id else False,
            }
        }
    
    def action_view_zns_messages(self):
        """View ZNS messages for this order"""
        return {
            'type': 'ir.actions.act_window',
            'name': f'ZNS Messages - {self.name}',
            'res_model': 'zns.message',
            'view_mode': 'tree,form',
            'domain': [('sale_order_id', '=', self.id)],
            'context': {'default_sale_order_id': self.id}
        }


class AccountMove(models.Model):
    _inherit = 'account.move'
    
    zns_message_ids = fields.One2many('zns.message', 'invoice_id', string='ZNS Messages')
    zns_message_count = fields.Integer('ZNS Message Count', compute='_compute_zns_message_count')
    
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for move in self:
            move.zns_message_count = len(move.zns_message_ids)
    
    def action_send_zns(self):
        """Open ZNS send wizard for invoice"""
        return {
            'type': 'ir.actions.act_window',
            'name': 'Send ZNS Message',
            'res_model': 'zns.send.wizard',
            'view_mode': 'form',
            'target': 'new',
            'context': {
                'default_partner_id': self.partner_id.id,
                'default_phone': self.partner_id.mobile or self.partner_id.phone,
                'default_invoice_id': self.id,
            }
        }