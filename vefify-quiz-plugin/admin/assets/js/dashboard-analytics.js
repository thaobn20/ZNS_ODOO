/**
 * Dashboard Analytics JavaScript
 * File: admin/js/dashboard-analytics.js
 */

var VefifyDashboard = {
    
    /**
     * Initialize dashboard
     */
    init: function(analyticsData) {
        this.data = analyticsData;
        this.bindEvents();
        this.renderCharts();
        this.startAutoRefresh();
        
        console.log('Vefify Dashboard initialized', analyticsData);
    },
    
    /**
     * Bind event handlers
     */
    bindEvents: function() {
        var self = this;
        
        // Module card hover effects
        jQuery('.vefify-module-card').on('mouseenter', function() {
            jQuery(this).addClass('hovered');
        }).on('mouseleave', function() {
            jQuery(this).removeClass('hovered');
        });
        
        // Quick action buttons
        jQuery('.module-actions .button').on('click', function(e) {
            var $button = jQuery(this);
            $button.addClass('loading');
            
            // Remove loading class after navigation
            setTimeout(function() {
                $button.removeClass('loading');
            }, 1000);
        });
        
        // Health check refresh
        jQuery('.health-actions .button').on('click', function(e) {
            e.preventDefault();
            self.refreshHealthCheck();
        });
        
        // Stat card click animation
        jQuery('.vefify-stat-card').on('click', function() {
            jQuery(this).addClass('clicked');
            setTimeout(function() {
                jQuery('.vefify-stat-card.clicked').removeClass('clicked');
            }, 200);
        });
    },
    
    /**
     * Render charts and visualizations
     */
    renderCharts: function() {
        this.renderTrendsChart();
        this.renderQuickStats();
        this.animateCounters();
    },
    
    /**
     * Render trends chart
     */
    renderTrendsChart: function() {
        var canvas = document.getElementById('trendsChart');
        if (!canvas || !this.data.trends) {
            return;
        }
        
        var ctx = canvas.getContext('2d');
        var trends = this.data.trends;
        
        // Prepare chart data
        var labels = trends.map(function(item) {
            return new Date(item.date).toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric' 
            });
        }).reverse();
        
        var participantsData = trends.map(function(item) {
            return parseInt(item.participants);
        }).reverse();
        
        var completedData = trends.map(function(item) {
            return parseInt(item.completed);
        }).reverse();
        
        // Simple chart implementation (you can replace with Chart.js for more features)
        this.drawSimpleLineChart(ctx, {
            labels: labels,
            datasets: [
                {
                    label: 'Participants',
                    data: participantsData,
                    color: '#007cba'
                },
                {
                    label: 'Completed',
                    data: completedData,
                    color: '#46b450'
                }
            ]
        });
    },
    
    /**
     * Simple line chart implementation
     */
    drawSimpleLineChart: function(ctx, chartData) {
        var canvas = ctx.canvas;
        var width = canvas.width;
        var height = canvas.height;
        var padding = 40;
        
        // Clear canvas
        ctx.clearRect(0, 0, width, height);
        
        // Set styles
        ctx.font = '12px Arial';
        ctx.lineWidth = 2;
        
        if (chartData.datasets.length === 0 || chartData.labels.length === 0) {
            ctx.fillStyle = '#666';
            ctx.textAlign = 'center';
            ctx.fillText('No data available', width / 2, height / 2);
            return;
        }
        
        // Calculate scales
        var maxValue = Math.max(...chartData.datasets.flatMap(d => d.data));
        var minValue = Math.min(...chartData.datasets.flatMap(d => d.data));
        var valueRange = maxValue - minValue || 1;
        
        var chartWidth = width - (padding * 2);
        var chartHeight = height - (padding * 2);
        var stepX = chartWidth / (chartData.labels.length - 1);
        
        // Draw grid lines
        ctx.strokeStyle = '#e1e1e1';
        ctx.lineWidth = 1;
        
        // Vertical grid lines
        for (var i = 0; i < chartData.labels.length; i++) {
            var x = padding + (i * stepX);
            ctx.beginPath();
            ctx.moveTo(x, padding);
            ctx.lineTo(x, height - padding);
            ctx.stroke();
        }
        
        // Horizontal grid lines
        for (var i = 0; i <= 5; i++) {
            var y = padding + (i * chartHeight / 5);
            ctx.beginPath();
            ctx.moveTo(padding, y);
            ctx.lineTo(width - padding, y);
            ctx.stroke();
        }
        
        // Draw datasets
        chartData.datasets.forEach(function(dataset, datasetIndex) {
            ctx.strokeStyle = dataset.color;
            ctx.fillStyle = dataset.color;
            ctx.lineWidth = 2;
            
            // Draw line
            ctx.beginPath();
            dataset.data.forEach(function(value, index) {
                var x = padding + (index * stepX);
                var y = height - padding - ((value - minValue) / valueRange * chartHeight);
                
                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });
            ctx.stroke();
            
            // Draw points
            dataset.data.forEach(function(value, index) {
                var x = padding + (index * stepX);
                var y = height - padding - ((value - minValue) / valueRange * chartHeight);
                
                ctx.beginPath();
                ctx.arc(x, y, 3, 0, 2 * Math.PI);
                ctx.fill();
            });
        });
        
        // Draw labels
        ctx.fillStyle = '#666';
        ctx.textAlign = 'center';
        chartData.labels.forEach(function(label, index) {
            var x = padding + (index * stepX);
            ctx.fillText(label, x, height - 10);
        });
        
        // Draw legend
        var legendY = 20;
        chartData.datasets.forEach(function(dataset, index) {
            var legendX = padding + (index * 100);
            
            ctx.fillStyle = dataset.color;
            ctx.fillRect(legendX, legendY, 15, 10);
            
            ctx.fillStyle = '#333';
            ctx.textAlign = 'left';
            ctx.fillText(dataset.label, legendX + 20, legendY + 8);
        });
    },
    
    /**
     * Animate counter numbers
     */
    animateCounters: function() {
        jQuery('.stat-value').each(function() {
            var $this = jQuery(this);
            var text = $this.text();
            var number = parseInt(text.replace(/[^0-9]/g, ''));
            
            if (!isNaN(number) && number > 0) {
                $this.text('0');
                $this.animate({ opacity: 1 }, {
                    duration: 1000,
                    step: function(now) {
                        var current = Math.ceil(number * now / 1000);
                        $this.text(text.replace(/[0-9,]+/, current.toLocaleString()));
                    }
                });
            }
        });
    },
    
    /**
     * Refresh dashboard data
     */
    refreshData: function() {
        var self = this;
        
        jQuery.ajax({
            url: this.data.ajax_url,
            type: 'POST',
            data: {
                action: 'vefify_refresh_dashboard',
                nonce: this.data.nonce
            },
            success: function(response) {
                if (response.success) {
                    self.updateDashboard(response.data);
                }
            },
            error: function() {
                console.log('Failed to refresh dashboard data');
            }
        });
    },
    
    /**
     * Update dashboard with new data
     */
    updateDashboard: function(newData) {
        // Update quick stats
        if (newData.quick_stats) {
            newData.quick_stats.forEach(function(stat, index) {
                var $card = jQuery('.vefify-stat-card').eq(index);
                var $value = $card.find('.stat-value');
                
                if ($value.text() !== stat.value) {
                    $value.addClass('updating');
                    setTimeout(function() {
                        $value.text(stat.value).removeClass('updating');
                    }, 300);
                }
            });
        }
        
        // Update module stats
        if (newData.modules) {
            Object.keys(newData.modules).forEach(function(moduleKey) {
                var module = newData.modules[moduleKey];
                var $moduleCard = jQuery('.vefify-module-card[data-module="' + moduleKey + '"]');
                
                if (module.stats) {
                    Object.keys(module.stats).forEach(function(statKey) {
                        var stat = module.stats[statKey];
                        var $statItem = $moduleCard.find('.stat-item').eq(
                            Object.keys(module.stats).indexOf(statKey)
                        );
                        var $statNumber = $statItem.find('.stat-number');
                        
                        if ($statNumber.length && $statNumber.text() !== stat.value) {
                            $statNumber.addClass('updating');
                            setTimeout(function() {
                                $statNumber.text(stat.value).removeClass('updating');
                            }, 300);
                        }
                    });
                }
            });
        }
        
        console.log('Dashboard updated with new data');
    },
    
    /**
     * Refresh health check
     */
    refreshHealthCheck: function() {
        var self = this;
        var $button = jQuery('.health-actions .button');
        
        $button.addClass('loading').text('Checking...');
        
        jQuery.ajax({
            url: this.data.ajax_url,
            type: 'POST',
            data: {
                action: 'vefify_health_check',
                nonce: this.data.nonce
            },
            success: function(response) {
                if (response.success) {
                    self.updateHealthStatus(response.data);
                }
            },
            error: function() {
                console.log('Health check failed');
            },
            complete: function() {
                $button.removeClass('loading').text('View Detailed Health Report');
            }
        });
    },
    
    /**
     * Update health status display
     */
    updateHealthStatus: function(healthData) {
        jQuery('.health-item').each(function() {
            var $item = jQuery(this);
            var label = $item.find('.health-label').text();
            
            if (healthData[label]) {
                var $status = $item.find('.health-status, .health-value');
                $status.removeClass('status-good status-warning status-error');
                
                if (healthData[label].status) {
                    $status.addClass('status-' + healthData[label].status);
                    $status.text(healthData[label].message);
                }
            }
        });
    },
    
    /**
     * Start auto-refresh timer
     */
    startAutoRefresh: function() {
        var self = this;
        
        // Refresh every 5 minutes
        setInterval(function() {
            self.refreshData();
        }, 300000);
        
        // Add visibility change handler to refresh when page becomes visible
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                self.refreshData();
            }
        });
    },
    
    /**
     * Show loading state
     */
    showLoading: function($element) {
        $element.addClass('loading');
        if (!$element.find('.vefify-loading').length) {
            $element.append('<span class="vefify-loading"></span>');
        }
    },
    
    /**
     * Hide loading state
     */
    hideLoading: function($element) {
        $element.removeClass('loading');
        $element.find('.vefify-loading').remove();
    },
    
    /**
     * Format numbers for display
     */
    formatNumber: function(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(1) + 'K';
        }
        return num.toString();
    },
    
    /**
     * Show notification
     */
    showNotification: function(message, type) {
        type = type || 'info';
        
        var $notification = jQuery('<div class="vefify-notification notification-' + type + '">')
            .text(message)
            .appendTo('body');
        
        setTimeout(function() {
            $notification.addClass('show');
        }, 100);
        
        setTimeout(function() {
            $notification.removeClass('show');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, 3000);
    }
};

// Initialize when document is ready
jQuery(document).ready(function($) {
    // Add CSS for notifications
    if (!$('#vefify-notification-styles').length) {
        $('<style id="vefify-notification-styles">')
            .html(`
                .vefify-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    border-radius: 4px;
                    color: white;
                    font-weight: 500;
                    z-index: 10000;
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
                }
                .vefify-notification.show {
                    transform: translateX(0);
                }
                .notification-info { background: #007cba; }
                .notification-success { background: #46b450; }
                .notification-warning { background: #f56e28; }
                .notification-error { background: #dc3232; }
            `)
            .appendTo('head');
    }
    
    // Add CSS for updating states
    if (!$('#vefify-update-styles').length) {
        $('<style id="vefify-update-styles">')
            .html(`
                .updating {
                    position: relative;
                    overflow: hidden;
                }
                .updating::after {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: -100%;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.8), transparent);
                    animation: shimmer 1s ease-in-out;
                }
                @keyframes shimmer {
                    0% { left: -100%; }
                    100% { left: 100%; }
                }
                .loading {
                    opacity: 0.7;
                    pointer-events: none;
                }
                .clicked {
                    transform: scale(0.98);
                }
            `)
            .appendTo('head');
    }
});