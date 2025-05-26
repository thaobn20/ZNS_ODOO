# -*- coding: utf-8 -*-

import json
import logging
from odoo import models, fields, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class ZnsSendWizard(models.TransientModel):
    _name = 'zns.send.wizard'
    _description = 'Send ZNS Message Wizard'

    # Template Selection
    template_mapping_id = fields.Many2one('zns.template.mapping', string='Template Mapping')
    template_id = fields.Many2one('zns.template', string='Template', required=True)
    connection_id = fields.Many2one('zns.connection', string='Connection', required=True)
    
    # Recipient Info
    phone = fields.Char('Phone Number', required=True)
    partner_id = fields.Many2one('res.partner', string='Contact')
    
    # Parameters
    parameter_ids = fields.One2many('zns.send.wizard.parameter', 'wizard_id', string='Parameters')
    
    # Context fields
    sale_order_id = fields.Many2one('sale.order', string='Sale Order')
    invoice_id = fields.Many2one('account.move', string='Invoice')
    
    # Quick Actions
    use_mapping = fields.Boolean('Use Template Mapping', default=True)
    auto_fill_params = fields.Boolean('Auto-fill Parameters', default=True)
    
    # Preview fields
    preview_message = fields.Text('Message Preview', readonly=True)
    show_preview = fields.Boolean('Show Preview', default=False)
    
    @api.onchange('template_mapping_id')
    def _onchange_template_mapping_id(self):
        if self.template_mapping_id:
            self.template_id = self.template_mapping_id.template_id
    
    @api.onchange('template_id')
    def _onchange_template_id(self):
        if self.template_id:
            self.connection_id = self.template_id.connection_id
            self._update_parameters()
    
    @api.onchange('partner_id')
    def _onchange_partner_id(self):
        if self.partner_id:
            self.phone = self.partner_id.mobile or self.partner_id.phone
    
    @api.onchange('sale_order_id', 'auto_fill_params')
    def _onchange_sale_order_auto_fill(self):
        if self.sale_order_id and self.auto_fill_params and self.template_id:
            self._auto_fill_sale_order_params()
    
    @api.onchange('invoice_id', 'auto_fill_params')
    def _onchange_invoice_auto_fill(self):
        if self.invoice_id and self.auto_fill_params and self.template_id:
            self._auto_fill_invoice_params()
    
    def _update_parameters(self):
        """Update parameter lines based on selected template"""
        if not self.template_id:
            self.parameter_ids = [(5, 0, 0)]  # Clear all
            return
        
        # Clear existing parameters
        self.parameter_ids = [(5, 0, 0)]
        
        # Create parameter lines
        params = []
        for param in self.template_id.parameter_ids:
            # Get mapped value if SO field mapping exists
            mapped_value = ''
            if param.so_field_mapping and self.sale_order_id:
                mapped_value = param.get_mapped_value(self.sale_order_id)
            elif param.custom_value:
                mapped_value = param.custom_value
            elif param.default_value:
                mapped_value = param.default_value
            
            params.append((0, 0, {
                'parameter_id': param.id,
                'name': param.name,
                'title': param.title,
                'param_type': param.param_type,
                'required': param.required,
                'value': mapped_value,
                'so_field_mapping': param.so_field_mapping,
                'mapping_info': self._get_mapping_info(param.so_field_mapping),
            }))
        self.parameter_ids = params
        
        # Auto-fill if enabled
        if self.auto_fill_params:
            if self.sale_order_id:
                self._auto_fill_sale_order_params()
            elif self.invoice_id:
                self._auto_fill_invoice_params()
    
    def _get_mapping_info(self, mapping):
        """Get human readable mapping info"""
        mapping_labels = {
            'partner_id.name': '👤 Customer Name',
            'partner_id.mobile': '📱 Customer Mobile',
            'partner_id.phone': '📞 Customer Phone',
            'partner_id.email': '📧 Customer Email',
            'name': '📋 SO Number',
            'date_order': '📅 Order Date',
            'amount_total': '💰 Total Amount',
            'amount_untaxed': '💵 Subtotal',
            'amount_tax': '🧾 Tax Amount',
            'user_id.name': '👨‍💼 Salesperson',
            'company_id.name': '🏢 Company Name',
            'client_order_ref': '📄 Customer Reference',
            'custom': '✏️ Custom Value',
        }
        return mapping_labels.get(mapping, mapping or '')
    
    def _auto_fill_sale_order_params(self):
        """Auto-fill parameters from sale order data using enhanced mapping"""
        if not self.sale_order_id or not self.template_id:
            return
        
        # Build parameters using enhanced helper
        params = self.env['zns.helper'].build_sale_order_params(self.sale_order_id, self.template_id)
        
        # Update parameter values
        for param_line in self.parameter_ids:
            if param_line.name in params:
                param_line.value = params[param_line.name]
    
    def _auto_fill_invoice_params(self):
        """Auto-fill parameters from invoice data using enhanced mapping"""
        if not self.invoice_id or not self.template_id:
            return
        
        # Build parameters using enhanced helper
        params = self.env['zns.helper'].build_invoice_params(self.invoice_id, self.template_id)
        
        # Update parameter values
        for param_line in self.parameter_ids:
            if param_line.name in params:
                param_line.value = params[param_line.name]
    
    def action_auto_detect_template(self):
        """Auto-detect best template mapping"""
        if self.sale_order_id:
            mapping = self.env['zns.template.mapping']._find_best_mapping('sale.order', self.sale_order_id)
        elif self.invoice_id:
            mapping = self.env['zns.template.mapping']._find_best_mapping('account.move', self.invoice_id)
        else:
            raise UserError("No document context for template detection")
        
        if mapping:
            self.template_mapping_id = mapping.id
            self.template_id = mapping.template_id.id
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': 'Template Detected',
                    'message': f"✅ Auto-detected template: {mapping.name}",
                    'type': 'success',
                    'sticky': False,
                }
            }
        else:
            raise UserError("No suitable template mapping found for this document")
    
    def action_preview_message(self):
        """Preview the message before sending"""
        # Validate required parameters
        missing_params = []
        for param in self.parameter_ids:
            if param.required and not param.value:
                missing_params.append(param.title)
        
        if missing_params:
            raise UserError(f"Missing required parameters: {', '.join(missing_params)}")
        
        # Build preview
        params = {param.name: param.value for param in self.parameter_ids if param.value}
        
        preview_text = f"""📱 ZNS Message Preview

🔗 Connection: {self.connection_id.name}
📋 Template: {self.template_id.name} (ID: {self.template_id.template_id})
📞 Phone: {self.phone}
🎯 Recipient: {self.partner_id.name if self.partner_id else 'Manual'}
📄 Document: {self.sale_order_id.name if self.sale_order_id else self.invoice_id.name if self.invoice_id else 'None'}

📝 Parameters ({len(params)} total):
{chr(10).join([f"• {param.title} ({param.name}): {param.value} {param.mapping_info}" for param in self.parameter_ids if param.value])}

📊 Template Type: {dict(self.template_id._fields['template_type'].selection)[self.template_id.template_type]}
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
    
    def action_auto_fill_from_template(self):
        """Auto-fill parameters using template field mappings"""
        if not self.template_id:
            raise UserError("Please select a template first")
        
        filled_count = 0
        for param_line in self.parameter_ids:
            param = param_line.parameter_id
            if param.so_field_mapping and self.sale_order_id:
                new_value = param.get_mapped_value(self.sale_order_id)
                if new_value and new_value != param_line.value:
                    param_line.value = new_value
                    filled_count += 1
        
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': 'Auto-Fill Complete',
                'message': f"✅ Filled {filled_count} parameters from template mappings",
                'type': 'success',
                'sticky': False,
            }
        }
    
    def send_message(self):
        """Send ZNS message"""
        # Validate required parameters
        missing_params = []
        for param in self.parameter_ids:
            if param.required and not param.value:
                missing_params.append(param.title)
        
        if missing_params:
            raise UserError(f"Missing required parameters: {', '.join(missing_params)}")
        
        # Build parameters dict
        params = {}
        for param in self.parameter_ids:
            if param.value:
                params[param.name] = param.value
        
        # Create message record
        message = self.env['zns.message'].create({
            'template_id': self.template_id.id,
            'connection_id': self.connection_id.id,
            'phone': self.phone,
            'parameters': json.dumps(params),
            'partner_id': self.partner_id.id if self.partner_id else False,
            'sale_order_id': self.sale_order_id.id if self.sale_order_id else False,
            'invoice_id': self.invoice_id.id if self.invoice_id else False,
        })
        
        # Send message
        result = message.send_zns_message()
        
        return {
            'type': 'ir.actions.act_window',
            'name': 'ZNS Message',
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
    title = fields.Char('Title', required=True)
    param_type = fields.Selection([
        ('string', 'String'),
        ('number', 'Number'),
        ('date', 'Date'),
        ('email', 'Email'),
        ('url', 'URL')
    ], string='Type', default='string')
    required = fields.Boolean('Required')
    value = fields.Char('Value')
    so_field_mapping = fields.Char('SO Field Mapping', readonly=True)
    mapping_info = fields.Char('Mapping Info', readonly=True, help="Human readable mapping info")