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
                # Check if we have template mappings for Sale Orders
                if hasattr(self.env['zns.template.mapping'], '_find_best_mapping'):
                    template_mapping = self.env['zns.template.mapping']._find_best_mapping('sale.order', order)
                    if template_mapping:
                        template = template_mapping.template_id
                        order.zns_best_template_info = f"{template.name} (via mapping: {template_mapping.name})"
                        continue
                
                # Find ANY active template that has SO parameter mappings
                templates_with_so_mappings = self.env['zns.template'].search([
                    ('active', '=', True),
                    ('connection_id.active', '=', True),
                ], limit=1)
                
                if templates_with_so_mappings:
                    template = templates_with_so_mappings[0]
                    order.zns_best_template_info = f"{template.name} (active template)"
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
        
        # 1. PRIORITY: Template mapping first (if available)
        try:
            if hasattr(self.env['zns.template.mapping'], '_find_best_mapping'):
                template_mapping = self.env['zns.template.mapping']._find_best_mapping('sale.order', self)
                if template_mapping:
                    template = template_mapping.template_id
                    _logger.info(f"✅ Found template via SO mapping: {template_mapping.name}")
                    return template
        except Exception as e:
            _logger.warning(f"Template mapping search failed: {e}")
        
        _logger.info("⚠️ No SO template mapping found, trying active templates...")
        
        # 2. FALLBACK: Any active template
        any_active_templates = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True)
        ], order='id')
        
        if any_active_templates:
            template = any_active_templates[0]
            _logger.info(f"✅ Using active template: {template.name}")
            return template
        
        _logger.error("❌ No active templates found at all!")
        return False

    def _send_confirmation_zns(self):
        """Send ZNS notification for order confirmation"""
        _logger.info(f"=== SENDING CONFIRMATION ZNS FOR SO {self.name} ===")
        
        try:
            # Find best template with detailed logging
            template = self._find_best_template_for_so()
            if not template:
                raise Exception("No templates found for Sale Order")
            
            # Check connection
            if not template.connection_id or not template.connection_id.active:
                raise Exception("No active connection found for template")
            
            # Build parameters using helper
            params = self.env['zns.helper'].build_sale_order_params(self, template)
            _logger.info(f"✅ Built {len(params)} parameters: {params}")
            
            # Format phone number - KEEP VIETNAMESE FORMAT
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
    
    def action_send_zns(self):
        """Send ZNS - alias for action_send_zns_manual for view compatibility"""
        return self.action_send_zns_manual()
    
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
    zns_template_info = fields.Char('Template Info', compute='_compute_template_info')
    zns_last_error = fields.Text('Last ZNS Error', readonly=True)
    
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for move in self:
            move.zns_message_count = len(move.zns_message_ids)
    
    @api.depends('partner_id', 'amount_total', 'move_type')
    def _compute_template_info(self):
        """Show which template would be used for ZNS"""
        for move in self:
            if move.move_type not in ['out_invoice', 'out_refund']:
                move.zns_template_info = "N/A (not customer invoice)"
                continue
                
            try:
                template = move._find_best_template_for_invoice()
                if template:
                    move.zns_template_info = f"✅ {template.name} (BOM ID: {template.template_id})"
                else:
                    move.zns_template_info = "❌ No template found"
            except Exception as e:
                move.zns_template_info = f"Error: {str(e)}"
    
    def action_post(self):
        """Override to send ZNS automatically when invoice is posted"""
        _logger.info(f"=== POSTING INVOICE(S): {[m.name for m in self]} ===")
        
        # Call original post method first
        result = super(AccountMove, self).action_post()
        
        # Send ZNS automatically for customer invoices
        for move in self:
            _logger.info(f"🔄 Processing ZNS auto-send for invoice {move.name}")
            move._try_auto_send_zns()
        
        return result
    
    def _try_auto_send_zns(self):
        """Try to send auto ZNS with comprehensive error handling"""
        _logger.info(f"=== AUTO ZNS CHECK FOR INVOICE {self.name} ===")
        
        try:
            # Clear previous error
            self.write({'zns_last_error': False})
            
            # STEP 1: Check invoice type
            _logger.info(f"Step 1: Invoice type check - {self.move_type}")
            if self.move_type not in ['out_invoice', 'out_refund']:
                _logger.info(f"❌ Skipping {self.name} - not customer invoice (type: {self.move_type})")
                return
            
            # STEP 2: Check auto-send setting
            _logger.info(f"Step 2: Auto-send setting - {self.zns_auto_send}")
            if not self.zns_auto_send:
                _logger.info(f"❌ Auto-send disabled for invoice {self.name}")
                self.write({'zns_last_error': "Auto-send is disabled for this invoice"})
                return
            
            # STEP 3: Check customer
            _logger.info(f"Step 3: Customer check - {self.partner_id.name if self.partner_id else 'None'}")
            if not self.partner_id:
                error_msg = f"No customer for invoice {self.name}"
                _logger.warning(f"❌ {error_msg}")
                self.write({'zns_last_error': error_msg})
                return
            
            # STEP 4: Check phone number
            phone = self.partner_id.mobile or self.partner_id.phone
            _logger.info(f"Step 4: Phone check - {phone}")
            if not phone:
                error_msg = f"No phone number for customer {self.partner_id.name} in invoice {self.name}"
                _logger.warning(f"❌ {error_msg}")
                self.write({'zns_last_error': error_msg})
                return
            
            # STEP 5: Try to send ZNS
            _logger.info(f"Step 5: Attempting to send ZNS for invoice {self.name}")
            self._send_invoice_zns()
            _logger.info(f"✅ Auto ZNS sent successfully for invoice {self.name}")
            self.write({'zns_last_error': False})
            
        except Exception as e:
            error_msg = f"Failed to send auto ZNS for invoice {self.name}: {str(e)}"
            _logger.error(f"❌ {error_msg}")
            self.write({'zns_last_error': error_msg})
            # Don't block the posting if ZNS fails, just log the error
    
    def _find_best_template_for_invoice(self):
        """Find the best template for this Invoice"""
        _logger.info(f"=== FINDING BEST TEMPLATE FOR INVOICE {self.name} ===")
        
        # 1. PRIORITY: Template mapping first (if available)
        try:
            if hasattr(self.env['zns.template.mapping'], '_find_best_mapping'):
                template_mapping = self.env['zns.template.mapping']._find_best_mapping('account.move', self)
                if template_mapping:
                    template = template_mapping.template_id
                    _logger.info(f"✅ Found template via invoice mapping: {template_mapping.name}")
                    return template
        except Exception as e:
            _logger.warning(f"Template mapping search failed: {e}")
        
        _logger.info("⚠️ No invoice template mapping found, trying active templates...")
        
        # 2. FALLBACK: Any active template
        any_active_templates = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True)
        ], order='id')
        
        if any_active_templates:
            template = any_active_templates[0]
            _logger.info(f"✅ Using active template: {template.name}")
            return template
        
        _logger.error("❌ No active templates found at all!")
        return False
    
    def _send_invoice_zns(self):
        """Send ZNS notification for invoice posting with enhanced error handling"""
        _logger.info(f"=== SENDING ZNS FOR INVOICE {self.name} ===")
        
        # Find best template
        template = self._find_best_template_for_invoice()
        if not template:
            raise Exception("No active templates found for Invoice. Please create templates in ZNS menu.")
        
        # Check connection
        if not template.connection_id or not template.connection_id.active:
            raise Exception(f"Template '{template.name}' has no active connection. Please check connection settings.")
        
        # Build parameters using helper
        params = self.env['zns.helper'].build_invoice_params(self, template)
        _logger.info(f"✅ Built {len(params)} parameters: {params}")
        
        if not params:
            _logger.warning(f"No parameters built for template {template.name}. Check parameter mappings.")
        
        # Format phone number
        phone = self.env['zns.helper'].format_phone_vietnamese(
            self.partner_id.mobile or self.partner_id.phone
        )
        
        if not phone:
            raise Exception(f"Cannot format phone number: {self.partner_id.mobile or self.partner_id.phone}")
        
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
    
    def action_test_zns_template(self):
        """Enhanced test to show step-by-step debugging"""
        test_results = []
        
        try:
            # Test 1: Invoice Type Check
            test_results.append(f"✅ Invoice Type: {self.move_type}")
            if self.move_type not in ['out_invoice', 'out_refund']:
                return self._show_test_results(test_results, "❌ Invoice type not supported", 'warning')
            
            # Test 2: Auto-send Setting
            test_results.append(f"✅ Auto-send enabled: {self.zns_auto_send}")
            if not self.zns_auto_send:
                test_results.append("⚠️ Auto-send is disabled - this is why ZNS didn't send automatically")
            
            # Test 3: Customer Check
            if self.partner_id:
                test_results.append(f"✅ Customer: {self.partner_id.name}")
            else:
                return self._show_test_results(test_results, "❌ No customer found", 'danger')
            
            # Test 4: Phone Check
            phone = self.partner_id.mobile or self.partner_id.phone
            if phone:
                test_results.append(f"✅ Phone: {phone}")
                formatted_phone = self.env['zns.helper'].format_phone_vietnamese(phone)
                if formatted_phone:
                    test_results.append(f"✅ Formatted phone: {formatted_phone}")
                else:
                    return self._show_test_results(test_results, f"❌ Cannot format phone: {phone}", 'danger')
            else:
                return self._show_test_results(test_results, "❌ No phone number found", 'danger')
            
            # Test 5: Template Check
            template = self._find_best_template_for_invoice()
            if template:
                test_results.append(f"✅ Template found: {template.name} (BOM ID: {template.template_id})")
                
                # Test 5a: Connection Check
                if template.connection_id and template.connection_id.active:
                    test_results.append(f"✅ Connection: {template.connection_id.name}")
                    
                    # Test 5b: Connection Token Check
                    try:
                        token = template.connection_id._get_access_token()
                        test_results.append(f"✅ Access token obtained: {token[:30]}...")
                    except Exception as token_error:
                        return self._show_test_results(test_results, f"❌ Token failed: {str(token_error)}", 'danger')
                        
                else:
                    return self._show_test_results(test_results, "❌ No active connection for template", 'danger')
            else:
                return self._show_test_results(test_results, "❌ No template found", 'danger')
            
            # Test 6: Parameter Building
            params = self.env['zns.helper'].build_invoice_params(self, template)
            test_results.append(f"✅ Parameters built: {len(params)} parameters")
            if params:
                param_preview = "\n".join([f"   • {k}: {v}" for k, v in list(params.items())[:5]])
                if len(params) > 5:
                    param_preview += f"\n   • ... and {len(params) - 5} more"
                test_results.append(f"📋 Sample parameters:\n{param_preview}")
            else:
                test_results.append("⚠️ No parameters found - check template parameter mappings")
            
            # Test 7: Full Test Send (if requested)
            return self._show_test_results(test_results, "✅ All checks passed! Ready to send ZNS.", 'success')
            
        except Exception as e:
            return self._show_test_results(test_results, f"❌ Test failed: {str(e)}", 'danger')
    
    def _show_test_results(self, test_results, conclusion, msg_type):
        """Show test results in a notification"""
        message = "\n".join(test_results)
        message += f"\n\n{conclusion}"
        
        if self.zns_last_error:
            message += f"\n\n🔍 Last Error: {self.zns_last_error}"
        
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': '🧪 ZNS Template Test Results',
                'message': message,
                'type': msg_type,
                'sticky': True,
            }
        }
    
    def action_debug_auto_send(self):
        """Debug why auto-send didn't work"""
        debug_info = []
        
        # Check current state
        debug_info.append(f"📋 Invoice: {self.name}")
        debug_info.append(f"📊 State: {self.state}")
        debug_info.append(f"📝 Type: {self.move_type}")
        debug_info.append(f"🔄 Auto-send: {self.zns_auto_send}")
        debug_info.append(f"👤 Customer: {self.partner_id.name if self.partner_id else 'None'}")
        
        if self.partner_id:
            phone = self.partner_id.mobile or self.partner_id.phone
            debug_info.append(f"📞 Phone: {phone if phone else 'None'}")
        
        # Check templates
        templates = self.env['zns.template'].search([('active', '=', True)])
        debug_info.append(f"📋 Active templates: {len(templates)}")
        if templates:
            for template in templates[:3]:
                debug_info.append(f"   • {template.name} (BOM ID: {template.template_id})")
        
        # Check connections
        connections = self.env['zns.connection'].search([('active', '=', True)])
        debug_info.append(f"🔗 Active connections: {len(connections)}")
        
        # Check last error
        if self.zns_last_error:
            debug_info.append(f"\n❌ Last Error:\n{self.zns_last_error}")
        else:
            debug_info.append(f"\n✅ No errors recorded")
        
        # Check ZNS messages
        debug_info.append(f"\n📱 ZNS Messages: {len(self.zns_message_ids)}")
        if self.zns_message_ids:
            latest = self.zns_message_ids[0]
            debug_info.append(f"   Latest: {latest.status} - {latest.create_date}")
        
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': '🔍 Auto-Send Debug Info',
                'message': "\n".join(debug_info),
                'type': 'info',
                'sticky': True,
            }
        }
    
    def action_retry_auto_zns(self):
        """Manually retry auto ZNS sending"""
        try:
            self._try_auto_send_zns()
            if not self.zns_last_error:
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': '✅ ZNS Sent Successfully',
                        'message': f"ZNS message sent for invoice {self.name}",
                        'type': 'success',
                        'sticky': False,
                    }
                }
            else:
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': '❌ ZNS Send Failed',
                        'message': f"Error: {self.zns_last_error}",
                        'type': 'danger',
                        'sticky': True,
                    }
                }
        except Exception as e:
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '❌ ZNS Retry Failed',
                    'message': f"Error: {str(e)}",
                    'type': 'danger',
                    'sticky': True,
                }
            }
    
    def action_send_zns(self):
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
                'default_invoice_id': self.id,
            }
        }