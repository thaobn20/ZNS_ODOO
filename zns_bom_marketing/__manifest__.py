# -*- coding: utf-8 -*-
{
    'name': 'ZNS BOM Marketing',
    'version': '1.0.0',
    'category': 'Marketing',
    'summary': 'Marketing automation for ZNS messaging with birthday campaigns',
    'description': """
ZNS BOM Marketing Module
========================

Advanced marketing automation system that optionally integrates with bom_zns_simple module to provide:

* Contact List Management (Static, Dynamic, Auto-Birthday)
* Campaign Management (Promotion, Birthday, Notification, Recurring)
* Automatic Birthday Detection and Messaging
* Campaign Analytics and Reporting
* Opt-out Management
* Message Queue Processing
* Scheduled Campaign Execution

Features:
---------
* Designed to work with bom_zns_simple infrastructure
* Automatic birthday ZNS sending
* Campaign performance tracking
* Contact list health monitoring
* Dynamic list updates
* Message retry handling
* Comprehensive dashboard

Note:
-----
* This module can be installed independently
* For full ZNS functionality, install bom_zns_simple module
* Templates and connections will be available once BOM ZNS Simple is installed
    """,
    'author': 'Your Company',
    'website': 'https://www.yourcompany.com',
    'depends': [
        'base',
        'contacts',
        'mail',
        # 'bom_zns_simple'  # Optional dependency - commented out for standalone installation
    ],
    'external_dependencies': {
        'python': [],
    },
    'data': [
        # Security
        'security/ir.model.access.csv',
        'security/zns_bom_marketing_security.xml',
        
        # Data
        'data/zns_bom_marketing_data.xml',
        'data/zns_bom_marketing_cron.xml',
        
        # Views
        'views/zns_bom_marketing_contact_list_views.xml',
        'views/zns_bom_marketing_campaign_views.xml',
        'views/zns_bom_marketing_message_views.xml',
        'views/zns_bom_marketing_opt_out_views.xml',
        'views/zns_bom_marketing_analytics_views.xml',
        'views/zns_bom_marketing_dashboard_views.xml',
        'views/zns_bom_marketing_menus.xml',
    ],
    'demo': [],
    'images': ['static/description/icon.png'],
    'license': 'LGPL-3',
    'installable': True,
    'application': True,
    'auto_install': False,
    'sequence': 100,
}