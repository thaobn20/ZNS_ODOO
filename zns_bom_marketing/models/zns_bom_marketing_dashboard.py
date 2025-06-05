# -*- coding: utf-8 -*-

import json
import logging
from datetime import datetime, timedelta
from odoo import models, fields, api, _

_logger = logging.getLogger(__name__)


class ZnsBomMarketingDashboard(models.Model):
    _name = 'zns.bom.marketing.dashboard'
    _description = 'ZNS BOM Marketing Dashboard'

    name = fields.Char('Dashboard Name', default='Marketing Dashboard')
    
    @api.model
    def get_dashboard_data(self):
        """Get all dashboard data"""
        return {
            'campaign_stats': self._get_campaign_statistics(),
            'message_stats': self._get_message_statistics(),
            'contact_stats': self._get_contact_statistics(),
            'opt_out_stats': self._get_opt_out_statistics(),
            'recent_campaigns': self._get_recent_campaigns(),
            'top_performing_campaigns': self._get_top_performing_campaigns(),
            'monthly_trends': self._get_monthly_trends(),
            'birthday_upcoming': self._get_upcoming_birthdays(),
        }
    
    def _get_campaign_statistics(self):
        """Get campaign statistics"""
        Campaign = self.env['zns.bom.marketing.campaign']
        
        total_campaigns = Campaign.search_count([])
        active_campaigns = Campaign.search_count([('status', '=', 'running')])
        scheduled_campaigns = Campaign.search_count([('status', '=', 'scheduled')])
        completed_campaigns = Campaign.search_count([('status', '=', 'completed')])
        draft_campaigns = Campaign.search_count([('status', '=', 'draft')])
        
        # Campaign types
        promotion_campaigns = Campaign.search_count([('campaign_type', '=', 'promotion')])
        birthday_campaigns = Campaign.search_count([('campaign_type', '=', 'birthday')])
        notification_campaigns = Campaign.search_count([('campaign_type', '=', 'notification')])
        recurring_campaigns = Campaign.search_count([('campaign_type', '=', 'recurring')])
        
        return {
            'total': total_campaigns,
            'active': active_campaigns,
            'scheduled': scheduled_campaigns,
            'completed': completed_campaigns,
            'draft': draft_campaigns,
            'types': {
                'promotion': promotion_campaigns,
                'birthday': birthday_campaigns,
                'notification': notification_campaigns,
                'recurring': recurring_campaigns,
            }
        }
    
    def _get_message_statistics(self):
        """Get message statistics"""
        Message = self.env['zns.bom.marketing.message']
        
        # Today's stats
        today = fields.Date.today()
        today_start = datetime.combine(today, datetime.min.time())
        today_end = datetime.combine(today, datetime.max.time())
        
        today_domain = [
            ('create_date', '>=', today_start),
            ('create_date', '<=', today_end)
        ]
        
        today_total = Message.search_count(today_domain)
        today_sent = Message.search_count(today_domain + [('status', 'in', ['sent', 'delivered'])])
        today_failed = Message.search_count(today_domain + [('status', '=', 'failed')])
        
        # Overall stats
        total_messages = Message.search_count([])
        queued_messages = Message.search_count([('status', '=', 'queued')])
        sent_messages = Message.search_count([('status', 'in', ['sent', 'delivered'])])
        failed_messages = Message.search_count([('status', '=', 'failed')])
        
        # Calculate rates
        delivery_rate = (sent_messages / total_messages * 100) if total_messages > 0 else 0
        failure_rate = (failed_messages / total_messages * 100) if total_messages > 0 else 0
        
        return {
            'today': {
                'total': today_total,
                'sent': today_sent,
                'failed': today_failed,
                'success_rate': (today_sent / today_total * 100) if today_total > 0 else 0
            },
            'overall': {
                'total': total_messages,
                'queued': queued_messages,
                'sent': sent_messages,
                'failed': failed_messages,
                'delivery_rate': delivery_rate,
                'failure_rate': failure_rate
            }
        }
    
    def _get_contact_statistics(self):
        """Get contact list statistics"""
        ContactList = self.env['zns.bom.marketing.contact.list']
        Partner = self.env['res.partner']
        
        total_lists = ContactList.search_count([('active', '=', True)])
        static_lists = ContactList.search_count([('list_type', '=', 'static'), ('active', '=', True)])
        dynamic_lists = ContactList.search_count([('list_type', '=', 'dynamic'), ('active', '=', True)])
        birthday_lists = ContactList.search_count([('list_type', '=', 'birthday_auto'), ('active', '=', True)])
        
        # Contact stats
        total_contacts = Partner.search_count([])
        contacts_with_phone = Partner.search_count(['|', ('mobile', '!=', False), ('phone', '!=', False)])
        contacts_with_birthday = Partner.search_count([('birthday', '!=', False)])
        
        # Health score average
        lists = ContactList.search([('active', '=', True)])
        avg_health_score = sum(lists.mapped('health_score')) / len(lists) if lists else 0
        
        return {
            'lists': {
                'total': total_lists,
                'static': static_lists,
                'dynamic': dynamic_lists,
                'birthday': birthday_lists,
                'avg_health_score': avg_health_score
            },
            'contacts': {
                'total': total_contacts,
                'with_phone': contacts_with_phone,
                'with_birthday': contacts_with_birthday,
                'phone_coverage': (contacts_with_phone / total_contacts * 100) if total_contacts > 0 else 0
            }
        }
    
    def _get_opt_out_statistics(self):
        """Get opt-out statistics"""
        return self.env['zns.bom.marketing.opt.out'].get_opt_out_statistics()
    
    def _get_recent_campaigns(self, limit=5):
        """Get recent campaigns"""
        campaigns = self.env['zns.bom.marketing.campaign'].search([
            ('status', '!=', 'draft')
        ], order='write_date desc', limit=limit)
        
        result = []
        for campaign in campaigns:
            result.append({
                'id': campaign.id,
                'name': campaign.name,
                'type': campaign.campaign_type,
                'status': campaign.status,
                'progress': campaign.progress_percentage,
                'messages_sent': campaign.messages_sent,
                'total_recipients': campaign.total_recipients,
                'delivery_rate': campaign.delivery_rate,
                'last_activity': campaign.write_date.strftime('%Y-%m-%d %H:%M') if campaign.write_date else '',
            })
        
        return result
    
    def _get_top_performing_campaigns(self, limit=5):
        """Get top performing campaigns by delivery rate"""
        campaigns = self.env['zns.bom.marketing.campaign'].search([
            ('status', 'in', ['running', 'completed']),
            ('messages_sent', '>', 0)
        ], order='delivery_rate desc', limit=limit)
        
        result = []
        for campaign in campaigns:
            result.append({
                'id': campaign.id,
                'name': campaign.name,
                'type': campaign.campaign_type,
                'delivery_rate': campaign.delivery_rate,
                'messages_sent': campaign.messages_sent,
                'messages_delivered': campaign.messages_delivered,
                'total_cost': campaign.total_cost,
            })
        
        return result
    
    def _get_monthly_trends(self, months=6):
        """Get monthly message trends"""
        trends = []
        
        for i in range(months):
            # Calculate month start and end
            target_date = fields.Date.today().replace(day=1) - timedelta(days=i*30)
            month_start = target_date.replace(day=1)
            
            # Calculate next month start for range
            if month_start.month == 12:
                next_month = month_start.replace(year=month_start.year + 1, month=1)
            else:
                next_month = month_start.replace(month=month_start.month + 1)
            
            # Get message stats for this month
            month_messages = self.env['zns.bom.marketing.message'].search_count([
                ('create_date', '>=', month_start),
                ('create_date', '<', next_month)
            ])
            
            month_sent = self.env['zns.bom.marketing.message'].search_count([
                ('create_date', '>=', month_start),
                ('create_date', '<', next_month),
                ('status', 'in', ['sent', 'delivered'])
            ])
            
            trends.append({
                'month': month_start.strftime('%Y-%m'),
                'month_name': month_start.strftime('%B %Y'),
                'total_messages': month_messages,
                'sent_messages': month_sent,
                'success_rate': (month_sent / month_messages * 100) if month_messages > 0 else 0
            })
        
        return list(reversed(trends))  # Most recent first
    
    def _get_upcoming_birthdays(self, days_ahead=7):
        """Get upcoming birthdays in next N days"""
        upcoming = []
        
        for i in range(days_ahead):
            target_date = fields.Date.today() + timedelta(days=i)
            month_day = target_date.strftime('%m-%d')
            
            # Find contacts with birthday on this date
            contacts = self.env['res.partner'].search([
                ('birthday', '!=', False),
                ('birthday', 'like', f'%-{month_day}')
            ])
            
            if contacts:
                upcoming.append({
                    'date': target_date.strftime('%Y-%m-%d'),
                    'date_display': target_date.strftime('%B %d'),
                    'days_from_now': i,
                    'contacts': len(contacts),
                    'contact_names': contacts[:3].mapped('name')  # Show first 3 names
                })
        
        return upcoming
    
    @api.model
    def get_campaign_performance_chart(self, campaign_id=None, days=30):
        """Get campaign performance chart data"""
        domain = []
        if campaign_id:
            domain.append(('campaign_id', '=', campaign_id))
        
        # Get data for last N days
        chart_data = []
        for i in range(days):
            date = fields.Date.today() - timedelta(days=i)
            date_start = datetime.combine(date, datetime.min.time())
            date_end = datetime.combine(date, datetime.max.time())
            
            day_domain = domain + [
                ('create_date', '>=', date_start),
                ('create_date', '<=', date_end)
            ]
            
            total = self.env['zns.bom.marketing.message'].search_count(day_domain)
            sent = self.env['zns.bom.marketing.message'].search_count(
                day_domain + [('status', 'in', ['sent', 'delivered'])]
            )
            failed = self.env['zns.bom.marketing.message'].search_count(
                day_domain + [('status', '=', 'failed')]
            )
            
            chart_data.append({
                'date': date.strftime('%Y-%m-%d'),
                'total': total,
                'sent': sent,
                'failed': failed,
                'success_rate': (sent / total * 100) if total > 0 else 0
            })
        
        return list(reversed(chart_data))  # Chronological order
    
    @api.model
    def get_real_time_stats(self):
        """Get real-time dashboard stats"""
        # Messages in last hour
        one_hour_ago = fields.Datetime.now() - timedelta(hours=1)
        recent_messages = self.env['zns.bom.marketing.message'].search_count([
            ('create_date', '>=', one_hour_ago)
        ])
        
        # Currently queued
        queued_count = self.env['zns.bom.marketing.message'].search_count([
            ('status', '=', 'queued')
        ])
        
        # Active campaigns
        active_campaigns = self.env['zns.bom.marketing.campaign'].search_count([
            ('status', '=', 'running')
        ])
        
        # Next scheduled campaign
        next_campaign = self.env['zns.bom.marketing.campaign'].search([
            ('status', '=', 'scheduled'),
            ('scheduled_date', '>', fields.Datetime.now())
        ], order='scheduled_date asc', limit=1)
        
        next_campaign_info = None
        if next_campaign:
            next_campaign_info = {
                'name': next_campaign.name,
                'scheduled_date': next_campaign.scheduled_date.strftime('%Y-%m-%d %H:%M'),
                'recipients': next_campaign.total_recipients
            }
        
        return {
            'recent_messages': recent_messages,
            'queued_messages': queued_count,
            'active_campaigns': active_campaigns,
            'next_campaign': next_campaign_info,
            'timestamp': fields.Datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        }