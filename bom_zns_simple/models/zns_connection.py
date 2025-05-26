# -*- coding: utf-8 -*-

import requests
import logging
from datetime import datetime, timedelta
from odoo import models, fields, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class ZnsConnection(models.Model):
    _name = 'zns.connection'
    _description = 'ZNS Connection Configuration'
    _rec_name = 'name'

    name = fields.Char('Name', required=True)
    api_key = fields.Char('API Key', required=True, help='BOM API Key (used to get access token)')
    api_secret = fields.Char('API Secret', help='Not used in v2 API') 
    api_base_url = fields.Char('API Base URL', default='https://zns.bom.asia/api/v2', required=True)
    access_token = fields.Text('Access Token', readonly=True)
    refresh_token = fields.Text('Refresh Token', readonly=True)
    token_expires_at = fields.Datetime('Token Expires At', readonly=True)
    active = fields.Boolean('Active', default=True)
    last_sync = fields.Datetime('Last Sync', readonly=True)
    
    def _get_access_token(self):
        """Get or refresh access token using API key"""
        # Check if current token is still valid
        if self.access_token and self.token_expires_at:
            if datetime.now() < self.token_expires_at - timedelta(minutes=5):  # 5 min buffer
                return self.access_token
        
        # Try to refresh token first if available
        if self.refresh_token:
            try:
                return self._refresh_access_token()
            except Exception as e:
                _logger.warning(f"Failed to refresh token: {e}")
        
        # Get new access token
        return self._get_new_access_token()
    
    def _get_new_access_token(self):
        """Get new access token using API key"""
        url = f"{self.api_base_url}/access-token"
        headers = {
            'Authorization': f'Bearer {self.api_key}'
        }
        data = {
            'grant_type': 'authorization_code'
        }
        
        try:
            # Use form data as shown in Postman collection
            response = requests.post(url, headers=headers, data=data, timeout=30)
            response.raise_for_status()
            
            result = response.json()
            if result.get('error') == 0:
                token_data = result.get('data', {})
                self.write({
                    'access_token': token_data.get('access_token'),
                    'refresh_token': token_data.get('refresh_token'),
                    'token_expires_at': datetime.now() + timedelta(seconds=token_data.get('expires_in', 90000))
                })
                return token_data.get('access_token')
            else:
                raise UserError(f"API Error: {result.get('message', 'Unknown error')}")
                
        except requests.exceptions.RequestException as e:
            raise UserError(f"Connection failed: {str(e)}")
    
    def _refresh_access_token(self):
        """Refresh access token using refresh token"""
        url = f"{self.api_base_url}/access-token"
        headers = {
            'Authorization': f'Bearer {self.api_key}'
        }
        data = {
            'grant_type': 'refresh_token',
            'refresh_token': self.refresh_token
        }
        
        try:
            # Use form data as shown in Postman collection
            response = requests.post(url, headers=headers, data=data, timeout=30)
            response.raise_for_status()
            
            result = response.json()
            if result.get('error') == 0:
                token_data = result.get('data', {})
                self.write({
                    'access_token': token_data.get('access_token'),
                    'refresh_token': token_data.get('refresh_token'),
                    'token_expires_at': datetime.now() + timedelta(seconds=token_data.get('expires_in', 90000))
                })
                return token_data.get('access_token')
            else:
                raise UserError(f"API Error: {result.get('message', 'Unknown error')}")
                
        except requests.exceptions.RequestException as e:
            raise UserError(f"Failed to refresh token: {str(e)}")
    
    def test_connection(self):
        """Test API connection by getting access token"""
        if not self.api_key:
            raise UserError("Please enter your API Key first")
        
        try:
            token = self._get_access_token()
            if token:
                self.write({'last_sync': fields.Datetime.now()})
                self.env.user.notify_success(message="✅ Connection successful! Access token obtained.")
                return True
        except Exception as e:
            raise UserError(f"❌ Connection test failed: {str(e)}")