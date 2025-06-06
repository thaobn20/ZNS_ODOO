# -*- coding: utf-8 -*-

import json
import logging
from datetime import datetime, timedelta
from odoo import models, fields, api, _

_logger = logging.getLogger(__name__)


class ZnsBomMarketingMessage(models.Model):
    _name = 'zns.bom.marketing.message'
    _description = 'ZNS BOM Marketing Campaign Message'
    _order = 'create_date desc'
    _rec_name = 'display_name'

    # Relations
    campaign_id = fields.Many2one('zns.bom.marketing.campaign', required=True, ondelete='cascade')
    bom_zns_message_id = fields.Many2one('bom.zns.message', string='BOM ZNS Message',
                                        help='Reference to BOM ZNS Simple message record')
    contact_id = fields.Many2one('res.partner', required=True)
    
    # Message Info
    phone_number = fields.Char('Phone Number', required=True)
    message_parameters = fields.Text('Parameters JSON')
    display_name = fields.Char('Display Name', compute='_compute_display_name')
    
    # Status Tracking
    status = fields.Selection([
        ('queued', 'Queued'),
        ('sending', 'Sending'),
        ('sent', 'Sent'),
        ('delivered', 'Delivered'),
        ('failed', 'Failed'),
        ('retry', 'Retry Pending'),
        ('skipped', 'Skipped (Opt-out)')
    ], default='queued', required=True, string='Status')
    
    status_color = fields.Char('Status Color', compute='_compute_status_color')
    
    # Timing
    queued_date = fields.Datetime('Queued', default=fields.Datetime.now)
    sent_date = fields.Datetime('Sent')
    delivered_date = fields.Datetime('Delivered')
    
    # Error Handling
    error_message = fields.Text('Error Message')
    retry_count = fields.Integer('Retry Count', default=0)
    next_retry_date = fields.Datetime('Next Retry')
    
    # Analytics
    message_cost = fields.Float('Message Cost', default=0.0)
    send_duration = fields.Float('Send Duration (seconds)')
    
    # Additional Fields
    template_name = fields.Char('Template', compute='_compute_related_fields', readonly=True)
    campaign_name = fields.Char('Campaign', compute='_compute_related_fields', readonly=True)
    contact_name = fields.Char('Contact', compute='_compute_related_fields', readonly=True)
    
    @api.depends('campaign_id', 'campaign_id.bom_zns_template_id', 'campaign_id.name', 'contact_id', 'contact_id.name')
    def _compute_related_fields(self):
        for record in self:
            # Template name
            if record.campaign_id and record.campaign_id.bom_zns_template_id:
                try:
                    # Try to get template name if model exists
                    record.template_name = record.campaign_id.bom_zns_template_id.name or 'Unknown Template'
                except:
                    record.template_name = 'Template'
            else:
                record.template_name = ''
            
            # Campaign name
            if record.campaign_id:
                record.campaign_name = record.campaign_id.name or ''
            else:
                record.campaign_name = ''
            
            # Contact name
            if record.contact_id:
                record.contact_name = record.contact_id.name or ''
            else:
                record.contact_name = ''
    
    @api.depends('contact_id', 'phone_number', 'status')
    def _compute_display_name(self):
        for record in self:
            contact_name = record.contact_id.name or 'Unknown'
            phone = record.phone_number or 'No Phone'
            record.display_name = f"{contact_name} - {phone} ({record.status})"
    
    @api.depends('status')
    def _compute_status_color(self):
        status_colors = {
            'queued': '#f0ad4e',      # orange
            'sending': '#5bc0de',     # light blue
            'sent': '#5cb85c',        # green
            'delivered': '#449d44',   # dark green
            'failed': '#d9534f',      # red
            'retry': '#f0ad4e',       # orange
            'skipped': '#6c757d'      # gray
        }
        
        for record in self:
            record.status_color = status_colors.get(record.status, '#6c757d')
    
    def action_retry_message(self):
        """Retry failed message"""
        if self.status != 'failed':
            return
        
        self.status = 'retry'
        self.retry_count += 1
        self.next_retry_date = fields.Datetime.now() + timedelta(minutes=5)
        self.error_message = ''
        
        # Queue for retry
        self.env['zns.bom.marketing.scheduler']._process_retry_messages()
        
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': _('Message Queued for Retry'),
                'message': _('Message will be retried in 5 minutes'),
                'type': 'info',
            }
        }
    
    def action_view_bom_message(self):
        """View related BOM ZNS message"""
        if not self.bom_zns_message_id:
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': _('No BOM ZNS Message'),
                    'message': _('No BOM ZNS message is linked to this campaign message'),
                    'type': 'warning',
                }
            }
        
        return {
            'name': _('BOM ZNS Message'),
            'type': 'ir.actions.act_window',
            'res_model': 'bom.zns.message',
            'res_id': self.bom_zns_message_id.id,
            'view_mode': 'form',
            'target': 'current',
        }
    
    def action_view_contact(self):
        """View contact"""
        return {
            'name': _('Contact'),
            'type': 'ir.actions.act_window',
            'res_model': 'res.partner',
            'res_id': self.contact_id.id,
            'view_mode': 'form',
            'target': 'current',
        }
    
    def get_formatted_parameters(self):
        """Get formatted parameters for display"""
        if not self.message_parameters:
            return {}
        
        try:
            return json.loads(self.message_parameters)
        except:
            return {}
    
    def get_parameter_display(self):
        """Get parameter display string"""
        params = self.get_formatted_parameters()
        if not params:
            return 'No parameters'
        
        display_parts = []
        for key, value in params.items():
            display_parts.append(f"{key}: {value}")
        
        return ', '.join(display_parts)
    
    @api.model
    def get_status_statistics(self, domain=None):
        """Get status statistics for dashboard"""
        if domain is None:
            domain = []
        
        messages = self.search(domain)
        total = len(messages)
        
        if total == 0:
            return {
                'total': 0,
                'queued': 0,
                'sent': 0,
                'delivered': 0,
                'failed': 0,
                'delivery_rate': 0.0,
                'failure_rate': 0.0
            }
        
        status_counts = {}
        for status in ['queued', 'sending', 'sent', 'delivered', 'failed', 'retry', 'skipped']:
            status_counts[status] = len(messages.filtered(lambda m: m.status == status))
        
        delivered = status_counts['delivered']
        failed = status_counts['failed']
        
        return {
            'total': total,
            'queued': status_counts['queued'] + status_counts['retry'],
            'sending': status_counts['sending'],
            'sent': status_counts['sent'],
            'delivered': delivered,
            'failed': failed,
            'skipped': status_counts['skipped'],
            'delivery_rate': (delivered / total * 100) if total > 0 else 0.0,
            'failure_rate': (failed / total * 100) if total > 0 else 0.0
        }