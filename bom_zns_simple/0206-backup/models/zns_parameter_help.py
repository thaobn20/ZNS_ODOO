# -*- coding: utf-8 -*-

from odoo import models, fields, api, _


class ZnsParameterHelp(models.TransientModel):
    _name = 'zns.parameter.help'
    _description = 'ZNS Parameter Help'
    
    parameter_name = fields.Char('Parameter Name', required=True)
    description = fields.Text('Description', compute='_compute_help_content')
    examples = fields.Text('Examples', compute='_compute_help_content')
    data_source = fields.Text('Data Source', compute='_compute_help_content')
    
    @api.depends('parameter_name')
    def _compute_help_content(self):
        """Compute help content based on parameter name"""
        help_data = self._get_parameter_help_data()
        
        for record in self:
            param_info = help_data.get(record.parameter_name, {})
            record.description = param_info.get('description', 'No description available')
            record.examples = param_info.get('examples', 'No examples available')
            record.data_source = param_info.get('data_source', 'Manual input')
    
    def _get_parameter_help_data(self):
        """Get parameter help data dictionary"""
        return {
            # Customer Information
            'customer_name': {
                'description': 'Name of the customer from the order/invoice',
                'examples': 'John Doe, ABC Company Ltd, Nguyễn Văn A',
                'data_source': 'Customer record (partner_id.name)'
            },
            'customer_phone': {
                'description': 'Phone number of the customer',
                'examples': '0987654321, +84987654321',
                'data_source': 'Customer mobile or phone field'
            },
            'customer_email': {
                'description': 'Email address of the customer',
                'examples': 'john@example.com, contact@company.com',
                'data_source': 'Customer email field'
            },
            'customer_code': {
                'description': 'Customer reference code',
                'examples': 'CUST001, VIP123',
                'data_source': 'Customer reference field'
            },
            'customer_address': {
                'description': 'Full address of the customer',
                'examples': '123 Main Street, Ho Chi Minh City',
                'data_source': 'Customer contact address'
            },
            
            # Order Information
            'order_id': {
                'description': 'Order number/reference',
                'examples': 'SO001, PO/2024/001',
                'data_source': 'Order name field'
            },
            'so_no': {
                'description': 'Sales order number (same as order_id)',
                'examples': 'SO001, SO/2024/001',
                'data_source': 'Sales order name field'
            },
            'order_date': {
                'description': 'Date when the order was created',
                'examples': '25/01/2024, 2024-01-25',
                'data_source': 'Order date field (formatted as DD/MM/YYYY)'
            },
            'order_reference': {
                'description': 'Customer reference for the order',
                'examples': 'REF001, Customer PO 123',
                'data_source': 'Client order reference field'
            },
            'order_status': {
                'description': 'Current status of the order',
                'examples': 'Draft, Confirmed, Done',
                'data_source': 'Order state field (translated)'
            },
            
            # Product Information
            'product_name': {
                'description': 'Name of the main product',
                'examples': 'T-Shirt, Laptop Dell XPS',
                'data_source': 'First order line product name'
            },
            'product_count': {
                'description': 'Number of different products in the order',
                'examples': '1, 5, 10',
                'data_source': 'Count of order lines'
            },
            'total_qty': {
                'description': 'Total quantity of all products',
                'examples': '1, 25, 100',
                'data_source': 'Sum of all order line quantities'
            },
            'main_product': {
                'description': 'Name of the first/main product',
                'examples': 'iPhone 15, Samsung TV',
                'data_source': 'First order line product'
            },
            
            # Amount Information
            'amount': {
                'description': 'Total amount of the order/invoice',
                'examples': '1000000, 5500000',
                'data_source': 'Total amount including taxes'
            },
            'total_amount': {
                'description': 'Total amount (same as amount)',
                'examples': '1000000, 5500000',
                'data_source': 'Total amount including taxes'
            },
            'subtotal': {
                'description': 'Subtotal amount (before taxes)',
                'examples': '900000, 5000000',
                'data_source': 'Amount before taxes'
            },
            'tax_amount': {
                'description': 'Total tax amount',
                'examples': '100000, 500000',
                'data_source': 'Calculated tax amount'
            },
            'amount_vnd': {
                'description': 'Amount formatted in Vietnamese style',
                'examples': '1.000.000, 5.500.000',
                'data_source': 'Formatted total amount'
            },
            'amount_words': {
                'description': 'Amount in Vietnamese words',
                'examples': '1 triệu, 5.5 triệu',
                'data_source': 'Converted amount to words'
            },
            
            # Invoice Specific
            'invoice_number': {
                'description': 'Invoice number',
                'examples': 'INV/2024/001, BILL/001',
                'data_source': 'Invoice name field'
            },
            'due_date': {
                'description': 'Payment due date',
                'examples': '31/01/2024, 2024-01-31',
                'data_source': 'Invoice due date (formatted as DD/MM/YYYY)'
            },
            'invoice_date': {
                'description': 'Invoice creation date',
                'examples': '25/01/2024, 2024-01-25',
                'data_source': 'Invoice date field'
            },
            'remaining_amount': {
                'description': 'Remaining amount to be paid',
                'examples': '500000, 0',
                'data_source': 'Invoice residual amount'
            },
            
            # Company Information
            'company_name': {
                'description': 'Name of your company',
                'examples': 'Your Company Ltd, ABC Corp',
                'data_source': 'Company name from order'
            },
            'salesperson': {
                'description': 'Name of the salesperson',
                'examples': 'John Smith, Nguyễn Văn B',
                'data_source': 'User assigned to order'
            },
            
            # Other
            'currency': {
                'description': 'Currency of the order/invoice',
                'examples': 'VND, USD, EUR',
                'data_source': 'Order currency'
            },
            'payment_terms': {
                'description': 'Payment terms',
                'examples': '30 Days, Immediate Payment',
                'data_source': 'Payment terms from order'
            },
            'delivery_date': {
                'description': 'Expected delivery date',
                'examples': '30/01/2024, 2024-01-30',
                'data_source': 'Commitment date from order'
            },
            'order_note': {
                'description': 'Notes/comments on the order',
                'examples': 'Rush order, Handle with care',
                'data_source': 'Internal notes field'
            },
        }