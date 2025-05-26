# -*- coding: utf-8 -*-
{
    'name': 'BOM ZNS Integration',
    'version': '15.0.2.0.0',
    'category': 'Marketing',
    'summary': 'Send Zalo ZNS messages through BOM API v2',
    'description': """
BOM ZNS Integration
===================

This module integrates Odoo with Zalo ZNS (Zalo Notification Service) through BOM API v2.

Features:
---------
* Send ZNS messages using templates
* Manage different message templates and parameters
* Track message history and status
* Integration with CRM, Sales, and Invoicing
* Token-based authentication with automatic refresh
* Template parameter synchronization

Requirements:
-------------
* BOM API credentials (API Key and API Secret)
* Zalo OA (Official Account) connected to BOM
* Python requests library

API Endpoints Used:
-------------------
* /api/v2/access-token - Get access token
* /api/v2/send-template - Send ZNS message
* /api/v2/template-params - Get template parameters

For more information, visit: https://zns.bom.asia/api/docs/version-2/
    """,
    'author': 'Your Company',
    'website': 'https://www.yourcompany.com',
    'license': 'LGPL-3',
    'depends': [
        'base',
        'contacts',
        'sale',
        'account',
        'mail',
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
    ],
    'qweb': [],
    'demo': [
        'demo/zns_demo.xml',
    ],
    'images': ['static/description/icon.png'],
    'installable': True,
    'auto_install': False,
    'application': True,
    'sequence': 1,
}