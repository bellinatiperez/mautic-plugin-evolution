<?php

return [
    'name'        => 'Evolution WhatsApp Integration',
    'description' => 'Integração completa entre Mautic e Evolution API para WhatsApp Business',
    'version'     => '1.0.0',
    'author'      => 'Evolution Team',
    
    'routes' => [
        'main' => [
            'mautic_evolution_config' => [
                'path'       => '/evolution/config',
                'controller' => 'EvolutionAnalyticsBundle:Config:index',
            ],
            'mautic_evolution_config_save' => [
                'path'       => '/evolution/config/save',
                'controller' => 'EvolutionAnalyticsBundle:Config:save',
                'method'     => 'POST',
            ],
            'mautic_evolution_test_connection' => [
                'path'       => '/evolution/test-connection',
                'controller' => 'EvolutionAnalyticsBundle:Config:testConnection',
                'method'     => 'POST',
            ],
            'mautic_evolution_setup_webhooks' => [
                'path'       => '/evolution/setup-webhooks',
                'controller' => 'EvolutionAnalyticsBundle:Config:setupWebhooks',
                'method'     => 'POST',
            ],
            'mautic_evolution_reset_config' => [
                'path'       => '/evolution/reset-config',
                'controller' => 'EvolutionAnalyticsBundle:Config:resetConfig',
                'method'     => 'POST',
            ],
            'mautic_evolution_quick_stats' => [
                'path'       => '/evolution/quick-stats',
                'controller' => 'EvolutionAnalyticsBundle:Config:quickStats',
                'method'     => 'GET',
            ],
            'mautic_evolution_analytics' => [
                'path'       => '/evolution/analytics',
                'controller' => 'EvolutionAnalyticsBundle:Analytics:index',
            ],
            'mautic_evolution_analytics_data' => [
                'path'       => '/evolution/analytics/data',
                'controller' => 'EvolutionAnalyticsBundle:Analytics:getData',
                'method'     => 'POST',
            ],
            'mautic_evolution_analytics_export' => [
                'path'       => '/evolution/analytics/export',
                'controller' => 'EvolutionAnalyticsBundle:Analytics:export',
                'method'     => 'POST',
            ],
            'mautic_evolution_analytics_realtime' => [
                'path'       => '/evolution/analytics/realtime',
                'controller' => 'EvolutionAnalyticsBundle:Analytics:getRealTimeData',
                'method'     => 'GET',
            ],
            'mautic_evolution_webhook_receive' => [
                'path'       => '/evolution/webhook/{instance}',
                'controller' => 'EvolutionAnalyticsBundle:Webhook:receive',
                'method'     => 'POST',
                'requirements' => [
                    'instance' => '[a-zA-Z0-9_-]+',
                ],
            ],
            'mautic_evolution_webhook_test' => [
                'path'       => '/evolution/webhook/test',
                'controller' => 'EvolutionAnalyticsBundle:Webhook:test',
                'method'     => 'POST',
            ],
            'mautic_evolution_webhook_logs' => [
                'path'       => '/evolution/webhook/logs',
                'controller' => 'EvolutionAnalyticsBundle:Webhook:getLogs',
                'method'     => 'GET',
            ],
        ],
        'api' => [
            'mautic_evolution_api_status' => [
                'path'       => '/evolution/api/status',
                'controller' => 'EvolutionAnalyticsBundle:Api:getStatus',
                'method'     => 'GET',
            ],
            'mautic_evolution_api_send_message' => [
                'path'       => '/evolution/api/send-message',
                'controller' => 'EvolutionAnalyticsBundle:Api:sendMessage',
                'method'     => 'POST',
            ],
            'mautic_evolution_api_get_contacts' => [
                'path'       => '/evolution/api/contacts',
                'controller' => 'EvolutionAnalyticsBundle:Api:getContacts',
                'method'     => 'GET',
            ],
        ],
    ],
    
    'menu' => [
        'main' => [
            'items' => [
                'mautic.evolution.menu.root' => [
                    'id'        => 'mautic_evolution_root',
                    'iconClass' => 'fa-whatsapp',
                    'access'    => ['evolution:config:view'],
                    'parent'    => 'mautic.core.channels',
                    'children'  => [
                        'mautic.evolution.menu.config' => [
                            'route' => 'mautic_evolution_config',
                        ],
                        'mautic.evolution.menu.analytics' => [
                            'route' => 'mautic_evolution_analytics',
                        ],
                    ],
                ],
            ],
        ],
    ],
    
    'services' => [
        'events' => [

            'mautic.evolution.subscriber.lead' => [
                'class'     => \MauticPlugin\EvolutionAnalyticsBundle\EventListener\LeadSubscriber::class,
                'arguments' => [
                    'mautic.evolution.service.api',
                    'mautic.evolution.model.analytics',
                ],
            ],

        ],
        'forms' => [
            'mautic.evolution.form.type.config' => [
                'class'     => \MauticPlugin\EvolutionAnalyticsBundle\Form\Type\ConfigType::class,
                'arguments' => [
                    'translator',
                ],
                'tag' => 'form.type',
                'alias' => 'evolution_config',
            ],
        ],
        'integrations' => [
            'mautic.evolution.integration.evolution' => [
                'class'     => \MauticPlugin\EvolutionAnalyticsBundle\Integration\EvolutionIntegration::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.helper.cache_storage',
                    'doctrine.orm.entity_manager',
                    'request_stack',
                    'router',
                    'translator',
                    'logger',
                    'mautic.helper.encryption',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'mautic.helper.integration',
                    'mautic.form.model.form',
                    'mautic.page.model.trackable',
                    'mautic.page.helper.token',
                    'mautic.asset.helper.token',
                ],
                'tag' => 'mautic.integration',
                'tagArguments' => [
                    'integration' => 'Evolution',
                ],
            ],
        ],
        'other' => [
            'mautic.evolution.service.api' => [
                'class'     => \MauticPlugin\EvolutionAnalyticsBundle\Service\EvolutionApiService::class,
                'arguments' => [
                    'mautic.helper.integration',
                    'monolog.logger.mautic',
                    'mautic.http.client',
                ],
            ],
            'mautic.evolution.service.webhook' => [
                'class'     => \MauticPlugin\EvolutionAnalyticsBundle\Service\WebhookService::class,
                'arguments' => [
                    'mautic.evolution.service.api',
                    'mautic.evolution.model.analytics',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.evolution.model.analytics' => [
                'class'     => \MauticPlugin\EvolutionAnalyticsBundle\Model\AnalyticsModel::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                    'monolog.logger.mautic',
                ],
            ],
        ],
    ],
    
    'parameters' => [
        'evolution_api_timeout' => 30,
        'evolution_webhook_timeout' => 10,
        'evolution_analytics_retention_days' => 90,
        'evolution_max_retry_attempts' => 3,
        'evolution_rate_limit_per_minute' => 60,
        'evolution_batch_size' => 100,
        'evolution_debug_mode' => false,
        'evolution_log_level' => 'info',
        'evolution_cache_ttl' => 3600,
        'evolution_webhook_signature_header' => 'X-Evolution-Signature',
        'evolution_supported_message_types' => [
            'text',
            'image',
            'document',
            'audio',
            'video',
            'location',
            'contact',
        ],
        'evolution_webhook_events' => [
            'MESSAGES_UPSERT',
            'MESSAGES_UPDATE',
            'MESSAGES_DELETE',
            'SEND_MESSAGE',
            'CONTACTS_UPSERT',
            'CONTACTS_UPDATE',
            'CHATS_UPSERT',
            'CHATS_UPDATE',
            'CHATS_DELETE',
            'PRESENCE_UPDATE',
            'CONNECTION_UPDATE',
            'CALL_UPSERT',
            'CALL_UPDATE',
            'GROUPS_UPSERT',
            'GROUPS_UPDATE',
            'GROUP_PARTICIPANTS_UPDATE',
        ],
    ],
    
    'permissions' => [
        'evolution:config' => [
            'view',
            'edit',
        ],
        'evolution:analytics' => [
            'view',
            'export',
        ],
        'evolution:webhooks' => [
            'manage',
        ],
    ],
];
