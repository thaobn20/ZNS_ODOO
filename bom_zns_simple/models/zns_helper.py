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
        """Enhanced parameter building for sale orders using template parameter mappings"""
        params = {}
        
        # Use template parameter mappings if available
        for param in template.parameter_ids:
            value = None
            
            # First try to get mapped value from SO field mapping
            if param.so_field_mapping:
                value = param.get_mapped_value(sale_order)
            
            # If no mapping or mapping failed, try standard parameter names
            if not value:
                value = self._get_standard_so_param_value(sale_order, param.name)
            
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
            
            # Company details
            'company_name': sale_order.company_id.name,
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
    def send_sale_order_zns(self, sale_order, template_id=None):
        """Quick send ZNS for sale order with enhanced parameter mapping"""
        if not template_id:
            # Find best template mapping
            template_mapping = self.env['zns.template.mapping']._find_best_mapping('sale.order', sale_order)
            if template_mapping:
                template = template_mapping.template_id
            else:
                # Find default sale order template
                template = self.env['zns.template'].search([
                    ('template_type', '=', 'transaction'),
                    ('active', '=', True)
                ], limit=1, order='id')
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
        """Quick send ZNS for invoice with enhanced parameter mapping"""
        if not template_id:
            # Find best template mapping
            template_mapping = self.env['zns.template.mapping']._find_best_mapping('account.move', invoice)
            if template_mapping:
                template = template_mapping.template_id
            else:
                # Find default invoice template
                template = self.env['zns.template'].search([
                    ('template_type', '=', 'transaction'),
                    ('active', '=', True)
                ], limit=1, order='id')
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
        return messageings.get(param_name, '')

    @api.model
    def build_invoice_params(self, invoice, template):
        """Enhanced parameter building for invoices using template parameter mappings"""
        params = {}
        
        # Use template parameter mappings if available
        for param in template.parameter_ids:
            value = None
            
            # Get mapped value from invoice fields
            if param.so_field_mapping:
                # Adapt SO field mapping to invoice fields
                invoice_mapping = self._adapt_so_mapping_to_invoice(param.so_field_mapping)
                if invoice_mapping:
                    try:
                        obj = invoice
                        for field_part in invoice_mapping.split('.'):
                            obj = getattr(obj, field_part, '')
                            if not obj:
                                break
                        
                        if param.param_type == 'date' and hasattr(obj, 'strftime'):
                            value = obj.strftime('%d/%m/%Y')
                        elif param.param_type == 'number':
                            value = str(obj) if obj else '0'
                        else:
                            value = str(obj) if obj else ''
                    except Exception as e:
                        _logger.warning(f"Error mapping invoice parameter {param.name}: {e}")
            
            # If no mapping or mapping failed, try standard parameter names
            if not value:
                value = self._get_standard_invoice_param_value(invoice, param.name)
            
            # Use default if no value found
            if not value:
                value = param.default_value or ''
            
            if value:
                params[param.name] = str(value)
        
        return params

    def _adapt_so_mapping_to_invoice(self, so_mapping):
        """Adapt Sale Order field mapping to Invoice fields"""
        # Map SO fields to invoice fields
        mapping_conversions = {
            'name': 'name',  # Invoice number
            'date_order': 'invoice_date',
            'amount_total': 'amount_total',
            'amount_untaxed': 'amount_untaxed',
            'amount_tax': 'amount_tax',
            'user_id.name': 'invoice_user_id.name',
            'client_order_ref': 'ref',
            'commitment_date': 'invoice_date_due',
            'note': 'narration',
            'state': 'state',
            'currency_id.name': 'currency_id.name',
            'payment_term_id.name': 'invoice_payment_term_id.name',
        }
        
        return mapping_conversions.get(so_mapping, so_mapping)

    def _get_standard_invoice_param_value(self, invoice, param_name):
        """Get standard parameter values for invoice by common names"""
        param_mappings = {
            # Customer details
            'customer_name': invoice.partner_id.name,
            'customer_phone': self.format_phone_vietnamese(invoice.partner_id.mobile or invoice.partner_id.phone),
            'customer_email': invoice.partner_id.email,
            'customer_code': invoice.partner_id.ref,
            'customer_address': invoice.partner_id.contact_address,
            
            # Invoice details
            'invoice_number': invoice.name,
            'invoice_no': invoice.name,
            'invoice_date': invoice.invoice_date.strftime('%d/%m/%Y') if invoice.invoice_date else '',
            'due_date': invoice.invoice_date_due.strftime('%d/%m/%Y') if invoice.invoice_date_due else '',
            'payment_terms': invoice.invoice_payment_term_id.name if invoice.invoice_payment_term_id else '',
            'invoice_note': invoice.narration,
            'currency': invoice.currency_id.name,
            
            # Amounts
            'amount': invoice.amount_total,
            'total_amount': invoice.amount_total,
            'subtotal': invoice.amount_untaxed,
            'tax_amount': invoice.amount_tax,
            'remaining_amount': invoice.amount_residual,
            'amount_vnd': f"{invoice.amount_total:,.0f}".replace(',', '.'),
            'remaining_vnd': f"{invoice.amount_residual:,.0f}".replace(',', '.'),
            'amount_words': self._number_to_words_vn(invoice.amount_total),
            
            # Company details
            'company_name': invoice.company_id.name,
            
            # Status
            'invoice_status': dict(invoice._fields['state'].selection).get(invoice.state),
            'is_paid': 'Yes' if invoice.amount_residual == 0 else 'No',
        }
        
        return param_mapp