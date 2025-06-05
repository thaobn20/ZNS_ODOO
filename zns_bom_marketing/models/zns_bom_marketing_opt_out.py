# -*- coding: utf-8 -*-

import logging
from odoo import models, fields, api, _
from odoo.exceptions import ValidationError

_logger = logging.getLogger(__name__)


class ZnsBomMarketingOptOut(models.Model):
    _name = 'zns.bom.marketing.opt.out'
    _description = 'ZNS BOM Marketing Opt-out Management'
    _rec_name = 'display_name'
    _order = 'opt_out_date desc'

    # Contact Information
    contact_id = fields.Many2one('res.partner', required=True, ondelete='cascade')
    phone_number = fields.Char('Phone Number', related='contact_id.mobile', readonly=True)
    contact_name = fields.Char('Contact Name', related='contact_id.name', readonly=True)
    display_name = fields.Char('Display Name', compute='_compute_display_name')
    
    # Opt-out Details
    opt_out_date = fields.Datetime('Opt-out Date', default=fields.Datetime.now, required=True)
    opt_out_reason = fields.Selection([
        ('customer_request', 'Customer Request'),
        ('invalid_phone', 'Invalid Phone Number'),
        ('complaint', 'Complaint'),
        ('bounced', 'Message Bounced'),
        ('admin', 'Admin Decision'),
        ('duplicate', 'Duplicate Contact'),
        ('unsubscribe_link', 'Unsubscribe Link'),
        ('manual', 'Manual Opt-out')
    ], required=True, string='Opt-out Reason')
    
    # Opt-out Scope
    global_opt_out = fields.Boolean('Global Opt-out', default=True, 
                                   help='Opt out from all marketing campaigns')
    campaign_types = fields.Selection([
        ('promotion', 'Promotions Only'),
        ('birthday', 'Birthday Messages Only'),
        ('notification', 'Notifications Only'),
        ('recurring', 'Recurring Messages Only')
    ], string='Specific Campaign Types',
       help='Opt out from specific campaign types only (if Global Opt-out is disabled)')
    
    # Additional Info
    notes = fields.Text('Notes')
    created_by = fields.Many2one('res.users', default=lambda self: self.env.user, string='Created By')
    
    # Re-engagement
    can_resubscribe = fields.Boolean('Can Re-subscribe', default=True)
    resubscribe_date = fields.Datetime('Re-subscribe Date')
    resubscribed_by = fields.Many2one('res.users', string='Re-subscribed By')
    
    # Status
    active = fields.Boolean('Active', default=True)
    
    @api.depends('contact_id', 'global_opt_out', 'campaign_types')
    def _compute_display_name(self):
        for record in self:
            contact_name = record.contact_id.name or 'Unknown Contact'
            if record.global_opt_out:
                scope = 'Global'
            else:
                scope = dict(record._fields['campaign_types'].selection).get(record.campaign_types, 'Specific')
            record.display_name = f"{contact_name} - {scope} Opt-out"
    
    @api.model
    def create(self, vals):
        """Override create to log opt-out"""
        result = super().create(vals)
        _logger.info(f"Contact {result.contact_id.name} opted out: {result.opt_out_reason}")
        return result
    
    def action_resubscribe(self):
        """Re-subscribe contact"""
        if not self.can_resubscribe:
            raise ValidationError(_('This contact cannot be re-subscribed'))
        
        self.write({
            'active': False,
            'resubscribe_date': fields.Datetime.now(),
            'resubscribed_by': self.env.user.id
        })
        
        _logger.info(f"Contact {self.contact_id.name} re-subscribed by {self.env.user.name}")
        
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': _('Re-subscribed'),
                'message': _('Contact has been re-subscribed successfully'),
                'type': 'success',
            }
        }
    
    def action_view_contact(self):
        """View contact form"""
        return {
            'name': _('Contact'),
            'type': 'ir.actions.act_window',
            'res_model': 'res.partner',
            'res_id': self.contact_id.id,
            'view_mode': 'form',
            'target': 'current',
        }
    
    @api.model
    def check_opt_out_status(self, contact_id, campaign_type=None):
        """Check if contact is opted out for specific campaign type"""
        domain = [
            ('contact_id', '=', contact_id),
            ('active', '=', True)
        ]
        
        opt_outs = self.search(domain)
        
        for opt_out in opt_outs:
            # Check global opt-out
            if opt_out.global_opt_out:
                return True
            
            # Check specific campaign type opt-out
            if campaign_type and opt_out.campaign_types == campaign_type:
                return True
        
        return False
    
    @api.model
    def bulk_opt_out(self, contact_ids, reason, notes=None):
        """Bulk opt-out contacts"""
        created_count = 0
        
        for contact_id in contact_ids:
            # Check if already opted out
            existing = self.search([
                ('contact_id', '=', contact_id),
                ('global_opt_out', '=', True),
                ('active', '=', True)
            ], limit=1)
            
            if not existing:
                self.create({
                    'contact_id': contact_id,
                    'opt_out_reason': reason,
                    'global_opt_out': True,
                    'notes': notes or '',
                })
                created_count += 1
        
        return created_count
    
    @api.model
    def process_bounced_messages(self):
        """Process bounced messages and auto opt-out"""
        # This would integrate with BOM ZNS to find bounced messages
        # and automatically opt-out those contacts
        
        # Example: Find failed messages with bounce indicators
        failed_messages = self.env['zns.bom.marketing.message'].search([
            ('status', '=', 'failed'),
            ('error_message', 'ilike', 'invalid'),  # Adjust based on actual error patterns
        ])
        
        bounce_contacts = []
        for message in failed_messages:
            if message.contact_id.id not in bounce_contacts:
                bounce_contacts.append(message.contact_id.id)
        
        # Auto opt-out bounced contacts
        if bounce_contacts:
            created = self.bulk_opt_out(
                bounce_contacts, 
                'bounced', 
                'Auto opt-out due to bounced messages'
            )
            _logger.info(f"Auto opted-out {created} contacts due to bounced messages")
        
        return created if bounce_contacts else 0
    
    @api.model
    def get_opt_out_statistics(self):
        """Get opt-out statistics for dashboard"""
        total_opt_outs = self.search_count([('active', '=', True)])
        global_opt_outs = self.search_count([('active', '=', True), ('global_opt_out', '=', True)])
        
        # Count by reason
        reasons = {}
        for reason_code, reason_name in self._fields['opt_out_reason'].selection:
            count = self.search_count([
                ('active', '=', True),
                ('opt_out_reason', '=', reason_code)
            ])
            reasons[reason_name] = count
        
        # Recent opt-outs (last 30 days)
        recent_date = fields.Datetime.now() - timedelta(days=30)
        recent_opt_outs = self.search_count([
            ('active', '=', True),
            ('opt_out_date', '>=', recent_date)
        ])
        
        return {
            'total_opt_outs': total_opt_outs,
            'global_opt_outs': global_opt_outs,
            'specific_opt_outs': total_opt_outs - global_opt_outs,
            'recent_opt_outs': recent_opt_outs,
            'reasons': reasons
        }
    
    @api.constrains('global_opt_out', 'campaign_types')
    def _check_opt_out_scope(self):
        for record in self:
            if not record.global_opt_out and not record.campaign_types:
                raise ValidationError(_('Please specify either global opt-out or specific campaign types'))
    
    @api.constrains('contact_id', 'global_opt_out', 'campaign_types')
    def _check_duplicate_opt_out(self):
        for record in self:
            domain = [
                ('contact_id', '=', record.contact_id.id),
                ('active', '=', True),
                ('id', '!=', record.id)
            ]
            
            if record.global_opt_out:
                domain.append(('global_opt_out', '=', True))
            else:
                domain.extend([
                    ('global_opt_out', '=', False),
                    ('campaign_types', '=', record.campaign_types)
                ])
            
            existing = self.search(domain, limit=1)
            if existing:
                raise ValidationError(_('This contact already has an active opt-out record for this scope'))