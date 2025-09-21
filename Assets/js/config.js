/**
 * Evolution Config JavaScript
 */

(function() {
    'use strict';

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeConfig();
    });

    /**
     * Initialize configuration page
     */
    function initializeConfig() {
        bindEventListeners();
        loadQuickStats();
        setupFormValidation();
        setupConditionalFields();
    }

    /**
     * Bind event listeners
     */
    function bindEventListeners() {
        // Test connection button
        const testConnectionBtn = document.getElementById('test-connection');
        if (testConnectionBtn) {
            testConnectionBtn.addEventListener('click', handleTestConnection);
        }

        // Setup webhooks button
        const setupWebhooksBtn = document.getElementById('setup-webhooks');
        if (setupWebhooksBtn) {
            setupWebhooksBtn.addEventListener('click', handleSetupWebhooks);
        }

        // Reset config button
        const resetConfigBtn = document.getElementById('reset-config');
        if (resetConfigBtn) {
            resetConfigBtn.addEventListener('click', handleResetConfig);
        }

        // Webhook enabled checkbox
        const webhookEnabledCheckbox = document.querySelector('input[name="config[webhook_enabled]"]');
        if (webhookEnabledCheckbox) {
            webhookEnabledCheckbox.addEventListener('change', handleWebhookToggle);
        }

        // Form submission
        const configForm = document.querySelector('form');
        if (configForm) {
            configForm.addEventListener('submit', handleFormSubmit);
        }

        // API URL field changes
        const apiUrlField = document.querySelector('input[name="config[api_url]"]');
        if (apiUrlField) {
            apiUrlField.addEventListener('blur', validateApiUrl);
        }

        // API Key field changes
        const apiKeyField = document.querySelector('input[name="config[api_key]"]');
        if (apiKeyField) {
            apiKeyField.addEventListener('blur', validateApiKey);
        }
    }

    /**
     * Handle test connection
     */
    function handleTestConnection() {
        const btn = document.getElementById('test-connection');
        if (!btn) return;

        // Show loading state
        btn.classList.add('loading');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testando...';

        // Get form data
        const formData = getFormData();

        // Make test request
        fetch(window.evolutionConfig.testConnectionUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Conexão testada com sucesso!', 'success');
                updateConnectionStatus(data.status);
            } else {
                showNotification(data.message || 'Erro ao testar conexão', 'error');
                updateConnectionStatus({
                    status: 'error',
                    message: data.message
                });
            }
        })
        .catch(error => {
            console.error('Error testing connection:', error);
            showNotification('Erro ao testar conexão', 'error');
            updateConnectionStatus({
                status: 'error',
                message: 'Erro de rede'
            });
        })
        .finally(() => {
            // Reset button state
            btn.classList.remove('loading');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-refresh"></i> Testar Conexão';
        });
    }

    /**
     * Handle setup webhooks
     */
    function handleSetupWebhooks() {
        const btn = document.getElementById('setup-webhooks');
        if (!btn) return;

        // Show loading state
        btn.classList.add('loading');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Configurando...';

        // Get form data
        const formData = getFormData();

        // Make setup request
        fetch(window.evolutionConfig.setupWebhooksUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Webhooks configurados com sucesso!', 'success');
                updateWebhookStatus(data.webhooks);
            } else {
                showNotification(data.message || 'Erro ao configurar webhooks', 'error');
            }
        })
        .catch(error => {
            console.error('Error setting up webhooks:', error);
            showNotification('Erro ao configurar webhooks', 'error');
        })
        .finally(() => {
            // Reset button state
            btn.classList.remove('loading');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-cog"></i> Configurar Webhooks';
        });
    }

    /**
     * Handle reset config
     */
    function handleResetConfig() {
        if (!confirm('Tem certeza que deseja resetar todas as configurações? Esta ação não pode ser desfeita.')) {
            return;
        }

        const btn = document.getElementById('reset-config');
        if (!btn) return;

        // Show loading state
        btn.classList.add('loading');
        btn.disabled = true;

        // Make reset request
        fetch(window.evolutionConfig.resetConfigUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Configurações resetadas com sucesso!', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showNotification(data.message || 'Erro ao resetar configurações', 'error');
            }
        })
        .catch(error => {
            console.error('Error resetting config:', error);
            showNotification('Erro ao resetar configurações', 'error');
        })
        .finally(() => {
            // Reset button state
            btn.classList.remove('loading');
            btn.disabled = false;
        });
    }

    /**
     * Handle webhook toggle
     */
    function handleWebhookToggle(e) {
        const webhookSettings = document.getElementById('webhook-settings');
        if (webhookSettings) {
            if (e.target.checked) {
                webhookSettings.style.display = 'block';
                slideDown(webhookSettings);
            } else {
                slideUp(webhookSettings, () => {
                    webhookSettings.style.display = 'none';
                });
            }
        }
    }

    /**
     * Handle form submission
     */
    function handleFormSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');

        // Show loading state
        if (submitBtn) {
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        }

        // Validate form
        if (!validateForm(form)) {
            if (submitBtn) {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
            return;
        }

        // Submit form
        const formData = new FormData(form);
        
        fetch(form.action || window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Configurações salvas com sucesso!', 'success');
                
                // Update connection status if provided
                if (data.connectionStatus) {
                    updateConnectionStatus(data.connectionStatus);
                }
            } else {
                showNotification(data.message || 'Erro ao salvar configurações', 'error');
            }
        })
        .catch(error => {
            console.error('Error saving config:', error);
            showNotification('Erro ao salvar configurações', 'error');
        })
        .finally(() => {
            // Reset button state
            if (submitBtn) {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        });
    }

    /**
     * Load quick stats
     */
    function loadQuickStats() {
        fetch(window.evolutionConfig.quickStatsUrl)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.stats) {
                    updateQuickStats(data.stats);
                }
            })
            .catch(error => {
                console.error('Error loading quick stats:', error);
            });
    }

    /**
     * Setup form validation
     */
    function setupFormValidation() {
        // Add real-time validation to required fields
        const requiredFields = document.querySelectorAll('input[required], select[required]');
        requiredFields.forEach(field => {
            field.addEventListener('blur', function() {
                validateField(this);
            });
            
            field.addEventListener('input', function() {
                clearFieldError(this);
            });
        });
    }

    /**
     * Setup conditional fields
     */
    function setupConditionalFields() {
        // Analytics enabled toggle
        const analyticsEnabledCheckbox = document.querySelector('input[name="config[analytics_enabled]"]');
        if (analyticsEnabledCheckbox) {
            analyticsEnabledCheckbox.addEventListener('change', function() {
                toggleAnalyticsFields(this.checked);
            });
            
            // Initial state
            toggleAnalyticsFields(analyticsEnabledCheckbox.checked);
        }

        // Debug mode toggle
        const debugModeCheckbox = document.querySelector('input[name="config[debug_mode]"]');
        if (debugModeCheckbox) {
            debugModeCheckbox.addEventListener('change', function() {
                toggleDebugFields(this.checked);
            });
            
            // Initial state
            toggleDebugFields(debugModeCheckbox.checked);
        }
    }

    /**
     * Toggle analytics fields
     */
    function toggleAnalyticsFields(enabled) {
        const fields = [
            'config[analytics_retention_days]',
            'config[track_message_types]',
            'config[track_response_time]'
        ];
        
        fields.forEach(fieldName => {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.disabled = !enabled;
                field.closest('.form-group').style.opacity = enabled ? '1' : '0.5';
            }
        });
    }

    /**
     * Toggle debug fields
     */
    function toggleDebugFields(enabled) {
        const logLevelField = document.querySelector('select[name="config[log_level]"]');
        if (logLevelField) {
            logLevelField.disabled = !enabled;
            logLevelField.closest('.form-group').style.opacity = enabled ? '1' : '0.5';
        }
    }

    /**
     * Validate API URL
     */
    function validateApiUrl() {
        const field = document.querySelector('input[name="config[api_url]"]');
        if (!field) return true;

        const url = field.value.trim();
        if (!url) {
            setFieldError(field, 'URL da API é obrigatória');
            return false;
        }

        try {
            new URL(url);
            clearFieldError(field);
            return true;
        } catch (e) {
            setFieldError(field, 'URL inválida');
            return false;
        }
    }

    /**
     * Validate API Key
     */
    function validateApiKey() {
        const field = document.querySelector('input[name="config[api_key]"]');
        if (!field) return true;

        const key = field.value.trim();
        if (!key) {
            setFieldError(field, 'Chave da API é obrigatória');
            return false;
        }

        if (key.length < 10) {
            setFieldError(field, 'Chave da API muito curta');
            return false;
        }

        clearFieldError(field);
        return true;
    }

    /**
     * Validate entire form
     */
    function validateForm(form) {
        let isValid = true;

        // Validate required fields
        const requiredFields = form.querySelectorAll('input[required], select[required]');
        requiredFields.forEach(field => {
            if (!validateField(field)) {
                isValid = false;
            }
        });

        // Custom validations
        if (!validateApiUrl()) isValid = false;
        if (!validateApiKey()) isValid = false;

        return isValid;
    }

    /**
     * Validate individual field
     */
    function validateField(field) {
        if (field.hasAttribute('required') && !field.value.trim()) {
            setFieldError(field, 'Este campo é obrigatório');
            return false;
        }

        clearFieldError(field);
        return true;
    }

    /**
     * Set field error
     */
    function setFieldError(field, message) {
        const formGroup = field.closest('.form-group');
        if (!formGroup) return;

        formGroup.classList.add('has-error');
        
        // Remove existing error message
        const existingError = formGroup.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }

        // Add new error message
        const errorElement = document.createElement('div');
        errorElement.className = 'field-error text-danger';
        errorElement.textContent = message;
        formGroup.appendChild(errorElement);
    }

    /**
     * Clear field error
     */
    function clearFieldError(field) {
        const formGroup = field.closest('.form-group');
        if (!formGroup) return;

        formGroup.classList.remove('has-error');
        
        const errorElement = formGroup.querySelector('.field-error');
        if (errorElement) {
            errorElement.remove();
        }
    }

    /**
     * Get form data as object
     */
    function getFormData() {
        const form = document.querySelector('form');
        if (!form) return {};

        const formData = new FormData(form);
        const data = {};

        for (let [key, value] of formData.entries()) {
            // Handle nested config keys
            if (key.startsWith('config[') && key.endsWith(']')) {
                const configKey = key.slice(7, -1);
                data[configKey] = value;
            } else {
                data[key] = value;
            }
        }

        return data;
    }

    /**
     * Update connection status
     */
    function updateConnectionStatus(status) {
        const statusPanel = document.querySelector('.panel-success, .panel-danger');
        if (!statusPanel) return;

        // Update panel class
        statusPanel.className = statusPanel.className.replace(/panel-(success|danger)/, 
            `panel-${status.status === 'connected' ? 'success' : 'danger'}`);

        // Update icon
        const icon = statusPanel.querySelector('.fa');
        if (icon) {
            icon.className = icon.className.replace(/fa-(check-circle|times-circle)/, 
                `fa-${status.status === 'connected' ? 'check-circle' : 'times-circle'}`);
        }

        // Update status text
        const statusLabel = statusPanel.querySelector('.label');
        if (statusLabel) {
            statusLabel.className = statusLabel.className.replace(/label-(success|danger)/, 
                `label-${status.status === 'connected' ? 'success' : 'danger'}`);
            statusLabel.textContent = status.status === 'connected' ? 'Conectado' : 'Desconectado';
        }

        // Update message
        const messageElement = statusPanel.querySelector('p:nth-child(2)');
        if (messageElement && status.message) {
            messageElement.innerHTML = `<strong>Mensagem:</strong> ${status.message}`;
        }

        // Update last check time
        const lastCheckElement = statusPanel.querySelector('p:last-child');
        if (lastCheckElement) {
            const now = new Date();
            lastCheckElement.innerHTML = `<strong>Última verificação:</strong> ${now.toLocaleString('pt-BR')}`;
        }
    }

    /**
     * Update webhook status
     */
    function updateWebhookStatus(webhooks) {
        const webhookList = document.querySelector('.webhook-status .list-group');
        if (!webhookList || !webhooks) return;

        webhookList.innerHTML = '';

        Object.entries(webhooks).forEach(([event, status]) => {
            const item = document.createElement('div');
            item.className = 'list-group-item';
            item.innerHTML = `
                <div class="row">
                    <div class="col-xs-8">
                        <strong>${event}</strong>
                    </div>
                    <div class="col-xs-4 text-right">
                        <span class="label label-${status.active ? 'success' : 'default'}">
                            ${status.active ? 'Ativo' : 'Inativo'}
                        </span>
                    </div>
                </div>
                ${status.last_received ? `<small class="text-muted">Último: ${new Date(status.last_received * 1000).toLocaleString('pt-BR')}</small>` : ''}
            `;
            webhookList.appendChild(item);
        });
    }

    /**
     * Update quick stats
     */
    function updateQuickStats(stats) {
        updateStatElement('total-messages', stats.total_messages);
        updateStatElement('total-contacts', stats.total_contacts);
        updateStatElement('today-messages', stats.today_messages);
        updateStatElement('active-instances', stats.active_instances);
    }

    /**
     * Update individual stat element
     */
    function updateStatElement(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = formatNumber(value || 0);
        }
    }

    /**
     * Format number with thousands separator
     */
    function formatNumber(num) {
        return new Intl.NumberFormat('pt-BR').format(num);
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
        const container = document.querySelector('.evolution-config');
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
     * Slide down animation
     */
    function slideDown(element) {
        element.style.height = '0';
        element.style.overflow = 'hidden';
        element.style.transition = 'height 0.3s ease';
        
        setTimeout(() => {
            element.style.height = element.scrollHeight + 'px';
            
            setTimeout(() => {
                element.style.height = 'auto';
                element.style.overflow = 'visible';
            }, 300);
        }, 10);
    }

    /**
     * Slide up animation
     */
    function slideUp(element, callback) {
        element.style.height = element.scrollHeight + 'px';
        element.style.overflow = 'hidden';
        element.style.transition = 'height 0.3s ease';
        
        setTimeout(() => {
            element.style.height = '0';
            
            setTimeout(() => {
                if (callback) callback();
            }, 300);
        }, 10);
    }

    // Auto-refresh quick stats every 30 seconds
    setInterval(loadQuickStats, 30000);

})();