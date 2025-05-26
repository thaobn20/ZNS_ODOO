# -*- coding: utf-8 -*-

import json
import logging
from datetime import datetime, timedelta
from dateutil.relativedelta import relativedelta
from odoo import models, fields, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class ZnsDashboard(models.Model):
    _name = 'zns.dashboard'
    _description = 'ZNS Dashboard'
    _auto = False

    def get_dashboard_data(self, period='month'):
        """Get comprehensive dashboard data"""
        domain = []
        
        # Date filtering based on period
        today = datetime.now().date()
        if period == 'today':
            domain.append(('create_date', '>=', today))
        elif period == 'week':
            week_start = today - timedelta(days=today.weekday())
            domain.append(('create_date', '>=', week_start))
        elif period == 'month':
            month_start = today.replace(day=1)
            domain.append(('create_date', '>=', month_start))
        elif period == 'year':
            year_start = today.replace(month=1, day=1)
            domain.append(('create_date', '>=', year_start))
        
        # Get basic statistics
        total_messages = self.env['zns.message'].search_count(domain)
        sent_messages = self.env['zns.message'].search_count(domain + [('status', '=', 'sent')])
        failed_messages = self.env['zns.message'].search_count(domain + [('status', '=', 'failed')])
        draft_messages = self.env['zns.message'].search_count(domain + [('status', '=', 'draft')])
        
        # Success rate
        success_rate = (sent_messages / total_messages * 100) if total_messages > 0 else 0
        
        # Get template usage statistics
        template_stats = self._get_template_statistics(domain)
        
        # Get daily/weekly/monthly trends
        trend_data = self._get_trend_data(period)
        
        # Get status distribution
        status_distribution = [
            {'name': 'Sent', 'count': sent_messages, 'color': '#28a745'},
            {'name': 'Failed', 'count': failed_messages, 'color': '#dc3545'},
            {'name': 'Draft', 'count': draft_messages, 'color': '#6c757d'},
        ]
        
        # Get recent messages
        recent_messages = self._get_recent_messages(limit=20)
        
        # Get source statistics (Contact/Sales/Invoice)
        source_stats = self._get_source_statistics(domain)
        
        # Get connection usage
        connection_stats = self._get_connection_statistics(domain)
        
        return {
            'summary': {
                'total_messages': total_messages,
                'sent_messages': sent_messages,
                'failed_messages': failed_messages,
                'draft_messages': draft_messages,
                'success_rate': round(success_rate, 2),
            },
            'template_stats': template_stats,
            'trend_data': trend_data,
            'status_distribution': status_distribution,
            'recent_messages': recent_messages,
            'source_stats': source_stats,
            'connection_stats': connection_stats,
        }
    
    def _get_template_statistics(self, domain):
        """Get template usage statistics"""
        query = """
            SELECT t.name, t.template_type, COUNT(m.id) as usage_count,
                   SUM(CASE WHEN m.status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                   SUM(CASE WHEN m.status = 'failed' THEN 1 ELSE 0 END) as failed_count
            FROM zns_message m
            JOIN zns_template t ON m.template_id = t.id
            WHERE m.create_date >= %s
            GROUP BY t.id, t.name, t.template_type
            ORDER BY usage_count DESC
        """
        
        # Extract date from domain
        date_filter = datetime.now().date().replace(day=1)  # Default to current month
        for condition in domain:
            if condition[0] == 'create_date' and condition[1] == '>=':
                date_filter = condition[2]
                break
        
        self.env.cr.execute(query, (date_filter,))
        results = self.env.cr.dictfetchall()
        
        return [{
            'name': row['name'],
            'type': row['template_type'],
            'total': row['usage_count'],
            'sent': row['sent_count'],
            'failed': row['failed_count'],
            'success_rate': round((row['sent_count'] / row['usage_count'] * 100) if row['usage_count'] > 0 else 0, 2)
        } for row in results]
    
    def _get_trend_data(self, period):
        """Get trend data for charts"""
        if period == 'today':
            return self._get_hourly_trend()
        elif period == 'week':
            return self._get_daily_trend(7)
        elif period == 'month':
            return self._get_daily_trend(30)
        else:  # year
            return self._get_monthly_trend()
    
    def _get_hourly_trend(self):
        """Get hourly trend for today"""
        today = datetime.now().date()
        query = """
            SELECT EXTRACT(hour FROM create_date) as hour,
                   COUNT(*) as total,
                   SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                   SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM zns_message
            WHERE DATE(create_date) = %s
            GROUP BY EXTRACT(hour FROM create_date)
            ORDER BY hour
        """
        self.env.cr.execute(query, (today,))
        results = self.env.cr.dictfetchall()
        
        # Fill missing hours with 0
        trend_data = []
        for hour in range(24):
            found = next((r for r in results if int(r['hour']) == hour), None)
            if found:
                trend_data.append({
                    'label': f"{hour:02d}:00",
                    'total': found['total'],
                    'sent': found['sent'],
                    'failed': found['failed']
                })
            else:
                trend_data.append({
                    'label': f"{hour:02d}:00",
                    'total': 0,
                    'sent': 0,
                    'failed': 0
                })
        
        return trend_data
    
    def _get_daily_trend(self, days):
        """Get daily trend for specified number of days"""
        end_date = datetime.now().date()
        start_date = end_date - timedelta(days=days-1)
        
        query = """
            SELECT DATE(create_date) as date,
                   COUNT(*) as total,
                   SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                   SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM zns_message
            WHERE DATE(create_date) BETWEEN %s AND %s
            GROUP BY DATE(create_date)
            ORDER BY date
        """
        self.env.cr.execute(query, (start_date, end_date))
        results = self.env.cr.dictfetchall()
        
        # Fill missing dates with 0
        trend_data = []
        current_date = start_date
        while current_date <= end_date:
            found = next((r for r in results if r['date'] == current_date), None)
            if found:
                trend_data.append({
                    'label': current_date.strftime('%m/%d'),
                    'total': found['total'],
                    'sent': found['sent'],
                    'failed': found['failed']
                })
            else:
                trend_data.append({
                    'label': current_date.strftime('%m/%d'),
                    'total': 0,
                    'sent': 0,
                    'failed': 0
                })
            current_date += timedelta(days=1)
        
        return trend_data
    
    def _get_monthly_trend(self):
        """Get monthly trend for current year"""
        current_year = datetime.now().year
        query = """
            SELECT EXTRACT(month FROM create_date) as month,
                   COUNT(*) as total,
                   SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                   SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM zns_message
            WHERE EXTRACT(year FROM create_date) = %s
            GROUP BY EXTRACT(month FROM create_date)
            ORDER BY month
        """
        self.env.cr.execute(query, (current_year,))
        results = self.env.cr.dictfetchall()
        
        # Fill missing months with 0
        trend_data = []
        for month in range(1, 13):
            found = next((r for r in results if int(r['month']) == month), None)
            month_name = datetime(current_year, month, 1).strftime('%b')
            if found:
                trend_data.append({
                    'label': month_name,
                    'total': found['total'],
                    'sent': found['sent'],
                    'failed': found['failed']
                })
            else:
                trend_data.append({
                    'label': month_name,
                    'total': 0,
                    'sent': 0,
                    'failed': 0
                })
        
        return trend_data
    
    def _get_recent_messages(self, limit=20):
        """Get recent messages for activity feed"""
        messages = self.env['zns.message'].search([
            ('status', '!=', 'draft')
        ], order='create_date desc', limit=limit)
        
        recent_data = []
        for msg in messages:
            recent_data.append({
                'id': msg.id,
                'template_name': msg.template_id.name,
                'phone': msg.phone,
                'partner_name': msg.partner_id.name if msg.partner_id else '',
                'status': msg.status,
                'create_date': msg.create_date.strftime('%Y-%m-%d %H:%M:%S'),
                'sent_date': msg.sent_date.strftime('%Y-%m-%d %H:%M:%S') if msg.sent_date else '',
                'error_message': msg.error_message[:100] + '...' if msg.error_message and len(msg.error_message) > 100 else msg.error_message or '',
                'source': self._get_message_source(msg),
            })
        
        return recent_data
    
    def _get_message_source(self, message):
        """Determine message source"""
        if message.sale_order_id:
            return f"Sales Order: {message.sale_order_id.name}"
        elif message.invoice_id:
            return f"Invoice: {message.invoice_id.name}"
        elif message.partner_id:
            return f"Contact: {message.partner_id.name}"
        else:
            return "Manual"
    
    def _get_source_statistics(self, domain):
        """Get statistics by source (Contact/Sales/Invoice)"""
        query = """
            SELECT 
                CASE 
                    WHEN sale_order_id IS NOT NULL THEN 'Sales Order'
                    WHEN invoice_id IS NOT NULL THEN 'Invoice'
                    WHEN partner_id IS NOT NULL THEN 'Contact'
                    ELSE 'Manual'
                END as source,
                COUNT(*) as count,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent
            FROM zns_message
            WHERE create_date >= %s
            GROUP BY source
            ORDER BY count DESC
        """
        
        # Extract date from domain
        date_filter = datetime.now().date().replace(day=1)
        for condition in domain:
            if condition[0] == 'create_date' and condition[1] == '>=':
                date_filter = condition[2]
                break
        
        self.env.cr.execute(query, (date_filter,))
        results = self.env.cr.dictfetchall()
        
        return [{
            'source': row['source'],
            'total': row['count'],
            'sent': row['sent'],
            'success_rate': round((row['sent'] / row['count'] * 100) if row['count'] > 0 else 0, 2)
        } for row in results]
    
    def _get_connection_statistics(self, domain):
        """Get statistics by connection"""
        query = """
            SELECT c.name, COUNT(m.id) as count,
                   SUM(CASE WHEN m.status = 'sent' THEN 1 ELSE 0 END) as sent
            FROM zns_message m
            JOIN zns_connection c ON m.connection_id = c.id
            WHERE m.create_date >= %s
            GROUP BY c.id, c.name
            ORDER BY count DESC
        """
        
        # Extract date from domain
        date_filter = datetime.now().date().replace(day=1)
        for condition in domain:
            if condition[0] == 'create_date' and condition[1] == '>=':
                date_filter = condition[2]
                break
        
        self.env.cr.execute(query, (date_filter,))
        results = self.env.cr.dictfetchall()
        
        return [{
            'connection': row['name'],
            'total': row['count'],
            'sent': row['sent'],
            'success_rate': round((row['sent'] / row['count'] * 100) if row['count'] > 0 else 0, 2)
        } for row in results]


class ZnsReportWizard(models.TransientModel):
    _name = 'zns.report.wizard'
    _description = 'ZNS Report Wizard'

    date_from = fields.Date('From Date', required=True, default=fields.Date.context_today)
    date_to = fields.Date('To Date', required=True, default=fields.Date.context_today)
    template_ids = fields.Many2many('zns.template', string='Templates')
    connection_ids = fields.Many2many('zns.connection', string='Connections')
    status = fields.Selection([
        ('all', 'All'),
        ('sent', 'Sent'),
        ('failed', 'Failed'),
        ('draft', 'Draft')
    ], string='Status', default='all')
    report_type = fields.Selection([
        ('summary', 'Summary Report'),
        ('detailed', 'Detailed Report'),
        ('template_analysis', 'Template Analysis'),
        ('trend_analysis', 'Trend Analysis')
    ], string='Report Type', default='summary')

    def generate_report(self):
        """Generate the requested report"""
        domain = [
            ('create_date', '>=', self.date_from),
            ('create_date', '<=', self.date_to)
        ]
        
        if self.template_ids:
            domain.append(('template_id', 'in', self.template_ids.ids))
        
        if self.connection_ids:
            domain.append(('connection_id', 'in', self.connection_ids.ids))
        
        if self.status != 'all':
            domain.append(('status', '=', self.status))
        
        if self.report_type == 'summary':
            return self._generate_summary_report(domain)
        elif self.report_type == 'detailed':
            return self._generate_detailed_report(domain)
        elif self.report_type == 'template_analysis':
            return self._generate_template_analysis(domain)
        else:  # trend_analysis
            return self._generate_trend_analysis(domain)
    
    def _generate_summary_report(self, domain):
        """Generate summary report"""
        messages = self.env['zns.message'].search(domain)
        
        return {
            'type': 'ir.actions.act_window',
            'name': 'ZNS Summary Report',
            'res_model': 'zns.message',
            'view_mode': 'tree,form',
            'domain': domain,
            'context': {'group_by': ['status', 'template_id']}
        }
    
    def _generate_detailed_report(self, domain):
        """Generate detailed report"""
        return {
            'type': 'ir.actions.act_window',
            'name': 'ZNS Detailed Report',
            'res_model': 'zns.message',
            'view_mode': 'tree,form',
            'domain': domain,
        }
    
    def _generate_template_analysis(self, domain):
        """Generate template analysis report"""
        return {
            'type': 'ir.actions.act_window',
            'name': 'ZNS Template Analysis',
            'res_model': 'zns.message',
            'view_mode': 'pivot,graph',
            'domain': domain,
            'context': {
                'pivot_measures': ['__count__'],
                'pivot_column_groupby': ['status'],
                'pivot_row_groupby': ['template_id'],
            }
        }
    
    def _generate_trend_analysis(self, domain):
        """Generate trend analysis report"""
        return {
            'type': 'ir.actions.act_window',
            'name': 'ZNS Trend Analysis',
            'res_model': 'zns.message',
            'view_mode': 'graph',
            'domain': domain,
            'context': {
                'graph_mode': 'line',
                'graph_measure': '__count__',
                'graph_groupbys': ['create_date:day'],
            }
        }