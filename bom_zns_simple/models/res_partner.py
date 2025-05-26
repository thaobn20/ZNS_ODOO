# -*- coding: utf-8 -*-

import json
import logging
from odoo import models, fields, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class ResPartner(models.Model):
    _inherit = 'res.partner'
    
    zns_message_ids = fields.One2many('zns.message', 'partner_id', string='ZNS Messages')
    zns_message_count = fields.Integer('ZNS Message Count', compute='_compute_zns_message_count')
    
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for partner in self:
            partner.zns_message_count = len(partner.zns_message_ids)
    
    def action_send_zns(self):
        """Open ZNS send wizard"""
        return {
            'type': 'ir.actions.act_window',
            'name': 'Send ZNS Message',
            'res_model': 'zns.send.wizard',
            'view_mode': 'form',
            'target': 'new',
            'context': {
                'default_partner_id': self.id,
                'default_phone': self.mobile or self.phone,
            }
        }


class SaleOrder(models.Model):
    _inherit = 'sale.order'
    
    # ZNS Integration Fields
    zns_message_ids = fields.One2many('zns.message', 'sale_order_id', string='ZNS Messages')
    zns_message_count = fields.Integer('ZNS Message Count', compute='_compute_zns_message_count')
    zns_auto_send = fields.Boolean('Auto Send ZNS', default=True, help="Automatically send ZNS when order is confirmed")
    zns_template_mapping_id = fields.Many2one('zns.template.mapping', string='ZNS Template Mapping', 
                                            help="Template mapping based on order conditions")
    
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for order in self:
            order.zns_message_count = len(order.zns_message_ids)
    
    @api.onchange('partner_id', 'amount_total', 'order_line')
    def _onchange_zns_template_mapping(self):
        """Auto-select template mapping based on order conditions"""
        if self.partner_id:
            mapping = self.env['zns.template.mapping']._find_best_mapping('sale.order', self)
            if mapping:
                self.zns_template_mapping_id = mapping.id
    
    def action_confirm(self):
        """Override to send ZNS automatically when order is confirmed"""
        result = super(SaleOrder, self).action_confirm()
        
        # Send ZNS automatically if enabled
        for order in self:
            if order.zns_auto_send and order.partner_id and (order.partner_id.mobile or order.partner_id.phone):
                try:
                    order._send_confirmation_zns()
                except Exception as e:
                    _logger.warning(f"Failed to send ZNS for SO {order.name}: {e}")
                    # Don't block the confirmation if ZNS fails
        
        return result
    
    def _send_confirmation_zns(self):
        """Send ZNS notification for order confirmation"""
        template_mapping = self.zns_template_mapping_id
        if not template_mapping:
            # Find best template mapping
            template_mapping = self.env['zns.template.mapping']._find_best_mapping('sale.order', self)
        
        if not template_mapping:
            _logger.warning(f"No ZNS template mapping found for SO {self.name}")
            return
        
        template = template_mapping.template_id
        if not template or not template.active:
            _logger.warning(f"No active template found for SO {self.name}")
            return
        
        # Build parameters
        params = self.env['zns.helper'].build_sale_order_params(self, template)
        
        # Format phone number
        phone = self.env['zns.helper'].format_phone_number(
            self.partner_id.mobile or self.partner_id.phone
        )
        
        if not phone:
            _logger.warning(f"No valid phone number for SO {self.name}")
            return
        
        # Create and send ZNS message
        message = self.env['zns.message'].create({
            'template_id': template.id,
            'connection_id': template.connection_id.id,
            'phone': phone,
            'parameters': json.dumps(params),
            'partner_id': self.partner_id.id,
            'sale_order_id': self.id,
        })
        
        # Send immediately
        try:
            message.send_zns_message()
            _logger.info(f"ZNS sent successfully for SO {self.name}")
        except Exception as e:
            _logger.error(f"Failed to send ZNS for SO {self.name}: {e}")
            raise
    
    def action_send_zns_manual(self):
        """Manual ZNS sending with template selection"""
        return {
            'type': 'ir.actions.act_window',
            'name': 'Send ZNS Message',
            'res_model': 'zns.send.wizard',
            'view_mode': 'form',
            'target': 'new',
            'context': {
                'default_partner_id': self.partner_id.id,
                'default_phone': self.partner_id.mobile or self.partner_id.phone,
                'default_sale_order_id': self.id,
                'default_template_mapping_id': self.zns_template_mapping_id.id if self.zns_template_mapping_id else False,
            }
        }
    
    def action_view_zns_messages(self):
        """View ZNS messages for this order"""
        return {
            'type': 'ir.actions.act_window',
            'name': f'ZNS Messages - {self.name}',
            'res_model': 'zns.message',
            'view_mode': 'tree,form',
            'domain': [('sale_order_id', '=', self.id)],
            'context': {'default_sale_order_id': self.id}
        }


class AccountMove(models.Model):
    _inherit = 'account.move'
    
    zns_message_ids = fields.One2many('zns.message', 'invoice_id', string='ZNS Messages')
    zns_message_count = fields.Integer('ZNS Message Count', compute='_compute_zns_message_count')
    
    @api.depends('zns_message_ids')
    def _compute_zns_message_count(self):
        for move in self:
            move.zns_message_count = len(move.zns_message_ids)
    
    def action_send_zns(self):
        """Open ZNS send wizard for invoice"""
        return {
            'type': 'ir.actions.act_window',
            'name': 'Send ZNS Message',
            'res_model': 'zns.send.wizard',
            'view_mode': 'form',
            'target': 'new',
            'context': {
                'default_partner_id': self.partner_id.id,
                'default_phone': self.partner_id.mobile or self.partner_id.phone,
                'default_invoice_id': self.id,
            }
        }