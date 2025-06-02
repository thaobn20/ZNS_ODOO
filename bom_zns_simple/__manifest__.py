# -*- coding: utf-8 -*-
{
    'name': 'BOM ZNS Integration - Enhanced',
    'version': '15.0.4.0.0',
    'category': 'Marketing',
    'summary': 'Zalo ZNS integration with BOM API v2',
    'description': """
BOM ZNS Integration
===================

Integration with Zalo ZNS (Zalo Notification Service) through BOM API v2.

Enhanced Zalo ZNS integration with smart template selection and auto-send capabilities.

New Features:
* Smart template selection with conflict resolution
* Template-based default settings for SO/Invoice/Contact
* Multiple defaults with priority system
* Invoice auto-send when posted
* Enhanced manual wizard with recommendations
* Usage analytics and performance tracking
* Comprehensive error handling and validation

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
        'views/account_move_enhanced_views.xml',  # ADD THIS LINE
        
        # Template config
        'views/zns_configuration_views.xml',
        'views/zns_setup_wizard_views.xml',
        
        # Template check (cleaned)
        'views/zns_template_check.xml',
        
        # Auto sync (cleaned)
        'views/zns_auto_sync_views.xml',
        # new extensions
        #'views/zns_template_enhanced_views.xml',
        #'views/zns_wizard_enhanced_views.xml',
        #'views/zns_menu_enhanced.xml',
        #'views/zns_test_enhanced.xml',
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