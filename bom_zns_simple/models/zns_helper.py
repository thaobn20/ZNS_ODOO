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
        _logger.info(f"=== BUILDING SO PARAMETERS FOR {sale_order.name} ===")
        _logger.info(f"Template: {template.name} (BOM ID: {template.template_id})")
        
        params = {}
        
        # Use template parameter mappings if available
        for param in template.parameter_ids:
            _logger.info(f"Processing SO parameter: {param.name}")
            value = None
            
            # First try to get mapped value from field mapping
            if hasattr(param, 'field_mapping') and param.field_mapping:
                value = param.get_mapped_value(sale_order)
                _logger.info(f"  Field mapping value: {value}")
            # Fallback to old so_field_mapping for backward compatibility
            elif hasattr(param, 'so_field_mapping') and param.so_field_mapping:
                value = param.get_mapped_value(sale_order)
                _logger.info(f"  SO field mapping value: {value}")
            
            # If no mapping or mapping failed, try standard parameter names
            if not value:
                value = self._get_standard_so_param_value(sale_order, param.name)
                _logger.info(f"  Standard SO value: {value}")
            
            # Use default if no value found
            if not value:
                value = param.default_value or ''
                _logger.info(f"  Default value: {value}")
            
            if value:
                params[param.name] = str(value)
                _logger.info(f"  ✅ Added SO param: {param.name} = {value}")
        
        _logger.info(f"✅ Final SO parameters: {params}")
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
        """Enhanced parameter building for invoices - CRITICAL FIX for Template data empty error"""
        _logger.info(f"=== BUILDING INVOICE PARAMETERS FOR {invoice.name} ===")
        _logger.info(f"Template: {template.name} (BOM ID: {template.template_id})")
        _logger.info(f"Template parameters count: {len(template.parameter_ids)}")
        
        params = {}
        
        # STEP 1: Try template parameter mappings first
        if template.parameter_ids:
            _logger.info("🔄 Using template parameter mappings...")
            for param in template.parameter_ids:
                _logger.info(f"Processing invoice parameter: {param.name}")
                value = None
                
                # Get mapped value from invoice fields
                if hasattr(param, 'field_mapping') and param.field_mapping:
                    value = param.get_mapped_value(invoice)
                    _logger.info(f"  Field mapping value: {value}")
                # Fallback to old so_field_mapping for backward compatibility
                elif hasattr(param, 'so_field_mapping') and param.so_field_mapping:
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
                            _logger.info(f"  SO field mapping adapted value: {value}")
                        except Exception as e:
                            _logger.warning(f"Error mapping invoice parameter {param.name}: {e}")
                
                # If no mapping or mapping failed, try standard parameter names
                if not value:
                    value = self._get_standard_invoice_param_value(invoice, param.name)
                    _logger.info(f"  Standard invoice value: {value}")
                
                # Use default if no value found
                if not value:
                    value = param.default_value or ''
                    _logger.info(f"  Default value: {value}")
                
                if value:
                    params[param.name] = str(value)
                    _logger.info(f"  ✅ Added invoice param: {param.name} = {value}")
        
        # STEP 2: If no parameters from template mappings, build standard ones
        if not params:
            _logger.warning(f"No parameters from template mappings, building standard invoice parameters...")
            # Build comprehensive standard parameters to avoid "Template data empty" error
            standard_params = {
                # Essential customer info
                'customer_name': invoice.partner_id.name or '',
                'customer_phone': self.format_phone_vietnamese(invoice.partner_id.mobile or invoice.partner_id.phone) or '',
                'customer_email': invoice.partner_id.email or '',
                
                # Essential invoice info
                'invoice_number': invoice.name or '',
                'invoice_no': invoice.name or '',
                'invoice_id': invoice.name or '',
                'bill_number': invoice.name or '',
                'order_id': invoice.name or '',  # Many templates use order_id for invoice number
                'so_no': invoice.name or '',     # Many templates use so_no for invoice number
                
                # Essential dates
                'invoice_date': invoice.invoice_date.strftime('%d/%m/%Y') if invoice.invoice_date else '',
                'due_date': invoice.invoice_date_due.strftime('%d/%m/%Y') if invoice.invoice_date_due else '',
                
                # Essential amounts
                'amount': str(invoice.amount_total) or '0',
                'total_amount': str(invoice.amount_total) or '0',
                'subtotal': str(invoice.amount_untaxed) or '0',
                'tax_amount': str(invoice.amount_tax) or '0',
                'remaining_amount': str(invoice.amount_residual) or '0',
                'amount_vnd': f"{invoice.amount_total:,.0f}".replace(',', '.') if invoice.amount_total else '0',
                
                # Essential company info
                'company_name': invoice.company_id.name or '',
                'company_vat': invoice.company_id.vat or '',
                'company_tax_id': invoice.company_id.vat or '',
                
                # Common parameters
                'currency': invoice.currency_id.name or 'VND',
                'payment_status': 'Paid' if invoice.amount_residual == 0 else 'Unpaid',
                'invoice_status': dict(invoice._fields['state'].selection).get(invoice.state, ''),
            }
            
            # Add non-empty parameters only
            for key, value in standard_params.items():
                if value:
                    params[key] = str(value)
                    _logger.info(f"  ✅ Added standard param: {key} = {value}")
        
        _logger.info(f"✅ Final invoice parameters count: {len(params)}")
        _logger.info(f"✅ Final invoice parameters: {params}")
        
        # CRITICAL: Ensure we have at least basic parameters
        if not params:
            _logger.error("❌ No parameters built! Creating minimal fallback...")
            params = {
                'customer_name': invoice.partner_id.name or 'Customer',
                'invoice_number': invoice.name or 'INV001',
                'amount': str(invoice.amount_total) or '0'
            }
            _logger.info(f"⚠️ Using fallback parameters: {params}")
        
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
            'partner_id.name': 'partner_id.name',
            'partner_id.mobile': 'partner_id.mobile',
            'partner_id.phone': 'partner_id.phone',
            'partner_id.email': 'partner_id.email',
            'partner_id.ref': 'partner_id.ref',
            'partner_id.vat': 'partner_id.vat',
            'company_id.name': 'company_id.name',
            'company_id.vat': 'company_id.vat',
        }
        
        return mapping_conversions.get(so_mapping, so_mapping)

    def _get_standard_invoice_param_value(self, invoice, param_name):
        """Get standard parameter values for invoice by common names - COMPREHENSIVE"""
        _logger.info(f"Getting standard invoice param: {param_name}")
        
        param_mappings = {
            # Customer details
            'customer_name': invoice.partner_id.name,
            'customer_phone': self.format_phone_vietnamese(invoice.partner_id.mobile or invoice.partner_id.phone),
            'customer_email': invoice.partner_id.email,
            'customer_code': invoice.partner_id.ref,
            'customer_address': invoice.partner_id.contact_address,
            'customer_vat': invoice.partner_id.vat,
            
            # Invoice details - COMPREHENSIVE COVERAGE
            'invoice_number': invoice.name,
            'invoice_no': invoice.name,
            'invoice_id': invoice.name,
            'bill_number': invoice.name,
            'order_id': invoice.name,        # CRITICAL: Many templates use order_id for invoice
            'so_no': invoice.name,           # CRITICAL: Many templates use so_no for invoice
            'order_number': invoice.name,    # Some templates use this
            'document_number': invoice.name, # Alternative name
            'ref_number': invoice.name,      # Reference number
            
            # Dates in multiple formats
            'invoice_date': invoice.invoice_date.strftime('%d/%m/%Y') if invoice.invoice_date else '',
            'due_date': invoice.invoice_date_due.strftime('%d/%m/%Y') if invoice.invoice_date_due else '',
            'order_date': invoice.invoice_date.strftime('%d/%m/%Y') if invoice.invoice_date else '',  # Some templates expect order_date
            'invoice_date_short': invoice.invoice_date.strftime('%d/%m') if invoice.invoice_date else '',
            'due_date_short': invoice.invoice_date_due.strftime('%d/%m') if invoice.invoice_date_due else '',
            
            # Amounts - CRITICAL for most templates
            'amount': invoice.amount_total,
            'total_amount': invoice.amount_total,
            'subtotal': invoice.amount_untaxed,
            'tax_amount': invoice.amount_tax,
            'remaining_amount': invoice.amount_residual,
            'amount_vnd': f"{invoice.amount_total:,.0f}".replace(',', '.'),
            'remaining_vnd': f"{invoice.amount_residual:,.0f}".replace(',', '.'),
            'amount_words': self._number_to_words_vn(invoice.amount_total),
            'total_vnd': f"{invoice.amount_total:,.0f}".replace(',', '.'),
            
            # Status and type
            'invoice_status': dict(invoice._fields['state'].selection).get(invoice.state),
            'invoice_type': dict(invoice._fields['move_type'].selection).get(invoice.move_type),
            'is_paid': 'Yes' if invoice.amount_residual == 0 else 'No',
            'payment_status': 'Paid' if invoice.amount_residual == 0 else 'Unpaid',
            'order_status': dict(invoice._fields['state'].selection).get(invoice.state),  # Some templates use this
            
            # Company details - IMPORTANT
            'company_name': invoice.company_id.name,
            'company_vat': invoice.company_id.vat,
            'company_tax_id': invoice.company_id.vat,
            'company_phone': invoice.company_id.phone,
            'company_email': invoice.company_id.email,
            'salesperson': invoice.invoice_user_id.name if invoice.invoice_user_id else '',
            'sales_person': invoice.invoice_user_id.name if invoice.invoice_user_id else '',
            
            # Additional info
            'currency': invoice.currency_id.name,
            'payment_terms': invoice.invoice_payment_term_id.name if invoice.invoice_payment_term_id else '',
            'invoice_note': invoice.narration,
            'reference': invoice.ref or '',
            'origin': invoice.invoice_origin or '',
            'sale_order': invoice.invoice_origin or '',  # Often contains SO reference
            
            # Product information (if available)
            'product_count': len(invoice.invoice_line_ids),
            'main_product': invoice.invoice_line_ids[0].product_id.name if invoice.invoice_line_ids else '',
            'product_name': invoice.invoice_line_ids[0].product_id.name if invoice.invoice_line_ids else '',
            'product_list': ', '.join(invoice.invoice_line_ids.mapped('product_id.name')[:3]) if invoice.invoice_line_ids else '',
            'total_qty': sum(invoice.invoice_line_ids.mapped('quantity')) if invoice.invoice_line_ids else 0,
        }
        
        value = param_mappings.get(param_name, '')
        _logger.info(f"Standard invoice param {param_name} = {value}")
        return value

    @api.model
    def send_sale_order_zns(self, sale_order, template_id=None):
        """Quick send ZNS for sale order with enhanced parameter mapping"""
        if not template_id:
            # Find best template mapping
            if hasattr(self.env['zns.template.mapping'], '_find_best_mapping'):
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
                template = self.env['zns.template'].search([
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
            if hasattr(self.env['zns.template.mapping'], '_find_best_mapping'):
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
                template = self.env['zns.template'].search([
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
        return message