<?php

namespace MauticPlugin\EvolutionWhatsAppBundle\Service;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Mautic\PluginBundle\Helper\IntegrationHelper;

/**
 * Evolution API Service
 * 
 * Handles all communication with Evolution API endpoints
 */
class EvolutionApiService
{
    private Client $httpClient;
    private LoggerInterface $logger;
    private IntegrationHelper $integrationHelper;
    private ?string $apiUrl = null;
    private ?string $apiKey = null;
    private ?string $instanceName = null;

    public function __construct(
        Client $httpClient,
        LoggerInterface $logger,
        IntegrationHelper $integrationHelper
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->integrationHelper = $integrationHelper;
        
        $this->initializeConfig();
    }

    /**
     * Initialize configuration from integration settings
     */
    private function initializeConfig(): void
    {
        $integration = $this->integrationHelper->getIntegrationObject('Evolution');
        
        if ($integration && $integration->getIntegrationSettings()->getIsPublished()) {
            $keys = $integration->getDecryptedApiKeys();
            $this->apiUrl = $keys['api_url'] ?? null;
            $this->apiKey = $keys['api_key'] ?? null;
            $this->instanceName = $keys['instance_name'] ?? null;
        }
    }

    /**
     * Check if the service is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiUrl) && !empty($this->apiKey) && !empty($this->instanceName);
    }

    /**
     * Get instance connection state
     */
    public function getInstanceState(): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Evolution API not configured');
        }

        try {
            $response = $this->makeRequest('GET', "/instance/connectionState/{$this->instanceName}");
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get instance state: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send WhatsApp message
     */
    public function sendMessage(string $phone, string $message, array $options = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Evolution API not configured');
        }

        $payload = [
            'number' => $phone,
            'text' => $message,
        ];

        if (isset($options['delay'])) {
            $payload['delay'] = $options['delay'];
        }

        try {
            $response = $this->makeRequest('POST', "/message/sendText/{$this->instanceName}", $payload);
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send message: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get chat messages
     */
    public function getChatMessages(string $phone, int $limit = 50): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Evolution API not configured');
        }

        try {
            $response = $this->makeRequest('GET', "/chat/findMessages/{$this->instanceName}", [
                'where' => [
                    'key' => [
                        'remoteJid' => $phone . '@s.whatsapp.net'
                    ]
                ],
                'limit' => $limit
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get chat messages: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all chats
     */
    public function getAllChats(): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Evolution API not configured');
        }

        try {
            $response = $this->makeRequest('GET', "/chat/findChats/{$this->instanceName}");
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get chats: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get contact information
     */
    public function getContact(string $phone): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Evolution API not configured');
        }

        try {
            $response = $this->makeRequest('GET', "/chat/whatsappNumbers/{$this->instanceName}", [
                'numbers' => [$phone]
            ]);
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get contact: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Set webhook URL
     */
    public function setWebhook(string $webhookUrl, array $events = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Evolution API not configured');
        }

        $defaultEvents = [
            'MESSAGES_UPSERT',
            'MESSAGES_UPDATE',
            'MESSAGES_DELETE',
            'SEND_MESSAGE',
            'CONTACTS_UPSERT',
            'CONTACTS_UPDATE',
            'PRESENCE_UPDATE',
            'CHATS_UPSERT',
            'CHATS_UPDATE',
            'CHATS_DELETE',
            'GROUPS_UPSERT',
            'GROUP_UPDATE',
            'GROUP_PARTICIPANTS_UPDATE',
            'CONNECTION_UPDATE',
            'CALL',
            'NEW_JWT_TOKEN'
        ];

        $payload = [
            'url' => $webhookUrl,
            'events' => !empty($events) ? $events : $defaultEvents,
            'webhook_by_events' => false
        ];

        try {
            $response = $this->makeRequest('POST', "/webhook/set/{$this->instanceName}", $payload);
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Failed to set webhook: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get webhook information
     */
    public function getWebhook(): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Evolution API not configured');
        }

        try {
            $response = $this->makeRequest('GET', "/webhook/find/{$this->instanceName}");
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get webhook: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Make HTTP request to Evolution API
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = rtrim($this->apiUrl, '/') . $endpoint;
        
        $options = [
            'headers' => [
                'apikey' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ];

        if (!empty($data)) {
            if ($method === 'GET') {
                $options['query'] = $data;
            } else {
                $options['json'] = $data;
            }
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $body = $response->getBody()->getContents();
            
            $decodedResponse = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            $this->logger->info('Evolution API request successful', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status_code' => $response->getStatusCode()
            ]);

            return $decodedResponse;
        } catch (\Exception $e) {
            $this->logger->error('Evolution API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get analytics data for a specific period
     */
    public function getAnalyticsData(\DateTime $startDate, \DateTime $endDate): array
    {
        // This would typically involve multiple API calls to gather analytics data
        $analytics = [
            'messages_sent' => 0,
            'messages_received' => 0,
            'contacts_active' => 0,
            'chats_total' => 0,
            'period' => [
                'start' => $startDate->format('Y-m-d H:i:s'),
                'end' => $endDate->format('Y-m-d H:i:s'),
            ],
        ];

        try {
            // Get all chats for the period
            $chats = $this->getAllChats();
            $analytics['chats_total'] = count($chats);

            // Additional analytics logic would go here
            // This is a simplified version for demonstration

            return $analytics;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get analytics data: ' . $e->getMessage());
            return $analytics;
        }
    }
}