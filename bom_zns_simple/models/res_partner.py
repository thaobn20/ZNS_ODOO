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
    
    # Enhanced ZNS Integration Fields
    zns_message_ids = fields.One2many('zns.message', 'sale_order_id', string='ZNS Messages')
    zns_message_count = fields.Integer('ZNS Message Count', compute='_compute_zns_message_count')
    zns_best_template_info = fields.Char('Best Template Info', compute='_compute_best_template_info')
            
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for order in self:
            order.zns_message_count = len(order.zns_message_ids)
    
    @api.depends('partner_id', 'amount_total', 'order_line', 'state')
    def _compute_best_template_info(self):
        """Show which template would be auto-selected"""
        for order in self:
            try:
                config = self.env['zns.configuration'].get_default_config()
                template = config.get_template_for_document('sale.order', order)
                if template:
                    order.zns_best_template_info = f"✅ {template.name} (BOM ID: {template.template_id})"
                else:
                    order.zns_best_template_info = "❌ No suitable template found"
            except Exception as e:
                order.zns_best_template_info = f"❌ Error: {str(e)}"
    
    def action_confirm(self):
        """Override to send ZNS automatically when order is confirmed"""
        _logger.info(f"=== CONFIRMING SALE ORDER {self.name} ===")
        
        # Call original confirm method first
        result = super(SaleOrder, self).action_confirm()
        
        # Get configuration using your existing method
        config = self.env['zns.configuration'].get_default_config()
        
        # Send ZNS automatically if enabled
        for order in self:
            # Check if should send using your existing configuration logic
            should_send, reason = config.should_send_zns('sale.order', 'confirmation', order)
            if not should_send:
                _logger.info(f"Not sending ZNS for SO {order.name}: {reason}")
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
        """Find the best template using your existing configuration system"""
        _logger.info(f"=== FINDING BEST TEMPLATE FOR SO {self.name} ===")
        
        # Use your existing configuration system
        config = self.env['zns.configuration'].get_default_config()
        template = config.get_template_for_document('sale.order', self)
        
        if template:
            _logger.info(f"✅ Found template: {template.name}")
            return template
        else:
            _logger.error("❌ No template found!")
            return False

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
            
            # Find best template using your existing system
            template = self._find_best_template_for_so()
            if not template:
                raise UserError("❌ No templates found. Please create templates in Templates menu")
            
            # Check connection
            if not template.connection_id or not template.connection_id.active:
                raise UserError(f"❌ Template '{template.name}' has no active connection")
            
            # Build parameters with safe handling
            _logger.info(f"Building parameters for template: {template.name}")
            try:
                params = self.env['zns.helper'].build_sale_order_params(self, template)
            except Exception as param_error:
                _logger.warning(f"Parameter building failed, using empty params: {param_error}")
                params = {}
            
            _logger.info(f"Built parameters: {params}")
            
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
        """Test the auto-send ZNS functionality (simulation only)"""
        _logger.info(f"=== TESTING AUTO SEND ZNS FOR SO {self.name} ===")
        
        try:
            # Get configuration using your existing system
            config = self.env['zns.configuration'].get_default_config()
            
            # Check if should send using your existing logic
            should_send, reason = config.should_send_zns('sale.order', 'confirmation', self)
            if not should_send:
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': '⚠️ Auto-send Not Configured',
                        'message': f'Auto-send test result: {reason}\n\nConfigure auto-send in Configuration → ZNS Settings.',
                        'type': 'warning',
                        'sticky': True,
                    }
                }
            
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
            
            # Find best template
            template = self._find_best_template_for_so()
            if not template:
                raise UserError("❌ No templates found. Please create templates in Templates menu")
            
            # Test connection
            if not template.connection_id or not template.connection_id.active:
                raise UserError(f"❌ Template '{template.name}' has no active connection")
            
            # Test parameter building
            try:
                params = self.env['zns.helper'].build_sale_order_params(self, template)
            except Exception as param_error:
                params = {}
                _logger.warning(f"Parameter building failed: {param_error}")
            
            # Test connection and token
            access_token = template.connection_id._get_access_token()
            
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '✅ Auto Send Test Successful',
                    'message': f"Auto-send test completed successfully!\n\n"
                             f"🔧 Config: SO auto-send {'enabled' if config.auto_send_so_confirmation else 'disabled'}\n"
                             f"👤 Customer: {self.partner_id.name}\n"
                             f"📞 Phone: {phone} → {formatted_phone}\n"
                             f"📋 Template: {template.name} (BOM ID: {template.template_id})\n"
                             f"📊 Parameters: {len(params)} found\n\n"
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
        """Send ZNS notification for order confirmation using your configuration system"""
        _logger.info(f"=== SENDING CONFIRMATION ZNS FOR SO {self.name} ===")
        
        config = self.env['zns.configuration'].get_default_config()
        
        try:
            # Find best template using your existing system
            template = self._find_best_template_for_so()
            if not template:
                if config.notify_on_send_failure:
                    _logger.warning("No template found for SO ZNS")
                return
            
            # Check connection
            if not template.connection_id or not template.connection_id.active:
                if config.notify_on_send_failure:
                    _logger.warning("No active connection found for template")
                return
            
            # Build parameters safely
            try:
                params = self.env['zns.helper'].build_sale_order_params(self, template)
            except Exception as param_error:
                params = {}
                _logger.warning(f"Parameter building failed: {param_error}")
            
            # Format phone number
            phone = self.env['zns.helper'].format_phone_vietnamese(
                self.partner_id.mobile or self.partner_id.phone
            )
            
            if not phone:
                _logger.warning("No valid phone number found")
                return
            
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
            if config.notify_on_send_failure:
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


class AccountMove(models.Model):
    _inherit = 'account.move'
    
    zns_message_ids = fields.One2many('zns.message', 'invoice_id', string='ZNS Messages')
    zns_message_count = fields.Integer('ZNS Message Count', compute='_compute_zns_message_count')
    zns_best_template_info = fields.Char('Best Template Info', compute='_compute_best_template_info')
    
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for move in self:
            move.zns_message_count = len(move.zns_message_ids)
    
    @api.depends('partner_id', 'amount_total', 'invoice_line_ids', 'state')
    def _compute_best_template_info(self):
        """Show which template would be auto-selected for invoices"""
        for invoice in self:
            try:
                config = self.env['zns.configuration'].get_default_config()
                template = config.get_template_for_document('account.move', invoice)
                if template:
                    invoice.zns_best_template_info = f"✅ {template.name} (BOM ID: {template.template_id})"
                else:
                    invoice.zns_best_template_info = "❌ No suitable template found"
            except Exception as e:
                invoice.zns_best_template_info = f"❌ Error: {str(e)}"

    def _find_best_template_for_invoice(self):
        """Find the best template using your existing configuration system"""
        _logger.info(f"=== FINDING BEST TEMPLATE FOR INVOICE {self.name} ===")
        
        # Use your existing configuration system
        config = self.env['zns.configuration'].get_default_config()
        template = config.get_template_for_document('account.move', self)
        
        if template:
            _logger.info(f"✅ Found template: {template.name}")
            return template
        else:
            _logger.error("❌ No template found!")
            return False
    
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
    
    def action_manual_test_invoice_zns(self):
        """Manual test ZNS for invoice - actually send a test message"""
        _logger.info(f"=== MANUAL TEST INVOICE ZNS FOR {self.name} ===")
        
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
            
            # Find best template using your existing system
            template = self._find_best_template_for_invoice()
            if not template:
                raise UserError("❌ No templates found. Please create templates in Templates menu")
            
            # Check connection
            if not template.connection_id or not template.connection_id.active:
                raise UserError(f"❌ Template '{template.name}' has no active connection")
            
            # Build parameters with safe handling
            _logger.info(f"Building parameters for template: {template.name}")
            try:
                params = self.env['zns.helper'].build_invoice_params(self, template)
            except Exception as param_error:
                _logger.warning(f"Parameter building failed, using empty params: {param_error}")
                params = {}
            
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
            _logger.info(f"✅ Created test invoice message record: {message.id}")
            
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
                    'title': '📱 Manual Test Invoice ZNS Sent!',
                    'message': success_message,
                    'type': 'success',
                    'sticky': True,
                }
            }
                
        except Exception as e:
            _logger.error(f"❌ Manual test invoice ZNS failed: {e}")
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '❌ Manual Test Invoice ZNS Failed',
                    'message': f"Test failed: {str(e)}\n\nCheck the logs for more details.",
                    'type': 'danger',
                    'sticky': True,
                }
            }

    def action_test_auto_send_invoice_zns(self):
        """Test the auto-send ZNS functionality for invoice (simulation only)"""
        _logger.info(f"=== TESTING AUTO SEND INVOICE ZNS FOR {self.name} ===")
        
        try:
            # Get configuration using your existing system
            config = self.env['zns.configuration'].get_default_config()
            
            # Check if should send using your existing logic
            should_send, reason = config.should_send_zns('account.move', 'posted', self)
            if not should_send:
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': '⚠️ Auto-send Not Configured',
                        'message': f'Auto-send test result: {reason}\n\nConfigure auto-send in Configuration → ZNS Settings.',
                        'type': 'warning',
                        'sticky': True,
                    }
                }
            
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
            
            # Find best template
            template = self._find_best_template_for_invoice()
            if not template:
                raise UserError("❌ No templates found. Please create templates in Templates menu")
            
            # Test connection
            if not template.connection_id or not template.connection_id.active:
                raise UserError(f"❌ Template '{template.name}' has no active connection")
            
            # Test parameter building
            try:
                params = self.env['zns.helper'].build_invoice_params(self, template)
            except Exception as param_error:
                params = {}
                _logger.warning(f"Parameter building failed: {param_error}")
            
            # Test connection and token
            access_token = template.connection_id._get_access_token()
            
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '✅ Auto Send Test Successful',
                    'message': f"Auto-send test completed successfully!\n\n"
                             f"🔧 Config: Invoice auto-send {'enabled' if config.auto_send_invoice_posted else 'disabled'}\n"
                             f"👤 Customer: {self.partner_id.name}\n"
                             f"📞 Phone: {phone} → {formatted_phone}\n"
                             f"📋 Template: {template.name} (BOM ID: {template.template_id})\n"
                             f"📊 Parameters: {len(params)} found\n\n"
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