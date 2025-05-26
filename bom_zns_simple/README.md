# BOM ZNS Simple - Odoo Integration Module

This module integrates Odoo with Zalo ZNS (Zalo Notification Service) through BOM API v2, enabling you to send ZNS messages from Purchase Orders, Sales Orders, and Invoices.

## 🚀 Features

- **API v2 Support**: Updated for BOM ZNS API v2 with Bearer token authentication
- **Automatic Token Management**: Access token refresh and expiration handling
- **Template Synchronization**: Sync template parameters directly from BOM dashboard
- **Multi-document Integration**: Send from contacts, sales orders, and invoices
- **Parameter Auto-mapping**: Automatic field mapping from Odoo to ZNS parameters
- **Message Tracking**: Complete history and status tracking
- **Phone Formatting**: Vietnamese phone number formatting
- **Security**: User groups and access controls

## 📋 Requirements

- Odoo 15.0+
- Python `requests` library
- BOM API credentials (API Key and Secret)
- Zalo Official Account connected to BOM

## 🔧 Installation

### 1. Download Module
```bash
cd /path/to/odoo/addons
git clone <repository-url> bom_zns_simple
# OR copy the entire bom_zns_simple folder to your addons directory
```

### 2. Install Dependencies
```bash
pip install requests
```

### 3. Update Odoo
```bash
# Restart Odoo service
sudo systemctl restart odoo
# OR
sudo service odoo restart

# Update apps list in Odoo interface
# Apps > Update Apps List
```

### 4. Install Module
- Go to Apps in Odoo
- Search for "BOM ZNS Integration"
- Click Install

## ⚙️ Configuration

### 1. Setup Connection
1. Go to **Zalo ZNS > Configuration > Connections**
2. Edit the default connection or create new:
   - **Name**: Your connection name
   - **API Key**: Your BOM API key
   - **API Secret**: Your BOM API secret
   - **API Base URL**: `https://zns.bom.asia/api/v2`
3. Click **Test Connection**

### 2. Create Templates
1. Go to **Zalo ZNS > Configuration > Templates**
2. Create new template:
   - **Template Name**: Descriptive name
   - **Template ID**: From BOM dashboard (e.g., "248079")
   - **Template Type**: Transaction/OTP/Promotion
   - **Connection**: Select your connection
3. Click **Sync Parameters** to fetch parameters

## 📱 Usage

### From Contacts
1. Open any contact
2. Click **ZNS Messages** button
3. Click **Send ZNS Message**
4. Select template and fill parameters

### From Sales Orders
1. Open sales order
2. Click **ZNS Messages** button
3. Parameters auto-filled from order data
4. Click **Send Message**

### From Invoices
1. Open invoice
2. Click **ZNS Messages** button
3. Parameters auto-filled from invoice data
4. Click **Send Message**

## 🔄 API v2 Changes

### Authentication
- **Old v1**: API key in headers
- **New v2**: Bearer token with auto-refresh

### Endpoints
- `/api/v2/access-token` - Token management
- `/api/v2/send-template` - Send messages
- `/api/v2/template-params` - Get parameters

### Request Format
```json
// v2 Format
{
  "phone": "84987654321",
  "template_id": "248079",
  "params": {
    "customer_name": "John Doe",
    "order_id": "SO001"
  }
}
```

## 📊 Parameter Mapping

| ZNS Parameter | Source Field | Description |
|---------------|--------------|-------------|
| `customer_name` | `partner_id.name` | Customer name |
| `order_id`, `so_no` | `name` | Order number |
| `amount` | `amount_total` | Total amount |
| `order_date` | `date_order` | Order date |
| `due_date` | `invoice_date_due` | Due date |
| `customer_phone` | `partner_id.mobile` | Phone number |
| `customer_email` | `partner_id.email` | Email address |

## 🛡️ Security

### User Groups
- **ZNS User**: Send messages, view own messages
- **ZNS Manager**: Full access, configuration

### Permissions
- Users can only see their own messages
- Managers can see all messages and configure system

## 🔍 Troubleshooting

### Common Issues
1. **Connection Failed**
   - Check API credentials
   - Verify network connectivity
   - Ensure correct API base URL

2. **Template Not Found**
   - Verify template ID exists in BOM
   - Check template is active

3. **Missing Parameters**
   - Sync template parameters
   - Check required fields are filled

### Debug Mode
Enable Odoo debug mode to see detailed error messages and API responses.

### Logs
```bash
tail -f /var/log/odoo/odoo.log | grep "ZNS"
```

## 📁 File Structure
```
bom_zns_simple/
├── __init__.py
├── __manifest__.py
├── models/
│   ├── __init__.py
│   ├── zns_connection.py      # Connection management
│   ├── zns_template.py        # Template handling
│   ├── zns_message.py         # Message processing
│   ├── zns_wizard.py          # Send wizard
│   ├── zns_helper.py          # Utility functions
│   └── res_partner.py         # Model extensions
├── views/
│   ├── zns_connection_views.xml
│   ├── zns_template_views.xml
│   ├── zns_message_views.xml
│   ├── zns_wizard_views.xml
│   ├── res_partner_views.xml
│   ├── sale_order_views.xml
│   ├── account_move_views.xml
│   └── zns_menus.xml
├── security/
│   ├── ir.model.access.csv
│   └── zns_security.xml
├── data/
│   └── zns_data.xml
└── demo/
    └── zns_demo.xml
```

## 🆘 Support

- **Email**: support@bom.asia
- **Website**: https://bom.asia
- **API Docs**: https://zns.bom.asia/api/docs/version-2/

## 📄 License

LGPL-3

---

**Ready to use!** Your BOM ZNS integration is now compatible with API v2 and ready for production use.