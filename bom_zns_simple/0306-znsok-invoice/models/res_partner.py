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
        """Open ZNS send wizard for contact"""
        return {
            'type': 'ir.actions.act_window',
            'name': 'Send ZNS Message',
            'res_model': 'zns.send.wizard',
            'view_mode': 'form',
            'target': 'new',
            'context': {
                'default_partner_id': self.id,
                'default_phone': self.mobile or self.phone,
                'default_document_type': 'contact',
            }
        }
    
    def action_view_zns_messages(self):
        """View ZNS messages for this contact"""
        return {
            'type': 'ir.actions.act_window',
            'name': f'ZNS Messages - {self.name}',
            'res_model': 'zns.message',
            'view_mode': 'tree,form',
            'domain': [('partner_id', '=', self.id)],
            'context': {'default_partner_id': self.id}
        }


class SaleOrder(models.Model):
    _inherit = 'sale.order'
    
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
        result = super(SaleOrder, self).action_confirm()
        
        for order in self:
            if order.zns_auto_send:
                try:
                    order._send_confirmation_zns()
                except Exception as e:
                    _logger.error(f"Failed to send ZNS for SO {order.name}: {e}")
        
        return result
    
    def _send_confirmation_zns(self):
        """Send ZNS notification for order confirmation"""
        # Find template for Sales Orders
        template = self.env['zns.template'].search([
            ('apply_to', 'in', ['sale_order', 'all']),
            ('active', '=', True)
        ], limit=1)
        
        if not template:
            _logger.warning(f"No ZNS template found for Sales Orders")
            return
        
        phone = self.env['zns.helper'].format_phone_vietnamese(
            self.partner_id.mobile or self.partner_id.phone
        )
        
        if not phone:
            _logger.warning(f"No phone number for customer {self.partner_id.name}")
            return
        
        # Build parameters using template field mappings
        params = {}
        for param in template.parameter_ids:
            value = param.get_mapped_value(self)
            if value:
                params[param.name] = str(value)
        
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
    
    def action_send_zns(self):
        """Manual ZNS sending for SO - FIXED method name"""
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
                'default_document_type': 'sale_order',
            }
        }
    
    def action_send_zns_manual(self):
        """Alias for backward compatibility - ADDED MISSING METHOD"""
        return self.action_send_zns()
    
    def action_test_auto_send_zns(self):
        """Test auto-send ZNS functionality - ADDED MISSING METHOD"""
        try:
            # Test basic validations
            if not self.partner_id:
                raise UserError("❌ No customer found")
            
            phone = self.partner_id.mobile or self.partner_id.phone
            if not phone:
                raise UserError("❌ No phone number found for customer")
            
            # Test phone formatting
            formatted_phone = self.env['zns.helper'].format_phone_vietnamese(phone)
            if not formatted_phone:
                raise UserError(f"❌ Cannot format phone number: {phone}")
            
            # Find template
            template = self.env['zns.template'].search([
                ('apply_to', 'in', ['sale_order', 'all']),
                ('active', '=', True)
            ], limit=1)
            
            if not template:
                raise UserError("❌ No ZNS template found for Sales Orders")
            
            # Test connection
            if not template.connection_id or not template.connection_id.active:
                raise UserError(f"❌ Template '{template.name}' has no active connection")
            
            # Test parameter building
            params = {}
            for param in template.parameter_ids:
                value = param.get_mapped_value(self)
                if value:
                    params[param.name] = str(value)
            
            # Test access token
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
    zns_auto_send = fields.Boolean('Auto Send ZNS', default=True, 
                                  help="Automatically send ZNS when invoice is posted")
    
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for move in self:
            move.zns_message_count = len(move.zns_message_ids)
    
    def action_post(self):
        """Override to send ZNS automatically when invoice is posted"""
        result = super(AccountMove, self).action_post()
        
        for invoice in self:
            # Only for customer invoices
            if invoice.move_type in ['out_invoice', 'out_refund'] and invoice.zns_auto_send:
                try:
                    invoice._send_invoice_zns()
                except Exception as e:
                    _logger.error(f"Failed to send ZNS for invoice {invoice.name}: {e}")
        
        return result
    
    def write(self, vals):
        """Override to send ZNS on payment status changes"""
        result = super(AccountMove, self).write(vals)
        
        # Send ZNS when invoice is fully paid
        if 'amount_residual' in vals:
            for invoice in self:
                if (invoice.move_type in ['out_invoice', 'out_refund'] and 
                    invoice.payment_state == 'paid' and 
                    invoice.zns_auto_send):
                    try:
                        invoice._send_payment_confirmation_zns()
                    except Exception as e:
                        _logger.error(f"Failed to send payment ZNS for {invoice.name}: {e}")
        
        return result
    
    def _send_invoice_zns(self):
        """Send ZNS notification for invoice posting"""
        template = self._find_invoice_template('invoice_posted')
        if template:
            self._send_zns_with_template(template)
    
    def _send_payment_confirmation_zns(self):
        """Send ZNS notification for payment confirmation"""
        template = self._find_invoice_template('payment_received')
        if template:
            self._send_zns_with_template(template)
    
    def _find_invoice_template(self, event_type):
        """Find appropriate template for invoice events"""
        # Try to find specific template for invoice event
        template = self.env['zns.template'].search([
            ('apply_to', 'in', ['invoice', 'all']),
            ('active', '=', True),
            ('template_type', '=', 'transaction')  # Prefer transaction type for invoices
        ], limit=1)
        
        if not template:
            _logger.warning(f"No ZNS template found for invoices")
        
        return template
    
    def _send_zns_with_template(self, template):
        """Send ZNS message using specified template"""
        phone = self.env['zns.helper'].format_phone_vietnamese(
            self.partner_id.mobile or self.partner_id.phone
        )
        
        if not phone:
            _logger.warning(f"No phone number for customer {self.partner_id.name}")
            return
        
        # Build parameters using template field mappings
        params = {}
        for param in template.parameter_ids:
            value = param.get_mapped_value(self)
            if value:
                params[param.name] = str(value)
        
        # Create and send message
        message = self.env['zns.message'].create({
            'template_id': template.id,
            'connection_id': template.connection_id.id,
            'phone': phone,
            'parameters': json.dumps(params),
            'partner_id': self.partner_id.id,
            'invoice_id': self.id,
        })
        
        try:
            message.send_zns_message()
            _logger.info(f"ZNS sent successfully for invoice {self.name}")
        except Exception as e:
            _logger.error(f"Failed to send ZNS for invoice {self.name}: {e}")
            raise
    
    def action_send_zns(self):
        """Manual ZNS sending for Invoice"""
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
                'default_document_type': 'invoice',
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
    
    def action_debug_zns(self):
        """Debug ZNS functionality for Invoice"""
        try:
            # Test template finding
            template = self._find_invoice_template('test')
            if not template:
                raise UserError("❌ No template found for invoices")
            
            # Test phone formatting
            phone = self.env['zns.helper'].format_phone_vietnamese(
                self.partner_id.mobile or self.partner_id.phone
            )
            if not phone:
                raise UserError("❌ No valid phone number found")
            
            # Test parameter building
            params = {}
            for param in template.parameter_ids:
                value = param.get_mapped_value(self)
                if value:
                    params[param.name] = str(value)
            
            # Test connection
            if not template.connection_id:
                raise UserError("❌ No connection configured for template")
            
            access_token = template.connection_id._get_access_token()
            
            debug_info = f"""✅ ZNS Debug Results for Invoice {self.name}:

📋 Template: {template.name} (ID: {template.template_id})
📞 Phone: {self.partner_id.mobile or self.partner_id.phone} → {phone}
👤 Customer: {self.partner_id.name}
🔗 Connection: {template.connection_id.name}
🔑 Token: {access_token[:30]}...

📊 Parameters ({len(params)} found):
{chr(10).join([f"• {k}: {v}" for k, v in params.items()])}

🎯 Auto-send enabled: {self.zns_auto_send}
📄 Invoice type: {self.move_type}
💰 Amount: {self.amount_total} {self.currency_id.name}
📅 Invoice date: {self.invoice_date}
"""
            
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '🔍 ZNS Debug Complete',
                    'message': debug_info,
                    'type': 'success',
                    'sticky': True,
                }
            }
            
        except Exception as e:
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '❌ ZNS Debug Failed',
                    'message': f"Debug failed: {str(e)}\n\nCheck logs for details.",
                    'type': 'danger',
                    'sticky': True,
                }
            }
    
    def action_test_zns_send(self):
        """Test ZNS sending for Invoice"""
        try:
            template = self._find_invoice_template('test')
            if not template:
                raise UserError("❌ No template found for invoices")
            
            self._send_zns_with_template(template)
            
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '✅ Test ZNS Sent',
                    'message': f"Test ZNS message sent successfully for invoice {self.name}!",
                    'type': 'success',
                    'sticky': False,
                }
            }
            
        except Exception as e:
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '❌ Test ZNS Failed',
                    'message': f"Test failed: {str(e)}",
                    'type': 'danger',
                    'sticky': True,
                }
            }