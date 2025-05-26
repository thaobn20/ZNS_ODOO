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
        """Enhanced parameter building for sale orders"""
        params = {}
        
        for param in template.parameter_ids:
            value = None
            
            # Standard parameters
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
                # Advanced parameter mapping
                value = self._get_sale_order_param_value(sale_order, param.name)
            
            # Use default if no value found
            if not value:
                value = param.default_value or ''
            
            if value:
                params[param.name] = str(value)
        
        return params

    @api.model
    def build_invoice_params(self, invoice, template):
        """Enhanced parameter building for invoices"""
        params = {}
        
        for param in template.parameter_ids:
            value = None
            
            # Standard parameters
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
                # Advanced parameter mapping
                value = self._get_invoice_param_value(invoice, param.name)
            
            # Use default if no value found
            if not value:
                value = param.default_value or ''
            
            if value:
                params[param.name] = str(value)
        
        return params

    def _get_sale_order_param_value(self, sale_order, param_name):
        """Get parameter value from sale order with advanced mapping"""
        # Advanced mappings
        param_mappings = {
            # Order details
            'order_reference': sale_order.client_order_ref,
            'payment_terms': sale_order.payment_term_id.name if sale_order.payment_term_id else '',
            'delivery_date': sale_order.commitment_date.strftime('%d/%m/%Y') if sale_order.commitment_date else '',
            'order_note': sale_order.note,
            'currency': sale_order.currency_id.name,
            
            # Customer details
            'customer_code': sale_order.partner_id.ref,
            'customer_address': sale_order.partner_id.contact_address,
            'customer_city': sale_order.partner_id.city,
            'customer_country': sale_order.partner_id.country_id.name if sale_order.partner_id.country_id else '',
            
            # Product details
            'product_count': len(sale_order.order_line),
            'main_product': sale_order.order_line[0].product_id.name if sale_order.order_line else '',
            'product_name': sale_order.order_line[0].product_id.name if sale_order.order_line else '',
            'total_qty': sum(sale_order.order_line.mapped('product_uom_qty')),
            
            # Amounts
            'subtotal': sale_order.amount_untaxed,
            'tax_amount': sale_order.amount_tax,
            'discount_amount': sum(line.price_unit * line.product_uom_qty * line.discount / 100 for line in sale_order.order_line),
            
            # Status
            'order_status': dict(sale_order._fields['state'].selection).get(sale_order.state),
            'is_confirmed': 'Yes' if sale_order.state in ['sale', 'done'] else 'No',
            
            # Formatted amounts (Vietnamese style)
            'amount_vnd': f"{sale_order.amount_total:,.0f}".replace(',', '.'),
            'amount_words': self._number_to_words_vn(sale_order.amount_total),
        }
        
        return param_mappings.get(param_name, '')

    def _get_invoice_param_value(self, invoice, param_name):
        """Get parameter value from invoice with advanced mapping"""
        # Advanced mappings for invoices
        param_mappings = {
            # Invoice details
            'payment_terms': invoice.invoice_payment_term_id.name if invoice.invoice_payment_term_id else '',
            'currency': invoice.currency_id.name,
            'invoice_note': invoice.narration,
            
            # Customer details
            'customer_code': invoice.partner_id.ref,
            'customer_address': invoice.partner_id.contact_address,
            'customer_city': invoice.partner_id.city,
            'customer_country': invoice.partner_id.country_id.name if invoice.partner_id.country_id else '',
            
            # Amounts
            'subtotal': invoice.amount_untaxed,
            'tax_amount': invoice.amount_tax,
            'amount_vnd': f"{invoice.amount_total:,.0f}".replace(',', '.'),
            'amount_words': self._number_to_words_vn(invoice.amount_total),
            'remaining_vnd': f"{invoice.amount_residual:,.0f}".replace(',', '.'),
            
            # Status
            'invoice_status': dict(invoice._fields['state'].selection).get(invoice.state),
            'is_paid': 'Yes' if invoice.amount_residual == 0 else 'No',
        }
        
        return param_mappings.get(param_name, '')

    def _number_to_words_vn(self, amount):
        """Convert number to Vietnamese words (simplified)"""
        # This is a simplified version - you can implement full Vietnamese number conversion
        if amount >= 1000000000:
            return f"{amount/1000000000:.1f} tỷ"
        elif amount >= 1000000:
            return f"{amount/1000000:.1f} triệu"
        elif amount >= 1000:
            return f"{amount/1000:.0f} nghìn"
        else:
            return f"{amount:.0f}"

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
        return message