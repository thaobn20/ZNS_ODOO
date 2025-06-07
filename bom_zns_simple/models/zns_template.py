# -*- coding: utf-8 -*-

import requests
import logging
from odoo import models, fields, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class ZnsTemplate(models.Model):
    _name = 'zns.template'
    _description = 'ZNS Template'
    _rec_name = 'name'

    name = fields.Char('Template Name', required=True)
    template_id = fields.Char('Template ID', required=True, help='Template ID from BOM ZNS')
    template_type = fields.Selection([
        ('transaction', 'Transaction'),
        ('otp', 'OTP'),
        ('promotion', 'Promotion'),
        ('customer_care', 'Customer Care')
    ], string='Template Type', default='transaction')
    
    # Document Type Selection - UPDATED: Changed "all" to "pending" and made it default
    apply_to = fields.Selection([
        ('pending', 'Pending'),
        ('sale_order', 'Sales Orders'),
        ('invoice', 'Invoices'),
        ('contact', 'Contacts'),
    ], string='Apply To', default='pending', required=True, 
       help="Choose which document types this template applies to. Set to 'Pending' until manually configured.")
    
    connection_id = fields.Many2one('zns.connection', string='ZNS Connection', required=True)
    active = fields.Boolean('Active', default=True)
    parameter_ids = fields.One2many('zns.template.parameter', 'template_id', string='Parameters')
    last_sync = fields.Datetime('Last Sync', readonly=True)
    sync_status = fields.Text('Last Sync Status', readonly=True)
    
    # ADDED: Fields to prevent duplicate sync and parameter clearing
    parameters_synced = fields.Boolean('Parameters Synced', default=False, readonly=True, 
                                     help="True if template parameters have been synced from BOM")
    
    def sync_template_params(self):
        """Sync template parameters from BOM API - UPDATED: Don't clear existing parameters"""
        connection = self.connection_id
        if not connection:
            raise UserError("No connection configured")
        
        try:
            # Get access token
            access_token = connection._get_access_token()
            
            # Use exact format from BOM API
            url = f"{connection.api_base_url}/get-param-zns-template"
            headers = {'Authorization': f'Bearer {access_token}'}
            data = {'template_id': self.template_id}
            
            response = requests.post(url, headers=headers, json=data, timeout=30)
            
            if response.status_code != 200:
                error_msg = f"HTTP {response.status_code}: {response.text}"
                self.write({'sync_status': error_msg})
                raise UserError(f"❌ {error_msg}")
            
            result = response.json()
            
            if result.get('error') == '0' or result.get('error') == 0:
                params_data = result.get('data', [])
                
                # UPDATED: Only clear parameters if none exist or forced
                if not self.parameter_ids or self.env.context.get('force_sync'):
                    # Clear existing parameters only if forced or none exist
                    self.parameter_ids.unlink()
                    
                    # Create new parameters
                    created_params = []
                    for param in params_data:
                        param_name = param.get('name') or param.get('key')
                        if param_name:
                            param_record = self.env['zns.template.parameter'].create({
                                'template_id': self.id,
                                'name': param_name,
                                'title': param.get('title') or param_name,
                                'param_type': self._map_param_type(param.get('type', 'string')),
                                'required': param.get('require', False),
                                'default_value': param.get('default_value', ''),
                                'description': param.get('description', '')
                            })
                            created_params.append(param_name)
                    
                    success_msg = f"Successfully synced {len(created_params)} parameters: {', '.join(created_params)}"
                    self.write({
                        'last_sync': fields.Datetime.now(),
                        'sync_status': success_msg,
                        'parameters_synced': True
                    })
                    
                    return {
                        'type': 'ir.actions.client',
                        'tag': 'display_notification',
                        'params': {
                            'title': 'Template Sync Successful',
                            'message': f"✅ Template '{self.name}'\n\n{success_msg}",
                            'type': 'success',
                            'sticky': True,
                        }
                    }
                else:
                    # Parameters already exist and not forced - just update sync time
                    self.write({
                        'last_sync': fields.Datetime.now(),
                        'sync_status': f"Template already has {len(self.parameter_ids)} parameters. Use 'Force Refresh' to re-sync.",
                        'parameters_synced': True
                    })
                    
                    return {
                        'type': 'ir.actions.client',
                        'tag': 'display_notification',
                        'params': {
                            'title': 'Template Already Synced',
                            'message': f"✅ Template '{self.name}' already has {len(self.parameter_ids)} parameters.\n\nParameters preserved to maintain your mappings.",
                            'type': 'info',
                            'sticky': True,
                        }
                    }
            else:
                error_msg = result.get('message', 'Unknown API error')
                self.write({'sync_status': error_msg})
                raise UserError(f"❌ {error_msg}")
                
        except Exception as e:
            error_msg = f"Sync failed: {str(e)}"
            self.write({'sync_status': error_msg})
            raise UserError(error_msg)
    
    def force_refresh_params(self):
        """Force refresh parameters from BOM (will clear existing mappings)"""
        return self.with_context(force_sync=True).sync_template_params()
    
    @api.model
    def sync_all_templates_from_bom(self):
        """Sync ALL templates from BOM API - UPDATED: Use template_id as primary key, no duplicates"""
        # Get active connection
        connection = self.env['zns.connection'].search([('active', '=', True)], limit=1)
        if not connection:
            raise UserError("❌ No active ZNS connection found. Please create and test a connection first.")
        
        try:
            # Get fresh access token
            access_token = connection._get_access_token()
            _logger.info(f"✅ Got access token for auto sync: {access_token[:30]}...")
        except Exception as e:
            error_msg = f"Failed to get access token: {str(e)}"
            raise UserError(f"❌ {error_msg}")
        
        # Get template list from BOM API
        list_url = f"{connection.api_base_url}/get-list-all-template"
        headers = {'Authorization': f'Bearer {access_token}'}
        
        try:
            _logger.info(f"🔄 Getting template list from BOM API...")
            response = requests.post(list_url, headers=headers, json={}, timeout=30)
            
            if response.status_code != 200:
                error_msg = f"Failed to get template list: HTTP {response.status_code} - {response.text}"
                raise UserError(f"❌ {error_msg}")
            
            result = response.json()
            
            if result.get('error') != '0' and result.get('error') != 0:
                error_msg = result.get('message', 'Failed to get template list')
                raise UserError(f"❌ API Error: {error_msg}")
            
            # Process template list
            templates_data = result.get('data', [])
            _logger.info(f"📋 BOM returned {len(templates_data)} templates")
            
            if not templates_data:
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': '⚠️ No Templates Found',
                        'message': "No templates found in your BOM dashboard.\n\nPlease create templates in BOM dashboard first.",
                        'type': 'warning',
                        'sticky': True,
                    }
                }
            
            # UPDATED: Use template_id as primary key to prevent duplicates
            synced_count = 0
            skipped_count = 0
            error_count = 0
            errors = []
            
            for template_data in templates_data:
                try:
                    template_id = template_data.get('id') or template_data.get('template_id')
                    template_name = template_data.get('name') or template_data.get('title') or f"Template {template_id}"
                    template_type = template_data.get('type', 'transaction').lower()
                    
                    if not template_id:
                        _logger.warning(f"Skipping template without ID: {template_data}")
                        continue
                    
                    # UPDATED: Check by template_id only (primary key logic)
                    existing_template = self.search([
                        ('template_id', '=', str(template_id)),
                        ('connection_id', '=', connection.id)
                    ], limit=1)
                    
                    if existing_template:
                        # UPDATED: Skip existing templates completely (no updates to preserve mappings)
                        skipped_count += 1
                        _logger.info(f"⏭️ Skipped existing template: {template_name} (BOM ID: {template_id})")
                        continue
                    else:
                        # UPDATED: Create new template with 'pending' apply_to and active=True
                        new_template = self.create({
                            'name': template_name,
                            'template_id': str(template_id),
                            'template_type': self._map_template_type(template_type),
                            'connection_id': connection.id,
                            'active': True,  # Active by default
                            'apply_to': 'pending'  # Pending by default
                        })
                        
                        # Sync parameters for new template only
                        try:
                            new_template.sync_template_params()
                            synced_count += 1
                            _logger.info(f"✅ Created NEW template: {template_name} (BOM ID: {template_id})")
                        except Exception as param_error:
                            _logger.warning(f"Failed to sync parameters for new {template_name}: {param_error}")
                            errors.append(f"{template_name}: Parameter sync failed")
                            error_count += 1
                            
                except Exception as e:
                    error_count += 1
                    template_name = template_data.get('name', 'Unknown')
                    error_msg = f"{template_name}: {str(e)}"
                    errors.append(error_msg)
                    _logger.error(f"Failed to process template {template_name}: {e}")
            
            # Build success message
            result_msg = f"🎉 Auto Template Sync Completed!\n\n"
            result_msg += f"✅ New templates created: {synced_count}\n"
            result_msg += f"⏭️ Existing templates skipped: {skipped_count}\n"
            result_msg += f"❌ Errors: {error_count}\n"
            result_msg += f"📊 Total from BOM: {len(templates_data)}"
            
            if synced_count > 0:
                result_msg += f"\n\n💡 New templates are set to 'Pending' by default.\nGo to Templates menu to configure 'Apply To' for each template."
            
            if errors and len(errors) <= 3:
                result_msg += f"\n\nErrors:\n" + "\n".join(errors)
            elif errors:
                result_msg += f"\n\nFirst 3 errors:\n" + "\n".join(errors[:3])
                result_msg += f"\n... and {len(errors) - 3} more errors"
            
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': 'Auto Template Sync Complete',
                    'message': result_msg,
                    'type': 'success' if error_count == 0 else 'warning',
                    'sticky': True,
                }
            }
            
        except requests.exceptions.RequestException as e:
            error_msg = f"Request failed: {str(e)}"
            raise UserError(f"❌ {error_msg}")
        except Exception as e:
            error_msg = f"Unexpected error: {str(e)}"
            _logger.error(f"Template auto sync error: {e}", exc_info=True)
            raise UserError(f"❌ {error_msg}")
    
    def _map_template_type(self, bom_type):
        """Map BOM template type to Odoo selection"""
        type_mapping = {
            'transaction': 'transaction',
            'otp': 'otp',
            'promotion': 'promotion',
            'marketing': 'promotion',
            'notification': 'transaction',
            'order': 'transaction',
            'confirm': 'transaction',
            'customer_care': 'customer_care',
            'support': 'customer_care'
        }
        return type_mapping.get(str(bom_type).lower(), 'transaction')
    
    def _map_param_type(self, api_type):
        """Map API parameter types to Odoo field types"""
        type_mapping = {
            'string': 'string',
            'text': 'string',
            'number': 'number',
            'integer': 'number',
            'float': 'number',
            'date': 'date',
            'datetime': 'date',
            'email': 'email',
            'url': 'url',
            'phone': 'string'
        }
        return type_mapping.get(str(api_type).lower(), 'string')
        
    def action_view_mappings(self):
    """View template mappings that use this template"""
    # Check if zns.template.mapping model exists
    if 'zns.template.mapping' in self.env:
        return {
            'type': 'ir.actions.act_window',
            'name': f'Mappings for {self.name}',
            'res_model': 'zns.template.mapping',
            'view_mode': 'tree,form',
            'domain': [('template_id', '=', self.id)],
            'context': {'default_template_id': self.id}
        }
    else:
        # If mapping model doesn't exist, show notification
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': 'Template Mappings',
                'message': f"Template '{self.name}' is ready to use.\n\nUse this template in Sales Orders and it will automatically map parameters based on your configuration.",
                'type': 'info',
                'sticky': False,
            }
        }
    
    def test_template(self):
        """Test template"""
        return self.sync_template_params()


class ZnsTemplateParameter(models.Model):
    _name = 'zns.template.parameter'
    _description = 'ZNS Template Parameter'
    _rec_name = 'title'

    template_id = fields.Many2one('zns.template', string='Template', required=True, ondelete='cascade')
    name = fields.Char('Parameter Name', required=True, help='Parameter key name for API')
    title = fields.Char('Parameter Title', required=True, help='Human readable parameter name')
    param_type = fields.Selection([
        ('string', 'String'),
        ('number', 'Number'),
        ('date', 'Date'),
        ('email', 'Email'),
        ('url', 'URL')
    ], string='Type', default='string')
    required = fields.Boolean('Required', default=False)
    default_value = fields.Char('Default Value')
    description = fields.Text('Description', help='Parameter description from BOM API')
    
    # Enhanced Field Mapping for all document types
    field_mapping = fields.Selection([
        # Customer/Partner fields (works for all document types)
        ('partner_id.name', 'Customer Name'),
        ('partner_id.mobile', 'Customer Mobile'),
        ('partner_id.phone', 'Customer Phone'),
        ('partner_id.email', 'Customer Email'),
        ('partner_id.vat', 'Customer VAT'),
        ('partner_id.ref', 'Customer Code'),
        ('partner_id.street', 'Customer Street'),
        ('partner_id.city', 'Customer City'),
        ('partner_id.country_id.name', 'Customer Country'),
        
        # Document fields (SO/Invoice)
        ('name', 'Document Number'),
        ('date_order', 'Document Date'),  # Will adapt to invoice_date for invoices
        ('amount_total', 'Total Amount'),
        ('amount_untaxed', 'Subtotal'),
        ('amount_tax', 'Tax Amount'),
        ('currency_id.name', 'Currency'),
        ('state', 'Status'),
        ('note', 'Notes'),
        
        # Company fields (includes vat)
        ('company_id.name', 'Company Name'),
        ('company_id.vat', 'Company Tax ID'),
        ('company_id.phone', 'Company Phone'),
        ('company_id.email', 'Company Email'),
        ('company_id.street', 'Company Address'),
        
        # User fields
        ('user_id.name', 'Salesperson'),
        ('user_id.email', 'Salesperson Email'),
        
        # Custom value
        ('custom', 'Custom Value'),
    ], string='Field Mapping', help='Map this parameter to a document field')
    
    custom_value = fields.Char('Custom Value', help='Custom value when mapping is "custom"')
    
    def get_mapped_value(self, record):
        """Get mapped value from any record (SO, Invoice, Contact)"""
        if not self.field_mapping:
            return self.default_value or ''
        
        if self.field_mapping == 'custom':
            return self.custom_value or ''
        
        try:
            # Handle field mapping based on record type
            field_path = self.field_mapping
            
            # Adapt field mapping for different record types
            if record._name == 'account.move':
                field_path = self._adapt_field_for_invoice(field_path)
            elif record._name == 'res.partner':
                field_path = self._adapt_field_for_contact(field_path)
            
            # Navigate through field path
            obj = record
            for field_part in field_path.split('.'):
                obj = getattr(obj, field_part, None)
                if obj is None:
                    break
            
            if obj is None:
                return self.default_value or ''
            
            # Format based on parameter type
            if self.param_type == 'date' and hasattr(obj, 'strftime'):
                return obj.strftime('%d/%m/%Y')
            elif self.param_type == 'number':
                return str(obj) if obj else '0'
            else:
                return str(obj) if obj else ''
                
        except Exception as e:
            _logger.warning(f"Error mapping parameter {self.name}: {e}")
            return self.default_value or ''
    
    def _adapt_field_for_invoice(self, field_path):
        """Adapt field mapping for invoice records"""
        adaptations = {
            'date_order': 'invoice_date',
            'user_id.name': 'invoice_user_id.name',
            'user_id.email': 'invoice_user_id.email',
            'note': 'narration',
        }
        return adaptations.get(field_path, field_path)
    
    def _adapt_field_for_contact(self, field_path):
        """Adapt field mapping for contact records"""
        adaptations = {
            'partner_id.name': 'name',
            'partner_id.mobile': 'mobile',
            'partner_id.phone': 'phone',
            'partner_id.email': 'email',
            'partner_id.vat': 'vat',
            'partner_id.ref': 'ref',
            'partner_id.street': 'street',
            'partner_id.city': 'city',
            'partner_id.country_id.name': 'country_id.name',
            'name': 'ref',
            'note': 'comment',
        }
        return adaptations.get(field_path, field_path)
        
# This is the ADDITION to your existing zns_template.py file
# Add this class to the END of your existing zns_template.py file

class ZnsTemplateParameter(models.Model):
    _name = 'zns.template.parameter'
    _description = 'ZNS Template Parameter'
    _rec_name = 'title'

    template_id = fields.Many2one('zns.template', string='Template', required=True, ondelete='cascade')
    name = fields.Char('Parameter Name', required=True, help='Parameter key name for API')
    title = fields.Char('Parameter Title', required=True, help='Human readable parameter name')
    param_type = fields.Selection([
        ('string', 'String'),
        ('number', 'Number'),
        ('date', 'Date'),
        ('email', 'Email'),
        ('url', 'URL')
    ], string='Type', default='string')
    required = fields.Boolean('Required', default=False)
    default_value = fields.Char('Default Value')
    description = fields.Text('Description', help='Parameter description from BOM API')
    
    # ADD THESE MISSING FIELDS - This is what was causing the error!
    so_field_mapping = fields.Selection([
        ('partner_id.name', 'Customer Name'),
        ('partner_id.mobile', 'Customer Mobile'),
        ('partner_id.phone', 'Customer Phone'),
        ('partner_id.email', 'Customer Email'),
        ('partner_id.vat', 'Customer VAT'),
        ('partner_id.ref', 'Customer Code'),
        ('partner_id.city', 'Customer City'),
        ('partner_id.country_id.name', 'Customer Country'),
        ('name', 'SO Number'),
        ('date_order', 'Order Date'),
        ('amount_total', 'Total Amount'),
        ('amount_untaxed', 'Subtotal'),
        ('amount_tax', 'Tax Amount'),
        ('user_id.name', 'Salesperson'),
        ('company_id.name', 'Company Name'),
        ('company_id.vat', 'Company VAT'),
        ('company_id.phone', 'Company Phone'),
        ('company_id.email', 'Company Email'),
        ('client_order_ref', 'Customer Reference'),
        ('commitment_date', 'Delivery Date'),
        ('note', 'Order Notes'),
        ('state', 'Order Status'),
        ('currency_id.name', 'Currency'),
        ('payment_term_id.name', 'Payment Terms'),
        ('custom', 'Custom Value'),
    ], string='Map to SO Field', help='Automatically map this parameter to Sale Order field')
    
    custom_value = fields.Char('Custom Value', help='Custom value when mapping type is "custom"')
    
    def get_mapped_value(self, sale_order):
        """Get the mapped value from sale order"""
        if not self.so_field_mapping:
            return self.default_value or ''
        
        if self.so_field_mapping == 'custom':
            return self.custom_value or ''
        
        try:
            # Handle nested field access like 'partner_id.name'
            obj = sale_order
            for field_part in self.so_field_mapping.split('.'):
                obj = getattr(obj, field_part, '')
                if not obj:
                    break
            
            # Format based on parameter type
            if self.param_type == 'date' and hasattr(obj, 'strftime'):
                return obj.strftime('%d/%m/%Y')
            elif self.param_type == 'number':
                return str(obj) if obj else '0'
            else:
                return str(obj) if obj else ''
                
        except Exception as e:
            _logger.warning(f"Error mapping parameter {self.name}: {e}")
            return self.default_value or ''