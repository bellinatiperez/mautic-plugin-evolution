/**
 * Evolution Analytics Dashboard JavaScript
 */

(function() {
    'use strict';

    // Global variables
    let charts = {};
    let refreshInterval;
    let isRealTimeEnabled = false;

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeAnalytics();
    });

    /**
     * Initialize analytics dashboard
     */
    function initializeAnalytics() {
        initializeCharts();
        bindEventListeners();
        loadInitialData();
        setupRealTimeUpdates();
    }

    /**
     * Initialize all charts
     */
    function initializeCharts() {
        initializeDailyMessagesChart();
        initializeMessageTypesChart();
        initializeHourlyDistributionChart();
    }

    /**
     * Initialize daily messages chart
     */
    function initializeDailyMessagesChart() {
        const ctx = document.getElementById('dailyMessagesChart');
        if (!ctx) return;

        const data = window.evolutionAnalyticsData?.dailyStats || [];
        
        charts.dailyMessages = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map(item => item.date),
                datasets: [{
                    label: 'Mensagens Enviadas',
                    data: data.map(item => item.sent_messages),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Mensagens Recebidas',
                    data: data.map(item => item.received_messages),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Data'
                        }
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Quantidade de Mensagens'
                        },
                        beginAtZero: true
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }

    /**
     * Initialize message types chart
     */
    function initializeMessageTypesChart() {
        const ctx = document.getElementById('messageTypesChart');
        if (!ctx) return;

        const data = window.evolutionAnalyticsData?.messageTypes || [];
        
        charts.messageTypes = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(item => item.type),
                datasets: [{
                    data: data.map(item => item.count),
                    backgroundColor: [
                        '#007bff',
                        '#28a745',
                        '#ffc107',
                        '#dc3545',
                        '#6f42c1',
                        '#fd7e14',
                        '#20c997'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${context.label}: ${context.parsed} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize hourly distribution chart
     */
    function initializeHourlyDistributionChart() {
        const ctx = document.getElementById('hourlyDistributionChart');
        if (!ctx) return;

        const data = window.evolutionAnalyticsData?.hourlyDistribution || [];
        
        charts.hourlyDistribution = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: Array.from({length: 24}, (_, i) => `${i.toString().padStart(2, '0')}:00`),
                datasets: [{
                    label: 'Mensagens por Hora',
                    data: Array.from({length: 24}, (_, i) => {
                        const hourData = data.find(item => item.hour === i);
                        return hourData ? hourData.count : 0;
                    }),
                    backgroundColor: 'rgba(0, 123, 255, 0.6)',
                    borderColor: '#007bff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                return `Hora: ${context[0].label}`;
                            },
                            label: function(context) {
                                return `Mensagens: ${context.parsed.y}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Hora do Dia'
                        }
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Quantidade de Mensagens'
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    /**
     * Bind event listeners
     */
    function bindEventListeners() {
        // Filter form submission
        const filterForm = document.getElementById('analytics-filters');
        if (filterForm) {
            filterForm.addEventListener('submit', handleFilterSubmit);
        }

        // Refresh button
        const refreshBtn = document.getElementById('refresh-data');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', handleRefreshData);
        }

        // Real-time toggle (if exists)
        const realtimeToggle = document.getElementById('realtime-toggle');
        if (realtimeToggle) {
            realtimeToggle.addEventListener('change', handleRealtimeToggle);
        }

        // Export buttons
        bindExportListeners();
    }

    /**
     * Handle filter form submission
     */
    function handleFilterSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const params = new URLSearchParams();
        
        for (let [key, value] of formData.entries()) {
            if (value) {
                params.append(key, value);
            }
        }
        
        // Reload page with new filters
        window.location.search = params.toString();
    }

    /**
     * Handle refresh data button
     */
    function handleRefreshData() {
        const btn = document.getElementById('refresh-data');
        if (btn) {
            btn.classList.add('loading');
            btn.disabled = true;
        }
        
        // Get current filters
        const filters = getCurrentFilters();
        
        // Fetch updated data
        fetchAnalyticsData(filters)
            .then(data => {
                updateCharts(data);
                updateStatistics(data);
                showNotification('Dados atualizados com sucesso!', 'success');
            })
            .catch(error => {
                console.error('Error refreshing data:', error);
                showNotification('Erro ao atualizar dados', 'error');
            })
            .finally(() => {
                if (btn) {
                    btn.classList.remove('loading');
                    btn.disabled = false;
                }
            });
    }

    /**
     * Handle real-time toggle
     */
    function handleRealtimeToggle(e) {
        isRealTimeEnabled = e.target.checked;
        
        if (isRealTimeEnabled) {
            startRealTimeUpdates();
            showNotification('Atualizações em tempo real ativadas', 'info');
        } else {
            stopRealTimeUpdates();
            showNotification('Atualizações em tempo real desativadas', 'info');
        }
    }

    /**
     * Bind export button listeners
     */
    function bindExportListeners() {
        const exportButtons = document.querySelectorAll('[href*="export"]');
        exportButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href) {
                    showNotification('Iniciando download...', 'info');
                }
            });
        });
    }

    /**
     * Load initial data
     */
    function loadInitialData() {
        // Data is already loaded from PHP, but we can enhance it here
        if (window.evolutionAnalyticsData) {
            updateStatistics(window.evolutionAnalyticsData);
        }
    }

    /**
     * Setup real-time updates
     */
    function setupRealTimeUpdates() {
        // Check if real-time is enabled by default
        const realtimeToggle = document.getElementById('realtime-toggle');
        if (realtimeToggle && realtimeToggle.checked) {
            isRealTimeEnabled = true;
            startRealTimeUpdates();
        }
    }

    /**
     * Start real-time updates
     */
    function startRealTimeUpdates() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
        
        refreshInterval = setInterval(() => {
            if (isRealTimeEnabled) {
                fetchRealTimeData();
            }
        }, 30000); // Update every 30 seconds
    }

    /**
     * Stop real-time updates
     */
    function stopRealTimeUpdates() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }

    /**
     * Fetch real-time data
     */
    function fetchRealTimeData() {
        const filters = getCurrentFilters();
        
        fetch('/s/evolution/analytics/realtime?' + new URLSearchParams(filters))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateRealTimeStats(data.data);
                }
            })
            .catch(error => {
                console.error('Error fetching real-time data:', error);
            });
    }

    /**
     * Fetch analytics data
     */
    function fetchAnalyticsData(filters = {}) {
        return fetch('/s/evolution/analytics/data?' + new URLSearchParams(filters))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    return data.data;
                } else {
                    throw new Error(data.message || 'Failed to fetch data');
                }
            });
    }

    /**
     * Update charts with new data
     */
    function updateCharts(data) {
        // Update daily messages chart
        if (charts.dailyMessages && data.dailyStats) {
            charts.dailyMessages.data.labels = data.dailyStats.map(item => item.date);
            charts.dailyMessages.data.datasets[0].data = data.dailyStats.map(item => item.sent_messages);
            charts.dailyMessages.data.datasets[1].data = data.dailyStats.map(item => item.received_messages);
            charts.dailyMessages.update();
        }

        // Update message types chart
        if (charts.messageTypes && data.messageTypes) {
            charts.messageTypes.data.labels = data.messageTypes.map(item => item.type);
            charts.messageTypes.data.datasets[0].data = data.messageTypes.map(item => item.count);
            charts.messageTypes.update();
        }

        // Update hourly distribution chart
        if (charts.hourlyDistribution && data.hourlyDistribution) {
            charts.hourlyDistribution.data.datasets[0].data = Array.from({length: 24}, (_, i) => {
                const hourData = data.hourlyDistribution.find(item => item.hour === i);
                return hourData ? hourData.count : 0;
            });
            charts.hourlyDistribution.update();
        }
    }

    /**
     * Update statistics cards
     */
    function updateStatistics(data) {
        if (data.stats) {
            updateStatCard('total-messages', data.stats.total_messages);
            updateStatCard('sent-messages', data.stats.sent_messages);
            updateStatCard('received-messages', data.stats.received_messages);
            updateStatCard('unique-contacts', data.stats.unique_contacts);
        }
    }

    /**
     * Update real-time statistics
     */
    function updateRealTimeStats(data) {
        if (data.stats) {
            updateStatCard('total-messages', data.stats.total_messages, true);
            updateStatCard('today-messages', data.stats.today_messages, true);
            updateStatCard('active-instances', data.stats.active_instances, true);
        }
    }

    /**
     * Update individual stat card
     */
    function updateStatCard(elementId, value, animate = false) {
        const element = document.getElementById(elementId);
        if (element) {
            if (animate) {
                animateNumber(element, parseInt(element.textContent.replace(/[^\d]/g, '')) || 0, value);
            } else {
                element.textContent = formatNumber(value);
            }
        }
    }

    /**
     * Animate number change
     */
    function animateNumber(element, from, to, duration = 1000) {
        const start = Date.now();
        const timer = setInterval(() => {
            const now = Date.now();
            const progress = Math.min((now - start) / duration, 1);
            const current = Math.floor(from + (to - from) * progress);
            
            element.textContent = formatNumber(current);
            
            if (progress === 1) {
                clearInterval(timer);
            }
        }, 16);
    }

    /**
     * Format number with thousands separator
     */
    function formatNumber(num) {
        return new Intl.NumberFormat('pt-BR').format(num || 0);
    }

    /**
     * Get current filters from form
     */
    function getCurrentFilters() {
        const form = document.getElementById('analytics-filters');
        if (!form) return {};
        
        const formData = new FormData(form);
        const filters = {};
        
        for (let [key, value] of formData.entries()) {
            if (value) {
                filters[key] = value;
            }
        }
        
        return filters;
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade in`;
        notification.innerHTML = `
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            ${message}
        `;
        
        // Add to page
        const container = document.querySelector('.evolution-analytics-dashboard');
        if (container) {
            container.insertBefore(notification, container.firstChild);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 5000);
        }
    }

    /**
     * Cleanup on page unload
     */
    window.addEventListener('beforeunload', function() {
        stopRealTimeUpdates();
        
        // Destroy charts
        Object.values(charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
    });

    // Export functions for external use
    window.EvolutionAnalytics = {
        refreshData: handleRefreshData,
        toggleRealTime: handleRealtimeToggle,
        updateCharts: updateCharts,
        updateStatistics: updateStatistics
    };

})();