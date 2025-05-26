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
        """Sync template parameters from BOM API using correct endpoint"""
        connection = self.connection_id
        if not connection:
            raise UserError("No connection configured")
        
        access_token = connection._get_access_token()
        # Use correct endpoint from Postman collection
        url = f"{connection.api_base_url}/get-param-zns-template"
        headers = {
            'Content-Type': 'application/json',
            'Authorization': f'Bearer {access_token}'
        }
        data = {
            'template_id': self.template_id
        }
        
        try:
            response = requests.post(url, headers=headers, json=data, timeout=30)
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
                        'title': param.get('title'),
                        'param_type': param.get('type', 'string'),
                        'required': param.get('require', False)
                    })
                
                self.last_sync = fields.Datetime.now()
                self.env.user.notify_success(message=f"Synced {len(params_data)} parameters")
            else:
                raise UserError(f"API Error: {result.get('message', 'Unknown error')}")
                
        except requests.exceptions.RequestException as e:
            raise UserError(f"Sync failed: {str(e)}")


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