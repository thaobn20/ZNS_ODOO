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
        """Sync template parameters from BOM API using correct endpoint and format"""
        connection = self.connection_id
        if not connection:
            raise UserError("No connection configured")
        
        try:
            # Get access token
            access_token = connection._get_access_token()
            _logger.info(f"✅ Got access token for template sync: {access_token[:30]}...")
        except Exception as e:
            error_msg = f"Failed to get access token: {str(e)}"
            self.write({'sync_status': error_msg})
            raise UserError(error_msg)
        
        # Use correct endpoint from Postman collection
        url = f"{connection.api_base_url}/get-param-zns-template"
        headers = {
            'Authorization': f'Bearer {access_token}',
            'Content-Type': 'application/json'
        }
        
        # Data structure from Postman collection
        data = {
            'template_id': self.template_id
        }
        
        try:
            _logger.info(f"🔄 Syncing template params from: {url}")
            _logger.info(f"📋 Template ID: {self.template_id}")
            _logger.info(f"📤 Request data: {data}")
            
            response = requests.post(url, headers=headers, json=data, timeout=30)
            
            _logger.info(f"📨 Response status: {response.status_code}")
            _logger.info(f"📨 Response headers: {dict(response.headers)}")
            _logger.info(f"📨 Response body: {response.text}")
            
            # Handle different response status codes
            if response.status_code == 404:
                error_msg = f"Template ID '{self.template_id}' not found in BOM system"
                self.write({'sync_status': error_msg})
                raise UserError(f"❌ {error_msg}")
            
            if response.status_code == 401:
                error_msg = "Authentication failed - check your API credentials"
                self.write({'sync_status': error_msg})
                raise UserError(f"❌ {error_msg}")
            
            if response.status_code != 200:
                error_msg = f"API returned status {response.status_code}: {response.text}"
                self.write({'sync_status': error_msg})
                raise UserError(f"❌ {error_msg}")
            
            # Parse JSON response
            try:
                result = response.json()
            except Exception as json_err:
                error_msg = f"Invalid JSON response: {response.text}"
                self.write({'sync_status': error_msg})
                raise UserError(f"❌ {error_msg}")
            
            _logger.info(f"📋 Parsed response: {result}")
            
            # Check BOM API response format
            if result.get('error') == 0 or result.get('success') == True:
                # Success response
                params_data = result.get('data', [])
                
                if not params_data:
                    warning_msg = f"No parameters found for template {self.template_id}"
                    self.write({'sync_status': warning_msg, 'last_sync': fields.Datetime.now()})
                    return {
                        'type': 'ir.actions.client',
                        'tag': 'display_notification',
                        'params': {
                            'title': 'Template Sync Complete',
                            'message': f"⚠️ {warning_msg}",
                            'type': 'warning',
                            'sticky': False,
                        }
                    }
                
                # Clear existing parameters
                self.parameter_ids.unlink()
                
                # Create new parameters
                created_params = []
                for param in params_data:
                    param_name = param.get('name') or param.get('key')
                    if param_name:
                        param_record = self.env['zns.template.parameter'].create({
                            'template_id': self.id,
                            'name': param_name,
                            'title': param.get('title') or param.get('label') or param_name,
                            'param_type': self._map_param_type(param.get('type', 'string')),
                            'required': param.get('require') or param.get('required', False),
                            'default_value': param.get('default_value') or param.get('default', ''),
                            'description': param.get('description', '')
                        })
                        created_params.append(param_name)
                
                success_msg = f"Successfully synced {len(created_params)} parameters: {', '.join(created_params)}"
                self.write({
                    'last_sync': fields.Datetime.now(),
                    'sync_status': success_msg
                })
                
                _logger.info(f"✅ {success_msg}")
                
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': 'Template Sync Successful',
                        'message': f"✅ {success_msg}",
                        'type': 'success',
                        'sticky': False,
                    }
                }
            else:
                # Error response
                error_msg = result.get('message') or result.get('error_message') or 'Unknown API error'
                self.write({'sync_status': f"API Error: {error_msg}"})
                raise UserError(f"❌ API Error: {error_msg}")
                
        except requests.exceptions.ConnectionError:
            error_msg = "Connection failed - check your internet connection"
            self.write({'sync_status': error_msg})
            raise UserError(f"❌ {error_msg}")
        except requests.exceptions.Timeout:
            error_msg = "Request timeout - BOM API is slow to respond"
            self.write({'sync_status': error_msg})
            raise UserError(f"❌ {error_msg}")
        except requests.exceptions.RequestException as e:
            error_msg = f"Request failed: {str(e)}"
            self.write({'sync_status': error_msg})
            raise UserError(f"❌ {error_msg}")
        except Exception as e:
            error_msg = f"Unexpected error: {str(e)}"
            self.write({'sync_status': error_msg})
            _logger.error(f"Template sync error: {e}", exc_info=True)
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
        """Test template by getting its parameters"""
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