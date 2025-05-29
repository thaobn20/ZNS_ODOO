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
        """Set default values and auto-fill parameters"""
        defaults = super().default_get(fields_list)
        
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
                
                # If template is provided in context (from SO default template)
                if self.env.context.get('default_template_id'):
                    template = self.env['zns.template'].browse(self.env.context['default_template_id'])
                    if template and template.exists():
                        # Set parameters with auto-filled values
                        param_values = []
                        for param in template.parameter_ids:
                            param_title = param.title or param.name or 'Parameter'
                            value = param.default_value or ''
                            
                            # Try to get mapped value from template configuration
                            if param.so_field_mapping:
                                try:
                                    mapped_value = param.get_mapped_value(sale_order)
                                    if mapped_value:
                                        value = str(mapped_value)
                                except Exception as e:
                                    _logger.warning(f"Error getting mapped value for {param.name}: {e}")
                            
                            # If no mapping, try standard parameters
                            if not value and param.name:
                                standard_params = self.env['zns.helper'].build_sale_order_params(sale_order, template)
                                if param.name in standard_params:
                                    value = str(standard_params[param.name])
                            
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
    
    @api.onchange('template_id')
    def _onchange_template_id(self):
        """Update parameters when template changes"""
        if self.template_id:
            self._update_parameters()
            
            # Auto-fill parameters if we have context
            if self.sale_order_id:
                self._auto_fill_sale_order_params()
            elif self.invoice_id:
                self._auto_fill_invoice_params()
    
    @api.onchange('partner_id')
    def _onchange_partner_id(self):
        if self.partner_id:
            phone = self.partner_id.mobile or self.partner_id.phone
            if phone:
                self.phone = self.env['zns.helper'].format_phone_number(phone)
    
    def _update_parameters(self):
        """Update parameter lines based on selected template"""
        if not self.template_id:
            self.parameter_ids = [(5, 0, 0)]
            return
        
        # Clear existing parameters
        self.parameter_ids = [(5, 0, 0)]
        
        # Create parameter lines from template
        params = []
        for param in self.template_id.parameter_ids:
            # Get the title - use title if available, otherwise use name
            param_title = param.title or param.name or 'Parameter'
            
            # Get default value from template parameter
            default_value = param.default_value or ''
            
            # If we're in SO context and parameter has mapping, get mapped value
            if self.sale_order_id and param.so_field_mapping:
                try:
                    mapped_value = param.get_mapped_value(self.sale_order_id)
                    if mapped_value:
                        default_value = mapped_value
                except Exception as e:
                    _logger.warning(f"Error getting mapped value for {param.name}: {e}")
            
            params.append((0, 0, {
                'parameter_id': param.id,
                'name': param.name,
                'title': param_title,  # Ensure title is always set
                'param_type': param.param_type,
                'required': param.required,
                'value': str(default_value) if default_value else '',
            }))
        self.parameter_ids = params
    
    def _auto_fill_sale_order_params(self):
        """Auto-fill parameters from sale order data using template parameter mappings"""
        if not self.sale_order_id or not self.template_id:
            return
        
        # For each parameter, check if it has SO field mapping in template
        for param_line in self.parameter_ids:
            if param_line.parameter_id and param_line.parameter_id.so_field_mapping:
                try:
                    # Get mapped value from template parameter configuration
                    mapped_value = param_line.parameter_id.get_mapped_value(self.sale_order_id)
                    if mapped_value:
                        param_line.value = str(mapped_value)
                except Exception as e:
                    _logger.warning(f"Error auto-filling parameter {param_line.name}: {e}")
            
            # If no mapping but we have standard parameter names, try to fill them
            elif param_line.name:
                # Use helper to get standard parameters
                standard_params = self.env['zns.helper'].build_sale_order_params(self.sale_order_id, self.template_id)
                if param_line.name in standard_params:
                    param_line.value = str(standard_params[param_line.name])
    
    def _auto_fill_invoice_params(self):
        """Auto-fill parameters from invoice data"""
        if not self.invoice_id or not self.template_id:
            return
        
        # For each parameter, check if it has mapping in template
        for param_line in self.parameter_ids:
            if param_line.parameter_id and param_line.parameter_id.so_field_mapping:
                try:
                    # Adapt SO mapping for invoice
                    invoice_mapping = self.env['zns.helper']._adapt_so_mapping_to_invoice(
                        param_line.parameter_id.so_field_mapping
                    )
                    if invoice_mapping:
                        obj = self.invoice_id
                        for field_part in invoice_mapping.split('.'):
                            obj = getattr(obj, field_part, '')
                            if not obj:
                                break
                        if obj:
                            param_line.value = str(obj)
                except Exception as e:
                    _logger.warning(f"Error auto-filling invoice parameter {param_line.name}: {e}")
            
            # Use standard parameters as fallback
            elif param_line.name:
                standard_params = self.env['zns.helper'].build_invoice_params(self.invoice_id, self.template_id)
                if param_line.name in standard_params:
                    param_line.value = str(standard_params[param_line.name])
    
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
        
        preview_text = f"""📱 ZNS Message Preview

🔗 Connection: {self.connection_id.name if self.connection_id else 'Not set'}
📋 Template: {self.template_id.name} (ID: {self.template_id.template_id})
📞 Phone: {self.phone}
🎯 Recipient: {self.partner_id.name if self.partner_id else 'Direct Input'}
📄 Document: {self.sale_order_id.name if self.sale_order_id else self.invoice_id.name if self.invoice_id else 'None'}

📝 Parameters ({len(params)} total):
{chr(10).join([f"• {param.title or param.name}: {param.value}" for param in self.parameter_ids if param.value])}

📊 Template Type: {dict(self.template_id._fields['template_type'].selection).get(self.template_id.template_type, 'Unknown')}
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