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
    available_contact_templates = fields.Many2many('zns.template', string='Available Templates', 
                                                  compute='_compute_available_contact_templates')
    
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for partner in self:
            partner.zns_message_count = len(partner.zns_message_ids)
    
    @api.depends()
    def _compute_available_contact_templates(self):
        """Show templates configured for contact messaging"""
        for partner in self:
            # Find templates configured for contact mapping
            contact_templates = self.env['zns.template'].search([
                ('active', '=', True),
                ('connection_id.active', '=', True),
                ('default_mapping_type', '=', 'contact')
            ])
            
            partner.available_contact_templates = contact_templates
    
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
    
    # Enhanced ZNS Integration Fields
    zns_message_ids = fields.One2many('zns.message', 'sale_order_id', string='ZNS Messages')
    zns_message_count = fields.Integer('ZNS Message Count', compute='_compute_zns_message_count')
    zns_auto_send = fields.Boolean('Auto Send ZNS', default=True, 
                                  help="Automatically send ZNS when order is confirmed")
            
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for order in self:
            order.zns_message_count = len(order.zns_message_ids)
    
    def action_confirm(self):
        """Override to send ZNS automatically when order is confirmed"""
        _logger.info(f"=== CONFIRMING SALE ORDER {self.name} ===")
        
        # Call original confirm method first
        result = super(SaleOrder, self).action_confirm()
        
        # Send ZNS automatically if enabled
        for order in self:
            _logger.info(f"Processing ZNS auto-send for order {order.name}")
            
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
                _logger.info(f"Attempting to send auto ZNS for SO {order.name}")
                order._send_confirmation_zns()
                _logger.info(f"✅ Auto ZNS sent successfully for SO {order.name}")
                
            except Exception as e:
                _logger.error(f"❌ Failed to send auto ZNS for SO {order.name}: {e}")
                # Don't block the confirmation if ZNS fails, just log the error
        
        return result

    def _find_best_template_for_so(self):
        """Find the best template for this Sale Order"""
        _logger.info(f"=== FINDING BEST TEMPLATE FOR SO {self.name} ===")
        
        # 1. PRIORITY: Templates configured for SO mapping type
        so_templates = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True),
            ('default_mapping_type', '=', 'so')
        ], order='id')
        
        if so_templates:
            template = so_templates[0]
            _logger.info(f"✅ Found SO template: {template.name}")
            return template
        
        # 2. FALLBACK: Any active template
        any_template = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True)
        ], limit=1)
        
        if any_template:
            _logger.info(f"⚠️ Using fallback template: {any_template.name}")
            return any_template
        
        _logger.error("❌ No active templates found!")
        return False

    def _send_confirmation_zns(self):
        """Send ZNS notification for order confirmation"""
        _logger.info(f"=== SENDING CONFIRMATION ZNS FOR SO {self.name} ===")
        
        try:
            # Find best template
            template = self._find_best_template_for_so()
            if not template:
                raise Exception("No templates found for Sale Order")
            
            # Check connection
            if not template.connection_id or not template.connection_id.active:
                raise Exception("No active connection found for template")
            
            # Build parameters using template parameter mappings
            params = self.env['zns.helper'].build_sale_order_params(self, template)
            _logger.info(f"✅ Built {len(params)} parameters: {params}")
            
            # Format phone number
            phone = self.env['zns.helper'].format_phone_vietnamese(
                self.partner_id.mobile or self.partner_id.phone
            )
            
            if not phone:
                raise Exception("No valid phone number found")
            
            # Create and send ZNS message
            message_vals = {
                'template_id': template.id,
                'connection_id': template.connection_id.id,
                'phone': phone,
                'parameters': json.dumps(params),
                'partner_id': self.partner_id.id,
                'sale_order_id': self.id,
            }
            
            message = self.env['zns.message'].create(message_vals)
            _logger.info(f"✅ Created ZNS message record: {message.id}")
            
            # Send immediately
            message.send_zns_message()
            _logger.info(f"✅ ZNS sent successfully for SO {self.name}")
                
        except Exception as e:
            _logger.error(f"❌ _send_confirmation_zns failed for SO {self.name}: {e}")
            raise
 
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

    def action_test_auto_send_zns(self):
        """Test the auto-send ZNS functionality (simulation only)"""
        _logger.info(f"=== TESTING AUTO SEND ZNS FOR SO {self.name} ===")
        
        try:
            # Check basic requirements
            if not self.partner_id:
                raise UserError("❌ No customer found")
            
            phone = self.partner_id.mobile or self.partner_id.phone
            if not phone:
                raise UserError("❌ No phone number found for customer")
            
            # Test phone formatting
            formatted_phone = self.env['zns.helper'].format_phone_vietnamese(phone)
            if not formatted_phone:
                raise UserError(f"❌ Cannot format phone number: {phone}")
            
            # Check auto-send setting
            if not self.zns_auto_send:
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
            
            # Find best template with detailed info
            template = self._find_best_template_for_so()
            if not template:
                raise UserError("❌ No templates found. Please create templates in Templates menu")
            
            # Test connection
            if not template.connection_id or not template.connection_id.active:
                raise UserError(f"❌ Template '{template.name}' has no active connection")
            
            # Test parameter building
            params = self.env['zns.helper'].build_sale_order_params(self, template)
            
            # Test connection and token
            access_token = template.connection_id._get_access_token()
            
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '✅ Auto Send Test Successful',
                    'message': f"Auto-send test completed successfully!\n\n"
                             f"Customer: {self.partner_id.name}\n"
                             f"Phone: {phone} → {formatted_phone}\n"
                             f"Template: {template.name} (BOM ID: {template.template_id})\n"
                             f"Type: {template.default_mapping_type}\n"
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

    def action_manual_test_zns(self):
        """Manual test ZNS - actually send a test message"""
        _logger.info(f"=== MANUAL TEST ZNS FOR SO {self.name} ===")
        
        try:
            # Basic validations
            if not self.partner_id:
                raise UserError("❌ No customer found")
            
            phone = self.partner_id.mobile or self.partner_id.phone
            if not phone:
                raise UserError("❌ No phone number found for customer")
            
            # Format phone
            formatted_phone = self.env['zns.helper'].format_phone_vietnamese(phone)
            if not formatted_phone:
                raise UserError(f"❌ Cannot format phone number: {phone}")
            
            # Find best template with detailed logging
            template = self._find_best_template_for_so()
            if not template:
                raise UserError("❌ No templates found. Please create templates in Templates menu")
            
            # Check connection
            if not template.connection_id or not template.connection_id.active:
                raise UserError(f"❌ Template '{template.name}' has no active connection")
            
            # Build parameters
            params = self.env['zns.helper'].build_sale_order_params(self, template)
            
            # Create and send test message
            message_vals = {
                'template_id': template.id,
                'connection_id': template.connection_id.id,
                'phone': formatted_phone,
                'parameters': json.dumps(params),
                'partner_id': self.partner_id.id,
                'sale_order_id': self.id,
            }
            
            message = self.env['zns.message'].create(message_vals)
            message.send_zns_message()
            
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '📱 Manual Test ZNS Sent!',
                    'message': f"Test ZNS message sent successfully!\n\n"
                             f"Customer: {self.partner_id.name}\n"
                             f"Phone: {phone} → {formatted_phone}\n"
                             f"Template: {template.name}\n"
                             f"Parameters: {len(params)} sent",
                    'type': 'success',
                    'sticky': True,
                }
            }
                
        except Exception as e:
            _logger.error(f"❌ Manual test ZNS failed: {e}")
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '❌ Manual Test ZNS Failed',
                    'message': f"Test failed: {str(e)}",
                    'type': 'danger',
                    'sticky': True,
                }
            }


# Add these methods to your AccountMove class in models/res_partner.py

class AccountMove(models.Model):
    _inherit = 'account.move'
    
    zns_message_ids = fields.One2many('zns.message', 'invoice_id', string='ZNS Messages')
    zns_message_count = fields.Integer('ZNS Message Count', compute='_compute_zns_message_count')
    zns_auto_send = fields.Boolean('Auto Send ZNS', default=True, 
                                  help="Automatically send ZNS when invoice is posted")
    zns_best_template_info = fields.Char('Best Template Info', compute='_compute_best_template_info')
    
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for move in self:
            move.zns_message_count = len(move.zns_message_ids)
    
    @api.depends('partner_id', 'amount_total', 'invoice_line_ids', 'state')
    def _compute_best_template_info(self):
        """Show which template would be auto-selected for invoice"""
        for invoice in self:
            try:
                # Only for customer invoices
                if invoice.move_type not in ['out_invoice', 'out_refund']:
                    invoice.zns_best_template_info = "N/A (not customer invoice)"
                    continue
                
                # Check template mappings for invoices
                template_mapping = self.env['zns.template.mapping']._find_best_mapping('account.move', invoice)
                if template_mapping:
                    template = template_mapping.template_id
                    invoice.zns_best_template_info = f"{template.name} (via mapping: {template_mapping.name})"
                else:
                    # Find any active template
                    any_template = self.env['zns.template'].search([
                        ('active', '=', True),
                        ('connection_id.active', '=', True)
                    ], limit=1)
                    if any_template:
                        invoice.zns_best_template_info = f"{any_template.name} (fallback)"
                    else:
                        invoice.zns_best_template_info = "❌ No active templates found"
            except Exception as e:
                invoice.zns_best_template_info = f"Error: {str(e)}"
    
    def action_post(self):
        """Override to send ZNS automatically when invoice is posted"""
        _logger.info(f"=== POSTING INVOICE {self.name} ===")
        
        # Call original post method first
        result = super(AccountMove, self).action_post()
        
        # Send ZNS automatically if enabled
        for invoice in self:
            # Only for customer invoices
            if invoice.move_type not in ['out_invoice', 'out_refund']:
                continue
                
            _logger.info(f"Processing ZNS auto-send for invoice {invoice.name}")
            
            # Check if auto-send is enabled
            if not invoice.zns_auto_send:
                _logger.info(f"Auto-send disabled for invoice {invoice.name}")
                continue
                
            # Check if customer and phone exist
            if not invoice.partner_id:
                _logger.warning(f"No customer for invoice {invoice.name}")
                continue
                
            phone = invoice.partner_id.mobile or invoice.partner_id.phone
            if not phone:
                _logger.warning(f"No phone number for customer {invoice.partner_id.name} in invoice {invoice.name}")
                continue
            
            try:
                _logger.info(f"Attempting to send auto ZNS for invoice {invoice.name}")
                invoice._send_invoice_zns()
                _logger.info(f"✅ Auto ZNS sent successfully for invoice {invoice.name}")
                
            except Exception as e:
                _logger.error(f"❌ Failed to send auto ZNS for invoice {invoice.name}: {e}")
                # Don't block the posting if ZNS fails, just log the error
        
        return result
    
    def _find_best_template_for_invoice(self):
        """Find the best template for this Invoice"""
        _logger.info(f"=== FINDING BEST TEMPLATE FOR INVOICE {self.name} ===")
        
        # 1. PRIORITY: Template mapping first
        template_mapping = self.env['zns.template.mapping']._find_best_mapping('account.move', self)
        if template_mapping:
            template = template_mapping.template_id
            _logger.info(f"✅ Found template via invoice mapping: {template_mapping.name}")
            _logger.info(f"   → Template: {template.name} (BOM ID: {template.template_id})")
            return template
        
        _logger.info("⚠️ No invoice template mapping found, trying any active template...")
        
        # 2. FALLBACK: Any active template
        any_active_templates = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True)
        ], order='id')
        
        if any_active_templates:
            template = any_active_templates[0]
            _logger.info(f"⚠️ Using fallback template: {template.name}")
            return template
        
        _logger.error("❌ No active templates found at all!")
        return False
    
    def action_manual_test_zns(self):
        """Manual test ZNS for invoice"""
        _logger.info(f"=== MANUAL TEST ZNS FOR INVOICE {self.name} ===")
        
        try:
            # Only for customer invoices
            if self.move_type not in ['out_invoice', 'out_refund']:
                raise UserError("❌ ZNS only available for customer invoices")
            
            # Basic validations
            if not self.partner_id:
                raise UserError("❌ No customer found")
            
            phone = self.partner_id.mobile or self.partner_id.phone
            if not phone:
                raise UserError("❌ No phone number found for customer")
            
            # Format phone
            formatted_phone = self.env['zns.helper'].format_phone_vietnamese(phone)
            if not formatted_phone:
                raise UserError(f"❌ Cannot format phone number: {phone}")
            
            # Find best template
            template = self._find_best_template_for_invoice()
            if not template:
                raise UserError("❌ No templates found. Please create templates in Templates menu")
            
            # Check connection
            if not template.connection_id or not template.connection_id.active:
                raise UserError(f"❌ Template '{template.name}' has no active connection")
            
            # Build parameters
            _logger.info(f"Building parameters for template: {template.name}")
            params = self.env['zns.helper'].build_invoice_params(self, template)
            _logger.info(f"Built parameters: {params}")
            
            # Create and send test message
            message_vals = {
                'template_id': template.id,
                'connection_id': template.connection_id.id,
                'phone': formatted_phone,
                'parameters': json.dumps(params),
                'partner_id': self.partner_id.id,
                'invoice_id': self.id,
            }
            
            message = self.env['zns.message'].create(message_vals)
            _logger.info(f"✅ Created test message record: {message.id}")
            
            # Send the message
            result = message.send_zns_message()
            
            success_message = f"Test ZNS message sent successfully!\n\n"
            success_message += f"Customer: {self.partner_id.name}\n"
            success_message += f"Phone: {phone} → {formatted_phone}\n"
            success_message += f"Template: {template.name} (BOM ID: {template.template_id})\n"
            success_message += f"Type: {template.template_type}\n"
            success_message += f"Parameters: {len(params)} sent\n"
            success_message += f"Message ID: {message.message_id or 'Pending'}"
            
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '📱 Manual Test ZNS Sent!',
                    'message': success_message,
                    'type': 'success',
                    'sticky': True,
                }
            }
                
        except Exception as e:
            _logger.error(f"❌ Manual test ZNS failed: {e}")
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '❌ Manual Test ZNS Failed',
                    'message': f"Test failed: {str(e)}\n\nCheck the logs for more details.",
                    'type': 'danger',
                    'sticky': True,
                }
            }
    
    def action_test_auto_send_zns(self):
        """Test the auto-send ZNS functionality for invoice (simulation only)"""
        _logger.info(f"=== TESTING AUTO SEND ZNS FOR INVOICE {self.name} ===")
        
        try:
            # Only for customer invoices
            if self.move_type not in ['out_invoice', 'out_refund']:
                raise UserError("❌ ZNS only available for customer invoices")
            
            # Check basic requirements
            if not self.partner_id:
                raise UserError("❌ No customer found")
            
            phone = self.partner_id.mobile or self.partner_id.phone
            if not phone:
                raise UserError("❌ No phone number found for customer")
            
            # Test phone formatting
            formatted_phone = self.env['zns.helper'].format_phone_vietnamese(phone)
            if not formatted_phone:
                raise UserError(f"❌ Cannot format phone number: {phone}")
            
            # Check auto-send setting
            if not self.zns_auto_send:
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': '⚠️ Auto-send Disabled',
                        'message': 'ZNS auto-send is disabled for this invoice. Enable it in the ZNS Configuration section.',
                        'type': 'warning',
                        'sticky': True,
                    }
                }
            
            # Find best template
            template = self._find_best_template_for_invoice()
            if not template:
                raise UserError("❌ No templates found. Please create templates in Templates menu")
            
            # Test connection
            if not template.connection_id or not template.connection_id.active:
                raise UserError(f"❌ Template '{template.name}' has no active connection")
            
            # Test parameter building
            params = self.env['zns.helper'].build_invoice_params(self, template)
            
            # Test connection and token
            access_token = template.connection_id._get_access_token()
            
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '✅ Auto Send Test Successful',
                    'message': f"Auto-send test completed successfully!\n\n"
                             f"Customer: {self.partner_id.name}\n"
                             f"Phone: {phone} → {formatted_phone}\n"
                             f"Template: {template.name} (BOM ID: {template.template_id})\n"
                             f"Type: {template.template_type}\n"
                             f"Parameters: {len(params)} found\n\n"
                             f"The message will be sent when invoice is posted.",
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
    
    def _send_invoice_zns(self):
        """Send ZNS notification for invoice posting"""
        _logger.info(f"=== SENDING INVOICE ZNS FOR {self.name} ===")
        
        try:
            # Find best template
            template = self._find_best_template_for_invoice()
            if not template:
                raise Exception("No templates found for Invoice")
            
            # Check connection
            if not template.connection_id or not template.connection_id.active:
                raise Exception("No active connection found for template")
            
            # Build parameters
            params = self.env['zns.helper'].build_invoice_params(self, template)
            _logger.info(f"✅ Built {len(params)} parameters: {params}")
            
            # Format phone number
            phone = self.env['zns.helper'].format_phone_vietnamese(
                self.partner_id.mobile or self.partner_id.phone
            )
            
            if not phone:
                raise Exception("No valid phone number found")
            
            # Create and send ZNS message
            message_vals = {
                'template_id': template.id,
                'connection_id': template.connection_id.id,
                'phone': phone,
                'parameters': json.dumps(params),
                'partner_id': self.partner_id.id,
                'invoice_id': self.id,
            }
            
            message = self.env['zns.message'].create(message_vals)
            _logger.info(f"✅ Created ZNS message record: {message.id}")
            
            # Send immediately
            message.send_zns_message()
            _logger.info(f"✅ ZNS sent successfully for invoice {self.name}")
                
        except Exception as e:
            _logger.error(f"❌ _send_invoice_zns failed for invoice {self.name}: {e}")
            raise
    
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
    
    def action_view_zns_messages(self):
        """View ZNS messages for this invoice"""
        return {
            'type': 'ir.actions.act_window',
            'name': f'ZNS Messages - {self.name}',
            'res_model': 'zns.message',
            'view_mode': 'tree,form',
            'domain': [('invoice_id', '=', self.id)],
            'context': {'default_invoice_id': self.id}
        }