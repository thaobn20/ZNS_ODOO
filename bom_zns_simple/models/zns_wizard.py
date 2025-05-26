# -*- coding: utf-8 -*-

import json
import logging
from odoo import models, fields, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class ZnsSendWizard(models.TransientModel):
    _name = 'zns.send.wizard'
    _description = 'Send ZNS Message Wizard'

    template_id = fields.Many2one('zns.template', string='Template', required=True)
    connection_id = fields.Many2one('zns.connection', string='Connection', required=True)
    phone = fields.Char('Phone Number', required=True)
    partner_id = fields.Many2one('res.partner', string='Contact')
    parameter_ids = fields.One2many('zns.send.wizard.parameter', 'wizard_id', string='Parameters')
    
    # Context fields
    sale_order_id = fields.Many2one('sale.order', string='Sale Order')
    invoice_id = fields.Many2one('account.move', string='Invoice')
    
    @api.onchange('template_id')
    def _onchange_template_id(self):
        if self.template_id:
            self.connection_id = self.template_id.connection_id
            # Create parameter lines
            params = []
            for param in self.template_id.parameter_ids:
                params.append((0, 0, {
                    'parameter_id': param.id,
                    'name': param.name,
                    'title': param.title,
                    'param_type': param.param_type,
                    'required': param.required,
                    'value': param.default_value or ''
                }))
            self.parameter_ids = params
    
    @api.onchange('partner_id')
    def _onchange_partner_id(self):
        if self.partner_id and self.partner_id.mobile:
            self.phone = self.partner_id.mobile
        elif self.partner_id and self.partner_id.phone:
            self.phone = self.partner_id.phone
    
    def send_message(self):
        """Send ZNS message"""
        # Validate required parameters
        for param in self.parameter_ids:
            if param.required and not param.value:
                raise UserError(f"Parameter '{param.title}' is required")
        
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
        message.send_zns_message()
        
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