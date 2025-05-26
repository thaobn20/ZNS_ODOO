# -*- coding: utf-8 -*-
{
    'name': 'BOM ZNS Integration - Enhanced',
    'version': '15.0.3.0.0',
    'category': 'Marketing',
    'summary': 'Advanced Zalo ZNS integration with automatic sending and template mapping',
    'description': """
BOM ZNS Integration - Enhanced
==============================

Advanced integration with Zalo ZNS (Zalo Notification Service) through BOM API v2.

🚀 NEW FEATURES:
-----------------
* **Automatic ZNS Sending**: Auto-send when Sales Orders are confirmed
* **Template Mapping System**: Smart template selection based on order conditions
* **Template Synchronization**: Sync templates directly from BOM API
* **Enhanced Parameter Mapping**: 30+ automatic parameters from order/invoice data
* **Parameter Help System**: Built-in help for all parameters
* **Advanced Wizard**: Auto-detect templates, preview messages, auto-fill parameters

📱 CORE FEATURES:
-----------------
* Send ZNS messages using templates
* Manage different message templates and parameters
* Track message history and status
* Integration with CRM, Sales, and Invoicing
* Token-based authentication with automatic refresh
* Template parameter synchronization
* Dashboard with analytics and reporting

🎯 TEMPLATE MAPPING:
-------------------
* **Condition-based**: Customer type, order amount, product categories
* **Custom Logic**: Python code for complex conditions
* **Priority System**: Multiple mappings with priority ordering
* **Usage Tracking**: Monitor mapping effectiveness
* **Testing Tools**: Test mappings before deployment

📊 SALES ORDER INTEGRATION:
---------------------------
* **Auto-send on Confirmation**: Configurable per order
* **Manual Sending**: Send anytime with template selection
* **Parameter Auto-fill**: 30+ parameters automatically populated
* **Template Detection**: Smart template recommendation
* **Message History**: Track all ZNS messages per order

📋 PARAMETER SYSTEM:
-------------------
Available parameters include:
• Customer: name, phone, email, address, code
• Order: number, date, status, reference, notes
• Products: name, count, quantities, categories
• Amounts: total, subtotal, taxes, formatted, in words
• Company: name, salesperson, payment terms
• Dates: delivery, due dates, creation dates

🔧 REQUIREMENTS:
---------------
* Odoo 15.0+
* Python `requests` library
* BOM API credentials (JWT Token)
* Zalo Official Account connected to BOM

📚 API ENDPOINTS USED:
---------------------
* /api/v2/access-token - Get access token
* /api/v2/send-zns-by-template - Send ZNS message
* /api/v2/get-param-zns-template - Get template parameters

For more information, visit: https://zns.bom.asia/api/docs/version-2/
    """,
    'author': 'Your Company',
    'website': 'https://www.yourcompany.com',
    'license': 'LGPL-3',
    'depends': [
        'base',
        'contacts',
        'sale',
        'purchase',
        'account',
        'mail',
        'product',
    ],
    'external_dependencies': {
        'python': ['requests'],
    },
    'data': [
        'security/zns_security.xml',
        'security/ir.model.access.csv',
        'data/zns_data.xml',
        'views/zns_connection_views.xml',
        'views/zns_template_views.xml',
        'views/zns_message_views.xml',
        'views/zns_wizard_views.xml',
        'views/zns_dashboard_views.xml',
        'views/res_partner_views.xml',
        'views/sale_order_views.xml',
        'views/account_move_views.xml',
        'views/zns_menus.xml',
        # New enhanced views
        'views/sale_order_enhanced_views.xml',
        'views/zns_template_mapping_views.xml',
    ],
    'qweb': [],
    'demo': [
        'demo/zns_demo.xml',
        'demo/zns_enhanced_demo.xml',
    ],
    'images': ['static/description/icon.png'],
    'installable': True,
    'auto_install': False,
    'application': True,
    'sequence': 1,
}