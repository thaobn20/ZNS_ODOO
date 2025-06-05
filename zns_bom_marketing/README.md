# 🎯 ZNS BOM Marketing

**Advanced Marketing Automation for ZNS Messaging with Birthday Campaigns**

[![License: LGPL-3](https://img.shields.io/badge/License-LGPL%20v3-blue.svg)](https://www.gnu.org/licenses/lgpl-3.0)
[![Odoo Version](https://img.shields.io/badge/Odoo-15.0%2B-purple)](https://odoo.com)

## 📋 Overview

ZNS BOM Marketing is a comprehensive marketing automation module that integrates seamlessly with the `bom_zns_simple` module to provide advanced ZNS (Zalo Notification Service) marketing capabilities. The module specializes in **automatic birthday campaigns** and sophisticated contact list management.

## ✨ Key Features

### 🎂 **Automatic Birthday Campaigns**
- **Daily Birthday Detection**: Automatically finds contacts with upcoming birthdays
- **Smart Scheduling**: Send birthday messages at optimal times (configurable)
- **No Duplicates**: Prevents sending multiple birthday messages per year
- **Age Calculation**: Automatically calculates and includes customer age in messages

### 📋 **Advanced Contact Management**
- **Static Lists**: Manual contact selection and management
- **Dynamic Lists**: Auto-updated lists based on custom filters
- **Birthday Auto Lists**: Automatically populated with birthday contacts
- **Health Monitoring**: Track phone validity, opt-out rates, and list quality

### 🚀 **Campaign Management**
- **Multiple Campaign Types**: Birthday, Promotion, Notification, Recurring
- **Flexible Scheduling**: Immediate, scheduled, or recurring campaigns
- **Template Integration**: Uses existing BOM ZNS templates
- **Progress Tracking**: Real-time campaign progress and analytics

### 📊 **Analytics & Reporting**
- **Delivery Tracking**: Monitor message delivery rates and failures
- **Campaign Performance**: Detailed analytics for each campaign
- **Dashboard**: Real-time marketing performance overview
- **Opt-out Management**: Comprehensive unsubscribe handling

### 🔄 **Automation Features**
- **Scheduled Jobs**: Automated daily, hourly, and weekly tasks
- **Retry Logic**: Automatic retry of failed messages
- **Dynamic Updates**: Auto-refresh of dynamic contact lists
- **Error Handling**: Robust error management and logging

## 🔧 Requirements

### Dependencies
- **Odoo 15.0+** (Community or Enterprise)
- **bom_zns_simple** module (must be installed and configured)
- **contacts** module (included with Odoo)

### System Requirements
- Python 3.8+
- PostgreSQL 12+
- 512MB+ RAM
- 100MB+ disk space

## 📦 Installation

### Method 1: Clone from GitHub
```bash
# Clone the repository
git clone https://github.com/yourusername/zns_bom_marketing.git

# Copy to Odoo addons directory
cp -r zns_bom_marketing /path/to/odoo/addons/

# Restart Odoo server
sudo systemctl restart odoo

# Update Apps List and Install
# Go to Apps > Update Apps List > Search "ZNS BOM Marketing" > Install
```

### Method 2: Manual Installation
1. Download the module files
2. Extract to your Odoo addons directory
3. Restart Odoo
4. Update Apps List
5. Install "ZNS BOM Marketing"

## ⚙️ Configuration

### 1. **Setup BOM ZNS Simple**
First, ensure `bom_zns_simple` is properly configured:
- Configure ZNS connection (API credentials)
- Create ZNS templates
- Test message sending

### 2. **Create Birthday Template**
Create a birthday message template in BOM ZNS:
```
🎂 Happy Birthday {customer_name}! 
You're now {age} years old! 🎉
Enjoy 20% off your next purchase with code BIRTHDAY20
```

### 3. **Setup Birthday Campaign**
1. Go to **ZNS Marketing > Campaigns > All Campaigns**
2. Click **Create**
3. Fill in campaign details:
   - **Name**: "Birthday Wishes 2024"
   - **Type**: Birthday Campaign
   - **Template**: Select your birthday template
   - **Days Before**: 0 (send on birthday)
   - **Send Time**: 9:00 AM
4. Click **Start Campaign**

### 4. **Configure Contact Lists**
Create contact lists for targeted campaigns:
- **Static Lists**: Manual contact selection
- **Dynamic Lists**: Auto-filtered contacts
- **Birthday Lists**: Auto-birthday detection

## 🎯 Usage Guide

### **Birthday Automation Setup**
1. **Create Template**: Design birthday message template
2. **Create Campaign**: Set up birthday campaign
3. **Activate**: Set campaign status to "Running"
4. **Monitor**: Check dashboard for birthday messages

### **Regular Campaign Creation**
1. **Prepare Contacts**: Create or update contact lists
2. **Design Message**: Create ZNS template
3. **Create Campaign**: Configure campaign settings
4. **Schedule/Send**: Launch campaign immediately or schedule

### **Contact List Management**
- **Static Lists**: Add contacts manually
- **Dynamic Lists**: Set domain filters for auto-updates
- **Birthday Lists**: Configure birthday detection settings

## 📈 Dashboard & Analytics

Access the marketing dashboard at **ZNS Marketing > Dashboard**:

### **Key Metrics**
- Active campaigns count
- Daily message statistics
- Delivery rate percentages
- Queue status

### **Performance Charts**
- Message trends over time
- Campaign type distribution
- Success/failure rates
- Monthly performance

### **Real-time Monitoring**
- Current queue status
- Active campaigns
- Recent activity
- Upcoming birthdays

## 🔄 Automation Details

### **Scheduled Jobs**
The module runs several automated background jobs:

| Job | Frequency | Purpose |
|-----|-----------|---------|
| Birthday Campaigns | Daily 6:00 AM | Find and queue birthday messages |
| Campaign Executor | Hourly | Execute scheduled campaigns |
| Message Processor | Every 5 minutes | Send queued messages |
| Dynamic List Updater | Daily 2:00 AM | Refresh dynamic contact lists |
| Retry Handler | Every 30 minutes | Retry failed messages |
| Cleanup | Weekly | Remove old message records |

### **Birthday Detection Logic**
1. **Daily Scan**: System scans all contacts at 6:00 AM
2. **Date Matching**: Finds contacts with today's birthday (or tomorrow, configurable)
3. **Filtering**: Applies contact list filters and opt-out rules
4. **Scheduling**: Queues messages for configured send time
5. **Sending**: Delivers messages via BOM ZNS Simple
6. **Tracking**: Records delivery status and analytics

## 🛠️ Troubleshooting

### **Common Issues**

#### **Birthday Messages Not Sending**
- Check if birthday campaign is set to "Running" status
- Verify BOM ZNS template is active and valid
- Ensure contacts have valid phone numbers
- Check opt-out status of contacts

#### **No Contacts in Birthday Lists**
- Verify contact birthday fields are populated
- Check birthday list configuration (days before, months)
- Ensure contacts have valid date format in birthday field

#### **Campaign Stuck in Queue**
- Check BOM ZNS Simple connection status
- Verify template parameters match contact data
- Review error messages in campaign messages

#### **Low Delivery Rates**
- Validate phone number formats
- Check ZNS provider account limits
- Review template content for compliance
- Monitor opt-out rates

### **Debug Mode**
Enable debug logging in Odoo configuration:
```ini
[options]
log_level = debug
log_handler = :DEBUG
```

Check logs for detailed error information:
```bash
tail -f /var/log/odoo/odoo.log | grep "zns_bom_marketing"
```

## 🔒 Security & Permissions

### **User Groups**
- **ZNS Marketing User**: Can create and manage own campaigns
- **ZNS Marketing Manager**: Full access to all marketing features

### **Access Rules**
- Users can only edit their own campaigns (unless manager)
- Message records are read-only for users
- Opt-out records are globally accessible
- Dashboard data respects user permissions

## 🚀 Advanced Features

### **Custom Parameters**
The module supports custom template parameters:
- `{customer_name}` - Contact name
- `{age}` - Calculated age for birthday campaigns
- `{phone}` - Contact phone number
- `{email}` - Contact email
- `{company_name}` - Associated company name

### **API Integration**
Extend functionality through Odoo's API:
```python
# Create campaign programmatically
campaign = env['zns.bom.marketing.campaign'].create({
    'name': 'Flash Sale Campaign',
    'campaign_type': 'promotion',
    'bom_zns_template_id': template_id,
    'contact_list_ids': [(6, 0, [list_id])]
})
campaign.action_start_campaign()
```

### **Webhook Support**
Handle delivery status updates from ZNS provider (requires customization).

## 📄 License

This module is licensed under LGPL-3.0. See [LICENSE](LICENSE) file for details.

## 🤝 Contributing

We welcome contributions! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## 📞 Support

- **Documentation**: Check this README and in-app help
- **Issues**: Report bugs on GitHub Issues
- **Community**: Join Odoo Community discussions
- **Professional Support**: Contact for enterprise support

## 🎉 Acknowledgments

- Built for integration with `bom_zns_simple`
- Inspired by modern marketing automation platforms
- Thanks to the Odoo community for feedback and testing

---

**Happy Marketing! 🎯📱**

*Automate your ZNS campaigns and never miss a birthday again!* 🎂