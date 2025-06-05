# -*- coding: utf-8 -*-

import json
import re
import logging
from datetime import datetime, timedelta, time
from odoo import models, fields, api, _

_logger = logging.getLogger(__name__)


class ZnsBomMarketingScheduler(models.Model):
    _name = 'zns.bom.marketing.scheduler'
    _description = 'ZNS BOM Marketing Scheduler'

    name = fields.Char('Name', default='ZNS BOM Marketing Scheduler')

    @api.model
    def process_birthday_campaigns(self):
        """
        Scheduled Job: Runs daily at 6:00 AM
        Automatically detect contacts with upcoming birthdays and send ZNS using BOM ZNS Simple
        """
        _logger.info("=== Processing Birthday Campaigns using BOM ZNS Simple ===")
        
        # Find all active birthday campaigns
        birthday_campaigns = self.env['zns.bom.marketing.campaign'].search([
            ('campaign_type', '=', 'birthday'),
            ('status', '=', 'running')
        ])
        
        total_messages = 0
        for campaign in birthday_campaigns:
            messages_count = self._process_single_birthday_campaign(campaign)
            total_messages += messages_count
        
        _logger.info(f"=== Birthday Campaign Processing Complete: {total_messages} messages queued ===")
        return total_messages
    
    def _process_single_birthday_campaign(self, campaign):
        """Process a single birthday campaign"""
        # Calculate target birthday date
        days_before = campaign.birthday_days_before
        target_date = fields.Date.today() + timedelta(days=days_before)
        
        # Find contacts with birthdays on target date
        birthday_contacts = self._find_birthday_contacts(target_date)
        
        # Filter contacts based on campaign lists (if specified)
        if campaign.contact_list_ids:
            campaign_contacts = set()
            for contact_list in campaign.contact_list_ids:
                campaign_contacts.update(contact_list.contact_ids.ids)
            birthday_contacts = birthday_contacts.filtered(
                lambda c: c.id in campaign_contacts
            )
        
        # Send birthday messages
        messages_queued = 0
        for contact in birthday_contacts:
            if self._should_send_birthday_message(contact, campaign):
                self._queue_birthday_message(campaign, contact)
                messages_queued += 1
        
        _logger.info(f"Birthday campaign '{campaign.name}': {messages_queued} messages queued")
        return messages_queued
    
    def _find_birthday_contacts(self, target_date):
        """Find all contacts with birthday on target date"""
        # Handle different date formats
        month_day = target_date.strftime('%m-%d')  # MM-DD
        month_day_alt = target_date.strftime('%-m-%-d')  # M-D (no leading zeros)
        
        domain = [
            ('birthday', '!=', False),
            '|', 
            ('birthday', 'like', f'%-{month_day}'),
            ('birthday', 'like', f'%-{month_day_alt}')
        ]
        
        contacts = self.env['res.partner'].search(domain)
        _logger.info(f"Found {len(contacts)} contacts with birthday on {target_date}")
        return contacts
    
    def _should_send_birthday_message(self, contact, campaign):
        """Check if we should send birthday message to contact"""
        # Check opt-out status
        if self.env['zns.bom.marketing.opt.out'].check_opt_out_status(contact.id, 'birthday'):
            _logger.debug(f"Contact {contact.name} is opted out from birthday messages")
            return False
        
        # Check if valid phone number
        phone = contact.mobile or contact.phone
        if not phone:
            _logger.debug(f"Contact {contact.name} has no phone number")
            return False
        
        # Check if already sent birthday message this year
        this_year = fields.Date.today().year
        existing_message = self.env['zns.bom.marketing.message'].search([
            ('campaign_id', '=', campaign.id),
            ('contact_id', '=', contact.id),
            ('create_date', '>=', f'{this_year}-01-01'),
            ('status', 'in', ['sent', 'delivered'])
        ], limit=1)
        
        if existing_message:
            _logger.debug(f"Birthday message already sent to {contact.name} this year")
            return False
        
        return True
    
    def _queue_birthday_message(self, campaign, contact):
        """Queue birthday message for contact using BOM ZNS Simple"""
        # Format phone number
        phone = contact.mobile or contact.phone
        if not phone:
            return
            
        # Clean phone number
        phone = self._clean_phone_number(phone)
        
        # Build parameters for BOM ZNS template
        params = self._build_birthday_parameters(contact, campaign.bom_zns_template_id)
        
        # Create BOM ZNS message using existing system
        try:
            bom_zns_message = self.env['bom.zns.message'].create({
                'template_id': campaign.bom_zns_template_id.id,
                'connection_id': campaign.bom_zns_template_id.connection_id.id,
                'phone': phone,
                'parameters': json.dumps(params) if params else '{}',
                'partner_id': contact.id,
                'status': 'draft'  # or whatever initial status bom_zns_simple uses
            })
            
            # Create campaign tracking record
            campaign_message = self.env['zns.bom.marketing.message'].create({
                'campaign_id': campaign.id,
                'bom_zns_message_id': bom_zns_message.id,
                'contact_id': contact.id,
                'phone_number': phone,
                'message_parameters': json.dumps(params) if params else '{}',
                'status': 'queued'
            })
            
            # Schedule sending at specified time
            send_time = campaign.birthday_send_time or 9.0
            send_datetime = datetime.combine(
                fields.Date.today(), 
                time(hour=int(send_time), minute=int((send_time % 1) * 60))
            )
            
            # If send time has passed today, send now
            if datetime.now().time() >= send_datetime.time():
                self._send_birthday_message(bom_zns_message, campaign_message)
            else:
                # Schedule for later today
                self.env['ir.cron'].create({
                    'name': f'Send Birthday ZNS - {contact.name}',
                    'model_id': self.env.ref('zns_bom_marketing.model_zns_bom_marketing_scheduler').id,
                    'state': 'code',
                    'code': f'model._send_scheduled_message({bom_zns_message.id}, {campaign_message.id})',
                    'nextcall': send_datetime,
                    'numbercall': 1,
                })
                
            _logger.info(f"Birthday message queued for {contact.name} - Phone: {phone}")
            
        except Exception as e:
            _logger.error(f"Failed to queue birthday message for {contact.name}: {e}")
    
    def _send_birthday_message(self, bom_zns_message, campaign_message):
        """Send birthday message using BOM ZNS Simple system"""
        try:
            # Update status to sending
            campaign_message.write({'status': 'sending'})
            
            # Use BOM ZNS Simple's sending method
            if hasattr(bom_zns_message, 'send_message'):
                result = bom_zns_message.send_message()
            elif hasattr(bom_zns_message, 'action_send'):
                result = bom_zns_message.action_send()
            elif hasattr(bom_zns_message, 'send'):
                result = bom_zns_message.send()
            else:
                # Try to find any send method
                send_methods = [method for method in dir(bom_zns_message) if 'send' in method.lower()]
                if send_methods:
                    method = getattr(bom_zns_message, send_methods[0])
                    result = method()
                else:
                    raise Exception("No send method found in bom_zns_simple")
            
            # Update campaign message status based on result
            campaign_message.write({
                'status': 'sent',
                'sent_date': fields.Datetime.now()
            })
            
            _logger.info(f"Birthday message sent successfully via BOM ZNS Simple")
            
        except Exception as e:
            _logger.error(f"Failed to send birthday message via BOM ZNS Simple: {e}")
            campaign_message.write({
                'status': 'failed',
                'error_message': str(e)
            })
    
    def _build_birthday_parameters(self, contact, bom_template):
        """Build birthday-specific parameters for BOM ZNS template"""
        # Calculate age
        today = fields.Date.today()
        age = None
        if contact.birthday:
            try:
                birth_date = fields.Date.from_string(contact.birthday)
                age = today.year - birth_date.year
                if today.month < birth_date.month or (today.month == birth_date.month and today.day < birth_date.day):
                    age -= 1
            except:
                age = None
        
        # Build parameters based on common patterns
        birthday_params = {
            'customer_name': contact.name or '',
            'name': contact.name or '',
            'birthday_age': str(age) if age else '',
            'age': str(age) if age else '',
            'birthday_year': str(today.year),
            'year': str(today.year),
            'customer_phone': contact.mobile or contact.phone or '',
            'phone': contact.mobile or contact.phone or '',
            'customer_email': contact.email or '',
            'email': contact.email or '',
            'company_name': contact.company_id.name if contact.company_id else '',
        }
        
        return birthday_params
    
    def _clean_phone_number(self, phone):
        """Clean phone number format"""
        if not phone:
            return phone
            
        # Remove spaces, dashes, parentheses
        phone = re.sub(r'[\s\-\(\)]', '', phone)
        
        # Handle Vietnamese phone numbers
        if phone.startswith('0'):
            phone = '+84' + phone[1:]
        elif not phone.startswith('+'):
            phone = '+84' + phone
            
        return phone
    
    def _send_scheduled_message(self, zns_message_id, campaign_message_id):
        """Send scheduled message (called by cron job)"""
        bom_zns_message = self.env['bom.zns.message'].browse(zns_message_id)
        campaign_message = self.env['zns.bom.marketing.message'].browse(campaign_message_id)
        
        if bom_zns_message.exists() and campaign_message.exists():
            self._send_birthday_message(bom_zns_message, campaign_message)
    
    @api.model
    def process_scheduled_campaigns(self):
        """Process scheduled campaigns that are due"""
        _logger.info("=== Processing Scheduled Campaigns ===")
        
        # Find campaigns that are scheduled and due
        now = fields.Datetime.now()
        scheduled_campaigns = self.env['zns.bom.marketing.campaign'].search([
            ('status', '=', 'scheduled'),
            ('send_mode', '=', 'scheduled'),
            ('scheduled_date', '<=', now)
        ])
        
        processed = 0
        for campaign in scheduled_campaigns:
            try:
                campaign.status = 'running'
                campaign._execute_campaign()
                processed += 1
                _logger.info(f"Executed scheduled campaign: {campaign.name}")
            except Exception as e:
                _logger.error(f"Failed to execute campaign {campaign.name}: {e}")
                campaign.status = 'failed'
        
        _logger.info(f"=== Processed {processed} scheduled campaigns