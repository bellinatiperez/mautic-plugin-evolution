<?php

namespace MauticPlugin\EvolutionWhatsAppBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractStandardFormController;
use MauticPlugin\EvolutionWhatsAppBundle\Form\Type\ConfigType;
use MauticPlugin\EvolutionWhatsAppBundle\Service\EvolutionApiService;
use MauticPlugin\EvolutionWhatsAppBundle\Model\AnalyticsModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Config Controller
 * 
 * Handles plugin configuration and settings
 */
class ConfigController extends AbstractStandardFormController
{
    private EvolutionApiService $evolutionApiService;
    private AnalyticsModel $analyticsModel;

    public function __construct(
        EvolutionApiService $evolutionApiService,
        AnalyticsModel $analyticsModel
    ) {
        $this->evolutionApiService = $evolutionApiService;
        $this->analyticsModel = $analyticsModel;
    }

    /**
     * Get model name for AbstractStandardFormController
     */
    protected function getModelName(): string
    {
        return 'evolution.config';
    }

    /**
     * Configuration form
     */
    public function indexAction(Request $request): Response
    {
        // Check permissions
        if (!$this->get('mautic.security')->isGranted('plugin:evolution_analytics:manage')) {
            return $this->accessDenied();
        }

        // Get current configuration
        $config = $this->evolutionApiService->getConfig();

        // Create form
        $form = $this->createForm(ConfigType::class, $config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();
                
                // Save configuration
                $this->evolutionApiService->saveConfig($data);

                // Test connection if requested
                if ($request->get('test_connection')) {
                    $testResult = $this->evolutionApiService->testConnection();
                    if ($testResult['success']) {
                        $this->addFlash('mautic.core.success', 'Connection test successful!');
                    } else {
                        $this->addFlash('mautic.core.error', 'Connection test failed: ' . $testResult['error']);
                    }
                } else {
                    $this->addFlash('mautic.core.success', 'Configuration saved successfully!');
                }

                // Setup webhooks if enabled
                if ($data['enable_webhooks']) {
                    $this->setupWebhooks($data);
                }

                // Create database tables if needed
                $this->analyticsModel->createTables();

                return $this->redirectToRoute('mautic_evolution_config_index');

            } catch (\Exception $e) {
                $this->addFlash('mautic.core.error', 'Error saving configuration: ' . $e->getMessage());
            }
        }

        return $this->delegateView([
            'viewParameters' => [
                'form' => $form->createView(),
                'config' => $config
            ],
            'contentTemplate' => 'EvolutionAnalyticsBundle:Config:index.html.php',
            'passthroughVars' => [
                'activeLink' => '#mautic_evolution_config_index',
                'mauticContent' => 'evolutionConfig'
            ]
        ]);
    }

    /**
     * Test API connection
     */
    public function testConnectionAction(Request $request): JsonResponse
    {
        // Check permissions
        if (!$this->get('mautic.security')->isGranted('plugin:evolution_analytics:manage')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        try {
            $apiUrl = $request->get('api_url');
            $apiKey = $request->get('api_key');
            $instanceName = $request->get('instance_name');

            if (!$apiUrl || !$apiKey) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'API URL and API Key are required'
                ]);
            }

            // Temporarily set config for testing
            $originalConfig = $this->evolutionApiService->getConfig();
            $testConfig = array_merge($originalConfig, [
                'api_url' => $apiUrl,
                'api_key' => $apiKey,
                'instance_name' => $instanceName
            ]);
            $this->evolutionApiService->setConfig($testConfig);

            // Test connection
            $result = $this->evolutionApiService->testConnection($instanceName);

            // Restore original config
            $this->evolutionApiService->setConfig($originalConfig);

            return new JsonResponse($result);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get instances from Evolution API
     */
    public function getInstancesAction(Request $request): JsonResponse
    {
        // Check permissions
        if (!$this->get('mautic.security')->isGranted('plugin:evolution_analytics:manage')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        try {
            $instances = $this->evolutionApiService->getInstances();
            
            return new JsonResponse([
                'success' => true,
                'instances' => $instances
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Setup webhooks
     */
    public function setupWebhooksAction(Request $request): JsonResponse
    {
        // Check permissions
        if (!$this->get('mautic.security')->isGranted('plugin:evolution_analytics:manage')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        try {
            $instanceName = $request->get('instance_name');
            $events = $request->get('events', []);

            if (!$instanceName) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Instance name is required'
                ]);
            }

            $result = $this->setupWebhooks([
                'instance_name' => $instanceName,
                'webhook_events' => $events
            ]);

            return new JsonResponse([
                'success' => true,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Remove webhooks
     */
    public function removeWebhooksAction(Request $request): JsonResponse
    {
        // Check permissions
        if (!$this->get('mautic.security')->isGranted('plugin:evolution_analytics:manage')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        try {
            $instanceName = $request->get('instance_name');

            if (!$instanceName) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Instance name is required'
                ]);
            }

            $result = $this->evolutionApiService->removeWebhook($instanceName);

            return new JsonResponse([
                'success' => true,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Reset plugin data
     */
    public function resetDataAction(Request $request): JsonResponse
    {
        // Check permissions
        if (!$this->get('mautic.security')->isGranted('plugin:evolution_analytics:manage')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        if ($request->getMethod() !== 'POST') {
            return new JsonResponse(['error' => 'Method not allowed'], 405);
        }

        try {
            $type = $request->get('type', 'all');
            
            switch ($type) {
                case 'messages':
                    $this->resetMessagesData();
                    break;
                case 'chats':
                    $this->resetChatsData();
                    break;
                case 'connections':
                    $this->resetConnectionsData();
                    break;
                case 'presence':
                    $this->resetPresenceData();
                    break;
                case 'all':
                default:
                    $this->resetAllData();
                    break;
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Data reset successfully'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get plugin status
     */
    public function statusAction(Request $request): JsonResponse
    {
        // Check permissions
        if (!$this->get('mautic.security')->isGranted('plugin:evolution_analytics:view')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        try {
            $config = $this->evolutionApiService->getConfig();
            $status = [
                'configured' => !empty($config['api_url']) && !empty($config['api_key']),
                'connected' => false,
                'webhooks_enabled' => $config['enable_webhooks'] ?? false,
                'instances' => [],
                'database_tables' => $this->checkDatabaseTables(),
                'last_activity' => $this->getLastActivity()
            ];

            // Test connection if configured
            if ($status['configured']) {
                try {
                    $connectionTest = $this->evolutionApiService->testConnection();
                    $status['connected'] = $connectionTest['success'];
                    
                    if ($status['connected']) {
                        $status['instances'] = $this->evolutionApiService->getInstances();
                    }
                } catch (\Exception $e) {
                    $status['connection_error'] = $e->getMessage();
                }
            }

            return new JsonResponse([
                'success' => true,
                'status' => $status
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Setup webhooks for Evolution API
     */
    private function setupWebhooks(array $config): array
    {
        $instanceName = $config['instance_name'];
        $events = $config['webhook_events'] ?? [
            'MESSAGES_UPSERT',
            'MESSAGES_UPDATE',
            'CONTACTS_UPSERT',
            'CONTACTS_UPDATE',
            'CHATS_UPSERT',
            'CONNECTION_UPDATE',
            'PRESENCE_UPDATE'
        ];

        // Generate webhook URL
        $webhookUrl = $this->generateUrl('mautic_evolution_webhook', [], true);

        return $this->evolutionApiService->setupWebhook($instanceName, $webhookUrl, $events);
    }

    /**
     * Reset messages data
     */
    private function resetMessagesData(): void
    {
        $this->getDoctrine()->getConnection()->executeStatement('TRUNCATE TABLE evolution_messages');
    }

    /**
     * Reset chats data
     */
    private function resetChatsData(): void
    {
        $this->getDoctrine()->getConnection()->executeStatement('TRUNCATE TABLE evolution_chats');
    }

    /**
     * Reset connections data
     */
    private function resetConnectionsData(): void
    {
        $this->getDoctrine()->getConnection()->executeStatement('TRUNCATE TABLE evolution_connections');
    }

    /**
     * Reset presence data
     */
    private function resetPresenceData(): void
    {
        $this->getDoctrine()->getConnection()->executeStatement('TRUNCATE TABLE evolution_presence');
    }

    /**
     * Reset all data
     */
    private function resetAllData(): void
    {
        $this->resetMessagesData();
        $this->resetChatsData();
        $this->resetConnectionsData();
        $this->resetPresenceData();
    }

    /**
     * Check if database tables exist
     */
    private function checkDatabaseTables(): array
    {
        $connection = $this->getDoctrine()->getConnection();
        $tables = ['evolution_messages', 'evolution_chats', 'evolution_connections', 'evolution_presence'];
        $status = [];

        foreach ($tables as $table) {
            try {
                $result = $connection->executeQuery("SHOW TABLES LIKE '$table'")->fetchOne();
                $status[$table] = !empty($result);
            } catch (\Exception $e) {
                $status[$table] = false;
            }
        }

        return $status;
    }

    /**
     * Get last activity timestamp
     */
    private function getLastActivity(): ?int
    {
        try {
            $connection = $this->getDoctrine()->getConnection();
            $result = $connection->executeQuery('SELECT MAX(timestamp) FROM evolution_messages')->fetchOne();
            return $result ? (int) $result : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}