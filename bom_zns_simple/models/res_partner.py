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
    
    @api.depends('partner_id')
    def _compute_zns_phone(self):
        for move in self:
            move.zns_phone = move.partner_id.mobile or move.partner_id.phone or ''
    
    
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
    zns_template_info = fields.Char('Template Info', compute='_compute_template_info')
    
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
                template_mapping = self.env['zns.template.mapping']._find_best_mapping('sale.order', order)
                if template_mapping:
                    template = template_mapping.template_id
                    order.zns_best_template_info = f"{template.name} (via mapping: {template_mapping.name})"
                else:
                    # Find ANY active template that has SO parameter mappings
                    templates_with_so_mappings = self.env['zns.template'].search([
                        ('active', '=', True),
                        ('connection_id.active', '=', True),
                        ('parameter_ids.so_field_mapping', '!=', False)  # Has SO field mappings
                    ], limit=1)
                    
                    if templates_with_so_mappings:
                        template = templates_with_so_mappings[0]
                        order.zns_best_template_info = f"{template.name} (has SO mappings)"
                    else:
                        # Last fallback: any active template
                        any_template = self.env['zns.template'].search([
                            ('active', '=', True),
                            ('connection_id.active', '=', True)
                        ], limit=1)
                        if any_template:
                            order.zns_best_template_info = f"{any_template.name} (fallback - no SO mappings)"
                        else:
                            order.zns_best_template_info = "❌ No active templates found"
            except Exception as e:
                order.zns_best_template_info = f"Error: {str(e)}"
    
    @api.depends('partner_id', 'amount_total', 'order_line', 'state')
    def _compute_template_info(self):
        """Compute template info for sale orders"""
        for order in self:
            # Just copy the value from zns_best_template_info
            order.zns_template_info = order.zns_best_template_info

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
        """Find the best template for this Sale Order - USE ANY TEMPLATE CONFIGURED FOR SO"""
        _logger.info(f"=== FINDING BEST TEMPLATE FOR SO {self.name} ===")
        
        # 1. PRIORITY: Template mapping first (configured conditions for SO)
        template_mapping = self.env['zns.template.mapping']._find_best_mapping('sale.order', self)
        if template_mapping:
            template = template_mapping.template_id
            _logger.info(f"✅ Found template via SO mapping: {template_mapping.name}")
            _logger.info(f"   → Template: {template.name} (BOM ID: {template.template_id})")
            _logger.info(f"   → Type: {template.template_type}")
            _logger.info(f"   → Parameters: {len(template.parameter_ids)}")
            return template
        
        _logger.info("⚠️ No SO template mapping found, trying templates with SO parameter mappings...")
        
        # 2. SECOND PRIORITY: Templates that have SO parameter mappings configured
        templates_with_so_mappings = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True),
            ('parameter_ids.so_field_mapping', '!=', False)  # Has SO field mappings
        ], order='id')
        
        _logger.info(f"Templates with SO parameter mappings: {len(templates_with_so_mappings)}")
        for t in templates_with_so_mappings:
            so_params = t.parameter_ids.filtered(lambda p: p.so_field_mapping)
            _logger.info(f"   • {t.name} (BOM ID: {t.template_id}, Type: {t.template_type}, SO params: {len(so_params)})")
        
        if templates_with_so_mappings:
            template = templates_with_so_mappings[0]  # Take first one with SO mappings
            _logger.info(f"✅ Using template with SO mappings: {template.name}")
            return template
        
        _logger.info("⚠️ No templates with SO mappings found, trying any active template...")
        
        # 3. LAST FALLBACK: Any active template (will have 0 parameters but won't crash)
        any_active_templates = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True)
        ], order='id')
        
        _logger.info(f"Any active templates: {len(any_active_templates)}")
        for t in any_active_templates:
            _logger.info(f"   • {t.name} (BOM ID: {t.template_id}, Type: {t.template_type})")
        
        if any_active_templates:
            template = any_active_templates[0]
            _logger.info(f"⚠️ Using fallback template (no SO config): {template.name}")
            return template
        
        _logger.error("❌ No active templates found at all!")
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
            
            # Find best template with detailed logging
            template = self._find_best_template_for_so()
            if not template:
                raise UserError("❌ No templates found. Please create templates in Templates menu")
            
            # Check connection
            if not template.connection_id or not template.connection_id.active:
                raise UserError(f"❌ Template '{template.name}' has no active connection")
            
            # Build parameters with detailed logging
            _logger.info(f"Building parameters for template: {template.name}")
            params = self.env['zns.helper'].build_sale_order_params(self, template)
            _logger.info(f"Built parameters: {params}")
            
            if not params:
                _logger.warning("No parameters built - checking template parameter configuration")
                for param in template.parameter_ids:
                    _logger.info(f"Template param: {param.name} -> SO mapping: {param.so_field_mapping}")
                
                # Show helpful message about parameter configuration
                param_config_msg = f"\n\nTemplate '{template.name}' parameter configuration:\n"
                if template.parameter_ids:
                    for param in template.parameter_ids:
                        mapping = param.so_field_mapping or "❌ Not mapped"
                        param_config_msg += f"• {param.name}: {mapping}\n"
                    param_config_msg += f"\n💡 Go to Templates → {template.name} → Parameters tab to configure SO field mappings"
                else:
                    param_config_msg += f"❌ No parameters found. Click 'Sync Parameters from BOM' in the template."
            
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
            
            if not params:
                success_message += param_config_msg
            
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
            # Get all template mappings for SO
            mappings = self.env['zns.template.mapping'].search([
                ('model', '=', 'sale.order'),
                ('active', '=', True)
            ], order='priority')
            
            mapping_info = []
            for mapping in mappings:
                matches = mapping._matches_conditions(self)
                mapping_info.append(f"• {mapping.name} (Priority: {mapping.priority}) - {'✅ MATCHES' if matches else '❌ No match'}")
            
            # Get templates with SO parameter mappings
            templates_with_so_mappings = self.env['zns.template'].search([
                ('active', '=', True),
                ('parameter_ids.so_field_mapping', '!=', False)
            ])
            
            so_template_info = []
            for template in templates_with_so_mappings:
                so_params = template.parameter_ids.filtered(lambda p: p.so_field_mapping)
                so_template_info.append(f"• {template.name} (BOM ID: {template.template_id}, Type: {template.template_type}, SO params: {len(so_params)})")
            
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
            
            message += f"🗺️ SO Template Mappings ({len(mappings)}):\n"
            message += "\n".join(mapping_info) if mapping_info else "❌ No SO mappings configured"
            message += f"\n\n📋 Templates with SO Parameter Mappings ({len(templates_with_so_mappings)}):\n"
            message += "\n".join(so_template_info) if so_template_info else "❌ No templates have SO parameter mappings configured"
            message += f"\n\n📝 All Active Templates ({len(all_templates)}):\n"
            message += "\n".join(all_template_info) if all_template_info else "❌ No active templates found"
            
            message += f"\n\n✅ Selected Template:\n"
            if selected_template:
                message += f"• {selected_template.name} (BOM ID: {selected_template.template_id}, Type: {selected_template.template_type})"
            else:
                message += "❌ No template would be selected"
            
            message += f"\n\n💡 To configure templates for SO:\n"
            message += f"1. Go to Templates menu\n"
            message += f"2. Create/edit template\n"
            message += f"3. In Parameters tab, set 'Map to SO Field' for each parameter\n"
            message += f"4. Optionally create Template Mappings for conditions"
            
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
        """Send ZNS notification for order confirmation using enhanced template selection"""
        _logger.info(f"=== SENDING CONFIRMATION ZNS FOR SO {self.name} ===")
        
        try:
            # Find best template with detailed logging
            template = self._find_best_template_for_so()
            if not template:
                raise Exception("No templates found for Sale Order")
            
            # Check connection
            if not template.connection_id or not template.connection_id.active:
                raise Exception("No active connection found for template")
            
            # Build parameters using template parameter mappings
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
    
    # ALL ZNS fields that might be referenced in views
    zns_message_ids = fields.One2many('zns.message', 'invoice_id', string='ZNS Messages')
    zns_message_count = fields.Integer('ZNS Message Count', compute='_compute_zns_message_count')
    zns_auto_send = fields.Boolean('Auto Send ZNS', default=False, 
                                  help="Automatically send ZNS when invoice is posted")
    zns_best_template_info = fields.Char('Best Template Info', compute='_compute_best_template_info')
    # ADD THE MISSING FIELD:
    zns_template_info = fields.Char('Template Info', compute='_compute_template_info')
    
    # ADD ALL POSSIBLE FIELDS THAT VIEWS MIGHT REFERENCE:
    zns_last_error = fields.Text('Last ZNS Error', readonly=True)
    zns_last_sent = fields.Datetime('Last ZNS Sent', readonly=True)
    zns_status = fields.Selection([
        ('none', 'No ZNS'),
        ('pending', 'Pending'),
        ('sent', 'Sent'),
        ('failed', 'Failed')
    ], string='ZNS Status', default='none', readonly=True)
    zns_template_id = fields.Many2one('zns.template', string='ZNS Template')
    zns_phone = fields.Char('ZNS Phone', compute='_compute_zns_phone')
    zns_connection_id = fields.Many2one('zns.connection', string='ZNS Connection')
    zns_enabled = fields.Boolean('ZNS Enabled', default=True)
    zns_retry_count = fields.Integer('ZNS Retry Count', default=0, readonly=True)
    
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for move in self:
            move.zns_message_count = len(move.zns_message_ids)
    
    @api.depends('partner_id', 'amount_total', 'move_type')
    def _compute_best_template_info(self):
        """Show which template would be auto-selected for invoices"""
        for move in self:
            try:
                if move.move_type not in ['out_invoice', 'out_refund']:
                    move.zns_best_template_info = "N/A (Not customer invoice)"
                    continue
                    
                # Check if we have template mappings for Invoices
                template_mapping = self.env['zns.template.mapping']._find_best_mapping('account.move', move)
                if template_mapping:
                    template = template_mapping.template_id
                    move.zns_best_template_info = f"{template.name} (via mapping: {template_mapping.name})"
                else:
                    # Find any active template
                    any_template = self.env['zns.template'].search([
                        ('active', '=', True),
                        ('connection_id.active', '=', True)
                    ], limit=1)
                    if any_template:
                        move.zns_best_template_info = f"{any_template.name} (fallback)"
                    else:
                        move.zns_best_template_info = "❌ No active templates found"
            except Exception as e:
                move.zns_best_template_info = f"Error: {str(e)}"
    
    @api.depends('partner_id', 'amount_total', 'move_type')  
    def _compute_template_info(self):
        """Compute template info - this is what the view is looking for"""
        for move in self:
            try:
                if move.move_type not in ['out_invoice', 'out_refund']:
                    move.zns_template_info = "N/A (Not customer invoice)"
                    continue
                    
                # Same logic as _compute_best_template_info
                template_mapping = self.env['zns.template.mapping']._find_best_mapping('account.move', move)
                if template_mapping:
                    template = template_mapping.template_id
                    move.zns_template_info = f"{template.name} (via mapping: {template_mapping.name})"
                else:
                    any_template = self.env['zns.template'].search([
                        ('active', '=', True),
                        ('connection_id.active', '=', True)
                    ], limit=1)
                    if any_template:
                        move.zns_template_info = f"{any_template.name} (fallback)"
                    else:
                        move.zns_template_info = "❌ No active templates found"
            except Exception as e:
                move.zns_template_info = f"Error: {str(e)}"
    
    def _find_best_template_for_invoice(self):
    """Find the best template specifically for Invoices"""
    _logger.info(f"=== Finding template for Invoice {self.name} ===")
    
    # 1. FIRST PRIORITY: Template mappings specifically for Invoices
    try:
        template_mapping = self.env['zns.template.mapping']._find_best_mapping('account.move', self)
        if template_mapping and template_mapping.template_id:
            template = template_mapping.template_id
            _logger.info(f"✅ Found INVOICE template via mapping: {template.name} (BOM ID: {template.template_id})")
            return template
    except Exception as e:
        _logger.warning(f"Template mapping search failed: {e}")
    
    # 2. SECOND PRIORITY: Templates that have invoice-related parameter names
    invoice_param_names = [
        'invoice_number', 'invoice_no', 'invoice_id', 'bill_number', 'due_date', 
        'remaining_amount', 'amount', 'customer_name', 'total_amount'
    ]
    
    templates_with_invoice_params = self.env['zns.template'].search([
        ('active', '=', True),
        ('connection_id.active', '=', True),
        ('parameter_ids.name', 'in', invoice_param_names)
    ], limit=1)
    
    if templates_with_invoice_params:
        template = templates_with_invoice_params
        _logger.info(f"✅ Found template with INVOICE parameters: {template.name} (BOM ID: {template.template_id})")
        return template
    
    # 3. THIRD PRIORITY: Templates with field mappings
    templates_with_field_mappings = self.env['zns.template'].search([
        ('active', '=', True),
        ('connection_id.active', '=', True),
        ('parameter_ids.field_mapping', '!=', False)
    ], limit=1)
    
    if templates_with_field_mappings:
        template = templates_with_field_mappings
        _logger.info(f"✅ Found template with field mappings: {template.name} (BOM ID: {template.template_id})")
        return template
    
    # 4. LAST FALLBACK: Use any active template
    fallback_template = self.env['zns.template'].search([
        ('active', '=', True),
        ('connection_id.active', '=', True)
    ], limit=1)
    
    if fallback_template:
        template = fallback_template
        _logger.warning(f"⚠️ Using fallback template for invoice: {template.name} (BOM ID: {template.template_id})")
        return template
    
    _logger.error("❌ No templates found for Invoice")
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
        

    # Add these methods to your AccountMove class in res_partner.py

    def action_test_auto_send_zns(self):
        """Test auto-send ZNS functionality for invoices"""
        _logger.info(f"=== TESTING AUTO SEND ZNS FOR INVOICE {self.name} ===")
        
        try:
            # Check basic requirements
            if self.move_type not in ['out_invoice', 'out_refund']:
                raise UserError("❌ This is not a customer invoice")
            
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

    def action_manual_test_zns(self):
        """Manual test ZNS - actually send a test message for invoice"""
        _logger.info(f"=== MANUAL TEST ZNS FOR INVOICE {self.name} ===")
        
        try:
            # Basic validations
            if self.move_type not in ['out_invoice', 'out_refund']:
                raise UserError("❌ This is not a customer invoice")
            
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