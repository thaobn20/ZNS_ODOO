# -*- coding: utf-8 -*-
{
    'name': 'BOM ZNS Integration - Enhanced',
    'version': '15.0.3.2.2',
    'category': 'Marketing',
    'summary': 'Zalo ZNS integration with BOM API v2',
    'description': """
BOM ZNS Integration
===================

Integration with Zalo ZNS (Zalo Notification Service) through BOM API v2.

Features:
* Send ZNS messages using templates
* Template parameter synchronization
* Sales Order and Invoice integration
* Message tracking and analytics
* Dashboard and reporting

Requirements:
* Odoo 15.0+
* BOM API credentials
* Python requests library
    """,
    'author': 'Your Company',
    'website': 'https://www.bom.asia',
    'license': 'LGPL-3',
    'depends': [
        'base',
        'contacts', 
        'sale',
        'account',
    ],
    'external_dependencies': {
        'python': ['requests'],
    },
    'data': [
        # Security
        'security/zns_security.xml',
        'security/ir.model.access.csv',
        
        # Data
        'data/zns_data.xml',
        
        # Base views
        'views/zns_connection_views.xml',
        'views/zns_template_views.xml',
        'views/zns_message_views.xml',
        'views/zns_wizard_views.xml',
        
        # Extended features
        'views/zns_template_mapping_views.xml',
        'views/zns_dashboard_views.xml',
        
        # Model extensions
        'views/res_partner_views.xml',
        'views/sale_order_views.xml',
        'views/account_move_views.xml',
        
        # Template check (cleaned)
        'views/zns_template_check.xml',
        
        # Auto sync (cleaned)
        'views/zns_auto_sync_views.xml',
        
        # Menus LAST
        'views/zns_menus.xml',
    ],
    'demo': [],
    'images': ['static/description/icon.png'],
    'installable': True,
    'auto_install': False,
    'application': True,
    'sequence': 1,
}