# -*- coding: utf-8 -*-

import json
import logging
from datetime import datetime, timedelta
from odoo import models, fields, api, tools, _
from odoo.tools import float_round

_logger = logging.getLogger(__name__)


class ZnsBomMarketingAnalytics(models.Model):
    _name = 'zns.bom.marketing.analytics'
    _description = 'ZNS BOM Marketing Analytics'
    _auto = False  # This is a reporting model, no table creation
    _rec_name = 'campaign_id'

    # Campaign Information
    campaign_id = fields.Many2one('zns.bom.marketing.campaign', string='Campaign')
    campaign_name = fields.Char('Campaign Name')
    campaign_type = fields.Selection([
        ('promotion', 'Promotion'),
        ('birthday', 'Birthday'),
        ('notification', 'Notification'),
        ('recurring', 'Recurring'),
        ('one_time', 'One Time')
    ], string='Campaign Type')
    
    # Date Fields for Grouping
    date = fields.Date('Date')
    month = fields.Char('Month')
    year = fields.Char('Year')
    week = fields.Char('Week')
    
    # Message Statistics
    total_messages = fields.Integer('Total Messages')
    messages_sent = fields.Integer('Messages Sent')
    messages_delivered = fields.Integer('Messages Delivered')
    messages_failed = fields.Integer('Messages Failed')
    messages_queued = fields.Integer('Messages Queued')
    messages_skipped = fields.Integer('Messages Skipped')
    
    # Calculated Rates
    delivery_rate = fields.Float('Delivery Rate %', group_operator='avg')
    failure_rate = fields.Float('Failure Rate %', group_operator='avg')
    success_rate = fields.Float('Success Rate %', group_operator='avg')
    
    # Cost Analysis
    total_cost = fields.Float('Total Cost', group_operator='sum')
    cost_per_message = fields.Float('Cost per Message', group_operator='avg')
    cost_per_delivery = fields.Float('Cost per Delivery', group_operator='avg')
    
    # Performance Metrics
    avg_send_duration = fields.Float('Avg Send Duration (seconds)', group_operator='avg')
    retry_count_total = fields.Integer('Total Retries')
    
    # Contact Analysis
    unique_contacts = fields.Integer('Unique Contacts')
    repeat_contacts = fields.Integer('Repeat Contacts')
    
    # Template Information
    template_id = fields.Many2one('bom.zns.template', string='Template')
    template_name = fields.Char('Template Name')
    connection_id = fields.Many2one('bom.zns.connection', string='Connection')
    
    def init(self):
        """Initialize the view"""
        tools.drop_view_if_exists(self.env.cr, self._table)
        self.env.cr.execute("""
            CREATE OR REPLACE VIEW %s AS (
                SELECT 
                    ROW_NUMBER() OVER() AS id,
                    c.id AS campaign_id,
                    c.name AS campaign_name,
                    c.campaign_type,
                    DATE(m.create_date) AS date,
                    TO_CHAR(m.create_date, 'YYYY-MM') AS month,
                    TO_CHAR(m.create_date, 'YYYY') AS year,
                    TO_CHAR(m.create_date, 'YYYY-"W"WW') AS week,
                    
                    -- Message counts
                    COUNT(m.id) AS total_messages,
                    COUNT(CASE WHEN m.status IN ('sent', 'delivered') THEN 1 END) AS messages_sent,
                    COUNT(CASE WHEN m.status = 'delivered' THEN 1 END) AS messages_delivered,
                    COUNT(CASE WHEN m.status = 'failed' THEN 1 END) AS messages_failed,
                    COUNT(CASE WHEN m.status = 'queued' THEN 1 END) AS messages_queued,
                    COUNT(CASE WHEN m.status = 'skipped' THEN 1 END) AS messages_skipped,
                    
                    -- Calculated rates
                    CASE 
                        WHEN COUNT(m.id) > 0 THEN 
                            ROUND((COUNT(CASE WHEN m.status = 'delivered' THEN 1 END)::FLOAT / COUNT(m.id) * 100), 2)
                        ELSE 0 
                    END AS delivery_rate,
                    
                    CASE 
                        WHEN COUNT(m.id) > 0 THEN 
                            ROUND((COUNT(CASE WHEN m.status = 'failed' THEN 1 END)::FLOAT / COUNT(m.id) * 100), 2)
                        ELSE 0 
                    END AS failure_rate,
                    
                    CASE 
                        WHEN COUNT(m.id) > 0 THEN 
                            ROUND((COUNT(CASE WHEN m.status IN ('sent', 'delivered') THEN 1 END)::FLOAT / COUNT(m.id) * 100), 2)
                        ELSE 0 
                    END AS success_rate,
                    
                    -- Cost analysis
                    COALESCE(SUM(m.message_cost), 0) AS total_cost,
                    CASE 
                        WHEN COUNT(m.id) > 0 THEN 
                            ROUND(COALESCE(SUM(m.message_cost), 0) / COUNT(m.id), 4)
                        ELSE 0 
                    END AS cost_per_message,
                    
                    CASE 
                        WHEN COUNT(CASE WHEN m.status = 'delivered' THEN 1 END) > 0 THEN 
                            ROUND(COALESCE(SUM(m.message_cost), 0) / COUNT(CASE WHEN m.status = 'delivered' THEN 1 END), 4)
                        ELSE 0 
                    END AS cost_per_delivery,
                    
                    -- Performance metrics
                    COALESCE(AVG(m.send_duration), 0) AS avg_send_duration,
                    COALESCE(SUM(m.retry_count), 0) AS retry_count_total,
                    
                    -- Contact analysis
                    COUNT(DISTINCT m.contact_id) AS unique_contacts,
                    COUNT(m.id) - COUNT(DISTINCT m.contact_id) AS repeat_contacts,
                    
                    -- Template info
                    c.bom_zns_template_id AS template_id,
                    t.name AS template_name,
                    c.bom_zns_connection_id AS connection_id
                    
                FROM zns_bom_marketing_campaign c
                LEFT JOIN zns_bom_marketing_message m ON c.id = m.campaign_id
                LEFT JOIN bom_zns_template t ON c.bom_zns_template_id = t.id
                WHERE m.id IS NOT NULL
                GROUP BY 
                    c.id, c.name, c.campaign_type, c.bom_zns_template_id, 
                    c.bom_zns_connection_id, t.name, DATE(m.create_date),
                    TO_CHAR(m.create_date, 'YYYY-MM'),
                    TO_CHAR(m.create_date, 'YYYY'),
                    TO_CHAR(m.create_date, 'YYYY-"W"WW')
            )
        """ % self._table)

    @api.model
    def get_campaign_performance_data(self, campaign_ids=None, date_from=None, date_to=None):
        """Get campaign performance data for charts"""
        domain = []
        
        if campaign_ids:
            domain.append(('campaign_id', 'in', campaign_ids))
        if date_from:
            domain.append(('date', '>=', date_from))
        if date_to:
            domain.append(('date', '<=', date_to))
        
        analytics = self.search(domain, order='date asc')
        
        # Prepare data for charts
        chart_data = {
            'dates': [],
            'total_messages': [],
            'delivery_rates': [],
            'failure_rates': [],
            'costs': []
        }
        
        for record in analytics:
            chart_data['dates'].append(record.date.strftime('%Y-%m-%d'))
            chart_data['total_messages'].append(record.total_messages)
            chart_data['delivery_rates'].append(record.delivery_rate)
            chart_data['failure_rates'].append(record.failure_rate)
            chart_data['costs'].append(record.total_cost)
        
        return chart_data

    @api.model
    def get_campaign_comparison(self, campaign_ids, metrics=['delivery_rate', 'total_cost']):
        """Compare multiple campaigns"""
        if not campaign_ids:
            return {}
        
        campaigns_data = {}
        
        for campaign_id in campaign_ids:
            analytics = self.search([('campaign_id', '=', campaign_id)])
            
            if analytics:
                # Aggregate data for this campaign
                total_messages = sum(analytics.mapped('total_messages'))
                total_delivered = sum(analytics.mapped('messages_delivered'))
                total_failed = sum(analytics.mapped('messages_failed'))
                total_cost = sum(analytics.mapped('total_cost'))
                
                delivery_rate = (total_delivered / total_messages * 100) if total_messages > 0 else 0
                failure_rate = (total_failed / total_messages * 100) if total_messages > 0 else 0
                cost_per_message = total_cost / total_messages if total_messages > 0 else 0
                
                campaign_name = analytics[0].campaign_name
                
                campaigns_data[campaign_id] = {
                    'name': campaign_name,
                    'total_messages': total_messages,
                    'delivery_rate': float_round(delivery_rate, 2),
                    'failure_rate': float_round(failure_rate, 2),
                    'total_cost': total_cost,
                    'cost_per_message': float_round(cost_per_message, 4),
                    'avg_send_duration': sum(analytics.mapped('avg_send_duration')) / len(analytics) if analytics else 0
                }
        
        return campaigns_data

    @api.model
    def get_monthly_trends(self, months=12):
        """Get monthly performance trends"""
        date_from = fields.Date.today().replace(day=1) - timedelta(days=months*30)
        
        analytics = self.search([
            ('date', '>=', date_from)
        ], order='month asc')
        
        monthly_data = {}
        for record in analytics:
            month = record.month
            if month not in monthly_data:
                monthly_data[month] = {
                    'month': month,
                    'total_messages': 0,
                    'messages_delivered': 0,
                    'messages_failed': 0,
                    'total_cost': 0,
                    'campaigns_count': set()
                }
            
            monthly_data[month]['total_messages'] += record.total_messages
            monthly_data[month]['messages_delivered'] += record.messages_delivered
            monthly_data[month]['messages_failed'] += record.messages_failed
            monthly_data[month]['total_cost'] += record.total_cost
            monthly_data[month]['campaigns_count'].add(record.campaign_id)
        
        # Convert to list and calculate rates
        result = []
        for month_data in monthly_data.values():
            total = month_data['total_messages']
            delivered = month_data['messages_delivered']
            failed = month_data['messages_failed']
            
            month_data['delivery_rate'] = (delivered / total * 100) if total > 0 else 0
            month_data['failure_rate'] = (failed / total * 100) if total > 0 else 0
            month_data['campaigns_count'] = len(month_data['campaigns_count'])
            
            result.append(month_data)
        
        return sorted(result, key=lambda x: x['month'])

    @api.model
    def get_campaign_type_analysis(self):
        """Analyze performance by campaign type"""
        analytics = self.search([])
        
        type_data = {}
        for record in analytics:
            camp_type = record.campaign_type
            if camp_type not in type_data:
                type_data[camp_type] = {
                    'type': camp_type,
                    'campaigns_count': set(),
                    'total_messages': 0,
                    'messages_delivered': 0,
                    'messages_failed': 0,
                    'total_cost': 0
                }
            
            type_data[camp_type]['campaigns_count'].add(record.campaign_id)
            type_data[camp_type]['total_messages'] += record.total_messages
            type_data[camp_type]['messages_delivered'] += record.messages_delivered
            type_data[camp_type]['messages_failed'] += record.messages_failed
            type_data[camp_type]['total_cost'] += record.total_cost
        
        # Calculate rates and convert to list
        result = []
        for data in type_data.values():
            total = data['total_messages']
            delivered = data['messages_delivered']
            
            data['delivery_rate'] = (delivered / total * 100) if total > 0 else 0
            data['campaigns_count'] = len(data['campaigns_count'])
            data['avg_cost_per_message'] = data['total_cost'] / total if total > 0 else 0
            
            result.append(data)
        
        return result

    @api.model
    def get_top_performing_campaigns(self, limit=10, metric='delivery_rate'):
        """Get top performing campaigns by specific metric"""
        analytics = self.search([], order=f'{metric} desc', limit=limit)
        
        result = []
        for record in analytics:
            result.append({
                'campaign_id': record.campaign_id,
                'campaign_name': record.campaign_name,
                'campaign_type': record.campaign_type,
                'delivery_rate': record.delivery_rate,
                'total_messages': record.total_messages,
                'total_cost': record.total_cost,
                'success_rate': record.success_rate
            })
        
        return result

    @api.model
    def get_cost_analysis(self, group_by='campaign_type'):
        """Analyze costs by different dimensions"""
        analytics = self.search([])
        
        if group_by == 'campaign_type':
            return self.get_campaign_type_analysis()
        elif group_by == 'month':
            return self.get_monthly_trends()
        elif group_by == 'template':
            # Group by template
            template_data = {}
            for record in analytics:
                template_name = record.template_name or 'Unknown'
                if template_name not in template_data:
                    template_data[template_name] = {
                        'template_name': template_name,
                        'total_messages': 0,
                        'total_cost': 0,
                        'campaigns_count': set()
                    }
                
                template_data[template_name]['total_messages'] += record.total_messages
                template_data[template_name]['total_cost'] += record.total_cost
                template_data[template_name]['campaigns_count'].add(record.campaign_id)
            
            result = []
            for data in template_data.values():
                data['campaigns_count'] = len(data['campaigns_count'])
                data['avg_cost_per_message'] = data['total_cost'] / data['total_messages'] if data['total_messages'] > 0 else 0
                result.append(data)
            
            return result
        
        return []


class ZnsBomMarketingReportWizard(models.TransientModel):
    _name = 'zns.bom.marketing.report.wizard'
    _description = 'ZNS Marketing Report Wizard'

    date_from = fields.Date('Date From', required=True, default=lambda self: fields.Date.today() - timedelta(days=30))
    date_to = fields.Date('Date To', required=True, default=fields.Date.today)
    campaign_ids = fields.Many2many('zns.bom.marketing.campaign', string='Campaigns')
    report_type = fields.Selection([
        ('performance', 'Campaign Performance'),
        ('comparison', 'Campaign Comparison'),
        ('cost_analysis', 'Cost Analysis'),
        ('monthly_trends', 'Monthly Trends')
    ], string='Report Type', required=True, default='performance')
    
    group_by = fields.Selection([
        ('campaign', 'Campaign'),
        ('campaign_type', 'Campaign Type'),
        ('month', 'Month'),
        ('template', 'Template')
    ], string='Group By', default='campaign')

    def action_generate_report(self):
        """Generate the selected report"""
        analytics_model = self.env['zns.bom.marketing.analytics']
        
        if self.report_type == 'performance':
            data = analytics_model.get_campaign_performance_data(
                campaign_ids=self.campaign_ids.ids if self.campaign_ids else None,
                date_from=self.date_from,
                date_to=self.date_to
            )
        elif self.report_type == 'comparison':
            data = analytics_model.get_campaign_comparison(
                campaign_ids=self.campaign_ids.ids if self.campaign_ids else []
            )
        elif self.report_type == 'cost_analysis':
            data = analytics_model.get_cost_analysis(group_by=self.group_by)
        elif self.report_type == 'monthly_trends':
            data = analytics_model.get_monthly_trends()
        else:
            data = {}
        
        # Return action to display results
        return {
            'name': _('Marketing Analytics Report'),
            'type': 'ir.actions.act_window',
            'res_model': 'zns.bom.marketing.analytics',
            'view_mode': 'graph,pivot,tree',
            'domain': [
                ('date', '>=', self.date_from),
                ('date', '<=', self.date_to),
            ] + ([('campaign_id', 'in', self.campaign_ids.ids)] if self.campaign_ids else []),
            'context': {
                'search_default_group_by_' + self.group_by: 1,
                'report_data': data
            }
        }