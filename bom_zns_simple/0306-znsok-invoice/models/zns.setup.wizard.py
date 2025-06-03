# -*- coding: utf-8 -*-

import logging
from odoo import models, fields, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class ZnsSetupWizard(models.TransientModel):
    _name = 'zns.setup.wizard'
    _description = 'ZNS Setup Wizard'

    # Step tracking
    current_step = fields.Selection([
        ('1_connection', 'Step 1: Connection'),
        ('2_templates', 'Step 2: Templates'), 
        ('3_defaults', 'Step 3: Default Settings'),
        ('4_complete', 'Step 4: Complete')
    ], string='Current Step', default='1_connection')
    
    # Connection setup
    connection_id = fields.Many2one('zns.connection', string='ZNS Connection')
    connection_status = fields.Char('Connection Status', readonly=True)
    
    # Template setup
    available_templates = fields.Text('Available Templates', readonly=True)
    template_count = fields.Integer('Template Count', readonly=True)
    
    # Default configuration
    config_name = fields.Char('Configuration Name', default='Default ZNS Setup')
    default_so_template_id = fields.Many2one('zns.template', string='Sale Order Template')
    default_invoice_template_id = fields.Many2one('zns.template', string='Invoice Template')
    auto_send_so = fields.Boolean('Auto-send SO Confirmation', default=True)
    auto_send_invoice = fields.Boolean('Auto-send Invoice Posted', default=True)
    
    # Business type selection
    business_type = fields.Selection([
        ('ecommerce', 'E-commerce / Online Store'),
        ('b2b', 'B2B / Wholesale Business'),
        ('service', 'Service Business'),
        ('retail', 'Retail Store'),
        ('custom', 'Custom Setup')
    ], string='Business Type', help='Pre-configure settings based on your business type')
    
    # Setup results
    setup_complete = fields.Boolean('Setup Complete', readonly=True)
    setup_summary = fields.Text('Setup Summary', readonly=True)
    
    @api.onchange('business_type')
    def _onchange_business_type(self):
        """Pre-configure settings based on business type"""
        if self.business_type == 'ecommerce':
            self.auto_send_so = True
            self.auto_send_invoice = True
            self.config_name = 'E-commerce ZNS Setup'
            
        elif self.business_type == 'b2b':
            self.auto_send_so = True
            self.auto_send_invoice = False  # B2B might want manual review
            self.config_name = 'B2B ZNS Setup'
            
        elif self.business_type == 'service':
            self.auto_send_so = True
            self.auto_send_invoice = True
            self.config_name = 'Service Business ZNS Setup'
            
        elif self.business_type == 'retail':
            self.auto_send_so = True
            self.auto_send_invoice = True
            self.config_name = 'Retail Store ZNS Setup'
    
    def action_step_1_check_connection(self):
        """Step 1: Check connection status"""
        # Find active connections
        connections = self.env['zns.connection'].search([('active', '=', True)])
        
        if not connections:
            self.connection_status = "❌ No active connections found. Please create a connection first."
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': 'No Connections Found',
                    'message': 'Please create and test a ZNS connection before running setup.',
                    'type': 'warning',
                    'sticky': True,
                }
            }
        
        # Test the first connection
        connection = connections[0]
        self.connection_id = connection.id
        
        try:
            # Test connection
            token = connection._get_access_token()
            self.connection_status = f"✅ Connection '{connection.name}' is working!"
            self.current_step = '2_templates'
            
            return self.action_continue_to_step_2()
            
        except Exception as e:
            self.connection_status = f"❌ Connection test failed: {str(e)}"
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': 'Connection Test Failed',
                    'message': f"Connection '{connection.name}' failed: {str(e)}",
                    'type': 'danger',
                    'sticky': True,
                }
            }
    
    def action_continue_to_step_2(self):
        """Continue to step 2: Templates - PUBLIC METHOD"""
        # Check available templates
        templates = self.env['zns.template'].search([('active', '=', True)])
        self.template_count = len(templates)
        
        if not templates:
            # No templates - stay on step 2 to show sync option
            self.current_step = '2_templates'
            return self.action_view_form()
        
        # Build template summary
        template_summary = []
        for template in templates[:5]:  # Show first 5
            template_summary.append(f"• {template.name} (BOM ID: {template.template_id})")
        
        if len(templates) > 5:
            template_summary.append(f"... and {len(templates) - 5} more")
        
        self.available_templates = "\n".join(template_summary)
        self.current_step = '3_defaults'
        
        # Auto-select suitable templates
        transaction_templates = templates.filtered(lambda t: t.template_type == 'transaction')
        if transaction_templates:
            self.default_so_template_id = transaction_templates[0].id
            if len(transaction_templates) > 1:
                self.default_invoice_template_id = transaction_templates[1].id
            else:
                self.default_invoice_template_id = transaction_templates[0].id
        
        return self.action_view_form()
    
    def action_step_2_sync_templates(self):
        """Step 2: Sync templates from BOM"""
        if not self.connection_id:
            raise UserError("No connection selected")
        
        try:
            # Use the template sync method
            result = self.env['zns.template'].sync_all_templates_from_bom(self.connection_id.id)
            
            # Refresh template list
            templates = self.env['zns.template'].search([('active', '=', True)])
            self.template_count = len(templates)
            
            if templates:
                # Build template summary
                template_summary = []
                for template in templates[:5]:
                    template_summary.append(f"• {template.name} (BOM ID: {template.template_id})")
                
                if len(templates) > 5:
                    template_summary.append(f"... and {len(templates) - 5} more")
                
                self.available_templates = "\n".join(template_summary)
                self.current_step = '3_defaults'
                
                # Auto-select templates
                transaction_templates = templates.filtered(lambda t: t.template_type == 'transaction')
                if transaction_templates:
                    self.default_so_template_id = transaction_templates[0].id
                    if len(transaction_templates) > 1:
                        self.default_invoice_template_id = transaction_templates[1].id
                    else:
                        self.default_invoice_template_id = transaction_templates[0].id
            
            return self.action_view_form()
            
        except Exception as e:
            raise UserError(f"Template sync failed: {str(e)}")
    
    def action_step_3_create_configuration(self):
        """Step 3: Create default configuration"""
        if not self.default_so_template_id:
            raise UserError("Please select a default Sale Order template")
        
        # Create or update configuration
        existing_config = self.env['zns.configuration'].search([('active', '=', True)], limit=1)
        
        config_vals = {
            'name': self.config_name,
            'default_connection_id': self.connection_id.id,
            'default_so_template_id': self.default_so_template_id.id,
            'default_invoice_template_id': self.default_invoice_template_id.id if self.default_invoice_template_id else self.default_so_template_id.id,
            'auto_send_so_confirmation': self.auto_send_so,
            'auto_send_invoice_posted': self.auto_send_invoice,
            'use_template_mappings': True,
            'fallback_to_default': True,
            'customer_phone_required': True,
            'exclude_test_customers': True,
            'notify_on_send_failure': True,
            'notify_on_send_success': False,
            'active': True,
        }
        
        if existing_config:
            existing_config.write(config_vals)
            config = existing_config
        else:
            config = self.env['zns.configuration'].create(config_vals)
        
        # Build setup summary
        business_type_name = dict(self._fields['business_type'].selection).get(self.business_type, 'Custom') if self.business_type else 'Custom'
        
        summary = f"""✅ ZNS Setup Complete!

🔧 Configuration Created: {config.name}

🔗 Connection: {self.connection_id.name}
📋 Templates Found: {self.template_count}
📄 Default SO Template: {self.default_so_template_id.name}
📄 Default Invoice Template: {(self.default_invoice_template_id or self.default_so_template_id).name}

🚀 Auto-Send Settings:
• Sale Order Confirmation: {'✅ Enabled' if self.auto_send_so else '❌ Disabled'}
• Invoice Posted: {'✅ Enabled' if self.auto_send_invoice else '❌ Disabled'}

🎯 Business Type: {business_type_name}

💡 Next Steps:
1. Test sending a ZNS from a Sale Order
2. Create Template Mappings for special conditions
3. Monitor messages in Dashboard
4. Adjust settings in Configuration menu
"""
        
        self.setup_summary = summary
        self.setup_complete = True
        self.current_step = '4_complete'
        
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': '🎉 ZNS Setup Complete!',
                'message': 'Your ZNS integration is now ready to use. Check the Setup Summary for next steps.',
                'type': 'success',
                'sticky': True,
            }
        }
    
    def action_view_form(self):
        """Return form view action"""
        return {
            'type': 'ir.actions.act_window',
            'name': 'ZNS Setup Wizard',
            'res_model': 'zns.setup.wizard',
            'res_id': self.id,
            'view_mode': 'form',
            'target': 'new',
        }
    
    def action_open_configuration(self):
        """Open the created configuration"""
        config = self.env['zns.configuration'].search([('active', '=', True)], limit=1)
        if config:
            return {
                'type': 'ir.actions.act_window',
                'name': 'ZNS Configuration',
                'res_model': 'zns.configuration',
                'res_id': config.id,
                'view_mode': 'form',
                'target': 'current',
            }
        else:
            raise UserError("No active configuration found")
    
    def action_open_dashboard(self):
        """Open ZNS dashboard"""
        return {
            'type': 'ir.actions.act_window',
            'name': 'ZNS Dashboard',
            'res_model': 'zns.message',
            'view_mode': 'graph,pivot,kanban,tree',
            'context': {
                'graph_mode': 'line',
                'graph_measure': '__count__',
                'graph_groupbys': ['create_date:day'],
            }
        }
    
    def action_test_so_zns(self):
        """Test ZNS from a sale order"""
        # Find a confirmed sale order to test with
        test_so = self.env['sale.order'].search([
            ('state', '=', 'sale'),
            ('partner_id.mobile', '!=', False)
        ], limit=1)
        
        if not test_so:
            test_so = self.env['sale.order'].search([
                ('state', '=', 'sale'),
                ('partner_id.phone', '!=', False)
            ], limit=1)
        
        if test_so:
            return {
                'type': 'ir.actions.act_window',
                'name': f'Test ZNS - {test_so.name}',
                'res_model': 'sale.order',
                'res_id': test_so.id,
                'view_mode': 'form',
                'target': 'current',
            }
        else:
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': 'No Test Orders Found',
                    'message': 'No confirmed sale orders with customer phone numbers found for testing.',
                    'type': 'info',
                    'sticky': False,
                }
            }