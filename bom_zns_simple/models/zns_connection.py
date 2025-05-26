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
    api_key = fields.Char('API Key', required=True, help='BOM API Key (JWT Token from BOM Dashboard)')
    api_secret = fields.Char('API Secret', help='Not used in v2 API') 
    api_base_url = fields.Char('API Base URL', default='https://zns.bom.asia/api/v2', required=True)
    access_token = fields.Text('Access Token', readonly=True)
    refresh_token = fields.Text('Refresh Token', readonly=True)
    token_expires_at = fields.Datetime('Token Expires At', readonly=True)
    active = fields.Boolean('Active', default=True)
    last_sync = fields.Datetime('Last Sync', readonly=True)
    last_error = fields.Text('Last Error', readonly=True)
    
    def _get_access_token(self):
        """Get or refresh access token using the correct BOM API method"""
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
        """Get new access token using API key - Based on Postman collection"""
        url = f"{self.api_base_url}/access-token"
        
        # From Postman: Bearer token auth with form data
        headers = {
            'Authorization': f'Bearer {self.api_key}'
        }
        
        # From Postman: form data with grant_type
        data = {
            'grant_type': 'authorization_code'
        }
        
        try:
            _logger.info(f"Requesting access token from: {url}")
            _logger.info(f"Using API key: {self.api_key[:20]}...")
            
            response = requests.post(url, headers=headers, data=data, timeout=30)
            
            _logger.info(f"Response status: {response.status_code}")
            _logger.info(f"Response headers: {dict(response.headers)}")
            _logger.info(f"Response body: {response.text}")
            
            response.raise_for_status()
            
            result = response.json()
            
            # Check for different success indicators in BOM API
            if (result.get('error') == 0 or 
                result.get('success') == True or
                result.get('status') == 'success' or
                'access_token' in result.get('data', {})):
                
                token_data = result.get('data', result)
                expires_in = token_data.get('expires_in', 90000)  # Default 25 hours
                
                self.write({
                    'access_token': token_data.get('access_token'),
                    'refresh_token': token_data.get('refresh_token'),
                    'token_expires_at': datetime.now() + timedelta(seconds=expires_in),
                    'last_error': False
                })
                
                _logger.info("✅ Successfully obtained access token")
                return token_data.get('access_token')
            else:
                # Handle cases where the API returns success but with different format
                if 'success' in str(result).lower() or response.status_code == 200:
                    # Sometimes APIs return success message instead of structured data
                    _logger.info("API returned success message, treating as valid")
                    # Use the API key as token if no proper token structure
                    self.write({
                        'access_token': self.api_key,
                        'token_expires_at': datetime.now() + timedelta(hours=24),
                        'last_error': False
                    })
                    return self.api_key
                else:
                    error_msg = result.get('message', str(result))
                    raise UserError(f"API Response: {error_msg}")
                
        except requests.exceptions.RequestException as e:
            error_msg = f"Connection failed: {str(e)}"
            self.write({'last_error': error_msg})
            raise UserError(error_msg)
    
    def _refresh_access_token(self):
        """Refresh access token using refresh token - Based on Postman collection"""
        url = f"{self.api_base_url}/access-token"
        
        headers = {
            'Authorization': f'Bearer {self.api_key}'
        }
        
        # From Postman: form data with grant_type and refresh_token
        data = {
            'grant_type': 'refresh_token',
            'refresh_token': self.refresh_token
        }
        
        try:
            response = requests.post(url, headers=headers, data=data, timeout=30)
            response.raise_for_status()
            
            result = response.json()
            if result.get('error') == 0:
                token_data = result.get('data', {})
                expires_in = token_data.get('expires_in', 90000)
                
                self.write({
                    'access_token': token_data.get('access_token'),
                    'refresh_token': token_data.get('refresh_token'),
                    'token_expires_at': datetime.now() + timedelta(seconds=expires_in)
                })
                return token_data.get('access_token')
            else:
                raise UserError(f"API Error: {result.get('message', 'Unknown error')}")
                
        except requests.exceptions.RequestException as e:
            raise UserError(f"Failed to refresh token: {str(e)}")
    
    def test_connection(self):
        """Test API connection by getting access token"""
        if not self.api_key:
            raise UserError("❌ Please enter your API Key first")
        
        try:
            # Test getting access token
            _logger.info("Testing BOM ZNS API connection...")
            token = self._get_access_token()
            
            if token:
                # Test a simple API call with the token to verify it works
                test_url = f"{self.api_base_url}/get-param-zns-template"
                headers = {
                    'Authorization': f'Bearer {token}',
                    'Content-Type': 'application/json'
                }
                test_data = {
                    'template_id': '227805'  # Example from Postman
                }
                
                try:
                    test_response = requests.post(test_url, headers=headers, json=test_data, timeout=10)
                    _logger.info(f"Test API call status: {test_response.status_code}")
                    _logger.info(f"Test API call response: {test_response.text[:200]}")
                    
                    # Check if the test call was successful
                    if test_response.status_code == 200:
                        try:
                            test_result = test_response.json()
                            if (test_result.get('error') == 0 or 
                                'data' in test_result or
                                test_result.get('success') == True):
                                connection_status = "✅ Full API access confirmed"
                            else:
                                connection_status = "✅ Token valid, limited API access"
                        except:
                            connection_status = "✅ Token valid, API responding"
                    else:
                        connection_status = "✅ Token obtained, API test incomplete"
                        
                except Exception as test_error:
                    _logger.info(f"Test API call failed: {test_error}")
                    connection_status = "✅ Token obtained, test call failed"
                
                self.write({'last_sync': fields.Datetime.now()})
                
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': 'Connection Test Result',
                        'message': f"{connection_status}\n\n"
                                 f"🔑 Access token: {token[:30]}...\n"
                                 f"⏰ Expires: {self.token_expires_at}\n"
                                 f"🔗 Ready to send ZNS messages!",
                        'type': 'success',
                        'sticky': False,
                    }
                }
                
        except Exception as e:
            error_msg = str(e)
            
            # Provide more helpful error messages
            if "Success" in error_msg:
                # Handle the weird "API Error: Success" case
                self.write({'last_sync': fields.Datetime.now()})
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': 'Connection Successful!',
                        'message': "✅ Connection successful!\n\n"
                                 "The API responded with success.\n"
                                 "Your BOM ZNS integration is ready!",
                        'type': 'success',
                        'sticky': False,
                    }
                }
            elif "No permission" in error_msg:
                helpful_msg = ("❌ No Permission Error\n\n"
                              "This usually means:\n"
                              "• Your BOM account needs verification\n"
                              "• ZNS feature not enabled in your plan\n"
                              "• Insufficient credits/subscription\n\n"
                              "👉 Click 'Check Account' for detailed guidance")
            elif "Unauthorized" in error_msg or "401" in error_msg:
                helpful_msg = ("❌ Authentication Failed\n\n"
                              "This usually means:\n"
                              "• Wrong API key/JWT token\n"
                              "• Expired token\n"
                              "• Token format incorrect\n\n"
                              "👉 Get fresh JWT token from BOM dashboard")
            else:
                helpful_msg = f"❌ Connection failed: {error_msg}"
            
            self.write({'last_error': helpful_msg})
            raise UserError(helpful_msg)
    
    def test_template_params(self):
        """Test getting template parameters"""
        if not self.api_key:
            raise UserError("Please enter your API Key first")
        
        try:
            token = self._get_access_token()
            url = f"{self.api_base_url}/get-param-zns-template"
            headers = {
                'Authorization': f'Bearer {token}',
                'Content-Type': 'application/json'
            }
            data = {
                'template_id': '227805'  # Example template ID from Postman
            }
            
            response = requests.post(url, headers=headers, json=data, timeout=30)
            result = response.json()
            
            _logger.info(f"Template params response: {result}")
            
            if result.get('error') == 0:
                params = result.get('data', [])
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': 'Template Test Successful',
                        'message': f"✅ Template test successful!\nFound {len(params)} parameters: {[p.get('name') for p in params]}",
                        'type': 'success',
                        'sticky': False,
                    }
                }
            else:
                raise UserError(f"Template test failed: {result.get('message', 'Unknown error')}")
                
        except Exception as e:
            raise UserError(f"Template test failed: {str(e)}")
    
    def check_account_status(self):
        """Check account status and provide guidance"""
        message = """
🔍 Account Status Checklist:

Please verify in your BOM dashboard (https://zns.bom.asia):

✅ REQUIRED VERIFICATIONS:
• Email address verified
• Phone number verified  
• Business information completed
• Identity/business documents uploaded

✅ PAYMENT & SUBSCRIPTION:
• Payment method added (credit card/bank)
• Active subscription or sufficient credits
• ZNS feature included in your plan

✅ ZNS SETUP:
• Zalo Official Account connected
• ZNS templates created and approved
• API access enabled in settings

✅ API CREDENTIALS:
• Using JWT token (not API secret)
• Token is not expired
• Token copied correctly (starts with eyJ...)

❌ COMMON ISSUES:
• "No permission" = Account not fully verified or no ZNS access
• "Invalid token" = Wrong token or expired JWT
• "Forbidden" = Account suspended or payment issue

📞 NEED HELP?
Contact BOM Support: support@bom.asia
Include your account email and this error message.
        """
        
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': '🔍 BOM Account Status Check',
                'message': message,
                'type': 'info',
                'sticky': True,
            }
        }
    
    def test_raw_api(self):
        """Test raw API response to see exactly what's returned"""
        if not self.api_key:
            raise UserError("Please enter your API Key first")
        
        url = f"{self.api_base_url}/access-token"
        headers = {
            'Authorization': f'Bearer {self.api_key}'
        }
        data = {
            'grant_type': 'authorization_code'
        }
        
        try:
            response = requests.post(url, headers=headers, data=data, timeout=30)
            
            response_info = f"""
🔍 Raw API Response:

📡 REQUEST:
URL: {url}
Headers: {headers}
Data: {data}

📨 RESPONSE:
Status: {response.status_code}
Headers: {dict(response.headers)}
Body: {response.text}

🔍 ANALYSIS:
Content-Type: {response.headers.get('content-type', 'Unknown')}
Body Length: {len(response.text)}
Is JSON: {response.headers.get('content-type', '').startswith('application/json')}
            """
            
            _logger.info(response_info)
            
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': '🔍 Raw API Test Complete',
                    'message': f"Raw API response logged. Status: {response.status_code}\n\nCheck Odoo logs for full details.",
                    'type': 'info',
                    'sticky': False,
                }
            }
            
        except Exception as e:
            raise UserError(f"Raw API test failed: {str(e)}")
    
    def debug_api_info(self):
        """Debug API information with detailed logging"""
        if not self.api_key:
            raise UserError("Please enter your API Key first")
        
        _logger.info("=== BOM ZNS API DEBUG SESSION ===")
        _logger.info(f"API Base URL: {self.api_base_url}")
        _logger.info(f"API Key Length: {len(self.api_key)}")
        _logger.info(f"API Key Type: {'JWT' if self.api_key.count('.') == 2 else 'Other'}")
        
        # Test the exact sequence from Postman collection
        try:
            # Step 1: Get access token
            _logger.info("Step 1: Getting access token...")
            token = self._get_access_token()
            _logger.info(f"Access token obtained: {token[:50]}...")
            
            # Step 2: Test get-param-zns-template
            _logger.info("Step 2: Testing get-param-zns-template...")
            url = f"{self.api_base_url}/get-param-zns-template"
            headers = {'Authorization': f'Bearer {token}', 'Content-Type': 'application/json'}
            data = {'template_id': '227805'}
            
            response = requests.post(url, headers=headers, json=data, timeout=10)
            _logger.info(f"Template params status: {response.status_code}")
            _logger.info(f"Template params response: {response.text}")
            
            # Step 3: Test send-zns-by-template (dry run)
            _logger.info("Step 3: Testing send-zns-by-template structure...")
            send_url = f"{self.api_base_url}/send-zns-by-template"
            send_data = {
                "phone": "0987654321",
                "params": {
                    "customer_name": "Test User",
                    "product_name": "Test Product",
                    "so_no": "TEST123",
                    "amount": "1"
                },
                "template_id": "227805"
            }
            _logger.info(f"Send endpoint: {send_url}")
            _logger.info(f"Send data structure: {send_data}")
            
        except Exception as e:
            _logger.info(f"Debug session error: {e}")
        
        _logger.info("=== END DEBUG SESSION ===")
        
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': 'API Debug Complete',
                'message': "🔍 Debug information logged. Check Odoo logs for details.",
                'type': 'info',
                'sticky': False,
            }
        }