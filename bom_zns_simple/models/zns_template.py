# Complete zns_template.py with Template-Level Mapping

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
    
    # NEW: Template-level mapping type (choose once per template)
    default_mapping_type = fields.Selection([
        ('so', 'Sale Order Fields'),
        ('invoice', 'Invoice Fields'), 
        ('contact', 'Contact Fields'),
        ('custom', 'Custom Values Only'),
    ], string='Default Mapping Type', default='so', 
       help='Choose which type of records this template is primarily used for')
    
    parameter_ids = fields.One2many('zns.template.parameter', 'template_id', string='Parameters')
    last_sync = fields.Datetime('Last Sync', readonly=True)
    sync_status = fields.Text('Last Sync Status', readonly=True)
    
    # Computed field to show mapping summary
    mapping_summary = fields.Char('Mapping Summary', compute='_compute_mapping_summary')
    
    @api.depends('default_mapping_type', 'parameter_ids')
    def _compute_mapping_summary(self):
        for template in self:
            param_count = len(template.parameter_ids)
            mapped_count = len(template.parameter_ids.filtered(lambda p: p.field_mapping))
            custom_count = len(template.parameter_ids.filtered(lambda p: p.field_mapping == 'custom'))
            
            template.mapping_summary = f"{mapped_count}/{param_count} mapped ({custom_count} custom)"
    
    @api.onchange('default_mapping_type')
    def _onchange_default_mapping_type(self):
        """When mapping type changes, reset all parameter mappings"""
        if self.default_mapping_type and self.parameter_ids:
            for param in self.parameter_ids:
                param.field_mapping = False
                param.custom_value = ''
    
    # Keep all your existing sync methods...
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
    
    # COMPLETE MAPPING FIELDS - Including company_id.vat
    so_field_mapping = fields.Selection([
        # Customer Information
        ('partner_id.name', 'Customer Name'),
        ('partner_id.mobile', 'Customer Mobile'),
        ('partner_id.phone', 'Customer Phone'),
        ('partner_id.email', 'Customer Email'),
        ('partner_id.ref', 'Customer Code'),
        ('partner_id.vat', 'Customer VAT'),
        ('partner_id.street', 'Customer Street'),
        ('partner_id.city', 'Customer City'),
        ('partner_id.state_id.name', 'Customer State'),
        ('partner_id.country_id.name', 'Customer Country'),
        ('partner_id.zip', 'Customer ZIP'),
        ('partner_id.function', 'Customer Job Position'),
        ('partner_id.website', 'Customer Website'),
        
        # Order Information
        ('name', 'SO Number'),
        ('date_order', 'Order Date'),
        ('client_order_ref', 'Customer Reference'),
        ('commitment_date', 'Delivery Date'),
        ('note', 'Order Notes'),
        ('state', 'Order Status'),
        ('validity_date', 'Validity Date'),
        
        # Amounts and Financial
        ('amount_total', 'Total Amount'),
        ('amount_untaxed', 'Subtotal'),
        ('amount_tax', 'Tax Amount'),
        ('currency_id.name', 'Currency'),
        ('currency_id.symbol', 'Currency Symbol'),
        ('payment_term_id.name', 'Payment Terms'),
        
        # Product Information  
        ('order_line.product_id.name', 'First Product Name'),
        ('order_line.product_uom_qty', 'First Product Quantity'),
        ('order_line.price_unit', 'First Product Price'),
        
        # Company Information
        ('company_id.name', 'Company Name'),
        ('company_id.vat', 'Company VAT'),  # THIS WAS MISSING
        ('company_id.phone', 'Company Phone'),
        ('company_id.email', 'Company Email'),
        ('company_id.street', 'Company Street'),
        ('company_id.city', 'Company City'),
        ('company_id.country_id.name', 'Company Country'),
        ('company_id.website', 'Company Website'),
        
        # User Information
        ('user_id.name', 'Salesperson'),
        ('user_id.email', 'Salesperson Email'),
        ('user_id.phone', 'Salesperson Phone'),
        ('user_id.mobile', 'Salesperson Mobile'),
        
        # Team Information
        ('team_id.name', 'Sales Team'),
        
        # Warehouse Information
        ('warehouse_id.name', 'Warehouse'),
        ('warehouse_id.code', 'Warehouse Code'),
        
        # Pricelist Information
        ('pricelist_id.name', 'Pricelist'),
        ('pricelist_id.currency_id.name', 'Pricelist Currency'),
        
        # Custom Value
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
            field_parts = self.so_field_mapping.split('.')
            
            for field_part in field_parts:
                if not obj:
                    break
                    
                # Handle special cases for One2many fields like order_line
                if field_part == 'order_line' and hasattr(obj, 'order_line'):
                    # Get first order line
                    if obj.order_line:
                        obj = obj.order_line[0]
                    else:
                        obj = None
                        break
                else:
                    obj = getattr(obj, field_part, None)
            
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
    
    def get_mapped_value_for_record(self, record):
        """Get mapped value for any record type (SO, Invoice, Contact)"""
        if not self.so_field_mapping:
            return self.default_value or ''
        
        if self.so_field_mapping == 'custom':
            return self.custom_value or ''
        
        try:
            # Determine record type and adapt mapping
            if record._name == 'sale.order':
                return self.get_mapped_value(record)
            elif record._name == 'account.move':
                # Adapt SO mapping to invoice fields
                invoice_mapping = self._adapt_so_mapping_to_invoice(self.so_field_mapping)
                if invoice_mapping:
                    obj = record
                    for field_part in invoice_mapping.split('.'):
                        obj = getattr(obj, field_part, None)
                        if obj is None:
                            break
                    
                    if obj is None:
                        return self.default_value or ''
                    
                    if self.param_type == 'date' and hasattr(obj, 'strftime'):
                        return obj.strftime('%d/%m/%Y')
                    elif self.param_type == 'number':
                        return str(obj) if obj else '0'
                    else:
                        return str(obj) if obj else ''
            elif record._name == 'res.partner':
                # Adapt SO mapping to contact fields
                contact_mapping = self._adapt_so_mapping_to_contact(self.so_field_mapping)
                if contact_mapping:
                    obj = record
                    for field_part in contact_mapping.split('.'):
                        obj = getattr(obj, field_part, None)
                        if obj is None:
                            break
                    return str(obj) if obj else ''
            
            return self.default_value or ''
                
        except Exception as e:
            _logger.warning(f"Error mapping parameter {self.name} for record {record._name}: {e}")
            return self.default_value or ''
    
    def _adapt_so_mapping_to_invoice(self, so_mapping):
        """Adapt Sale Order field mapping to Invoice fields"""
        mapping_conversions = {
            # Basic fields
            'name': 'name',  # Invoice number
            'date_order': 'invoice_date',
            'amount_total': 'amount_total',
            'amount_untaxed': 'amount_untaxed',
            'amount_tax': 'amount_tax',
            'client_order_ref': 'ref',
            'commitment_date': 'invoice_date_due',
            'note': 'narration',
            'state': 'state',
            'currency_id.name': 'currency_id.name',
            'currency_id.symbol': 'currency_id.symbol',
            'payment_term_id.name': 'invoice_payment_term_id.name',
            
            # Partner fields (same)
            'partner_id.name': 'partner_id.name',
            'partner_id.mobile': 'partner_id.mobile',
            'partner_id.phone': 'partner_id.phone',
            'partner_id.email': 'partner_id.email',
            'partner_id.ref': 'partner_id.ref',
            'partner_id.vat': 'partner_id.vat',
            'partner_id.street': 'partner_id.street',
            'partner_id.city': 'partner_id.city',
            'partner_id.state_id.name': 'partner_id.state_id.name',
            'partner_id.country_id.name': 'partner_id.country_id.name',
            'partner_id.zip': 'partner_id.zip',
            
            # Company fields (same)
            'company_id.name': 'company_id.name',
            'company_id.vat': 'company_id.vat',
            'company_id.phone': 'company_id.phone',
            'company_id.email': 'company_id.email',
            'company_id.street': 'company_id.street',
            'company_id.city': 'company_id.city',
            'company_id.country_id.name': 'company_id.country_id.name',
            'company_id.website': 'company_id.website',
            
            # User fields
            'user_id.name': 'invoice_user_id.name',
            'user_id.email': 'invoice_user_id.email',
            'user_id.phone': 'invoice_user_id.phone',
            'user_id.mobile': 'invoice_user_id.mobile',
            
            # Line fields
            'order_line.product_id.name': 'invoice_line_ids.product_id.name',
        }
        return mapping_conversions.get(so_mapping, so_mapping)
    
    def _adapt_so_mapping_to_contact(self, so_mapping):
        """Adapt Sale Order field mapping to Contact fields"""
        mapping_conversions = {
            # Partner to contact direct mapping
            'partner_id.name': 'name',
            'partner_id.mobile': 'mobile',
            'partner_id.phone': 'phone', 
            'partner_id.email': 'email',
            'partner_id.ref': 'ref',
            'partner_id.vat': 'vat',
            'partner_id.street': 'street',
            'partner_id.city': 'city',
            'partner_id.state_id.name': 'state_id.name',
            'partner_id.country_id.name': 'country_id.name',
            'partner_id.zip': 'zip',
            'partner_id.function': 'function',
            'partner_id.website': 'website',
            
            # Company fields for contacts
            'company_id.name': 'company_id.name',
            'company_id.vat': 'company_id.vat',
            'company_id.phone': 'company_id.phone',
            'company_id.email': 'company_id.email',
            
            # Other mappings
            'name': 'ref',  # Contact reference
            'note': 'comment',
        }
        return mapping_conversions.get(so_mapping, None)  # Return None if no mapping