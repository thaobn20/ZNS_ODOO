# -*- coding: utf-8 -*-

import json
import logging
from datetime import datetime, timedelta
from odoo import models, fields, api, _
from odoo.exceptions import ValidationError

_logger = logging.getLogger(__name__)


class ZnsBomMarketingContactList(models.Model):
    _name = 'zns.bom.marketing.contact.list'
    _description = 'ZNS BOM Marketing Contact List'
    _order = 'name'
    _rec_name = 'name'

    # Basic Information
    name = fields.Char('List Name', required=True, index=True)
    description = fields.Text('Description')
    active = fields.Boolean('Active', default=True)
    color = fields.Integer('Color Index', default=0)
    
    # Contact Management
    contact_ids = fields.Many2many(
        'res.partner', 
        'zns_bom_marketing_list_contact_rel', 
        'list_id', 
        'contact_id',
        string='Contacts'
    )
    contact_count = fields.Integer('Contact Count', compute='_compute_contact_stats', store=True)
    
    # List Type
    list_type = fields.Selection([
        ('static', 'Static List'),
        ('dynamic', 'Dynamic List'),
        ('birthday_auto', 'Birthday Auto List')
    ], required=True, default='static', string='List Type')
    
    # Dynamic List Settings
    filter_domain = fields.Text('Filter Domain', help='Domain filter for dynamic lists')
    auto_update = fields.Boolean('Auto Update Daily', default=False)
    last_updated = fields.Datetime('Last Updated')
    
    # Birthday List Settings
    birthday_days_before = fields.Integer('Days Before Birthday', default=1, 
                                        help='How many days before birthday to include contacts')
    birthday_months = fields.Selection([
        ('all', 'All Months'),
        ('current', 'Current Month Only'),
        ('next', 'Next Month Only')
    ], default='all', string='Birthday Months')
    
    # Health Statistics
    valid_phone_count = fields.Integer('Valid Phones', compute='_compute_health_stats', store=True)
    invalid_phone_count = fields.Integer('Invalid Phones', compute='_compute_health_stats', store=True)
    opt_out_count = fields.Integer('Opted Out', compute='_compute_health_stats', store=True)
    health_score = fields.Float('Health Score %', compute='_compute_health_stats', store=True)
    
    # Campaign Relations
    campaign_ids = fields.Many2many(
        'zns.bom.marketing.campaign',
        'zns_bom_marketing_campaign_list_rel',
        'list_id',
        'campaign_id',
        string='Campaigns'
    )
    
    @api.depends('contact_ids')
    def _compute_contact_stats(self):
        for record in self:
            record.contact_count = len(record.contact_ids)
    
    @api.depends('contact_ids', 'contact_ids.mobile', 'contact_ids.phone')
    def _compute_health_stats(self):
        for record in self:
            total_contacts = len(record.contact_ids)
            if total_contacts == 0:
                record.valid_phone_count = 0
                record.invalid_phone_count = 0
                record.opt_out_count = 0
                record.health_score = 0.0
                continue
            
            valid_phones = 0
            invalid_phones = 0
            opt_outs = 0
            
            for contact in record.contact_ids:
                # Check phone validity
                phone = contact.mobile or contact.phone
                if phone and len(phone.strip()) >= 10:
                    valid_phones += 1
                else:
                    invalid_phones += 1
                
                # Check opt-out status
                opt_out = self.env['zns.bom.marketing.opt.out'].search([
                    ('contact_id', '=', contact.id),
                    ('global_opt_out', '=', True)
                ], limit=1)
                if opt_out:
                    opt_outs += 1
            
            record.valid_phone_count = valid_phones
            record.invalid_phone_count = invalid_phones
            record.opt_out_count = opt_outs
            
            # Calculate health score
            if total_contacts > 0:
                health_score = (valid_phones - opt_outs) / total_contacts * 100
                record.health_score = max(0, health_score)
            else:
                record.health_score = 0.0
    
    @api.model
    def create(self, vals):
        result = super().create(vals)
        if result.list_type == 'dynamic' and result.filter_domain:
            result._update_dynamic_contacts()
        elif result.list_type == 'birthday_auto':
            result._update_birthday_contacts()
        return result
    
    def write(self, vals):
        result = super().write(vals)
        for record in self:
            if 'filter_domain' in vals and record.list_type == 'dynamic':
                record._update_dynamic_contacts()
            elif vals.get('list_type') == 'birthday_auto' or 'birthday_days_before' in vals:
                if record.list_type == 'birthday_auto':
                    record._update_birthday_contacts()
        return result
    
    def _update_dynamic_contacts(self):
        """Update contacts for dynamic lists"""
        if not self.filter_domain:
            return
        
        try:
            domain = eval(self.filter_domain)
            contacts = self.env['res.partner'].search(domain)
            self.contact_ids = [(6, 0, contacts.ids)]
            self.last_updated = fields.Datetime.now()
            _logger.info(f"Updated dynamic list '{self.name}': {len(contacts)} contacts")
        except Exception as e:
            _logger.error(f"Error updating dynamic list '{self.name}': {e}")
    
    def _update_birthday_contacts(self):
        """Update contacts for birthday auto lists"""
        target_date = fields.Date.today() + timedelta(days=self.birthday_days_before)
        
        # Build domain for birthday search
        month_day = target_date.strftime('%m-%d')
        month_day_alt = target_date.strftime('%-m-%-d')
        
        domain = [
            ('birthday', '!=', False),
            '|', 
            ('birthday', 'like', f'%-{month_day}'),
            ('birthday', 'like', f'%-{month_day_alt}')
        ]
        
        # Filter by months if specified
        if self.birthday_months == 'current':
            current_month = fields.Date.today().month
            domain.append(('birthday', 'like', f'%-{current_month:02d}-%'))
        elif self.birthday_months == 'next':
            next_month = (fields.Date.today().month % 12) + 1
            domain.append(('birthday', 'like', f'%-{next_month:02d}-%'))
        
        contacts = self.env['res.partner'].search(domain)
        self.contact_ids = [(6, 0, contacts.ids)]
        self.last_updated = fields.Datetime.now()
        _logger.info(f"Updated birthday list '{self.name}': {len(contacts)} contacts")
    
    @api.model
    def update_all_dynamic_lists(self):
        """Cron job: Update all dynamic lists"""
        dynamic_lists = self.search([
            ('list_type', '=', 'dynamic'),
            ('auto_update', '=', True),
            ('active', '=', True)
        ])
        
        for lst in dynamic_lists:
            lst._update_dynamic_contacts()
        
        # Also update birthday auto lists
        birthday_lists = self.search([
            ('list_type', '=', 'birthday_auto'),
            ('active', '=', True)
        ])
        
        for lst in birthday_lists:
            lst._update_birthday_contacts()
        
        _logger.info(f"Updated {len(dynamic_lists)} dynamic lists and {len(birthday_lists)} birthday lists")
    
    def action_update_contacts(self):
        """Manual update button action"""
        if self.list_type == 'dynamic':
            self._update_dynamic_contacts()
        elif self.list_type == 'birthday_auto':
            self._update_birthday_contacts()
        
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': _('Success'),
                'message': _('Contact list updated successfully'),
                'type': 'success',
            }
        }
    
    def action_view_contacts(self):
        """Open contacts view"""
        return {
            'name': _('Contacts'),
            'type': 'ir.actions.act_window',
            'res_model': 'res.partner',
            'view_mode': 'tree,form',
            'domain': [('id', 'in', self.contact_ids.ids)],
            'context': {'default_is_company': False}
        }
    
    def action_export_contacts(self):
        """Export contacts to CSV"""
        # This would typically generate a report or download
        return {
            'type': 'ir.actions.act_url',
            'url': f'/web/export_csv/zns.bom.marketing.contact.list/{self.id}',
            'target': 'new',
        }
    
    @api.constrains('filter_domain')
    def _check_filter_domain(self):
        for record in self:
            if record.list_type == 'dynamic' and record.filter_domain:
                try:
                    domain = eval(record.filter_domain)
                    if not isinstance(domain, list):
                        raise ValidationError(_('Filter domain must be a valid list'))
                except Exception as e:
                    raise ValidationError(_('Invalid filter domain: %s') % str(e))