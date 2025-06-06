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
                template = order._find_best_template_for_so()
                if template:
                    order.zns_best_template_info = f"{template.name} (BOM ID: {template.template_id})"
                else:
                    order.zns_best_template_info = "❌ No suitable templates found"
            except Exception as e:
                order.zns_best_template_info = f"Error: {str(e)}"
    
    def action_confirm(self):
        """Override to send ZNS automatically when order is confirmed"""
        _logger.info(f"=== CONFIRMING SALE ORDER {self.name} ===")
        
        # Call original confirm method first
        result = super(SaleOrder, self).action_confirm()
        
        # Send ZNS automatically if enabled
        for order in self:
            if order.zns_auto_send:
                try:
                    order._send_confirmation_zns()
                    _logger.info(f"✅ Auto ZNS sent successfully for SO {order.name}")
                except Exception as e:
                    _logger.error(f"❌ Failed to send auto ZNS for SO {order.name}: {e}")
        
        return result

    def _find_best_template_for_so(self):
        """Find the best template for Sale Orders - UPDATED: Filter out 'pending' templates"""
        _logger.info(f"=== Finding template for Sale Order {self.name} ===")
        
        # 1. FIRST PRIORITY: Template mappings specifically for Sale Orders
        template_mapping = self.env['zns.template.mapping']._find_best_mapping('sale.order', self)
        if template_mapping and template_mapping.template_id:
            template = template_mapping.template_id
            _logger.info(f"✅ Found SO template via mapping: {template.name} (BOM ID: {template.template_id})")
            return template
        
        # 2. SECOND PRIORITY: Templates configured for Sale Orders (not pending)
        so_templates = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True),
            ('apply_to', '=', 'sale_order')  # Only templates configured for SO
        ], limit=1)
        
        if so_templates:
            template = so_templates
            _logger.info(f"✅ Found SO template configured for sale orders: {template.name} (BOM ID: {template.template_id})")
            return template
        
        # 3. THIRD PRIORITY: Templates with SO-compatible parameter names (not pending)
        so_param_names = [
            'order_id', 'so_no', 'order_number', 'order_date', 'amount', 'total_amount',
            'customer_name', 'product_name', 'salesperson'
        ]
        
        templates_with_so_params = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True),
            ('apply_to', '!=', 'pending'),  # Exclude pending templates
            ('parameter_ids.name', 'in', so_param_names)
        ], limit=1)
        
        if templates_with_so_params:
            template = templates_with_so_params
            _logger.info(f"✅ Found template with SO-compatible parameters: {template.name} (BOM ID: {template.template_id})")
            return template
        
        # 4. NO FALLBACK: Don't use pending templates
        _logger.error("❌ No templates configured for Sale Orders (all templates are 'pending')")
        return False

    def _send_confirmation_zns(self):
        """Send ZNS notification for order confirmation"""
        template = self._find_best_template_for_so()
        if not template:
            raise Exception("No templates configured for Sale Orders. Please set templates to 'Sales Orders' in Templates menu.")
        
        phone = self.env['zns.helper'].format_phone_vietnamese(
            self.partner_id.mobile or self.partner_id.phone
        )
        if not phone:
            raise Exception("No valid phone number found")
        
        # Build parameters using helper - PRESERVE ALL EXISTING FUNCTIONALITY
        params = self.env['zns.helper'].build_sale_order_params(self, template)
        
        # Create and send message
        message = self.env['zns.message'].create({
            'template_id': template.id,
            'connection_id': template.connection_id.id,
            'phone': phone,
            'parameters': json.dumps(params),
            'partner_id': self.partner_id.id,
            'sale_order_id': self.id,
        })
        
        message.send_zns_message()
        _logger.info(f"✅ ZNS sent for SO {self.name}")

    def action_send_zns(self):
        """Open ZNS send wizard for sale order - FIXED METHOD NAME"""
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
                raise UserError("❌ No templates configured for Sale Orders. Please set templates to 'Sales Orders' in Templates menu.")
            
            # Check connection
            if not template.connection_id or not template.connection_id.active:
                raise UserError(f"❌ Template '{template.name}' has no active connection")
            
            # Build parameters with detailed logging
            _logger.info(f"Building parameters for template: {template.name}")
            params = self.env['zns.helper'].build_sale_order_params(self, template)
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
                raise UserError("❌ No templates configured for Sale Orders. Please set templates to 'Sales Orders' in Templates menu.")
            
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


class AccountMove(models.Model):
    _inherit = 'account.move'
    
    zns_message_ids = fields.One2many('zns.message', 'invoice_id', string='ZNS Messages')
    zns_message_count = fields.Integer('ZNS Message Count', compute='_compute_zns_message_count')
    # UPDATED: Default to True for auto-send on invoices
    zns_auto_send = fields.Boolean('Auto Send ZNS', default=True,
                                  help="Automatically send ZNS when invoice is posted")
    
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for move in self:
            move.zns_message_count = len(move.zns_message_ids)
    
    def action_post(self):
        """Override to send ZNS automatically when invoice is posted"""
        _logger.info(f"=== POSTING INVOICE {self.name} ===")
        
        # Call original post method first
        result = super(AccountMove, self).action_post()
        
        # Send ZNS automatically if enabled and this is a customer invoice
        for invoice in self.filtered(lambda inv: inv.move_type in ['out_invoice', 'out_refund']):
            if invoice.zns_auto_send:
                try:
                    invoice._send_invoice_zns()
                    _logger.info(f"✅ Auto ZNS sent successfully for invoice {invoice.name}")
                except Exception as e:
                    _logger.error(f"❌ Failed to send auto ZNS for invoice {invoice.name}: {e}")
        
        return result

    def _find_best_template_for_invoice(self):
        """Find the best template specifically for Invoices - UPDATED: Filter out 'pending' templates"""
        _logger.info(f"=== Finding template for Invoice {self.name} ===")
        
        # 1. FIRST PRIORITY: Template mappings specifically for Invoices
        template_mapping = self.env['zns.template.mapping']._find_best_mapping('account.move', self)
        if template_mapping and template_mapping.template_id:
            template = template_mapping.template_id
            _logger.info(f"✅ Found INVOICE template via mapping: {template.name} (BOM ID: {template.template_id})")
            return template
        
        # 2. SECOND PRIORITY: Templates configured for Invoices (not pending)
        invoice_templates = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True),
            ('apply_to', '=', 'invoice')  # Only templates configured for invoices
        ], limit=1)
        
        if invoice_templates:
            template = invoice_templates
            _logger.info(f"✅ Found INVOICE template configured for invoices: {template.name} (BOM ID: {template.template_id})")
            return template
        
        # 3. THIRD PRIORITY: Templates that have invoice-related parameter names (not pending)
        invoice_param_names = [
            'invoice_number', 'invoice_no', 'invoice_id', 'bill_number', 'due_date', 
            'remaining_amount', 'amount', 'customer_name', 'total_amount'
        ]
        
        templates_with_invoice_params = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True),
            ('apply_to', '!=', 'pending'),  # Exclude pending templates
            ('parameter_ids.name', 'in', invoice_param_names)
        ], limit=1)
        
        if templates_with_invoice_params:
            template = templates_with_invoice_params
            _logger.info(f"✅ Found template with INVOICE parameters: {template.name} (BOM ID: {template.template_id})")
            return template
        
        # 4. NO FALLBACK: Don't use pending templates
        _logger.error("❌ No templates configured for Invoices (all templates are 'pending')")
        return False

    def _send_invoice_zns(self):
        """Send ZNS notification for invoice posting"""
        template = self._find_best_template_for_invoice()
        if not template:
            raise Exception("No templates configured for Invoices. Please set templates to 'Invoices' in Templates menu.")
        
        phone = self.env['zns.helper'].format_phone_vietnamese(
            self.partner_id.mobile or self.partner_id.phone
        )
        if not phone:
            raise Exception("No valid phone number found")
        
        # Build parameters using invoice-specific helper - PRESERVE ALL EXISTING FUNCTIONALITY
        params = self.env['zns.helper'].build_invoice_params(self, template)
        
        # Create and send message
        message = self.env['zns.message'].create({
            'template_id': template.id,
            'connection_id': template.connection_id.id,
            'phone': phone,
            'parameters': json.dumps(params),
            'partner_id': self.partner_id.id,
            'invoice_id': self.id,
        })
        
        message.send_zns_message()
        _logger.info(f"✅ ZNS sent for invoice {self.name}")
    
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
    
    def action_test_auto_send_zns(self):
        """Test the auto-send ZNS functionality for invoice"""
        _logger.info(f"=== TESTING AUTO SEND ZNS FOR INVOICE {self.name} ===")
        
        try:
            # Check basic requirements
            if not self.partner_id:
                raise UserError("❌ No customer found")
            
            if self.move_type not in ['out_invoice', 'out_refund']:
                raise UserError("❌ Only customer invoices support ZNS")
            
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
                raise UserError("❌ No templates configured for invoices. Please set templates to 'Invoices' in Templates menu.")
            
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