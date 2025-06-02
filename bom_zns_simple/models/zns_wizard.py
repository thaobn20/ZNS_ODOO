# -*- coding: utf-8 -*-

import json
import logging
from odoo import models, fields, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class ZnsSendWizard(models.TransientModel):
    _name = 'zns.send.wizard'
    _description = 'Send ZNS Message Wizard'

    # Template Selection - Simplified
    template_id = fields.Many2one('zns.template', string='Template', required=True,
                                 domain="[('active', '=', True), ('connection_id', '!=', False)]")
    connection_id = fields.Many2one('zns.connection', string='Connection', 
                                   related='template_id.connection_id', readonly=True)
    
    # Recipient Info
    phone = fields.Char('Phone Number', required=True)
    partner_id = fields.Many2one('res.partner', string='Contact')
    
    # Parameters
    parameter_ids = fields.One2many('zns.send.wizard.parameter', 'wizard_id', string='Parameters')
    
    # Context fields
    sale_order_id = fields.Many2one('sale.order', string='Sale Order')
    invoice_id = fields.Many2one('account.move', string='Invoice')
    
    # Preview fields
    preview_message = fields.Text('Message Preview', readonly=True)
    show_preview = fields.Boolean('Show Preview', default=False)
    

    @api.model
    def default_get(self, fields_list):
        """Set default values and auto-fill parameters - ENHANCED VERSION"""
        defaults = super(ZnsSendWizard, self).default_get(fields_list)
        
        # If coming from Sale Order
        if self.env.context.get('default_sale_order_id'):
            sale_order = self.env['sale.order'].browse(self.env.context['default_sale_order_id'])
            if sale_order and sale_order.partner_id:
                # Set phone from partner
                if not defaults.get('phone'):
                    phone = sale_order.partner_id.mobile or sale_order.partner_id.phone
                    if phone:
                        defaults['phone'] = self.env['zns.helper'].format_phone_number(phone)
                defaults['partner_id'] = sale_order.partner_id.id
                
                # Get template - Use the WORKING template selection logic
                template = self._find_best_template_for_sale_order(sale_order)
                
                if template and template.exists():
                    defaults['template_id'] = template.id
                    # Set parameters with auto-filled values using ENHANCED mapping
                    param_values = []
                    for param in template.parameter_ids:
                        param_title = param.title or param.name or 'Parameter'
                        value = param.default_value or ''
                        
                        # Use ENHANCED mapping system
                        try:
                            mapped_value = param.get_mapped_value_for_record(sale_order)
                            if mapped_value:
                                value = str(mapped_value)
                        except Exception as e:
                            _logger.warning(f"Error getting enhanced mapped value for {param.name}: {e}")
                        
                        # Fallback to old SO mapping if new system fails
                        if not value and hasattr(param, 'so_field_mapping') and param.so_field_mapping:
                            try:
                                mapped_value = param.get_mapped_value(sale_order)
                                if mapped_value:
                                    value = str(mapped_value)
                            except Exception as e:
                                _logger.warning(f"Error getting SO mapped value for {param.name}: {e}")
                        
                        # If no mapping, try standard parameters
                        if not value and param.name:
                            try:
                                standard_params = self.env['zns.helper'].build_sale_order_params(sale_order, template)
                                if param.name in standard_params:
                                    value = str(standard_params[param.name])
                            except Exception as e:
                                _logger.warning(f"Error building standard params: {e}")
                        
                        param_values.append((0, 0, {
                            'parameter_id': param.id,
                            'name': param.name,
                            'title': param_title,
                            'param_type': param.param_type,
                            'required': param.required,
                            'value': value,
                        }))
                    
                    if param_values:
                        defaults['parameter_ids'] = param_values
        
        # If coming from Invoice
        elif self.env.context.get('default_invoice_id'):
            invoice = self.env['account.move'].browse(self.env.context['default_invoice_id'])
            if invoice and invoice.partner_id:
                # Set phone from partner
                if not defaults.get('phone'):
                    phone = invoice.partner_id.mobile or invoice.partner_id.phone
                    if phone:
                        defaults['phone'] = self.env['zns.helper'].format_phone_number(phone)
                defaults['partner_id'] = invoice.partner_id.id
                
                # Get template for invoice
                template = self._find_best_template_for_invoice(invoice)
                
                if template and template.exists():
                    defaults['template_id'] = template.id
                    # Set parameters with auto-filled values for INVOICE
                    param_values = []
                    for param in template.parameter_ids:
                        param_title = param.title or param.name or 'Parameter'
                        value = param.default_value or ''
                        
                        # Use ENHANCED mapping system for invoice
                        try:
                            mapped_value = param.get_mapped_value_for_record(invoice)
                            if mapped_value:
                                value = str(mapped_value)
                        except Exception as e:
                            _logger.warning(f"Error getting enhanced invoice mapped value for {param.name}: {e}")
                        
                        # Fallback to standard parameters
                        if not value and param.name:
                            try:
                                standard_params = self.env['zns.helper'].build_invoice_params(invoice, template)
                                if param.name in standard_params:
                                    value = str(standard_params[param.name])
                            except Exception as e:
                                _logger.warning(f"Error building invoice standard params: {e}")
                        
                        param_values.append((0, 0, {
                            'parameter_id': param.id,
                            'name': param.name,
                            'title': param_title,
                            'param_type': param.param_type,
                            'required': param.required,
                            'value': value,
                        }))
                    
                    if param_values:
                        defaults['parameter_ids'] = param_values
        
        # If coming from Contact (direct contact)
        elif self.env.context.get('default_partner_id') and not self.env.context.get('default_sale_order_id') and not self.env.context.get('default_invoice_id'):
            partner = self.env['res.partner'].browse(self.env.context['default_partner_id'])
            if partner:
                # Set phone from partner
                if not defaults.get('phone'):
                    phone = partner.mobile or partner.phone
                    if phone:
                        defaults['phone'] = self.env['zns.helper'].format_phone_number(phone)
                defaults['partner_id'] = partner.id
                
                # Get template for contact
                template = self._find_best_template_for_contact(partner)
                
                if template and template.exists():
                    defaults['template_id'] = template.id
                    # Set parameters with auto-filled values for CONTACT
                    param_values = []
                    for param in template.parameter_ids:
                        param_title = param.title or param.name or 'Parameter'
                        value = param.default_value or ''
                        
                        # Use ENHANCED mapping system for contact
                        try:
                            mapped_value = param.get_mapped_value_for_record(partner)
                            if mapped_value:
                                value = str(mapped_value)
                        except Exception as e:
                            _logger.warning(f"Error getting enhanced contact mapped value for {param.name}: {e}")
                        
                        # Fallback to standard parameters
                        if not value and param.name:
                            try:
                                standard_params = self.env['zns.helper'].build_contact_params(partner, template)
                                if param.name in standard_params:
                                    value = str(standard_params[param.name])
                            except Exception as e:
                                _logger.warning(f"Error building contact standard params: {e}")
                        
                        param_values.append((0, 0, {
                            'parameter_id': param.id,
                            'name': param.name,
                            'title': param_title,
                            'param_type': param.param_type,
                            'required': param.required,
                            'value': value,
                        }))
                    
                    if param_values:
                        defaults['parameter_ids'] = param_values
        
        return defaults
    
    def _find_best_template_for_sale_order(self, sale_order):
        """Find best template for sale order - ENHANCED"""
        _logger.info(f"Finding best template for SO {sale_order.name}")
        
        # 1. Try template mapping first
        try:
            template_mapping = self.env['zns.template.mapping']._find_best_mapping('sale.order', sale_order)
            if template_mapping:
                _logger.info(f"Found template via mapping: {template_mapping.template_id.name}")
                return template_mapping.template_id
        except:
            pass
        
        # 2. Try templates with SO parameter mappings (new enhanced system)
        templates_with_so_mappings = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True),
            ('parameter_ids.mapping_type', '=', 'so')
        ], limit=1)
        
        if templates_with_so_mappings:
            _logger.info(f"Found template with enhanced SO mappings: {templates_with_so_mappings.name}")
            return templates_with_so_mappings
        
        # 3. Try templates with old SO parameter mappings
        templates_with_old_so_mappings = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True),
            ('parameter_ids.so_field_mapping', '!=', False)
        ], limit=1)
        
        if templates_with_old_so_mappings:
            _logger.info(f"Found template with old SO mappings: {templates_with_old_so_mappings.name}")
            return templates_with_old_so_mappings
        
        # 4. Fallback to any active template
        any_template = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True)
        ], limit=1)
        
        if any_template:
            _logger.info(f"Using fallback template: {any_template.name}")
            return any_template
        
        _logger.warning("No templates found")
        return False
    
    def _find_best_template_for_invoice(self, invoice):
        """Find best template for invoice - ENHANCED"""
        _logger.info(f"Finding best template for invoice {invoice.name}")
        
        # 1. Try template mapping first
        try:
            template_mapping = self.env['zns.template.mapping']._find_best_mapping('account.move', invoice)
            if template_mapping:
                return template_mapping.template_id
        except:
            pass
        
        # 2. Try templates with Invoice parameter mappings (enhanced system)
        templates_with_invoice_mappings = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True),
            ('parameter_ids.mapping_type', '=', 'invoice')
        ], limit=1)
        
        if templates_with_invoice_mappings:
            _logger.info(f"Found template with invoice mappings: {templates_with_invoice_mappings.name}")
            return templates_with_invoice_mappings
        
        # 3. Fallback to any active template
        any_template = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True)
        ], limit=1)
        
        return any_template
    
    def _find_best_template_for_contact(self, contact):
        """Find best template for contact - NEW"""
        _logger.info(f"Finding best template for contact {contact.name}")
        
        # 1. Try templates with Contact parameter mappings (enhanced system)
        templates_with_contact_mappings = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True),
            ('parameter_ids.mapping_type', '=', 'contact')
        ], limit=1)
        
        if templates_with_contact_mappings:
            _logger.info(f"Found template with contact mappings: {templates_with_contact_mappings.name}")
            return templates_with_contact_mappings
        
        # 2. Fallback to any active template
        any_template = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True)
        ], limit=1)
        
        return any_template
    
    @api.onchange('template_id')
    def _onchange_template_id(self):
        """Update parameters when template changes - ENHANCED"""
        if self.template_id:
            self._update_parameters()
            
            # Auto-fill parameters if we have context using ENHANCED system
            if self.sale_order_id:
                self._auto_fill_sale_order_params()
            elif self.invoice_id:
                self._auto_fill_invoice_params()
            elif self.partner_id and not self.sale_order_id and not self.invoice_id:
                self._auto_fill_contact_params()
    
    @api.onchange('partner_id')
    def _onchange_partner_id(self):
        if self.partner_id:
            phone = self.partner_id.mobile or self.partner_id.phone
            if phone:
                self.phone = self.env['zns.helper'].format_phone_number(phone)
    
    def _update_parameters(self):
        """Enhanced parameter update with auto-filling based on context"""
        if not self.template_id:
            self.parameter_ids = [(5, 0, 0)]
            return
        
        # Clear existing parameters
        self.parameter_ids = [(5, 0, 0)]
        
        # Create parameter lines from template
        params = []
        for param in self.template_id.parameter_ids:
            param_title = param.title or param.name or 'Parameter'
            default_value = param.default_value or ''
            
            # Auto-fill based on context and mapping type using ENHANCED system
            if self.sale_order_id:
                try:
                    mapped_value = param.get_mapped_value_for_record(self.sale_order_id)
                    if mapped_value:
                        default_value = mapped_value
                except Exception as e:
                    _logger.warning(f"Error getting SO mapped value for {param.name}: {e}")
            
            elif self.invoice_id:
                try:
                    mapped_value = param.get_mapped_value_for_record(self.invoice_id)
                    if mapped_value:
                        default_value = mapped_value
                except Exception as e:
                    _logger.warning(f"Error getting invoice mapped value for {param.name}: {e}")
            
            elif self.partner_id:
                try:
                    mapped_value = param.get_mapped_value_for_record(self.partner_id)
                    if mapped_value:
                        default_value = mapped_value
                except Exception as e:
                    _logger.warning(f"Error getting contact mapped value for {param.name}: {e}")
            
            params.append((0, 0, {
                'parameter_id': param.id,
                'name': param.name,
                'title': param_title,
                'param_type': param.param_type,
                'required': param.required,
                'value': str(default_value) if default_value else '',
            }))
        
        self.parameter_ids = params
    
    def _auto_fill_sale_order_params(self):
        """Auto-fill parameters from sale order using enhanced mapping system"""
        if not self.sale_order_id or not self.template_id:
            return
        
        for param_line in self.parameter_ids:
            if param_line.parameter_id:
                try:
                    # Use the new enhanced mapping system
                    mapped_value = param_line.parameter_id.get_mapped_value_for_record(self.sale_order_id)
                    if mapped_value:
                        param_line.value = str(mapped_value)
                except Exception as e:
                    _logger.warning(f"Error auto-filling parameter {param_line.name}: {e}")
            
            # Fallback to standard parameters if no mapping
            elif param_line.name and not param_line.value:
                try:
                    standard_params = self.env['zns.helper'].build_sale_order_params(self.sale_order_id, self.template_id)
                    if param_line.name in standard_params:
                        param_line.value = str(standard_params[param_line.name])
                except Exception as e:
                    _logger.warning(f"Error getting standard params for {param_line.name}: {e}")
    
    def _auto_fill_invoice_params(self):
        """Auto-fill parameters from invoice using enhanced mapping system"""
        if not self.invoice_id or not self.template_id:
            return
        
        for param_line in self.parameter_ids:
            if param_line.parameter_id:
                try:
                    # Use the new enhanced mapping system
                    mapped_value = param_line.parameter_id.get_mapped_value_for_record(self.invoice_id)
                    if mapped_value:
                        param_line.value = str(mapped_value)
                except Exception as e:
                    _logger.warning(f"Error auto-filling invoice parameter {param_line.name}: {e}")
            
            # Fallback to standard parameters if no mapping
            elif param_line.name and not param_line.value:
                try:
                    standard_params = self.env['zns.helper'].build_invoice_params(self.invoice_id, self.template_id)
                    if param_line.name in standard_params:
                        param_line.value = str(standard_params[param_line.name])
                except Exception as e:
                    _logger.warning(f"Error getting invoice standard params: {e}")

    def _auto_fill_contact_params(self):
        """Auto-fill parameters from contact using enhanced mapping system - NEW"""
        if not self.partner_id or not self.template_id:
            return
        
        for param_line in self.parameter_ids:
            if param_line.parameter_id:
                try:
                    # Use the new enhanced mapping system
                    mapped_value = param_line.parameter_id.get_mapped_value_for_record(self.partner_id)
                    if mapped_value:
                        param_line.value = str(mapped_value)
                except Exception as e:
                    _logger.warning(f"Error auto-filling contact parameter {param_line.name}: {e}")
            
            # Fallback to standard parameters if no mapping
            elif param_line.name and not param_line.value:
                try:
                    standard_params = self.env['zns.helper'].build_contact_params(self.partner_id, self.template_id)
                    if param_line.name in standard_params:
                        param_line.value = str(standard_params[param_line.name])
                except Exception as e:
                    _logger.warning(f"Error getting contact standard params: {e}")
    
    def action_preview_message(self):
        """Preview the message before sending"""
        # Validate required parameters
        missing_params = []
        for param in self.parameter_ids:
            if param.required and not param.value:
                missing_params.append(param.title or param.name)
        
        if missing_params:
            raise UserError(f"Missing required parameters: {', '.join(missing_params)}")
        
        # Build preview
        params = {param.name: param.value for param in self.parameter_ids if param.value}
        
        # Determine context type for preview
        context_info = "None"
        if self.sale_order_id:
            context_info = f"Sale Order: {self.sale_order_id.name}"
        elif self.invoice_id:
            context_info = f"Invoice: {self.invoice_id.name}"
        elif self.partner_id:
            context_info = f"Contact: {self.partner_id.name}"
        
        preview_text = f"""📱 ZNS Message Preview

🔗 Connection: {self.connection_id.name if self.connection_id else 'Not set'}
📋 Template: {self.template_id.name} (ID: {self.template_id.template_id})
📞 Phone: {self.phone}
🎯 Recipient: {self.partner_id.name if self.partner_id else 'Direct Input'}
📄 Context: {context_info}

📝 Parameters ({len(params)} total):
{chr(10).join([f"• {param.title or param.name}: {param.value}" for param in self.parameter_ids if param.value])}

📊 Template Type: {dict(self.template_id._fields['template_type'].selection).get(self.template_id.template_type, 'Unknown')}
🎯 Mapping Info: {len([p for p in self.template_id.parameter_ids if hasattr(p, 'mapping_type')])} parameters with enhanced mapping
✅ Ready to send: {'Yes' if not missing_params else 'No'}
        """
        
        self.preview_message = preview_text
        self.show_preview = True
        
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': '📱 ZNS Message Preview Generated',
                'message': "Preview updated! Check the Preview tab below.",
                'type': 'info',
                'sticky': False,
            }
        }
    
    def send_message(self):
        """Send ZNS message"""
        # Validate required fields
        if not self.template_id:
            raise UserError("Please select a template")
        
        if not self.phone:
            raise UserError("Phone number is required")
        
        # Validate required parameters
        missing_params = []
        for param in self.parameter_ids:
            if param.required and not param.value:
                missing_params.append(param.title or param.name)
        
        if missing_params:
            raise UserError(f"Missing required parameters: {', '.join(missing_params)}")
        
        # Build parameters dict
        params = {}
        for param in self.parameter_ids:
            if param.value:
                params[param.name] = param.value
        
        # Create message record
        message_vals = {
            'template_id': self.template_id.id,
            'connection_id': self.connection_id.id,
            'phone': self.phone,
            'parameters': json.dumps(params),
            'partner_id': self.partner_id.id if self.partner_id else False,
            'sale_order_id': self.sale_order_id.id if self.sale_order_id else False,
            'invoice_id': self.invoice_id.id if self.invoice_id else False,
        }
        
        message = self.env['zns.message'].create(message_vals)
        
        # Send message
        message.send_zns_message()
        
        # Return action to show the sent message
        return {
            'type': 'ir.actions.act_window',
            'name': 'ZNS Message Sent',
            'res_model': 'zns.message',
            'res_id': message.id,
            'view_mode': 'form',
            'target': 'current',
        }


class ZnsSendWizardParameter(models.TransientModel):
    _name = 'zns.send.wizard.parameter'
    _description = 'Send ZNS Wizard Parameter'

    wizard_id = fields.Many2one('zns.send.wizard', string='Wizard', required=True, ondelete='cascade')
    parameter_id = fields.Many2one('zns.template.parameter', string='Parameter')
    name = fields.Char('Name', required=True)
    title = fields.Char('Title', required=True, default='Parameter')  # Add default to prevent error
    param_type = fields.Selection([
        ('string', 'String'),
        ('number', 'Number'),
        ('date', 'Date'),
        ('email', 'Email'),
        ('url', 'URL')
    ], string='Type', default='string')
    required = fields.Boolean('Required')
    value = fields.Char('Value')