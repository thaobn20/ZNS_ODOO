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
    
    # ZNS Integration Fields - ADD ALL MISSING FIELDS
    zns_message_ids = fields.One2many('zns.message', 'sale_order_id', string='ZNS Messages')
    zns_message_count = fields.Integer('ZNS Message Count', compute='_compute_zns_message_count')
    zns_auto_send = fields.Boolean('Auto Send ZNS', default=True, 
                                  help="Automatically send ZNS when order is confirmed")
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
                # Check if we have zns configuration
                if 'zns.configuration' in self.env:
                    config = self.env['zns.configuration'].get_default_config()
                    template = config.get_template_for_document('sale.order', order)
                    if template:
                        order.zns_best_template_info = f"{template.name} (via configuration)"
                    else:
                        order.zns_best_template_info = "❌ No template configured"
                else:
                    # Fallback: try to find any active template
                    any_template = self.env['zns.template'].search([
                        ('active', '=', True),
                        ('connection_id.active', '=', True)
                    ], limit=1)
                    if any_template:
                        order.zns_best_template_info = f"{any_template.name} (fallback)"
                    else:
                        order.zns_best_template_info = "❌ No active templates found"
            except Exception as e:
                order.zns_best_template_info = f"Error: {str(e)}"
    
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
        
        # 1. Try using zns configuration if it exists
        if 'zns.configuration' in self.env:
            try:
                config = self.env['zns.configuration'].get_default_config()
                template = config.get_template_for_document('sale.order', self)
                if template:
                    _logger.info(f"✅ Found template via configuration: {template.name}")
                    return template
            except Exception as e:
                _logger.warning(f"Configuration method failed: {e}")
        
        # 2. Fallback: Find any active template
        any_active_templates = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True)
        ], order='id')
        
        _logger.info(f"Fallback: Found {len(any_active_templates)} active templates")
        
        if any_active_templates:
            template = any_active_templates[0]
            _logger.info(f"⚠️ Using fallback template: {template.name}")
            return template
        
        _logger.error("❌ No active templates found at all!")
        return False

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
                             f"Type: {template.template_type}\n"
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

    def action_show_template_selection(self):
        """Show template selection logic and available templates"""
        _logger.info(f"=== SHOWING TEMPLATE SELECTION FOR SO {self.name} ===")
        
        try:
            # Get all active templates
            all_templates = self.env['zns.template'].search([('active', '=', True)])
            all_template_info = []
            for template in all_templates:
                all_template_info.append(f"• {template.name} (BOM ID: {template.template_id}, Type: {template.template_type})")
            
            # Find what would be selected
            selected_template = self._find_best_template_for_so()
            
            message = f"📋 Template Selection Logic for SO {self.name}:\n\n"
            message += f"🎯 Order Details:\n"
            message += f"• Customer: {self.partner_id.name}\n"
            message += f"• Amount: {self.amount_total:,.0f} {self.currency_id.name}\n"
            message += f"• Products: {len(self.order_line)}\n\n"
            
            message += f"📝 All Active Templates ({len(all_templates)}):\n"
            message += "\n".join(all_template_info) if all_template_info else "❌ No active templates found"
            
            message += f"\n\n✅ Selected Template:\n"
            if selected_template:
                message += f"• {selected_template.name} (BOM ID: {selected_template.template_id}, Type: {selected_template.template_type})"
            else:
                message += "❌ No template would be selected"
            
            message += f"\n\n💡 To configure templates for SO:\n"
            message += f"1. Go to Templates menu\n"
            message += f"2. Create/edit template\n"
            message += f"3. In Parameters tab, set 'Map to SO Field' for each parameter"
            
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '🔍 Template Selection Logic',
                    'message': message,
                    'type': 'info',
                    'sticky': True,
                }
            }
            
        except Exception as e:
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '❌ Template Selection Error',
                    'message': f"Error analyzing template selection: {str(e)}",
                    'type': 'danger',
                    'sticky': True,
                }
            }
    
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
            
            # Build parameters using helper
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
    
    # ZNS Integration Fields - ADD ALL MISSING FIELDS
    zns_message_ids = fields.One2many('zns.message', 'invoice_id', string='ZNS Messages')
    zns_message_count = fields.Integer('ZNS Message Count', compute='_compute_zns_message_count')
    zns_auto_send = fields.Boolean('Auto Send ZNS', default=True, 
                                  help="Automatically send ZNS when invoice is posted")
    zns_best_template_info = fields.Char('Best Template Info', compute='_compute_best_template_info')
    
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for move in self:
            move.zns_message_count = len(move.zns_message_ids)
    
    @api.depends('partner_id', 'amount_total', 'move_type', 'state')
    def _compute_best_template_info(self):
        """Show which template would be auto-selected"""
        for invoice in self:
            if invoice.move_type not in ['out_invoice', 'out_refund']:
                invoice.zns_best_template_info = False
                continue
                
            try:
                # Check if we have zns configuration
                if 'zns.configuration' in self.env:
                    config = self.env['zns.configuration'].get_default_config()
                    template = config.get_template_for_document('account.move', invoice)
                    if template:
                        invoice.zns_best_template_info = f"{template.name} (via configuration)"
                    else:
                        invoice.zns_best_template_info = "❌ No template configured"
                else:
                    # Fallback: try to find any active template
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
        
        # Send ZNS automatically if enabled for customer invoices
        for invoice in self:
            if invoice.move_type not in ['out_invoice', 'out_refund']:
                continue  # Only for customer invoices
                
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
                invoice._send_posted_zns()
                _logger.info(f"✅ Auto ZNS sent successfully for invoice {invoice.name}")
                
            except Exception as e:
                _logger.error(f"❌ Failed to send auto ZNS for invoice {invoice.name}: {e}")
                # Don't block the posting if ZNS fails, just log the error
        
        return result

    def _find_best_template_for_invoice(self):
        """Find the best template for this Invoice"""
        _logger.info(f"=== FINDING BEST TEMPLATE FOR INVOICE {self.name} ===")
        
        # 1. Try using zns configuration if it exists
        if 'zns.configuration' in self.env:
            try:
                config = self.env['zns.configuration'].get_default_config()
                template = config.get_template_for_document('account.move', self)
                if template:
                    _logger.info(f"✅ Found template via configuration: {template.name}")
                    return template
            except Exception as e:
                _logger.warning(f"Configuration method failed: {e}")
        
        # 2. Fallback: Find any active template
        any_active_templates = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True)
        ], order='id')
        
        _logger.info(f"Fallback: Found {len(any_active_templates)} active templates")
        
        if any_active_templates:
            template = any_active_templates[0]
            _logger.info(f"⚠️ Using fallback template: {template.name}")
            return template
        
        _logger.error("❌ No active templates found at all!")
        return False

    def action_send_zns_manual(self):
        """Manual ZNS sending for invoice"""
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

    def action_test_auto_send_zns(self):
        """Test the auto-send ZNS functionality for invoice"""
        _logger.info(f"=== TESTING AUTO SEND ZNS FOR INVOICE {self.name} ===")
        
        try:
            # Check if this is a customer invoice
            if self.move_type not in ['out_invoice', 'out_refund']:
                raise UserError("❌ ZNS is only available for customer invoices")
            
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
            
            # Find best template with detailed info
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

    def action_show_template_selection(self):
        """Show template selection logic and available templates"""
        _logger.info(f"=== SHOWING TEMPLATE SELECTION FOR INVOICE {self.name} ===")
        
        try:
            # Get all active templates
            all_templates = self.env['zns.template'].search([('active', '=', True)])
            all_template_info = []
            for template in all_templates:
                all_template_info.append(f"• {template.name} (BOM ID: {template.template_id}, Type: {template.template_type})")
            
            # Find what would be selected
            selected_template = self._find_best_template_for_invoice()
            
            message = f"📋 Template Selection Logic for Invoice {self.name}:\n\n"
            message += f"🎯 Invoice Details:\n"
            message += f"• Customer: {self.partner_id.name}\n"
            message += f"• Amount: {self.amount_total:,.0f} {self.currency_id.name}\n"
            message += f"• Type: {self.move_type}\n\n"
            
            message += f"📝 All Active Templates ({len(all_templates)}):\n"
            message += "\n".join(all_template_info) if all_template_info else "❌ No active templates found"
            
            message += f"\n\n✅ Selected Template:\n"
            if selected_template:
                message += f"• {selected_template.name} (BOM ID: {selected_template.template_id}, Type: {selected_template.template_type})"
            else:
                message += "❌ No template would be selected"
            
            message += f"\n\n💡 To configure templates for invoices:\n"
            message += f"1. Go to Templates menu\n"
            message += f"2. Create/edit template\n"
            message += f"3. Configure parameter mappings"
            
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '🔍 Template Selection Logic',
                    'message': message,
                    'type': 'info',
                    'sticky': True,
                }
            }
            
        except Exception as e:
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '❌ Template Selection Error',
                    'message': f"Error analyzing template selection: {str(e)}",
                    'type': 'danger',
                    'sticky': True,
                }
            }

    def _send_posted_zns(self):
        """Send ZNS notification for posted invoice"""
        _logger.info(f"=== SENDING POSTED ZNS FOR INVOICE {self.name} ===")
        
        try:
            # Find best template
            template = self._find_best_template_for_invoice()
            if not template:
                raise Exception("No templates found for Invoice")
            
            # Check connection
            if not template.connection_id or not template.connection_id.active:
                raise Exception("No active connection found for template")
            
            # Build parameters using helper
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
            _logger.error(f"❌ _send_posted_zns failed for invoice {self.name}: {e}")
            raise

    def action_send_zns(self):
        """Legacy method - redirect to manual send"""
        return self.action_send_zns_manual()

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