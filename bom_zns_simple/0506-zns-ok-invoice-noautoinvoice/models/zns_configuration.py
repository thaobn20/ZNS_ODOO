# -*- coding: utf-8 -*-

import json
import logging
from odoo import models, fields, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class ZnsConfiguration(models.Model):
    _name = 'zns.configuration'
    _description = 'ZNS Default Configuration'
    _rec_name = 'name'

    name = fields.Char('Configuration Name', required=True, default='Default ZNS Config')
    
    # Default Templates for Different Document Types
    default_so_template_id = fields.Many2one(
        'zns.template', 
        string='Default Sale Order Template',
        help='Default template used for Sale Order confirmations'
    )
    default_invoice_template_id = fields.Many2one(
        'zns.template', 
        string='Default Invoice Template',
        help='Default template used for Invoice notifications'
    )
    default_contract_template_id = fields.Many2one(
        'zns.template', 
        string='Default Contract Template',
        help='Default template used for Contract notifications'
    )
    
    # Auto-send Settings
    auto_send_so_confirmation = fields.Boolean(
        'Auto Send SO Confirmation', 
        default=True,
        help='Automatically send ZNS when Sale Order is confirmed'
    )
    auto_send_invoice_created = fields.Boolean(
        'Auto Send Invoice Created', 
        default=False,
        help='Automatically send ZNS when Invoice is created'
    )
    auto_send_invoice_posted = fields.Boolean(
        'Auto Send Invoice Posted', 
        default=True,
        help='Automatically send ZNS when Invoice is posted'
    )
    auto_send_payment_received = fields.Boolean(
        'Auto Send Payment Received', 
        default=False,
        help='Automatically send ZNS when payment is received'
    )
    
    # Template Selection Rules
    use_template_mappings = fields.Boolean(
        'Use Template Mappings', 
        default=True,
        help='Use smart template mappings based on conditions (amount, customer type, etc.)'
    )
    fallback_to_default = fields.Boolean(
        'Fallback to Default', 
        default=True,
        help='Use default templates if no mapping matches'
    )
    
    # Connection Settings
    default_connection_id = fields.Many2one(
        'zns.connection',
        string='Default Connection',
        help='Default ZNS connection to use'
    )
    
    # Customer Filter Settings
    customer_phone_required = fields.Boolean(
        'Require Customer Phone', 
        default=True,
        help='Only send ZNS to customers with valid phone numbers'
    )
    exclude_test_customers = fields.Boolean(
        'Exclude Test Customers', 
        default=True,
        help='Skip customers marked as test customers'
    )
    
    # Notification Settings  
    notify_on_send_success = fields.Boolean(
        'Notify on Send Success', 
        default=False,
        help='Show notification when ZNS is sent successfully'
    )
    notify_on_send_failure = fields.Boolean(
        'Notify on Send Failure', 
        default=True,
        help='Show notification when ZNS send fails'
    )
    
    active = fields.Boolean('Active', default=True)
    
    @api.model
    def get_default_config(self):
        """Get the default active configuration"""
        config = self.search([('active', '=', True)], limit=1)
        if not config:
            # Create default configuration if none exists
            config = self.create({
                'name': 'Default ZNS Configuration',
                'auto_send_so_confirmation': True,
                'auto_send_invoice_posted': True,
                'use_template_mappings': True,
                'fallback_to_default': True,
                'customer_phone_required': True,
                'exclude_test_customers': True,
                'notify_on_send_failure': True,
            })
        return config
    
    def get_template_for_document(self, document_type, document=None):
        """Get the best template for a document type"""
        _logger.info(f"=== GETTING TEMPLATE FOR {document_type.upper()} ===")
        
        # Step 1: Try template mappings if enabled
        if self.use_template_mappings and document:
            try:
                template_mapping = self.env['zns.template.mapping']._find_best_mapping(document_type, document)
                if template_mapping:
                    _logger.info(f"✅ Found template via mapping: {template_mapping.name} -> {template_mapping.template_id.name}")
                    return template_mapping.template_id
            except Exception as e:
                _logger.warning(f"Template mapping failed: {e}")
        
        # Step 2: Use default template for document type
        if self.fallback_to_default:
            default_template = None
            if document_type == 'sale.order':
                default_template = self.default_so_template_id
            elif document_type == 'account.move':
                default_template = self.default_invoice_template_id
            elif document_type == 'contract':
                default_template = self.default_contract_template_id
            
            if default_template:
                _logger.info(f"✅ Using default template: {default_template.name}")
                return default_template
        
        # Step 3: Find any suitable template by document type
        template_types = {
            'sale.order': 'transaction',
            'account.move': 'transaction', 
            'contract': 'transaction'
        }
        
        template_type = template_types.get(document_type, 'transaction')
        suitable_template = self.env['zns.template'].search([
            ('active', '=', True),
            ('template_type', '=', template_type),
            ('connection_id.active', '=', True)
        ], limit=1)
        
        if suitable_template:
            _logger.info(f"✅ Found suitable template: {suitable_template.name}")
            return suitable_template
        
        # Step 4: Any active template as last resort
        any_template = self.env['zns.template'].search([
            ('active', '=', True),
            ('connection_id.active', '=', True)
        ], limit=1)
        
        if any_template:
            _logger.info(f"⚠️ Using fallback template: {any_template.name}")
            return any_template
        
        _logger.error(f"❌ No templates found for {document_type}")
        return False
    
    def should_send_zns(self, document_type, event_type, document=None):
        """Check if ZNS should be sent for this document and event"""
        
        # Check auto-send settings
        if document_type == 'sale.order' and event_type == 'confirmation':
            if not self.auto_send_so_confirmation:
                return False, "Auto-send disabled for SO confirmation"
                
        elif document_type == 'account.move' and event_type == 'posted':
            if not self.auto_send_invoice_posted:
                return False, "Auto-send disabled for invoice posting"
                
        elif document_type == 'account.move' and event_type == 'created':
            if not self.auto_send_invoice_created:
                return False, "Auto-send disabled for invoice creation"
        
        # Check customer requirements
        if document and self.customer_phone_required:
            if not document.partner_id:
                return False, "No customer found"
            
            phone = document.partner_id.mobile or document.partner_id.phone
            if not phone:
                return False, "Customer has no phone number"
        
        # Check test customer exclusion
        if document and self.exclude_test_customers:
            if hasattr(document.partner_id, 'is_test_customer') and document.partner_id.is_test_customer:
                return False, "Test customer excluded"
        
        return True, "OK"
    
    def test_configuration(self):
        """Test the current configuration"""
        results = []
        
        # Test default templates
        templates_to_test = [
            ('Sale Order', self.default_so_template_id),
            ('Invoice', self.default_invoice_template_id),
            ('Contract', self.default_contract_template_id),
        ]
        
        for doc_type, template in templates_to_test:
            if template:
                if template.active and template.connection_id and template.connection_id.active:
                    results.append(f"✅ {doc_type}: {template.name} (Ready)")
                else:
                    results.append(f"⚠️ {doc_type}: {template.name} (Inactive or no connection)")
            else:
                results.append(f"❌ {doc_type}: No default template set")
        
        # Test default connection
        if self.default_connection_id:
            if self.default_connection_id.active:
                results.append(f"✅ Default Connection: {self.default_connection_id.name} (Active)")
            else:
                results.append(f"❌ Default Connection: {self.default_connection_id.name} (Inactive)")
        else:
            results.append("⚠️ No default connection set")
        
        # Test template mappings
        mappings = self.env['zns.template.mapping'].search([('active', '=', True)])
        results.append(f"📋 Template Mappings: {len(mappings)} active")
        
        # Test available templates
        templates = self.env['zns.template'].search([('active', '=', True)])
        results.append(f"📋 Active Templates: {len(templates)} total")
        
        message = "🔧 ZNS Configuration Test Results:\n\n" + "\n".join(results)
        
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': 'Configuration Test Complete',
                'message': message,
                'type': 'info',
                'sticky': True,
            }
        }


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
            self.auto_send_invoice = False
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
        connections = self.env['zns.connection'].search([('active', '=', True)])
        
        if not connections:
            self.connection_status = "❌ No active connections found."
            return self.action_view_form()
        
        connection = connections[0]
        self.connection_id = connection.id
        
        try:
            token = connection._get_access_token()
            self.connection_status = f"✅ Connection '{connection.name}' is working!"
            self.current_step = '2_templates'
            return self.action_step_2_check_templates()
        except Exception as e:
            self.connection_status = f"❌ Connection test failed: {str(e)}"
            return self.action_view_form()
    
    def action_step_2_check_templates(self):
        """Step 2: Check templates - PUBLIC METHOD"""
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
    
    def action_step_2_sync_templates(self):
        """Step 2: Sync templates from BOM"""
        if not self.connection_id:
            raise UserError("No connection selected")
        
        try:
            result = self.env['zns.template'].sync_all_templates_from_bom(self.connection_id.id)
            return self.action_step_2_check_templates()
        except Exception as e:
            raise UserError(f"Template sync failed: {str(e)}")
    
    def action_step_3_create_configuration(self):
        """Step 3: Create configuration"""
        if not self.default_so_template_id:
            raise UserError("Please select a default Sale Order template")
        
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
        
        business_type_name = dict(self._fields['business_type'].selection).get(self.business_type, 'Custom') if self.business_type else 'Custom'
        
        summary = f"""✅ ZNS Setup Complete!

🔧 Configuration: {config.name}
🔗 Connection: {self.connection_id.name}
📋 Templates: {self.template_count}
📄 Default SO: {self.default_so_template_id.name}
📄 Default Invoice: {(self.default_invoice_template_id or self.default_so_template_id).name}

🚀 Auto-Send:
• SO Confirmation: {'✅' if self.auto_send_so else '❌'}
• Invoice Posted: {'✅' if self.auto_send_invoice else '❌'}

🎯 Business Type: {business_type_name}

💡 Next Steps:
1. Test ZNS from Sale Order
2. Monitor in Dashboard
3. Create Template Mappings
4. Adjust settings as needed
"""
        
        self.setup_summary = summary
        self.setup_complete = True
        self.current_step = '4_complete'
        
        return self.action_view_form()
    
    def action_view_form(self):
        """Return form view"""
        return {
            'type': 'ir.actions.act_window',
            'name': 'ZNS Setup Wizard',
            'res_model': 'zns.setup.wizard',
            'res_id': self.id,
            'view_mode': 'form',
            'target': 'new',
        }
    
    def action_open_configuration(self):
        """Open configuration"""
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
        """Open dashboard"""
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
        """Test ZNS"""
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
                    'message': 'No confirmed sale orders with phone numbers found.',
                    'type': 'info',
                    'sticky': False,
                }
            }