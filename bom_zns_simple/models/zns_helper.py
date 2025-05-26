# -*- coding: utf-8 -*-

import json
import logging
import re
from odoo import models, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class ZnsHelper(models.AbstractModel):
    _name = 'zns.helper'
    _description = 'ZNS Helper Functions'

    @api.model
    def format_phone_number(self, phone):
        """Format phone number for ZNS (Vietnamese format)"""
        if not phone:
            return False
        
        # Remove all non-digit characters
        phone = re.sub(r'\D', '', phone)
        
        # Handle Vietnamese phone numbers
        if phone.startswith('84'):
            # Already has country code
            return phone
        elif phone.startswith('0'):
            # Remove leading 0 and add country code
            return '84' + phone[1:]
        elif len(phone) == 9:
            # 9 digit number, add country code
            return '84' + phone
        else:
            # Return as is, might be international
            return phone

    @api.model
    def build_sale_order_params(self, sale_order, template):
        """Build parameters for sale order ZNS message"""
        params = {}
        
        for param in template.parameter_ids:
            value = None
            
            if param.name == 'customer_name':
                value = sale_order.partner_id.name
            elif param.name == 'order_id' or param.name == 'so_no':
                value = sale_order.name
            elif param.name == 'amount' or param.name == 'total_amount':
                value = str(sale_order.amount_total)
            elif param.name == 'order_date':
                value = sale_order.date_order.strftime('%d/%m/%Y') if sale_order.date_order else ''
            elif param.name == 'salesperson':
                value = sale_order.user_id.name if sale_order.user_id else ''
            elif param.name == 'company_name':
                value = sale_order.company_id.name
            elif param.name == 'customer_phone':
                value = self.format_phone_number(sale_order.partner_id.mobile or sale_order.partner_id.phone)
            elif param.name == 'customer_email':
                value = sale_order.partner_id.email
            else:
                # Try to get from default value or leave empty
                value = param.default_value or ''
            
            if value:
                params[param.name] = str(value)
        
        return params

    @api.model
    def build_invoice_params(self, invoice, template):
        """Build parameters for invoice ZNS message"""
        params = {}
        
        for param in template.parameter_ids:
            value = None
            
            if param.name == 'customer_name':
                value = invoice.partner_id.name
            elif param.name == 'invoice_number':
                value = invoice.name
            elif param.name == 'amount' or param.name == 'total_amount':
                value = str(invoice.amount_total)
            elif param.name == 'due_date':
                value = invoice.invoice_date_due.strftime('%d/%m/%Y') if invoice.invoice_date_due else ''
            elif param.name == 'invoice_date':
                value = invoice.invoice_date.strftime('%d/%m/%Y') if invoice.invoice_date else ''
            elif param.name == 'remaining_amount':
                value = str(invoice.amount_residual)
            elif param.name == 'company_name':
                value = invoice.company_id.name
            elif param.name == 'customer_phone':
                value = self.format_phone_number(invoice.partner_id.mobile or invoice.partner_id.phone)
            elif param.name == 'customer_email':
                value = invoice.partner_id.email
            else:
                # Try to get from default value or leave empty
                value = param.default_value or ''
            
            if value:
                params[param.name] = str(value)
        
        return params

    @api.model
    def send_sale_order_zns(self, sale_order, template_id=None):
        """Quick send ZNS for sale order"""
        if not template_id:
            # Find default sale order template
            template = self.env['zns.template'].search([
                ('template_type', '=', 'transaction'),
                ('active', '=', True)
            ], limit=1, order='id')
        else:
            template = self.env['zns.template'].browse(template_id)
        
        if not template:
            raise UserError(_("No ZNS template found"))
        
        phone = self.format_phone_number(
            invoice.partner_id.mobile or invoice.partner_id.phone
        )
        if not phone:
            raise UserError(_("No phone number found for customer"))
        
        params = self.build_invoice_params(invoice, template)
        
        # Create and send message
        message = self.env['zns.message'].create({
            'template_id': template.id,
            'connection_id': template.connection_id.id,
            'phone': phone,
            'parameters': json.dumps(params),
            'partner_id': invoice.partner_id.id,
            'invoice_id': invoice.id,
        })
        
        message.send_zns_message()
        return message.env['zns.template'].browse(template_id)
        
        if not template:
            raise UserError(_("No ZNS template found"))
        
        phone = self.format_phone_number(
            sale_order.partner_id.mobile or sale_order.partner_id.phone
        )
        if not phone:
            raise UserError(_("No phone number found for customer"))
        
        params = self.build_sale_order_params(sale_order, template)
        
        # Create and send message
        message = self.env['zns.message'].create({
            'template_id': template.id,
            'connection_id': template.connection_id.id,
            'phone': phone,
            'parameters': json.dumps(params),
            'partner_id': sale_order.partner_id.id,
            'sale_order_id': sale_order.id,
        })
        
        message.send_zns_message()
        return message

    @api.model
    def send_invoice_zns(self, invoice, template_id=None):
        """Quick send ZNS for invoice"""
        if not template_id:
            # Find default invoice template
            template = self.env['zns.template'].search([
                ('template_type', '=', 'transaction'),
                ('active', '=', True)
            ], limit=1, order='id')
        else:
            template = self