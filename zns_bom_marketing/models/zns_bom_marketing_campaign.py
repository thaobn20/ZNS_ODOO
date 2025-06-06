# -*- coding: utf-8 -*-

import json
import logging
from datetime import datetime, timedelta, time
from odoo import models, fields, api, _
from odoo.exceptions import ValidationError, UserError

_logger = logging.getLogger(__name__)


class ZnsBomMarketingCampaign(models.Model):
    _name = 'zns.bom.marketing.campaign'
    _description = 'ZNS BOM Marketing Campaign'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    _order = 'create_date desc'
    _rec_name = 'name'

    # Basic Information
    name = fields.Char('Campaign Name', required=True, tracking=True)
    description = fields.Text('Description')
    active = fields.Boolean('Active', default=True)
    color = fields.Integer('Color Index', default=0)
    
    # Campaign Type
    campaign_type = fields.Selection([
        ('promotion', 'Promotion'),
        ('birthday', 'Birthday'),
        ('notification', 'Notification'),
        ('recurring', 'Recurring'),
        ('one_time', 'One Time')
    ], required=True, default='promotion', string='Campaign Type', tracking=True)
    
    # Status
    status = fields.Selection([
        ('draft', 'Draft'),
        ('scheduled', 'Scheduled'),
        ('running', 'Running'),
        ('paused', 'Paused'),
        ('completed', 'Completed'),
        ('cancelled', 'Cancelled'),
        ('failed', 'Failed')
    ], default='draft', required=True, string='Status', tracking=True)
    
    # BOM ZNS Integration
    bom_zns_template_id = fields.Many2one('bom.zns.template', string='ZNS Template',
                                         help='Select ZNS template from BOM ZNS Simple module')
    bom_zns_connection_id = fields.Many2one('bom.zns.connection', string='ZNS Connection', 
                                           help='ZNS Connection used for this campaign')
    
    # Target Audience
    contact_list_ids = fields.Many2many(
        'zns.bom.marketing.contact.list',
        'zns_bom_marketing_campaign_list_rel',
        'campaign_id',
        'list_id',
        string='Target Contact Lists'
    )
    excluded_contact_ids = fields.Many2many(
        'res.partner',
        'zns_bom_marketing_campaign_excluded_rel',
        'campaign_id',
        'contact_id',
        string='Excluded Contacts'
    )
    
    # Scheduling
    send_mode = fields.Selection([
        ('immediate', 'Send Immediately'),
        ('scheduled', 'Scheduled'),
        ('recurring', 'Recurring'),
        ('birthday_auto', 'Birthday Auto')
    ], required=True, default='immediate', string='Send Mode')
    
    scheduled_date = fields.Datetime('Scheduled Date')
    timezone = fields.Selection(
        lambda self: self._get_timezone_list(),
        string='Timezone',
        default='UTC'
    )
    
    # Execution Tracking
    started_date = fields.Datetime('Started Date', readonly=True)
    completed_date = fields.Datetime('Completed Date', readonly=True)
    
    # Recurring Settings
    recurring_type = fields.Selection([
        ('daily', 'Daily'),
        ('weekly', 'Weekly'),
        ('monthly', 'Monthly')
    ], string='Recurring Type')
    
    recurring_interval = fields.Integer('Recurring Interval', default=1)
    recurring_weekday = fields.Selection([
        ('0', 'Monday'),
        ('1', 'Tuesday'),
        ('2', 'Wednesday'),
        ('3', 'Thursday'),
        ('4', 'Friday'),
        ('5', 'Saturday'),
        ('6', 'Sunday')
    ], string='Weekday')
    
    recurring_day_of_month = fields.Integer('Day of Month', default=1)
    recurring_end_date = fields.Date('End Date')
    last_run_date = fields.Datetime('Last Run Date', readonly=True)
    next_run_date = fields.Datetime('Next Run Date', readonly=True)
    
    # Birthday Settings
    birthday_days_before = fields.Integer('Days Before Birthday', default=0)
    birthday_send_time = fields.Float('Send Time', default=9.0, help='Time in 24h format (9.0 = 9:00 AM)')
    
    # Business Settings
    respect_opt_out = fields.Boolean('Respect Opt-out', default=True)
    enable_retry = fields.Boolean('Enable Retry', default=True)
    max_retry_attempts = fields.Integer('Max Retry Attempts', default=3)
    max_send_per_hour = fields.Integer('Max Send per Hour', default=1000)
    
    # Messages
    message_ids = fields.One2many('zns.bom.marketing.message', 'campaign_id', string='Messages')
    
    # Statistics (computed fields)
    total_recipients = fields.Integer('Total Recipients', compute='_compute_recipients', store=True)
    messages_total = fields.Integer('Total Messages', compute='_compute_progress', store=True)
    messages_sent = fields.Integer('Messages Sent', compute='_compute_progress', store=True)
    messages_delivered = fields.Integer('Messages Delivered', compute='_compute_progress', store=True)
    messages_failed = fields.Integer('Messages Failed', compute='_compute_progress', store=True)
    messages_queued = fields.Integer('Messages Queued', compute='_compute_progress', store=True)
    
    progress_percentage = fields.Float('Progress %', compute='_compute_progress', store=True)
    delivery_rate = fields.Float('Delivery Rate %', compute='_compute_analytics', store=True)
    failure_rate = fields.Float('Failure Rate %', compute='_compute_analytics', store=True)
    
    # Cost Tracking
    total_cost = fields.Float('Total Cost', compute='_compute_analytics', store=True)
    
    @api.onchange('bom_zns_template_id')
    def _onchange_bom_zns_template_id(self):
        """Update connection when template changes"""
        if self.bom_zns_template_id:
            # Try to get connection from template if it has one
            try:
                if hasattr(self.bom_zns_template_id, 'connection_id'):
                    self.bom_zns_connection_id = self.bom_zns_template_id.connection_id
                elif hasattr(self.bom_zns_template_id, 'zns_connection_id'):
                    self.bom_zns_connection_id = self.bom_zns_template_id.zns_connection_id
                elif hasattr(self.bom_zns_template_id, 'bom_connection_id'):
                    self.bom_zns_connection_id = self.bom_zns_template_id.bom_connection_id
                else:
                    # If no connection field found, leave it empty for manual selection
                    self.bom_zns_connection_id = False
            except:
                self.bom_zns_connection_id = False
        else:
            self.bom_zns_connection_id = False
    
    @api.depends('contact_list_ids', 'contact_list_ids.contact_ids', 'excluded_contact_ids')
    def _compute_recipients(self):
        for record in self:
            if record.campaign_type == 'birthday':
                # For birthday campaigns, count all contacts with birthdays
                all_contacts = self.env['res.partner'].search([('birthday', '!=', False)])
                excluded = record.excluded_contact_ids
                record.total_recipients = len(all_contacts - excluded)
            else:
                # Regular campaigns use contact lists
                all_contacts = self.env['res.partner']
                for contact_list in record.contact_list_ids:
                    all_contacts |= contact_list.contact_ids
                
                excluded = record.excluded_contact_ids
                record.total_recipients = len(all_contacts - excluded)
    
    @api.depends('message_ids', 'message_ids.status')
    def _compute_progress(self):
        for record in self:
            messages = record.message_ids
            record.messages_total = len(messages)
            record.messages_sent = len(messages.filtered(lambda m: m.status in ['sent', 'delivered']))
            record.messages_delivered = len(messages.filtered(lambda m: m.status == 'delivered'))
            record.messages_failed = len(messages.filtered(lambda m: m.status == 'failed'))
            record.messages_queued = len(messages.filtered(lambda m: m.status == 'queued'))
            
            if record.messages_total > 0:
                completed = record.messages_sent + record.messages_failed
                record.progress_percentage = (completed / record.messages_total) * 100
            else:
                record.progress_percentage = 0.0
    
    @api.depends('message_ids', 'message_ids.status', 'message_ids.message_cost')
    def _compute_analytics(self):
        for record in self:
            messages = record.message_ids
            total = len(messages)
            
            if total > 0:
                delivered = len(messages.filtered(lambda m: m.status == 'delivered'))
                failed = len(messages.filtered(lambda m: m.status == 'failed'))
                
                record.delivery_rate = (delivered / total) * 100
                record.failure_rate = (failed / total) * 100
                record.total_cost = sum(messages.mapped('message_cost'))
            else:
                record.delivery_rate = 0.0
                record.failure_rate = 0.0
                record.total_cost = 0.0
    
    def _get_timezone_list(self):
        """Get list of timezones"""
        return [
            ('UTC', 'UTC'),
            ('Asia/Ho_Chi_Minh', 'Asia/Ho Chi Minh (Vietnam)'),
            ('Asia/Bangkok', 'Asia/Bangkok (Thailand)'),
            ('Asia/Singapore', 'Asia/Singapore'),
            ('Europe/London', 'Europe/London'),
            ('US/Eastern', 'US/Eastern'),
            ('US/Pacific', 'US/Pacific'),
        ]
    
    def action_start_campaign(self):
        """Start the campaign"""
        if self.status != 'draft':
            raise UserError(_('Only draft campaigns can be started'))
        
        # Check if BOM ZNS Simple is available
        if 'bom.zns.template' not in self.env:
            raise UserError(_('BOM ZNS Simple module is required for ZNS functionality. Please install it first.'))
        
        if not self.bom_zns_template_id:
            raise UserError(_('Please select a ZNS template'))
        
        if self.campaign_type != 'birthday' and not self.contact_list_ids:
            raise UserError(_('Please select at least one contact list'))
        
        if self.send_mode == 'immediate':
            self.status = 'running'
            self.started_date = fields.Datetime.now()
            self._execute_campaign()
        elif self.send_mode == 'scheduled':
            if not self.scheduled_date:
                raise UserError(_('Please set a scheduled date'))
            self.status = 'scheduled'
        elif self.send_mode == 'birthday_auto':
            self.status = 'running'
            self.started_date = fields.Datetime.now()
        elif self.send_mode == 'recurring':
            self.status = 'running'
            self.started_date = fields.Datetime.now()
            self.next_run_date = self._calculate_next_run_date()
        
        return True
    
    def action_pause_campaign(self):
        """Pause the campaign"""
        if self.status not in ['running', 'scheduled']:
            raise UserError(_('Only running or scheduled campaigns can be paused'))
        
        self.status = 'paused'
        return True
    
    def action_resume_campaign(self):
        """Resume the campaign"""
        if self.status != 'paused':
            raise UserError(_('Only paused campaigns can be resumed'))
        
        self.status = 'running'
        return True
    
    def action_cancel_campaign(self):
        """Cancel the campaign"""
        if self.status in ['completed', 'cancelled']:
            raise UserError(_('Campaign is already completed or cancelled'))
        
        self.status = 'cancelled'
        return True
    
    def action_test_send(self):
        """Send test message"""
        if self.status != 'draft':
            raise UserError(_('Test send is only available for draft campaigns'))
        
        # Implementation for test send
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': _('Test Send'),
                'message': _('Test message functionality will be implemented'),
                'type': 'info',
            }
        }
    
    def action_view_messages(self):
        """View campaign messages"""
        return {
            'name': _('Campaign Messages'),
            'type': 'ir.actions.act_window',
            'res_model': 'zns.bom.marketing.message',
            'view_mode': 'tree,form',
            'domain': [('campaign_id', '=', self.id)],
            'context': {'default_campaign_id': self.id}
        }
    
    def _execute_campaign(self):
        """Execute the campaign"""
        if self.campaign_type == 'birthday':
            # Birthday campaigns are handled by scheduler
            return
        
        # Get target contacts
        target_contacts = self._get_target_contacts()
        
        # Create messages for each contact
        for contact in target_contacts:
            self._create_campaign_message(contact)
        
        # Update progress
        self._compute_progress()
        
        if self.messages_total > 0:
            _logger.info(f"Campaign '{self.name}' executed: {self.messages_total} messages created")
    
    def _get_target_contacts(self):
        """Get target contacts for campaign"""
        target_contacts = self.env['res.partner']
        
        # Collect contacts from lists
        for contact_list in self.contact_list_ids:
            target_contacts |= contact_list.contact_ids
        
        # Remove excluded contacts
        target_contacts -= self.excluded_contact_ids
        
        # Filter valid contacts
        valid_contacts = self.env['res.partner']
        for contact in target_contacts:
            if self._is_valid_contact(contact):
                valid_contacts |= contact
        
        return valid_contacts
    
    def _is_valid_contact(self, contact):
        """Check if contact is valid for sending"""
        # Check phone number
        phone = contact.mobile or contact.phone
        if not phone:
            return False
        
        # Check opt-out status
        if self.respect_opt_out:
            opt_out = self.env['zns.bom.marketing.opt.out'].search([
                ('contact_id', '=', contact.id),
                ('active', '=', True),
                '|',
                ('global_opt_out', '=', True),
                ('campaign_types', '=', self.campaign_type)
            ], limit=1)
            if opt_out:
                return False
        
        return True
    
    def _create_campaign_message(self, contact):
        """Create a campaign message for contact"""
        phone = contact.mobile or contact.phone
        if not phone:
            return
        
        # Clean phone number
        phone = self._clean_phone_number(phone)
        
        # Build parameters
        params = self._build_message_parameters(contact)
        
        # Get connection - try template's connection or use campaign's connection
        connection_id = False
        if self.bom_zns_template_id:
            try:
                if hasattr(self.bom_zns_template_id, 'connection_id') and self.bom_zns_template_id.connection_id:
                    connection_id = self.bom_zns_template_id.connection_id.id
                elif hasattr(self.bom_zns_template_id, 'zns_connection_id') and self.bom_zns_template_id.zns_connection_id:
                    connection_id = self.bom_zns_template_id.zns_connection_id.id
                elif hasattr(self.bom_zns_template_id, 'bom_connection_id') and self.bom_zns_template_id.bom_connection_id:
                    connection_id = self.bom_zns_template_id.bom_connection_id.id
            except:
                pass
        
        # Fallback to campaign's connection
        if not connection_id and self.bom_zns_connection_id:
            connection_id = self.bom_zns_connection_id.id
        
        # Create message record
        message = self.env['zns.bom.marketing.message'].create({
            'campaign_id': self.id,
            'contact_id': contact.id,
            'phone_number': phone,
            'message_parameters': json.dumps(params) if params else '{}',
            'status': 'queued'
        })
        
        return message
    
    def _build_message_parameters(self, contact):
        """Build message parameters for contact"""
        params = {
            'customer_name': contact.name or '',
            'name': contact.name or '',
            'customer_phone': contact.mobile or contact.phone or '',
            'phone': contact.mobile or contact.phone or '',
            'customer_email': contact.email or '',
            'email': contact.email or '',
            'company_name': contact.company_id.name if contact.company_id else '',
        }
        
        # Add birthday-specific parameters
        if self.campaign_type == 'birthday' and contact.birthday:
            try:
                birth_date = fields.Date.from_string(contact.birthday)
                today = fields.Date.today()
                age = today.year - birth_date.year
                if today.month < birth_date.month or (today.month == birth_date.month and today.day < birth_date.day):
                    age -= 1
                params.update({
                    'birthday_age': str(age) if age > 0 else '',
                    'age': str(age) if age > 0 else '',
                })
            except:
                params.update({
                    'birthday_age': '',
                    'age': '',
                })
        
        return params
    
    def _clean_phone_number(self, phone):
        """Clean phone number format"""
        if not phone:
            return phone
        
        import re
        # Remove spaces, dashes, parentheses
        phone = re.sub(r'[\s\-\(\)]', '', phone)
        
        # Handle Vietnamese phone numbers
        if phone.startswith('0'):
            phone = '+84' + phone[1:]
        elif not phone.startswith('+'):
            phone = '+84' + phone
        
        return phone
    
    def _calculate_next_run_date(self):
        """Calculate next run date for recurring campaign"""
        if not self.recurring_type:
            return False
        
        now = fields.Datetime.now()
        
        if self.recurring_type == 'daily':
            return now + timedelta(days=self.recurring_interval)
        elif self.recurring_type == 'weekly':
            return now + timedelta(weeks=self.recurring_interval)
        elif self.recurring_type == 'monthly':
            # Simple monthly calculation
            return now + timedelta(days=self.recurring_interval * 30)
        
        return now + timedelta(days=1)
    
    @api.constrains('recurring_type', 'recurring_weekday', 'recurring_day_of_month')
    def _check_recurring_settings(self):
        for record in self:
            if record.send_mode == 'recurring':
                if not record.recurring_type:
                    raise ValidationError(_('Recurring type is required for recurring campaigns'))
                
                if record.recurring_type == 'weekly' and not record.recurring_weekday:
                    raise ValidationError(_('Weekday is required for weekly recurring campaigns'))
                
                if record.recurring_type == 'monthly':
                    if not record.recurring_day_of_month or record.recurring_day_of_month < 1 or record.recurring_day_of_month > 31:
                        raise ValidationError(_('Day of month must be between 1 and 31 for monthly recurring campaigns'))
    
    @api.constrains('scheduled_date')
    def _check_scheduled_date(self):
        for record in self:
            if record.send_mode == 'scheduled' and record.scheduled_date:
                if record.scheduled_date <= fields.Datetime.now():
                    raise ValidationError(_('Scheduled date must be in the future'))
    
    @api.constrains('birthday_send_time')
    def _check_birthday_send_time(self):
        for record in self:
            if record.campaign_type == 'birthday':
                if record.birthday_send_time < 0 or record.birthday_send_time >= 24:
                    raise ValidationError(_('Birthday send time must be between 0.0 and 23.99'))