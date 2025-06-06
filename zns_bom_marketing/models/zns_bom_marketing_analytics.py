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
        """Initialize the view - skip completely during installation"""
        try:
            # Only try to create view if both required tables exist
            self.env.cr.execute("""
                SELECT EXISTS (
                    SELECT 1 FROM information_schema.tables 
                    WHERE table_name = 'zns_bom_marketing_campaign'
                ) AND EXISTS (
                    SELECT 1 FROM information_schema.tables 
                    WHERE table_name = 'zns_bom_marketing_message'
                )
            """)
            
            if self.env.cr.fetchone()[0]:
                tools.drop_view_if_exists(self.env.cr, self._table)
                self._create_minimal_analytics_view()
                _logger.info("Analytics view created successfully")
            else:
                _logger.info("Analytics view creation skipped - required tables not ready yet")
        except Exception as e:
            _logger.info(f"Analytics view creation deferred: {e}")
    
    def _create_minimal_analytics_view(self):
        """Create a minimal analytics view"""
        # Create the simplest possible view to avoid SQL issues
        self.env.cr.execute("""
            CREATE OR REPLACE VIEW %s AS (
                SELECT 
                    m.id,
                    c.id AS campaign_id,
                    c.name AS campaign_name,
                    c.campaign_type,
                    DATE(m.create_date) AS date,
                    EXTRACT(YEAR FROM m.create_date)::text AS year,
                    EXTRACT(MONTH FROM m.create_date)::text AS month,
                    EXTRACT(WEEK FROM m.create_date)::text AS week,
                    1 AS total_messages,
                    CASE WHEN m.status IN ('sent', 'delivered') THEN 1 ELSE 0 END AS messages_sent,
                    CASE WHEN m.status = 'delivered' THEN 1 ELSE 0 END AS messages_delivered,
                    CASE WHEN m.status = 'failed' THEN 1 ELSE 0 END AS messages_failed,
                    CASE WHEN m.status = 'queued' THEN 1 ELSE 0 END AS messages_queued,
                    CASE WHEN m.status = 'skipped' THEN 1 ELSE 0 END AS messages_skipped,
                    CASE WHEN m.status = 'delivered' THEN 100.0 ELSE 0.0 END AS delivery_rate,
                    CASE WHEN m.status = 'failed' THEN 100.0 ELSE 0.0 END AS failure_rate,
                    CASE WHEN m.status IN ('sent', 'delivered') THEN 100.0 ELSE 0.0 END AS success_rate,
                    COALESCE(m.message_cost, 0) AS total_cost,
                    COALESCE(m.message_cost, 0) AS cost_per_message,
                    COALESCE(m.message_cost, 0) AS cost_per_delivery,
                    COALESCE(m.send_duration, 0) AS avg_send_duration,
                    COALESCE(m.retry_count, 0) AS retry_count_total,
                    1 AS unique_contacts,
                    0 AS repeat_contacts,
                    c.bom_zns_template_id AS template_id,
                    'Template' AS template_name,
                    c.bom_zns_connection_id AS connection_id
                FROM zns_bom_marketing_message m
                LEFT JOIN zns_bom_marketing_campaign c ON c.id = m.campaign_id
                WHERE m.id IS NOT NULL
            )
        """ % self._table)
        
    @api.model
    def refresh_analytics_view(self):
        """Method to refresh the analytics view after installation"""
        try:
            self._create_minimal_analytics_view()
            _logger.info("Analytics view refreshed successfully")
        except Exception as e:
            _logger.error(f"Failed to refresh analytics view: {e}")

    @api.model
    def get_campaign_performance_data(self, campaign_ids=None, date_from=None, date_to=None):
        """Get campaign performance data for charts"""
        try:
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
                chart_data['dates'].append(record.date.strftime('%Y-%m-%d') if record.date else '')
                chart_data['total_messages'].append(record.total_messages)
                chart_data['delivery_rates'].append(record.delivery_rate)
                chart_data['failure_rates'].append(record.failure_rate)
                chart_data['costs'].append(record.total_cost)
            
            return chart_data
        except Exception as e:
            _logger.error(f"Error getting campaign performance data: {e}")
            return {'dates': [], 'total_messages': [], 'delivery_rates': [], 'failure_rates': [], 'costs': []}

    @api.model
    def get_campaign_comparison(self, campaign_ids, metrics=['delivery_rate', 'total_cost']):
        """Compare multiple campaigns"""
        if not campaign_ids:
            return {}
        
        try:
            campaigns_data = {}
            
            for campaign_id in campaign_ids:
                # Get data directly from campaign and message models
                campaign = self.env['zns.bom.marketing.campaign'].browse(campaign_id)
                if campaign.exists():
                    campaigns_data[campaign_id] = {
                        'name': campaign.name,
                        'total_messages': campaign.messages_total,
                        'delivery_rate': campaign.delivery_rate,
                        'failure_rate': campaign.failure_rate,
                        'total_cost': campaign.total_cost,
                        'cost_per_message': campaign.total_cost / campaign.messages_total if campaign.messages_total > 0 else 0,
                    }
            
            return campaigns_data
        except Exception as e:
            _logger.error(f"Error getting campaign comparison: {e}")
            return {}

    @api.model
    def get_monthly_trends(self, months=12):
        """Get monthly performance trends"""
        try:
            # Get data directly from models instead of analytics view
            campaigns = self.env['zns.bom.marketing.campaign'].search([
                ('status', 'in', ['running', 'completed'])
            ])
            
            result = []
            for i in range(months):
                target_date = fields.Date.today().replace(day=1) - timedelta(days=i*30)
                month_start = target_date.replace(day=1)
                
                if month_start.month == 12:
                    next_month = month_start.replace(year=month_start.year + 1, month=1)
                else:
                    next_month = month_start.replace(month=month_start.month + 1)
                
                messages = self.env['zns.bom.marketing.message'].search([
                    ('create_date', '>=', month_start),
                    ('create_date', '<', next_month)
                ])
                
                total_messages = len(messages)
                delivered = len(messages.filtered(lambda m: m.status == 'delivered'))
                failed = len(messages.filtered(lambda m: m.status == 'failed'))
                
                result.append({
                    'month': month_start.strftime('%Y-%m'),
                    'month_name': month_start.strftime('%B %Y'),
                    'total_messages': total_messages,
                    'messages_delivered': delivered,
                    'messages_failed': failed,
                    'delivery_rate': (delivered / total_messages * 100) if total_messages > 0 else 0,
                    'failure_rate': (failed / total_messages * 100) if total_messages > 0 else 0,
                })
            
            return list(reversed(result))
        except Exception as e:
            _logger.error(f"Error getting monthly trends: {e}")
            return []

    @api.model
    def get_campaign_type_analysis(self):
        """Analyze performance by campaign type"""
        try:
            campaigns = self.env['zns.bom.marketing.campaign'].search([])
            
            type_data = {}
            for campaign in campaigns:
                camp_type = campaign.campaign_type
                if camp_type not in type_data:
                    type_data[camp_type] = {
                        'type': camp_type,
                        'campaigns_count': 0,
                        'total_messages': 0,
                        'messages_delivered': 0,
                        'messages_failed': 0,
                        'total_cost': 0
                    }
                
                type_data[camp_type]['campaigns_count'] += 1
                type_data[camp_type]['total_messages'] += campaign.messages_total
                type_data[camp_type]['messages_delivered'] += campaign.messages_delivered
                type_data[camp_type]['messages_failed'] += campaign.messages_failed
                type_data[camp_type]['total_cost'] += campaign.total_cost
            
            result = []
            for data in type_data.values():
                total = data['total_messages']
                delivered = data['messages_delivered']
                
                data['delivery_rate'] = (delivered / total * 100) if total > 0 else 0
                data['avg_cost_per_message'] = data['total_cost'] / total if total > 0 else 0
                
                result.append(data)
            
            return result
        except Exception as e:
            _logger.error(f"Error getting campaign type analysis: {e}")
            return []

    @api.model
    def get_top_performing_campaigns(self, limit=10, metric='delivery_rate'):
        """Get top performing campaigns by specific metric"""
        try:
            campaigns = self.env['zns.bom.marketing.campaign'].search([
                ('status', 'in', ['running', 'completed']),
                ('messages_total', '>', 0)
            ], order=f'{metric} desc', limit=limit)
            
            result = []
            for campaign in campaigns:
                result.append({
                    'campaign_id': campaign.id,
                    'campaign_name': campaign.name,
                    'campaign_type': campaign.campaign_type,
                    'delivery_rate': campaign.delivery_rate,
                    'total_messages': campaign.messages_total,
                    'total_cost': campaign.total_cost,
                    'success_rate': campaign.delivery_rate  # Simplified
                })
            
            return result
        except Exception as e:
            _logger.error(f"Error getting top performing campaigns: {e}")
            return []

    @api.model
    def get_cost_analysis(self, group_by='campaign_type'):
        """Analyze costs by different dimensions"""
        if group_by == 'campaign_type':
            return self.get_campaign_type_analysis()
        elif group_by == 'month':
            return self.get_monthly_trends()
        else:
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
        
        try:
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
        except Exception as e:
            data = {}
            _logger.error(f"Error generating report: {e}")
        
        # Return action to display results - show campaigns instead if analytics fails
        return {
            'name': _('Marketing Analytics Report'),
            'type': 'ir.actions.act_window',
            'res_model': 'zns.bom.marketing.campaign',
            'view_mode': 'tree,form',
            'domain': [('status', 'in', ['running', 'completed'])],
            'context': {
                'search_default_group_by_' + self.group_by: 1,
                'report_data': data
            }
        }