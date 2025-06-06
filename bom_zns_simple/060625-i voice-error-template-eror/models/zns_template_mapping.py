# -*- coding: utf-8 -*-

import json
import logging
import requests
from odoo import models, fields, api, _
from odoo.exceptions import UserError, ValidationError

_logger = logging.getLogger(__name__)


class ZnsTemplateMapping(models.Model):
    _name = 'zns.template.mapping'
    _description = 'ZNS Template Mapping Rules'
    _order = 'priority, id'
    
    name = fields.Char('Mapping Name', required=True)
    priority = fields.Integer('Priority', default=10, help="Lower numbers = higher priority")
    active = fields.Boolean('Active', default=True)
    
    # Template - with better validation
    template_id = fields.Many2one('zns.template', string='ZNS Template', required=True,
                                help="Select the ZNS template to use for this mapping")
    
    # Model Integration
    model = fields.Selection([
        ('sale.order', 'Sales Order'),
        ('purchase.order', 'Purchase Order'),
        ('account.move', 'Invoice'),
    ], string='Document Type', required=True, default='sale.order')
    
    # Conditions
    partner_ids = fields.Many2many('res.partner', string='Specific Customers', 
                                 help="Leave empty to apply to all customers")
    partner_category_ids = fields.Many2many('res.partner.category', string='Customer Categories')
    amount_min = fields.Float('Minimum Amount')
    amount_max = fields.Float('Maximum Amount')
    product_category_ids = fields.Many2many('product.category', string='Product Categories')
    condition_code = fields.Text('Custom Condition Code', help="Python code for custom conditions")
    
    # Usage Stats
    usage_count = fields.Integer('Usage Count', readonly=True)
    last_used = fields.Datetime('Last Used', readonly=True)
    
    # Helper fields
    template_status = fields.Char('Template Status', compute='_compute_template_status')
    parameter_count = fields.Integer('Parameters', compute='_compute_template_info')
    
    @api.depends('template_id', 'template_id.active', 'template_id.parameter_ids')
    def _compute_template_status(self):
        for record in self:
            if record.template_id:
                if record.template_id.active:
                    record.template_status = f"✅ Active ({len(record.template_id.parameter_ids)} params)"
                else:
                    record.template_status = "⚠️ Inactive template"
            else:
                record.template_status = "❌ No template selected"
    
    @api.depends('template_id', 'template_id.parameter_ids')
    def _compute_template_info(self):
        for record in self:
            record.parameter_count = len(record.template_id.parameter_ids) if record.template_id else 0
    
    @api.constrains('template_id')
    def _check_template_id(self):
        for record in self:
            if not record.template_id:
                raise ValidationError(_("❌ ZNS Template is required. Please select a template before saving."))
    
    @api.constrains('amount_min', 'amount_max')
    def _check_amounts(self):
        for record in self:
            if record.amount_min and record.amount_max and record.amount_min > record.amount_max:
                raise ValidationError(_("Minimum amount cannot be greater than maximum amount"))
    
    @api.model
    def create(self, vals):
        """Override create to ensure template_id is provided"""
        if not vals.get('template_id'):
            raise ValidationError(_("❌ ZNS Template is required. Please select a template first.\n\n"
                                  "Steps:\n"
                                  "1. Go to Templates → Template List\n"
                                  "2. Create a template and sync parameters\n"
                                  "3. Then create template mappings"))
        return super().create(vals)
    
    @api.model
    def _find_best_mapping(self, model, record):
        """Find the best template mapping for a record"""
        domain = [('model', '=', model), ('active', '=', True)]
        mappings = self.search(domain, order='priority, id')
        
        for mapping in mappings:
            if mapping._matches_conditions(record):
                # Update usage stats
                mapping.write({
                    'usage_count': mapping.usage_count + 1,
                    'last_used': fields.Datetime.now()
                })
                return mapping
        
        return False
    
    def _matches_conditions(self, record):
        """Check if record matches mapping conditions"""
        # Partner conditions
        if self.partner_ids and record.partner_id not in self.partner_ids:
            return False
        
        if self.partner_category_ids:
            if not any(cat in record.partner_id.category_id for cat in self.partner_category_ids):
                return False
        
        # Amount conditions
        amount = getattr(record, 'amount_total', 0)
        if self.amount_min and amount < self.amount_min:
            return False
        if self.amount_max and amount > self.amount_max:
            return False
        
        # Product category conditions (for SO/PO)
        if self.product_category_ids and hasattr(record, 'order_line'):
            product_categories = record.order_line.mapped('product_id.categ_id')
            if not any(cat in product_categories for cat in self.product_category_ids):
                return False
        
        # Custom condition
        if self.condition_code:
            try:
                local_dict = {'record': record, 'env': self.env}
                exec(self.condition_code, {}, local_dict)
                result = local_dict.get('result', True)
                if not result:
                    return False
            except Exception as e:
                _logger.warning(f"Error in custom condition for mapping {self.name}: {e}")
                return False
        
        return True
    
    def test_mapping(self):
        """Test this mapping with sample data"""
        if not self.template_id:
            raise UserError(_("❌ Cannot test mapping without a template. Please select a template first."))
        
        # Find a sample record to test
        if self.model == 'sale.order':
            sample = self.env['sale.order'].search([('state', '!=', 'cancel')], limit=1)
        elif self.model == 'purchase.order':
            sample = self.env['purchase.order'].search([('state', '!=', 'cancel')], limit=1)
        else:  # account.move
            sample = self.env['account.move'].search([('move_type', '=', 'out_invoice')], limit=1)
        
        if sample:
            matches = self._matches_conditions(sample)
            
            # Build test parameters
            if matches and self.template_id:
                try:
                    if self.model == 'sale.order':
                        params = self.env['zns.helper'].build_sale_order_params(sample, self.template_id)
                    else:
                        params = {}
                    
                    param_preview = "\n".join([f"• {k}: {v}" for k, v in params.items()]) if params else "No parameters"
                    
                    return {
                        'type': 'ir.actions.client',
                        'tag': 'display_notification',
                        'params': {
                            'title': '✅ Mapping Test Result',
                            'message': f"Template: {self.template_id.name}\n"
                                     f"Record: {sample.name}\n"
                                     f"Matches: {'YES' if matches else 'NO'}\n\n"
                                     f"Parameters would be:\n{param_preview}",
                            'type': 'success' if matches else 'warning',
                            'sticky': True,
                        }
                    }
                except Exception as e:
                    return {
                        'type': 'ir.actions.client',
                        'tag': 'display_notification',
                        'params': {
                            'title': '⚠️ Mapping Test Warning',
                            'message': f"Mapping matches but parameter building failed:\n{str(e)}",
                            'type': 'warning',
                            'sticky': True,
                        }
                    }
            else:
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': '❌ Mapping Test Result',
                        'message': f"Mapping DOES NOT MATCH sample record: {sample.name}\n\n"
                                 f"Check your conditions:\n"
                                 f"• Amount range: {self.amount_min or 0} - {self.amount_max or 'unlimited'}\n"
                                 f"• Customer categories: {len(self.partner_category_ids)} selected\n"
                                 f"• Product categories: {len(self.product_category_ids)} selected\n"
                                 f"• Custom condition: {'Yes' if self.condition_code else 'No'}",
                        'type': 'warning',
                        'sticky': True,
                    }
                }
        else:
            raise UserError(_("No sample records found to test mapping"))
    
    def action_view_template(self):
        """View the associated template"""
        if not self.template_id:
            raise UserError(_("No template associated with this mapping"))
        
        return {
            'type': 'ir.actions.act_window',
            'name': f'Template: {self.template_id.name}',
            'res_model': 'zns.template',
            'res_id': self.template_id.id,
            'view_mode': 'form',
            'target': 'current',
        }


class ZnsTemplateSyncWizard(models.TransientModel):
    _name = 'zns.template.sync.wizard'
    _description = 'Sync ZNS Templates from BOM API'
    
    connection_id = fields.Many2one('zns.connection', string='ZNS Connection', required=True)
    sync_mode = fields.Selection([
        ('add_new', 'Add new templates only'),
        ('update_existing', 'Update existing templates'),
        ('full_sync', 'Full synchronization (add + update)'),
    ], string='Sync Mode', default='full_sync', required=True)
    
    def sync_templates(self):
        """Sync templates from BOM API"""
        if not self.connection_id:
            raise UserError("Please select a connection")
        
        try:
            # Get access token
            token = self.connection_id._get_access_token()
            
            # Get templates list from BOM API
            templates_data = self._fetch_templates_from_api(token)
            
            synced_count = 0
            updated_count = 0
            
            for template_data in templates_data:
                template_id = template_data.get('template_id')
                if not template_id:
                    continue
                
                # Check if template exists
                existing = self.env['zns.template'].search([
                    ('template_id', '=', template_id),
                    ('connection_id', '=', self.connection_id.id)
                ])
                
                if existing:
                    if self.sync_mode in ['update_existing', 'full_sync']:
                        self._update_template(existing, template_data)
                        updated_count += 1
                else:
                    if self.sync_mode in ['add_new', 'full_sync']:
                        self._create_template(template_data)
                        synced_count += 1
            
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': 'Template Sync Complete',
                    'message': f"✅ Sync completed!\nNew templates: {synced_count}\nUpdated templates: {updated_count}",
                    'type': 'success',
                    'sticky': False,
                }
            }
            
        except Exception as e:
            raise UserError(f"Template sync failed: {str(e)}")
    
    def _fetch_templates_from_api(self, token):
        """Fetch templates list from BOM API"""
        # This would be the actual BOM API endpoint for templates list
        # For now, return sample data since we don't have the exact endpoint
        return [
            {
                'template_id': '227805',
                'name': 'Order Confirmation',
                'type': 'transaction',
                'status': 'active'
            }
        ]
    
    def _create_template(self, template_data):
        """Create new template from API data"""
        template = self.env['zns.template'].create({
            'name': template_data.get('name', f"Template {template_data.get('template_id')}"),
            'template_id': template_data.get('template_id'),
            'template_type': template_data.get('type', 'transaction'),
            'connection_id': self.connection_id.id,
            'active': template_data.get('status') == 'active',
        })
        
        # Sync parameters for new template
        try:
            template.sync_template_params()
        except:
            pass  # Don't fail if parameter sync fails
        
        return template
    
    def _update_template(self, template, template_data):
        """Update existing template from API data"""
        template.write({
            'name': template_data.get('name', template.name),
            'template_type': template_data.get('type', template.template_type),
            'active': template_data.get('status') == 'active',
        })
        
        # Sync parameters for updated template
        try:
            template.sync_template_params()
        except:
            pass  # Don't fail if parameter sync fails