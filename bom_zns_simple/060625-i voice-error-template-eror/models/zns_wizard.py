# -*- coding: utf-8 -*-

import json
import logging
from odoo import models, fields, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class ZnsSendWizard(models.TransientModel):
    _name = 'zns.send.wizard'
    _description = 'Send ZNS Message Wizard'

    # Document type context
    document_type = fields.Selection([
        ('sale_order', 'Sales Order'),
        ('invoice', 'Invoice'),
        ('contact', 'Contact'),
    ], string='Document Type', help="Type of document this message is for")
    
    # Template Selection - filtered by document type
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
        """Set default values and auto-fill parameters based on document type"""
        defaults = super(ZnsSendWizard, self).default_get(fields_list)
        
        # Get document context
        document_type = self.env.context.get('default_document_type')
        if document_type:
            defaults['document_type'] = document_type
        
        # Auto-fill based on document type
        if document_type == 'sale_order' and self.env.context.get('default_sale_order_id'):
            sale_order = self.env['sale.order'].browse(self.env.context['default_sale_order_id'])
            defaults.update(self._fill_from_sale_order(sale_order, defaults))
            
        elif document_type == 'invoice' and self.env.context.get('default_invoice_id'):
            invoice = self.env['account.move'].browse(self.env.context['default_invoice_id'])
            defaults.update(self._fill_from_invoice(invoice, defaults))
            
        elif document_type == 'contact' and self.env.context.get('default_partner_id'):
            contact = self.env['res.partner'].browse(self.env.context['default_partner_id'])
            defaults.update(self._fill_from_contact(contact, defaults))
        
        return defaults
    
    def _fill_from_sale_order(self, sale_order, defaults):
        """Fill wizard from sale order data"""
        updates = {}
        
        if sale_order and sale_order.partner_id:
            if not defaults.get('phone'):
                phone = sale_order.partner_id.mobile or sale_order.partner_id.phone
                if phone:
                    updates['phone'] = self.env['zns.helper'].format_phone_vietnamese(phone)
            updates['partner_id'] = sale_order.partner_id.id
            updates['sale_order_id'] = sale_order.id
            
            # Find template for sale orders
            template = self._find_template_for_document('sale_order')
            if template:
                updates['template_id'] = template.id
                updates['parameter_ids'] = self._build_parameter_lines(template, sale_order)
        
        return updates
    
    def _fill_from_invoice(self, invoice, defaults):
        """Fill wizard from invoice data"""
        updates = {}
        
        if invoice and invoice.partner_id:
            if not defaults.get('phone'):
                phone = invoice.partner_id.mobile or invoice.partner_id.phone
                if phone:
                    updates['phone'] = self.env['zns.helper'].format_phone_vietnamese(phone)
            updates['partner_id'] = invoice.partner_id.id
            updates['invoice_id'] = invoice.id
            
            # Find template for invoices
            template = self._find_template_for_document('invoice')
            if template:
                updates['template_id'] = template.id
                updates['parameter_ids'] = self._build_parameter_lines(template, invoice)
        
        return updates
    
    def _fill_from_contact(self, contact, defaults):
        """Fill wizard from contact data"""
        updates = {}
        
        if contact:
            if not defaults.get('phone'):
                phone = contact.mobile or contact.phone
                if phone:
                    updates['phone'] = self.env['zns.helper'].format_phone_vietnamese(phone)
            updates['partner_id'] = contact.id
            
            # Find template for contacts
            template = self._find_template_for_document('contact')
            if template:
                updates['template_id'] = template.id
                updates['parameter_ids'] = self._build_parameter_lines(template, contact)
        
        return updates
    
    def _find_template_for_document(self, doc_type):
        """Find appropriate template for document type"""
        return self.env['zns.template'].search([
            ('apply_to', 'in', [doc_type, 'all']),
            ('active', '=', True)
        ], limit=1)
    
    def _build_parameter_lines(self, template, record):
        """Build parameter lines with auto-filled values"""
        param_lines = []
        for param in template.parameter_ids:
            value = param.get_mapped_value(record) or param.default_value or ''
            param_lines.append((0, 0, {
                'parameter_id': param.id,
                'name': param.name,
                'title': param.title or param.name,
                'param_type': param.param_type,
                'required': param.required,
                'value': str(value),
            }))
        return param_lines
    
    @api.onchange('template_id')
    def _onchange_template_id(self):
        """Update parameters when template changes"""
        if self.template_id:
            self._update_parameters()
    
    @api.onchange('partner_id')
    def _onchange_partner_id(self):
        if self.partner_id:
            phone = self.partner_id.mobile or self.partner_id.phone
            if phone:
                self.phone = self.env['zns.helper'].format_phone_vietnamese(phone)
    
    def _update_parameters(self):
        """Update parameter lines based on selected template"""
        if not self.template_id:
            self.parameter_ids = [(5, 0, 0)]
            return
        
        # Clear existing parameters
        self.parameter_ids = [(5, 0, 0)]
        
        # Get source record for auto-filling
        source_record = None
        if self.sale_order_id:
            source_record = self.sale_order_id
        elif self.invoice_id:
            source_record = self.invoice_id
        elif self.partner_id:
            source_record = self.partner_id
        
        # Create parameter lines
        params = []
        for param in self.template_id.parameter_ids:
            value = ''
            if source_record:
                value = param.get_mapped_value(source_record) or param.default_value or ''
            else:
                value = param.default_value or ''
            
            params.append((0, 0, {
                'parameter_id': param.id,
                'name': param.name,
                'title': param.title or param.name,
                'param_type': param.param_type,
                'required': param.required,
                'value': str(value),
            }))
        self.parameter_ids = params
    
    @api.onchange('document_type')
    def _onchange_document_type(self):
        """Filter templates based on document type"""
        if self.document_type:
            # Update domain for template_id
            return {
                'domain': {
                    'template_id': [
                        ('apply_to', 'in', [self.document_type, 'all']),
                        ('active', '=', True),
                        ('connection_id', '!=', False)
                    ]
                }
            }
    
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
        
        doc_info = ""
        if self.sale_order_id:
            doc_info = f"📄 Sale Order: {self.sale_order_id.name}"
        elif self.invoice_id:
            doc_info = f"📄 Invoice: {self.invoice_id.name}"
        elif self.document_type:
            doc_info = f"📄 Document Type: {dict(self._fields['document_type'].selection)[self.document_type]}"
        
        preview_text = f"""📱 ZNS Message Preview

🔗 Connection: {self.connection_id.name if self.connection_id else 'Not set'}
📋 Template: {self.template_id.name} (ID: {self.template_id.template_id})
📞 Phone: {self.phone}
🎯 Recipient: {self.partner_id.name if self.partner_id else 'Direct Input'}
{doc_info}

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
    title = fields.Char('Title', required=True, default='Parameter')
    param_type = fields.Selection([
        ('string', 'String'),
        ('number', 'Number'),
        ('date', 'Date'),
        ('email', 'Email'),
        ('url', 'URL')
    ], string='Type', default='string')
    required = fields.Boolean('Required')
    value = fields.Char('Value')