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
    
    def sync_template_params(self):
        """Sync template parameters from BOM API using correct endpoint and format"""
        connection = self.connection_id
        if not connection:
            raise UserError("No connection configured")
        
        try:
            # Get access token
            access_token = connection._get_access_token()
        except Exception as e:
            raise UserError(f"Failed to get access token: {str(e)}")
        
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
            _logger.info(f"Syncing template params from: {url}")
            _logger.info(f"Template ID: {self.template_id}")
            
            response = requests.post(url, headers=headers, json=data, timeout=30)
            
            _logger.info(f"Response status: {response.status_code}")
            _logger.info(f"Response body: {response.text}")
            
            response.raise_for_status()
            result = response.json()
            
            if result.get('error') == 0:
                params_data = result.get('data', [])
                
                # Clear existing parameters
                self.parameter_ids.unlink()
                
                # Create new parameters
                for param in params_data:
                    self.env['zns.template.parameter'].create({
                        'template_id': self.id,
                        'name': param.get('name'),
                        'title': param.get('title', param.get('name')),
                        'param_type': self._map_param_type(param.get('type', 'string')),
                        'required': param.get('require', False),
                        'default_value': param.get('default_value', '')
                    })
                
                self.last_sync = fields.Datetime.now()
                
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': 'Template Sync Successful',
                        'message': f"✅ Successfully synced {len(params_data)} parameters!\n" +
                                 f"Parameters: {[p.get('name') for p in params_data]}",
                        'type': 'success',
                        'sticky': False,
                    }
                }
            else:
                error_msg = result.get('message', 'Unknown error')
                raise UserError(f"❌ API Error: {error_msg}")
                
        except requests.exceptions.RequestException as e:
            raise UserError(f"❌ Sync failed: {str(e)}")
        except Exception as e:
            raise UserError(f"❌ Unexpected error: {str(e)}")
    
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
        return type_mapping.get(api_type.lower(), 'string')
    
    def test_template(self):
        """Test template by getting its parameters"""
        return self.sync_template_params()


class ZnsTemplateParameter(models.Model):
    _name = 'zns.template.parameter'
    _description = 'ZNS Template Parameter'
    _rec_name = 'title'

    template_id = fields.Many2one('zns.template', string='Template', required=True, ondelete='cascade')
    name = fields.Char('Parameter Name', required=True, help='Parameter key name')
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