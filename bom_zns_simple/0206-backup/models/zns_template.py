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
        ('promotion', 'Promotion')
    ], string='Template Type', default='transaction')
    connection_id = fields.Many2one('zns.connection', string='ZNS Connection', required=True)
    active = fields.Boolean('Active', default=True)
    parameter_ids = fields.One2many('zns.template.parameter', 'template_id', string='Parameters')
    last_sync = fields.Datetime('Last Sync', readonly=True)
    sync_status = fields.Text('Last Sync Status', readonly=True)
    
    def sync_template_params(self):
        """Sync template parameters from BOM API using the EXACT same method that works for connection test"""
        connection = self.connection_id
        if not connection:
            raise UserError("No connection configured")
        
        try:
            # Force get a fresh access token using the proven working method
            _logger.info(f"Getting fresh access token for template sync...")
            access_token = connection._get_new_access_token()  # Use _get_new_access_token directly
            _logger.info(f"✅ Got fresh access token: {access_token[:30]}...")
        except Exception as e:
            error_msg = f"Failed to get access token: {str(e)}"
            self.write({'sync_status': error_msg})
            raise UserError(f"❌ {error_msg}")
        
        # Use EXACT format from your working Postman collection - no modifications
        url = f"{connection.api_base_url}/get-param-zns-template"
        headers = {
            'Authorization': f'Bearer {access_token}'
            # NO Content-Type header - exactly like your working connection test
        }
        
        # Data structure exactly from Postman collection  
        data = {
            'template_id': self.template_id
        }
        
        try:
            _logger.info(f"🔄 Template sync request:")
            _logger.info(f"URL: {url}")
            _logger.info(f"Headers: {headers}")
            _logger.info(f"Data: {data}")
            _logger.info(f"Template ID being tested: {self.template_id}")
            
            # Use same request format as working connection test
            response = requests.post(url, headers=headers, json=data, timeout=30)
            
            _logger.info(f"📨 Template sync response:")
            _logger.info(f"Status: {response.status_code}")
            _logger.info(f"Body: {response.text}")
            
            # Handle response exactly like connection test handles it
            if response.status_code != 200:
                error_msg = f"HTTP {response.status_code}: {response.text}"
                self.write({'sync_status': error_msg})
                raise UserError(f"❌ {error_msg}")
            
            # Parse JSON response
            try:
                result = response.json()
                _logger.info(f"📋 Parsed JSON: {result}")
            except Exception as json_err:
                error_msg = f"Invalid JSON response: {response.text}"
                self.write({'sync_status': error_msg})
                raise UserError(f"❌ {error_msg}")
            
            # Check response using your proven format (error: "0" for success)
            if result.get('error') == '0' or result.get('error') == 0:
                # Success response
                params_data = result.get('data', [])
                _logger.info(f"📊 Parameters data: {params_data}")
                
                if not params_data or len(params_data) == 0:
                    success_msg = f"Template {self.template_id} synchronized successfully - no parameters found (normal for some templates)"
                    self.write({
                        'sync_status': success_msg, 
                        'last_sync': fields.Datetime.now()
                    })
                    return {
                        'type': 'ir.actions.client',
                        'tag': 'display_notification',
                        'params': {
                            'title': 'Template Sync Complete',
                            'message': f"✅ {success_msg}",
                            'type': 'success',
                            'sticky': True,
                        }
                    }
                
                # Clear existing parameters
                self.parameter_ids.unlink()
                
                # Create new parameters
                created_params = []
                for param in params_data:
                    param_name = param.get('name') or param.get('key') or param.get('parameter_name')
                    if param_name:
                        try:
                            param_record = self.env['zns.template.parameter'].create({
                                'template_id': self.id,
                                'name': param_name,
                                'title': param.get('title') or param.get('label') or param.get('display_name') or param_name,
                                'param_type': self._map_param_type(param.get('type', 'string')),
                                'required': param.get('require') or param.get('required', False),
                                'default_value': param.get('default_value') or param.get('default', ''),
                                'description': param.get('description', '')
                            })
                            created_params.append(param_name)
                            _logger.info(f"✅ Created parameter: {param_name}")
                        except Exception as param_error:
                            _logger.warning(f"Failed to create parameter {param_name}: {param_error}")
                
                success_msg = f"Successfully synced {len(created_params)} parameters: {', '.join(created_params)}"
                self.write({
                    'last_sync': fields.Datetime.now(),
                    'sync_status': success_msg
                })
                
                _logger.info(f"✅ Template sync complete: {success_msg}")
                
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': 'Template Sync Successful',
                        'message': f"✅ Template '{self.name}' (ID: {self.template_id})\n\n{success_msg}",
                        'type': 'success',
                        'sticky': True,
                    }
                }
            else:
                # Error response - handle the same way connection test handles errors
                error_code = result.get('error', 'unknown')
                error_msg = result.get('message', 'Unknown API error')
                
                if error_code == '-115' or 'Access token not exist' in error_msg:
                    detailed_error = f"Template sync failed: Access token not exist\n\nThis might mean:\n• Template ID '{self.template_id}' doesn't exist in your BOM dashboard\n• Template is not approved/active in BOM\n• Different API endpoint authentication\n\nPlease verify template {self.template_id} exists and is active in https://zns.bom.asia"
                else:
                    detailed_error = f"API Error {error_code}: {error_msg}"
                
                self.write({'sync_status': detailed_error})
                raise UserError(f"❌ {detailed_error}")
                
        except requests.exceptions.RequestException as e:
            error_msg = f"Request failed: {str(e)}"
            self.write({'sync_status': error_msg})
            raise UserError(f"❌ {error_msg}")
        except Exception as e:
            error_msg = f"Unexpected error: {str(e)}"
            self.write({'sync_status': error_msg})
            _logger.error(f"Template sync error for {self.template_id}: {e}", exc_info=True)
            raise UserError(f"❌ {error_msg}")
    
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
    
    def test_template(self):
        """Test template by syncing its parameters"""
        return self.sync_template_params()
    
    def action_view_mappings(self):
        """View template mappings that use this template"""
        return {
            'type': 'ir.actions.act_window',
            'name': f'Mappings for {self.name}',
            'res_model': 'zns.template.mapping',
            'view_mode': 'tree,form',
            'domain': [('template_id', '=', self.id)],
            'context': {'default_template_id': self.id}
        }
    
    @api.model
    def sync_all_templates_from_bom(self, connection_id=None):
        """Automatically sync ALL templates from BOM dashboard - no manual creation needed!"""
        
        # Get connection
        if connection_id:
            connection = self.env['zns.connection'].browse(connection_id)
        else:
            connection = self.env['zns.connection'].search([('active', '=', True)], limit=1)
        
        if not connection:
            raise UserError("❌ No active ZNS connection found. Please create and test a connection first.")
        
        try:
            # Get fresh access token using proven working method
            access_token = connection._get_new_access_token()
            _logger.info(f"✅ Got access token for template list sync: {access_token[:30]}...")
        except Exception as e:
            error_msg = f"Failed to get access token: {str(e)}"
            raise UserError(f"❌ {error_msg}")
        
        # Use exact format from your Postman collection
        list_url = f"{connection.api_base_url}/get-list-all-template"
        headers = {
            'Authorization': f'Bearer {access_token}'
            # NO Content-Type header - matching your Postman
        }
        
        try:
            _logger.info(f"🔄 Getting template list from BOM...")
            _logger.info(f"URL: {list_url}")
            _logger.info(f"Headers: {headers}")
            
            # POST with empty JSON body (might work better than empty form data)
            response = requests.post(list_url, headers=headers, json={}, timeout=30)
            
            _logger.info(f"📨 Template list response:")
            _logger.info(f"Status: {response.status_code}")
            _logger.info(f"Body: {response.text[:500]}...")  # First 500 chars to avoid huge logs
            
            if response.status_code != 200:
                error_msg = f"Failed to get template list: HTTP {response.status_code} - {response.text}"
                raise UserError(f"❌ {error_msg}")
            
            # Parse response
            try:
                result = response.json()
                _logger.info(f"📋 Parsed template list: {result}")
            except Exception as json_err:
                error_msg = f"Invalid JSON response: {response.text}"
                raise UserError(f"❌ {error_msg}")
            
            # Check for success (your format: error: "0" for success)
            if result.get('error') != '0' and result.get('error') != 0:
                error_msg = result.get('message', 'Failed to get template list')
                raise UserError(f"❌ API Error: {error_msg}")
            
            # Process template list
            templates_data = result.get('data', [])
            _logger.info(f"📋 BOM returned {len(templates_data)} templates: {templates_data}")
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
            
            # Sync each template
            synced_count = 0
            updated_count = 0
            error_count = 0
            errors = []
            
            for template_data in templates_data:
                try:
                    template_id = template_data.get('id') or template_data.get('template_id')
                    template_name = template_data.get('name') or template_data.get('title') or f"Template {template_id}"
                    template_type = template_data.get('type', 'transaction').lower()
                    template_status = template_data.get('status', 'unknown')
                    template_status = template_data.get('status', 'active')  # Default to active
                    
                    if not template_id:
                        _logger.warning(f"Skipping template without ID: {template_data}")
                        continue
                    
                    _logger.info(f"Processing template: {template_name} ({template_id})")
                    _logger.info(f"Checking if template {template_id} exists in Odoo...")
                    # Check if template already exists (check both string and number formats)
                    existing_template = self.search([
                        '|',
                        ('template_id', '=', str(template_id)),
                        ('template_id', '=', template_id),
                        ('connection_id', '=', connection.id)
                    ], limit=1)
                    
                    if existing_template:
                        # Update existing template
                        existing_template.write({
                            'name': template_name,
                            'template_type': self._map_template_type(template_type),
                            'active': True  # Always create as active
                        })
                        
                        # Sync parameters for this template
                        try:
                            existing_template._sync_single_template_params(access_token)
                            updated_count += 1
                            _logger.info(f"✅ Updated template: {template_name}")
                        except Exception as param_error:
                            _logger.warning(f"Failed to sync parameters for {template_name}: {param_error}")
                            errors.append(f"{template_name}: Parameter sync failed")
                            error_count += 1
                    else:
                        # Get template status - default to active if not specified
                        is_active = True  # Default to active
                        if template_status:
                            is_active = template_status.lower() in ['active', 'approved', 'enabled', 'published', 'live', '1', 'true']
                        
                        new_template = self.create({
                            'name': template_name,
                            'template_id': str(template_id),
                            'template_type': self._map_template_type(template_type),
                            'connection_id': connection.id,
                            'active': is_active  # Use the determined status
                        })
                        _logger.info(f"✅ CREATED new template in DB: {new_template.name} (ID: {new_template.id}, Template ID: {template_id})")
                        # Sync parameters for new template
                        try:
                            new_template._sync_single_template_params(access_token)
                            synced_count += 1
                            _logger.info(f"✅ Created template: {template_name}")
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
            total_processed = synced_count + updated_count
            result_msg = f"🎉 Template sync completed!\n\n"
            result_msg += f"✅ New templates: {synced_count}\n"
            result_msg += f"🔄 Updated templates: {updated_count}\n"
            result_msg += f"❌ Errors: {error_count}\n"
            result_msg += f"📊 Total processed: {total_processed}"
            
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
            _logger.error(f"Template list sync error: {e}", exc_info=True)
            raise UserError(f"❌ {error_msg}")

    def _sync_single_template_params(self, access_token):
        """Sync parameters for a single template using provided access token"""
        connection = self.connection_id
        
        # Use exact format from your working Postman for get-param-zns-template
        param_url = f"{connection.api_base_url}/get-param-zns-template"
        headers = {
            'Authorization': f'Bearer {access_token}'
            # NO Content-Type header
        }
        data = {
            'template_id': self.template_id
        }
        
        _logger.info(f"🔄 Syncing parameters for template {self.name} ({self.template_id})")
        
        response = requests.post(param_url, headers=headers, json=data, timeout=30)
        
        _logger.info(f"Parameter sync response for {self.template_id}: {response.status_code} - {response.text}")
        
        if response.status_code != 200:
            raise Exception(f"HTTP {response.status_code}: {response.text}")
        
        result = response.json()
        
        if result.get('error') == '0' or result.get('error') == 0:
            # Success - process parameters
            params_data = result.get('data', [])
            
            # Clear existing parameters
            self.parameter_ids.unlink()
            
            # Create new parameters
            for param in params_data:
                param_name = param.get('name') or param.get('key') or param.get('parameter_name')
                if param_name:
                    self.env['zns.template.parameter'].create({
                        'template_id': self.id,
                        'name': param_name,
                        'title': param.get('title') or param.get('label') or param_name,
                        'param_type': self._map_param_type(param.get('type', 'string')),
                        'required': param.get('require') or param.get('required', False),
                        'default_value': param.get('default_value') or param.get('default', ''),
                        'description': param.get('description', '')
                    })
            
            # Update sync status
            self.write({
                'last_sync': fields.Datetime.now(),
                'sync_status': f"Successfully synced {len(params_data)} parameters"
            })
            
            _logger.info(f"✅ Synced {len(params_data)} parameters for {self.name}")
        else:
            error_msg = result.get('message', 'Unknown parameter sync error')
            _logger.warning(f"Parameter sync failed for {self.name}: {error_msg}")
            
            # Still update the template but note the parameter sync failure
            self.write({
                'last_sync': fields.Datetime.now(),
                'sync_status': f"Template synced but parameter sync failed: {error_msg}"
            })

    def _map_template_type(self, bom_type):
        """Map BOM template type to Odoo selection"""
        type_mapping = {
            'transaction': 'transaction',
            'otp': 'otp',
            'promotion': 'promotion',
            'marketing': 'promotion',
            'notification': 'transaction',
            'order': 'transaction',
            'confirm': 'transaction'
        }
        return type_mapping.get(str(bom_type).lower(), 'transaction')

    # Add action method for calling from menu
    @api.model
    def action_sync_all_templates_from_bom(self):
        """Menu action to sync all templates from BOM"""
        return self.sync_all_templates_from_bom()
    
    @api.model
    def action_sync_all_templates(self):
        """Sync all active templates - called from tree view action"""
        templates = self.search([('active', '=', True), ('connection_id', '!=', False)])
        
        if not templates:
            raise UserError("❌ No active templates with connections found")
        
        success_count = 0
        error_count = 0
        errors = []
        
        for template in templates:
            try:
                template.sync_template_params()
                success_count += 1
                _logger.info(f"✅ Synced template: {template.name}")
            except Exception as e:
                error_count += 1
                error_msg = f"{template.name}: {str(e)}"
                errors.append(error_msg)
                _logger.warning(f"❌ Failed to sync template {template.name}: {e}")
        
        # Build result message
        result_msg = f"✅ Sync completed!\nSuccess: {success_count}\nErrors: {error_count}"
        if errors:
            result_msg += f"\n\nErrors:\n" + "\n".join(errors[:3])  # Show first 3 errors
            if len(errors) > 3:
                result_msg += f"\n... and {len(errors) - 3} more errors"
        
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': 'Bulk Template Sync Complete',
                'message': result_msg,
                'type': 'success' if error_count == 0 else 'warning',
                'sticky': True,
            }
        }


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
    
    # Mapping fields for SO integration
    so_field_mapping = fields.Selection([
        ('partner_id.name', 'Customer Name'),
        ('partner_id.mobile', 'Customer Mobile'),
        ('partner_id.phone', 'Customer Phone'),
        ('partner_id.email', 'Customer Email'),
        ('name', 'SO Number'),
        ('date_order', 'Order Date'),
        ('amount_total', 'Total Amount'),
        ('amount_untaxed', 'Subtotal'),
        ('amount_tax', 'Tax Amount'),
        ('user_id.name', 'Salesperson'),
        ('company_id.name', 'Company Name'),
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