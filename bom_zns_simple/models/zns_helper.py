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
        """Build parameters for sale orders using template field mappings"""
        params = {}
        
        # Use template parameter mappings
        for param in template.parameter_ids:
            value = param.get_mapped_value(sale_order)
            if value:
                params[param.name] = str(value)
        
        # Add common fallback parameters if not mapped
        fallback_params = self._get_sale_order_fallback_params(sale_order)
        for param in template.parameter_ids:
            if param.name not in params and param.name in fallback_params:
                params[param.name] = fallback_params[param.name]
        
        return params

    @api.model
    def build_invoice_params(self, invoice, template):
        """Build parameters for invoices using template field mappings"""
        params = {}
        
        # Use template parameter mappings
        for param in template.parameter_ids:
            value = param.get_mapped_value(invoice)
            if value:
                params[param.name] = str(value)
        
        # Add common fallback parameters if not mapped
        fallback_params = self._get_invoice_fallback_params(invoice)
        for param in template.parameter_ids:
            if param.name not in params and param.name in fallback_params:
                params[param.name] = fallback_params[param.name]
        
        return params

    @api.model
    def build_contact_params(self, contact, template):
        """Build parameters for contacts using template field mappings"""
        params = {}
        
        # Use template parameter mappings
        for param in template.parameter_ids:
            value = param.get_mapped_value(contact)
            if value:
                params[param.name] = str(value)
        
        # Add common fallback parameters if not mapped
        fallback_params = self._get_contact_fallback_params(contact)
        for param in template.parameter_ids:
            if param.name not in params and param.name in fallback_params:
                params[param.name] = fallback_params[param.name]
        
        return params

    def _get_sale_order_fallback_params(self, sale_order):
        """Get fallback parameters for sale orders"""
        return {
            # Customer details
            'customer_name': sale_order.partner_id.name,
            'customer_phone': self.format_phone_vietnamese(sale_order.partner_id.mobile or sale_order.partner_id.phone),
            'customer_email': sale_order.partner_id.email,
            'customer_vat': sale_order.partner_id.vat,
            'customer_code': sale_order.partner_id.ref,
            
            # Order details
            'order_id': sale_order.name,
            'so_no': sale_order.name,
            'order_date': sale_order.date_order.strftime('%d/%m/%Y') if sale_order.date_order else '',
            'order_reference': sale_order.client_order_ref,
            'delivery_date': sale_order.commitment_date.strftime('%d/%m/%Y') if sale_order.commitment_date else '',
            
            # Amounts
            'amount': sale_order.amount_total,
            'total_amount': sale_order.amount_total,
            'subtotal': sale_order.amount_untaxed,
            'tax_amount': sale_order.amount_tax,
            'currency': sale_order.currency_id.name,
            'amount_vnd': f"{sale_order.amount_total:,.0f}".replace(',', '.'),
            
            # Company details (includes vat)
            'company_name': sale_order.company_id.name,
            'company_tax_id': sale_order.company_id.vat,
            'company_vat': sale_order.company_id.vat,
            'company_phone': sale_order.company_id.phone,
            'company_email': sale_order.company_id.email,
            
            # Staff
            'salesperson': sale_order.user_id.name if sale_order.user_id else '',
            'sales_person': sale_order.user_id.name if sale_order.user_id else '',
            
            # Product details
            'product_count': len(sale_order.order_line),
            'product_name': sale_order.order_line[0].product_id.name if sale_order.order_line else '',
            'main_product': sale_order.order_line[0].product_id.name if sale_order.order_line else '',
            'total_qty': sum(sale_order.order_line.mapped('product_uom_qty')),
            
            # Status
            'order_status': dict(sale_order._fields['state'].selection).get(sale_order.state),
        }

    def _get_invoice_fallback_params(self, invoice):
        """Get fallback parameters for invoices"""
        return {
            # Customer details
            'customer_name': invoice.partner_id.name,
            'customer_phone': self.format_phone_vietnamese(invoice.partner_id.mobile or invoice.partner_id.phone),
            'customer_email': invoice.partner_id.email,
            'customer_vat': invoice.partner_id.vat,
            'customer_code': invoice.partner_id.ref,
            
            # Invoice details
            'invoice_number': invoice.name,
            'invoice_no': invoice.name,
            'invoice_date': invoice.invoice_date.strftime('%d/%m/%Y') if invoice.invoice_date else '',
            'due_date': invoice.invoice_date_due.strftime('%d/%m/%Y') if invoice.invoice_date_due else '',
            'payment_terms': invoice.invoice_payment_term_id.name if invoice.invoice_payment_term_id else '',
            'invoice_reference': invoice.ref,
            
            # Amounts
            'amount': invoice.amount_total,
            'total_amount': invoice.amount_total,
            'subtotal': invoice.amount_untaxed,
            'tax_amount': invoice.amount_tax,
            'remaining_amount': invoice.amount_residual,
            'currency': invoice.currency_id.name,
            'amount_vnd': f"{invoice.amount_total:,.0f}".replace(',', '.'),
            'remaining_vnd': f"{invoice.amount_residual:,.0f}".replace(',', '.'),
            
            # Company details (includes vat)
            'company_name': invoice.company_id.name,
            'company_tax_id': invoice.company_id.vat,
            'company_vat': invoice.company_id.vat,
            'company_phone': invoice.company_id.phone,
            'company_email': invoice.company_id.email,
            
            # Status
            'invoice_status': dict(invoice._fields['state'].selection).get(invoice.state),
            'payment_status': dict(invoice._fields['payment_state'].selection).get(invoice.payment_state),
            'is_paid': 'Yes' if invoice.amount_residual == 0 else 'No',
        }

    def _get_contact_fallback_params(self, contact):
        """Get fallback parameters for contacts"""
        return {
            # Contact details
            'customer_name': contact.name,
            'contact_name': contact.name,
            'name': contact.name,
            'customer_phone': self.format_phone_vietnamese(contact.mobile or contact.phone),
            'customer_mobile': self.format_phone_vietnamese(contact.mobile),
            'phone': contact.phone,
            'mobile': contact.mobile,
            'customer_email': contact.email,
            'email': contact.email,
            'customer_vat': contact.vat,
            'customer_code': contact.ref,
            'reference': contact.ref,
            'job_position': contact.function,
            'function': contact.function,
            'website': contact.website,
            
            # Address details
            'customer_address': contact.contact_address,
            'full_address': contact.contact_address,
            'street': contact.street,
            'street2': contact.street2,
            'city': contact.city,
            'state': contact.state_id.name if contact.state_id else '',
            'country': contact.country_id.name if contact.country_id else '',
            'zip': contact.zip,
            'zip_code': contact.zip,
            
            # Business details
            'company_name': contact.company_id.name if contact.company_id else contact.name,
            'customer_company': contact.company_id.name if contact.company_id else '',
            'company_tax_id': contact.company_id.vat if contact.company_id else '',
            'company_vat': contact.company_id.vat if contact.company_id else '',
            'tax_id': contact.vat,
            'vat': contact.vat,
            
            # Categories and tags
            'customer_tags': ', '.join(contact.category_id.mapped('name')) if contact.category_id else '',
            'tags': ', '.join(contact.category_id.mapped('name')) if contact.category_id else '',
            
            # Contact type
            'is_company': 'Yes' if contact.is_company else 'No',
            'contact_type': 'Company' if contact.is_company else 'Person',
            
            # Notes
            'notes': contact.comment,
            'comment': contact.comment,
        }

    @api.model
    def send_sale_order_zns(self, sale_order, template_id=None):
        """Send ZNS for sale order"""
        if not template_id:
            # Find template for Sales Orders
            template = self.env['zns.template'].search([
                ('apply_to', 'in', ['sale_order', 'all']),
                ('active', '=', True)
            ], limit=1)
        else:
            template = self.env['zns.template'].browse(template_id)
        
        if not template:
            raise UserError(_("No ZNS template found for Sales Orders"))
        
        phone = self.format_phone_vietnamese(
            sale_order.partner_id.mobile or sale_order.partner_id.phone
        )
        if not phone:
            raise UserError(_("No phone number found for customer"))
        
        # Build parameters
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
        """Send ZNS for invoice"""
        if not template_id:
            # Find template for Invoices
            template = self.env['zns.template'].search([
                ('apply_to', 'in', ['invoice', 'all']),
                ('active', '=', True)
            ], limit=1)
        else:
            template = self.env['zns.template'].browse(template_id)
        
        if not template:
            raise UserError(_("No ZNS template found for Invoices"))
        
        phone = self.format_phone_vietnamese(
            invoice.partner_id.mobile or invoice.partner_id.phone
        )
        if not phone:
            raise UserError(_("No phone number found for customer"))
        
        # Build parameters
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

    @api.model
    def send_contact_zns(self, contact, template_id=None):
        """Send ZNS for contact"""
        if not template_id:
            # Find template for Contacts
            template = self.env['zns.template'].search([
                ('apply_to', 'in', ['contact', 'all']),
                ('active', '=', True)
            ], limit=1)
        else:
            template = self.env['zns.template'].browse(template_id)
        
        if not template:
            raise UserError(_("No ZNS template found for Contacts"))
        
        phone = self.format_phone_vietnamese(contact.mobile or contact.phone)
        if not phone:
            raise UserError(_("No phone number found for contact"))
        
        # Build parameters
        params = self.build_contact_params(contact, template)
        
        # Create and send message
        message = self.env['zns.message'].create({
            'template_id': template.id,
            'connection_id': template.connection_id.id,
            'phone': phone,
            'parameters': json.dumps(params),
            'partner_id': contact.id,
        })
        
        message.send_zns_message()
        return message