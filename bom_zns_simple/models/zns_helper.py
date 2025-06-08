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
    def format_phone_vietnamese(self, phone):
        """Format phone number for ZNS - KEEP VIETNAMESE FORMAT (0xxxxxxxxx)"""
        if not phone:
            return False
        
        # Remove all non-digit characters
        phone = re.sub(r'\D', '', phone)
        
        if not phone:
            return False
            
        _logger.info(f"Formatting phone: {phone}")
        
        # Handle different Vietnamese phone number formats
        if phone.startswith('84'):
            # Convert from international format (84xxxxxxxxx) to Vietnamese (0xxxxxxxxx)
            if len(phone) >= 10 and len(phone) <= 12:
                vietnamese_phone = '0' + phone[2:]  # Remove 84, add 0
                _logger.info(f"Converted 84{phone[2:]} → {vietnamese_phone}")
                return vietnamese_phone
            else:
                _logger.warning(f"Invalid international phone length: {phone}")
                return False
                
        elif phone.startswith('0'):
            # Already Vietnamese format - validate length
            if len(phone) >= 10 and len(phone) <= 11:
                _logger.info(f"Vietnamese format confirmed: {phone}")
                return phone
            else:
                _logger.warning(f"Invalid Vietnamese phone length: {phone}")
                return False
                
        elif len(phone) >= 9 and len(phone) <= 10:
            # Assume it's Vietnamese number without 0 prefix
            vietnamese_phone = '0' + phone
            _logger.info(f"Added 0 prefix: {phone} → {vietnamese_phone}")
            return vietnamese_phone
        else:
            _logger.warning(f"Cannot format phone number: {phone} (length: {len(phone)})")
            return False

    @api.model
    def format_phone_number(self, phone):
        """Alias for backward compatibility"""
        return self.format_phone_vietnamese(phone)

    @api.model
    def build_sale_order_params(self, sale_order, template):
        """Enhanced parameter building for sale orders with safe handling"""
        params = {}
        
        # Handle templates without parameters (like some OTP templates)
        if not template.parameter_ids:
            _logger.info(f"Template {template.name} has no parameters, returning empty params")
            return params
        
        # Use template parameter mappings if available
        for param in template.parameter_ids:
            value = None
            
            # Check if parameter has SO field mapping (safely)
            if hasattr(param, 'so_field_mapping') and param.so_field_mapping:
                try:
                    value = param.get_mapped_value(sale_order)
                except Exception as e:
                    _logger.warning(f"Error getting mapped value for {param.name}: {e}")
            
            # If no mapping or mapping failed, try standard parameter names
            if not value:
                try:
                    value = self._get_standard_so_param_value(sale_order, param.name)
                except Exception as e:
                    _logger.warning(f"Error getting standard SO param {param.name}: {e}")
            
            # Use default if no value found
            if not value:
                value = param.default_value or ''
            
            if value:
                params[param.name] = str(value)
        
        return params

    def _get_standard_so_param_value(self, sale_order, param_name):
        """Get standard parameter values by common names"""
        # Standard parameter mappings based on common ZNS parameter names
        param_mappings = {
            # Customer details
            'customer_name': sale_order.partner_id.name,
            'customer_phone': self.format_phone_vietnamese(sale_order.partner_id.mobile or sale_order.partner_id.phone),
            'customer_email': sale_order.partner_id.email,
            'customer_code': sale_order.partner_id.ref,
            'customer_address': sale_order.partner_id.contact_address,
            'customer_city': sale_order.partner_id.city,
            'customer_country': sale_order.partner_id.country_id.name if sale_order.partner_id.country_id else '',
            'customer_vat': sale_order.partner_id.vat,
            
            # Order details
            'order_id': sale_order.name,
            'so_no': sale_order.name,
            'order_number': sale_order.name,
            'order_date': sale_order.date_order.strftime('%d/%m/%Y') if sale_order.date_order else '',
            'order_reference': sale_order.client_order_ref,
            'payment_terms': sale_order.payment_term_id.name if sale_order.payment_term_id else '',
            'delivery_date': sale_order.commitment_date.strftime('%d/%m/%Y') if sale_order.commitment_date else '',
            'order_note': sale_order.note,
            'order_notes': sale_order.note,
            'currency': sale_order.currency_id.name,
            
            # Amounts
            'amount': sale_order.amount_total,
            'total_amount': sale_order.amount_total,
            'subtotal': sale_order.amount_untaxed,
            'tax_amount': sale_order.amount_tax,
            'amount_vnd': f"{sale_order.amount_total:,.0f}".replace(',', '.'),
            'amount_words': self._number_to_words_vn(sale_order.amount_total),
            'total_vnd': f"{sale_order.amount_total:,.0f}".replace(',', '.'),
            
            # Product details
            'product_count': len(sale_order.order_line),
            'main_product': sale_order.order_line[0].product_id.name if sale_order.order_line else '',
            'product_name': sale_order.order_line[0].product_id.name if sale_order.order_line else '',
            'total_qty': sum(sale_order.order_line.mapped('product_uom_qty')),
            'product_list': ', '.join(sale_order.order_line.mapped('product_id.name')[:3]),  # First 3 products
            
            # Company details (includes vat)
            'company_name': sale_order.company_id.name,
            'company_vat': sale_order.company_id.vat,
            'company_tax_id': sale_order.company_id.vat,
            'company_phone': sale_order.company_id.phone,
            'company_email': sale_order.company_id.email,
            'salesperson': sale_order.user_id.name if sale_order.user_id else '',
            'sales_person': sale_order.user_id.name if sale_order.user_id else '',
            
            # Status
            'order_status': dict(sale_order._fields['state'].selection).get(sale_order.state),
            'is_confirmed': 'Yes' if sale_order.state in ['sale', 'done'] else 'No',
            
            # Calculated fields
            'discount_amount': sum(line.price_unit * line.product_uom_qty * line.discount / 100 for line in sale_order.order_line),
        }
        
        return param_mappings.get(param_name, '')

    def _number_to_words_vn(self, amount):
        """Convert number to Vietnamese words (simplified)"""
        if amount >= 1000000000:
            return f"{amount/1000000000:.1f} tỷ"
        elif amount >= 1000000:
            return f"{amount/1000000:.1f} triệu"
        elif amount >= 1000:
            return f"{amount/1000:.0f} nghìn"
        else:
            return f"{amount:.0f}"

    @api.model
    def build_invoice_params(self, invoice, template):
        """Enhanced parameter building for invoices with safe handling"""
        params = {}
        
        # Handle templates without parameters (like some OTP templates)
        if not template.parameter_ids:
            _logger.info(f"Template {template.name} has no parameters, returning empty params")
            return params
        
        # Use template parameter mappings if available
        for param in template.parameter_ids:
            value = None
            
            # Try standard parameter names for invoices
            try:
                value = self._get_standard_invoice_param_value(invoice, param.name)
            except Exception as e:
                _logger.warning(f"Error getting standard invoice param {param.name}: {e}")
            
            # Use default if no value found
            if not value:
                value = param.default_value or ''
            
            if value:
                params[param.name] = str(value)
        
        return params

    def _get_standard_invoice_param_value(self, invoice, param_name):
        """Get standard parameter values for invoice by common names"""
        param_mappings = {
            # Customer details
            'customer_name': invoice.partner_id.name,
            'customer_phone': self.format_phone_vietnamese(invoice.partner_id.mobile or invoice.partner_id.phone),
            'customer_email': invoice.partner_id.email,
            'customer_code': invoice.partner_id.ref,
            'customer_address': invoice.partner_id.contact_address,
            'customer_city': invoice.partner_id.city,
            'customer_country': invoice.partner_id.country_id.name if invoice.partner_id.country_id else '',
            'customer_vat': invoice.partner_id.vat,
            
            # Invoice details
            'invoice_number': invoice.name,
            'invoice_no': invoice.name,
            'invoice_date': invoice.invoice_date.strftime('%d/%m/%Y') if invoice.invoice_date else '',
            'due_date': invoice.invoice_date_due.strftime('%d/%m/%Y') if invoice.invoice_date_due else '',
            'payment_terms': invoice.invoice_payment_term_id.name if invoice.invoice_payment_term_id else '',
            'invoice_note': invoice.narration,
            'invoice_notes': invoice.narration,
            'currency': invoice.currency_id.name,
            'invoice_reference': invoice.ref,
            'payment_reference': invoice.payment_reference,
            
            # Amounts
            'amount': invoice.amount_total,
            'total_amount': invoice.amount_total,
            'subtotal': invoice.amount_untaxed,
            'tax_amount': invoice.amount_tax,
            'remaining_amount': invoice.amount_residual,
            'paid_amount': invoice.amount_total - invoice.amount_residual,
            'amount_vnd': f"{invoice.amount_total:,.0f}".replace(',', '.'),
            'remaining_vnd': f"{invoice.amount_residual:,.0f}".replace(',', '.'),
            'paid_vnd': f"{(invoice.amount_total - invoice.amount_residual):,.0f}".replace(',', '.'),
            'amount_words': self._number_to_words_vn(invoice.amount_total),
            'remaining_words': self._number_to_words_vn(invoice.amount_residual),
            
            # Product details (if invoice has lines)
            'product_count': len(invoice.invoice_line_ids.filtered(lambda l: not l.display_type)),
            'main_product': invoice.invoice_line_ids.filtered(lambda l: not l.display_type)[0].product_id.name if invoice.invoice_line_ids.filtered(lambda l: not l.display_type) else '',
            'product_name': invoice.invoice_line_ids.filtered(lambda l: not l.display_type)[0].product_id.name if invoice.invoice_line_ids.filtered(lambda l: not l.display_type) else '',
            'total_qty': sum(invoice.invoice_line_ids.filtered(lambda l: not l.display_type).mapped('quantity')),
            'product_list': ', '.join(invoice.invoice_line_ids.filtered(lambda l: not l.display_type).mapped('product_id.name')[:3]),
            
            # Company details
            'company_name': invoice.company_id.name,
            'company_vat': invoice.company_id.vat,
            'company_tax_id': invoice.company_id.vat,
            'company_phone': invoice.company_id.phone,
            'company_email': invoice.company_id.email,
            'company_address': invoice.company_id.contact_address,
            
            # Related Sale Order (if exists)
            'order_id': invoice.invoice_origin if invoice.invoice_origin else '',
            'so_no': invoice.invoice_origin if invoice.invoice_origin else '',
            'order_reference': invoice.ref if invoice.ref else '',
            
            # Status and dates
            'invoice_status': dict(invoice._fields['state'].selection).get(invoice.state),
            'is_paid': 'Yes' if invoice.amount_residual == 0 else 'No',
            'is_overdue': 'Yes' if (invoice.invoice_date_due and invoice.invoice_date_due < invoice._context.get('today', fields.Date.context_today(invoice)) and invoice.amount_residual > 0) else 'No',
            
            # Invoice type
            'invoice_type': dict(invoice._fields['move_type'].selection).get(invoice.move_type),
            'is_refund': 'Yes' if invoice.move_type in ['out_refund', 'in_refund'] else 'No',
        }
        
        return param_mappings.get(param_name, '')

    @api.model
    def send_sale_order_zns(self, sale_order, template_id=None):
        """Quick send ZNS for sale order using your existing configuration"""
        config = self.env['zns.configuration'].get_default_config()
        
        if not template_id:
            template = config.get_template_for_document('sale.order', sale_order)
        else:
            template = self.env['zns.template'].browse(template_id)
        
        if not template:
            raise UserError(_("No ZNS template found"))
        
        phone = self.format_phone_vietnamese(
            sale_order.partner_id.mobile or sale_order.partner_id.phone
        )
        if not phone:
            raise UserError(_("No phone number found for customer"))
        
        # Use enhanced parameter building
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
        """Quick send ZNS for invoice using your existing configuration"""
        config = self.env['zns.configuration'].get_default_config()
        
        if not template_id:
            template = config.get_template_for_document('account.move', invoice)
        else:
            template = self.env['zns.template'].browse(template_id)
        
        if not template:
            raise UserError(_("No ZNS template found"))
        
        phone = self.format_phone_vietnamese(
            invoice.partner_id.mobile or invoice.partner_id.phone
        )
        if not phone:
            raise UserError(_("No phone number found for customer"))
        
        # Use enhanced parameter building
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