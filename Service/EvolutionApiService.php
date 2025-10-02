<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEvolutionBundle\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticEvolutionBundle\Integration\MauticEvolutionIntegration;
use Psr\Log\LoggerInterface;
use Mautic\LeadBundle\Entity\LeadEventLog;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\CoreBundle\Helper\UserHelper;
use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use MauticPlugin\MauticEvolutionBundle\Entity\EvolutionMessage;

/**
 * Class EvolutionApiService
 * 
 * Serviço para comunicação com a Evolution API
 */
class EvolutionApiService
{
    private Client $httpClient;
    private CoreParametersHelper $coreParametersHelper;
    private LoggerInterface $logger;
    private IntegrationHelper $integrationHelper;
    private UserHelper $userHelper;
    private EntityManagerInterface $entityManager;

    public function __construct(
        IntegrationHelper $integrationHelper,
        Client $httpClient,
        LoggerInterface $logger,
        UserHelper $userHelper,
        EntityManagerInterface $entityManager
    ) {
        $this->integrationHelper = $integrationHelper;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->userHelper = $userHelper;
        $this->entityManager = $entityManager;
    }

    /**
     * Envia mensagem de texto via Evolution API
     */
    public function sendTextMessage(string $number, string $message, Lead $contact = null, CampaignExecutionEvent $event = null): array
    {
        $data = [
            'number' => $number,
            'text' => $message,
        ];

        return $this->makeRequest('POST', '/message/sendText/' . $this->getInstance(), $data, $contact, $event);
    }

    /**
     * Envia mensagem de texto with balancing via Evolution API
     */
    public function sendTextWithBalancing(string $number, string $message, Lead $contact = null, CampaignExecutionEvent $event = null): array
    {
        $data = [
            'number' => $number,
            'text' => $message,
        ];

        return $this->makeRequest('POST', '/message/sendTextWithBalancing/', $data, $contact, $event);
    }

    /**
     * Envia mensagem de mídia via Evolution API
     */
    public function sendMediaMessage(string $number, string $mediaUrl, string $caption = '', Lead $contact = null, CampaignExecutionEvent $event = null): array
    {
        $data = [
            'number' => $number,
            'mediaMessage' => [
                'mediaUrl' => $mediaUrl,
                'caption' => $caption,
            ],
        ];

        return $this->makeRequest('POST', '/message/sendMedia/' . $this->getInstance(), $data, $contact, $event);
    }

    /**
     * Define webhook para receber mensagens
     */
    public function setWebhook(string $webhookUrl, Lead $contact = null, CampaignExecutionEvent $event = null): array
    {
        $data = [
            'webhook' => $webhookUrl,
            'events' => [
                'APPLICATION_STARTUP',
                'QRCODE_UPDATED',
                'MESSAGES_UPSERT',
                'MESSAGES_UPDATE',
                'SEND_MESSAGE',
            ],
        ];

        return $this->makeRequest('POST', '/webhook/set/' . $this->getInstance(), $data, $contact, $event);
    }

    /**
     * Obtém mensagens de uma conversa
     */
    public function getMessages(string $remoteJid, int $limit = 20, Lead $contact = null, CampaignExecutionEvent $event = null): array
    {
        $data = [
            'where' => [
                'remoteJid' => $remoteJid,
            ],
            'limit' => $limit,
        ];

        return $this->makeRequest('POST', '/chat/findMessages/' . $this->getInstance(), $data, $contact, $event);
    }

    /**
     * Marca mensagem como lida
     */
    public function markAsRead(string $remoteJid, string $messageId, Lead $contact = null, CampaignExecutionEvent $event = null): array
    {
        $data = [
            'readMessages' => [
                [
                    'remoteJid' => $remoteJid,
                    'id' => $messageId,
                ],
            ],
        ];

        return $this->makeRequest('POST', '/chat/markMessageAsRead/' . $this->getInstance(), $data, $contact, $event);
    }

    /**
     * Verifica se um número é WhatsApp
     */
    public function checkWhatsAppNumber(string $number, Lead $contact = null, CampaignExecutionEvent $event = null): array
    {
        $data = [
            'numbers' => [$number],
        ];

        return $this->makeRequest('POST', '/chat/whatsappNumbers/' . $this->getInstance(), $data, $contact, $event);
    }

    /**
     * Faz requisição para a Evolution API
     */
    private function makeRequest(string $method, string $endpoint, array $data = [], Lead $contact = null, CampaignExecutionEvent $event = null): array
    {
        $apiUrl = $this->getApiUrl();
        $apiKey = $this->getApiKey();

        if (empty($apiUrl) || empty($apiKey)) {
            $this->logger->error('Evolution API não configurada corretamente', [
                'api_url' => $apiUrl,
                'api_key' => $apiKey,
            ]);

            $errorMessage = 'Evolution API não configurada corretamente';
            
            // Registra falha no evento de campanha se disponível
            if ($event instanceof CampaignExecutionEvent) {
                $event->setFailed($errorMessage);
            }
            
            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        }

        $url = rtrim($apiUrl, '/') . '/' . ltrim($endpoint, '/');
        $headers = [
            'Content-Type' => 'application/json',
            'apikey' => $apiKey,
        ];

        try {
            $this->logger->info('Evolution API Request', [
                'method' => $method,
                'endpoint' => $endpoint,
                'data' => $data,
                'api_url' => $apiUrl,
                'endpoint' => $endpoint,
                'full_url' => $url,
                'integration_settings' => $this->getIntegrationSettings(),
            ]);

            $options = [
                'headers' => $headers,
                'timeout' => $this->getTimeout(),
            ];

            if (!empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->httpClient->request($method, $url, $options);
            $responseData = json_decode($response->getBody()->getContents(), true);


            // Verifica se o status da resposta é 2xx (sucesso)
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                // Sucesso: Retorna os dados da resposta
                return [
                    'success' => true,
                    'status_code' => $response->getStatusCode(),
                    'data' => $responseData,
                ];
            } 

            // Exceção personalizada para erro HTTP (status 4xx ou 5xx)
            $errorDetails = [
                'method' => $method,
                'endpoint' => $endpoint,
                'data' => (string) $response->getBody(),
                'status_code' => $response->getStatusCode()
            ];
            
            // Caso o código de status não seja 2xx, trata como erro
            throw new \Exception('Erro na requisição, status code: ' . json_encode($errorDetails));
            
        } catch (GuzzleException $e) {
            $errorMessage = $e->getMessage();
            $context = [
                'method' => $method,
                'endpoint' => $endpoint,
                'data' => $data,
                'status_code' => $e->getCode(),
            ];

            $this->logger->error('Evolution API Error', $context);

            // Registra falha no evento de campanha se disponível
            if ($event instanceof CampaignExecutionEvent) {
                $event->setFailed($errorMessage);
                
                // Adiciona metadata detalhada sobre a falha
                $log = $event->getLogEntry();
                if ($log) {
                    $log->appendToMetadata([
                        'failed' => 1,
                        'reason' => $errorMessage,
                        'error_details' => [
                            'endpoint' => $endpoint,
                            'method' => $method,
                            'status_code' => $e->getCode(),
                            'context' => $context,
                        ],
                    ]);
                }
            }

            // Registra evento de falha no timeline do contato se disponível
            if ($contact instanceof Lead) {
                $action = $this->getActionFromEndpoint($endpoint);
                $this->logFailureEvent($contact, $action, $errorMessage, $context);
            }

            return [
                'success' => false,
                'error' => $errorMessage,
                'status_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Extrai a ação do endpoint para logging
     */
    private function getActionFromEndpoint(string $endpoint): string
    {
        if (strpos($endpoint, '/message/sendText/') !== false) {
            return 'Send Text Message';
        } elseif (strpos($endpoint, '/message/sendMedia/') !== false) {
            return 'Send Media Message';
        } elseif (strpos($endpoint, '/webhook/set/') !== false) {
            return 'Set Webhook';
        } elseif (strpos($endpoint, '/chat/findMessages/') !== false) {
            return 'Get Messages';
        } elseif (strpos($endpoint, '/chat/markMessageAsRead/') !== false) {
            return 'Mark as Read';
        } elseif (strpos($endpoint, '/chat/whatsappNumbers/') !== false) {
            return 'Check WhatsApp Number';
        } else {
            return 'API Request';
        }
    }

    /**
     * Obtém headers para requisições
     */
    private function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'apikey' => $this->getApiKey(),
        ];
    }

    /**
     * Formata número de telefone
     */
    private function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove caracteres não numéricos
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Adiciona código do país se não tiver
        if (strlen($phoneNumber) === 11 && substr($phoneNumber, 0, 1) !== '55') {
            $phoneNumber = '55' . $phoneNumber;
        }

        return $phoneNumber;
    }

    /**
     * Obtém configurações da integração
     */
    private function getIntegrationSettings(): array
    {
        $integration = $this->integrationHelper->getIntegrationObject('MauticEvolution');
        
        if (!$integration || !$integration->getIntegrationSettings()->getIsPublished()) {
            return [];
        }

        // Obtém as chaves descriptografadas (onde estão as configurações da API)
        $apiKeys = $integration->getDecryptedApiKeys();
        
        // Obtém as feature settings (configurações adicionais)
        $featureSettings = $integration->getIntegrationSettings()->getFeatureSettings();
        
        // Mapeia as chaves descriptografadas para nomes corretos
        $mappedApiKeys = [];
        if (is_array($apiKeys) && count($apiKeys) >= 2) {
            $mappedApiKeys = [
                'evolution_api_url' => $apiKeys[0] ?? '',
                'evolution_api_key' => $apiKeys[1] ?? '',
            ];
        }
        
        // Mescla as duas configurações
        return array_merge($mappedApiKeys, $featureSettings);
    }

    /**
     * Obtém URL da API
     */
    private function getApiUrl(): string
    {
        $settings = $this->getIntegrationSettings();
        $url = rtrim($settings['evolution_api_url'] ?? '', '/');
        
        // Debug: Log para verificar se a URL está sendo carregada
        $this->logger->info('Evolution API - getApiUrl Debug', [
            'settings' => $settings,
            'evolution_api_url' => $settings['evolution_api_url'] ?? 'NOT_SET',
            'final_url' => $url,
        ]);
        
        return $url;
    }

    /**
     * Obtém API Key
     */
    private function getApiKey(): string
    {
        $settings = $this->getIntegrationSettings();
        $apiKey = $settings['evolution_api_key'] ?? '';
        
        // Debug: Log para verificar se a API Key está sendo carregada (sem expor a chave completa)
        $this->logger->info('Evolution API - getApiKey Debug', [
            'has_api_key' => !empty($apiKey),
            'api_key_length' => strlen($apiKey),
            'api_key_preview' => !empty($apiKey) ? substr($apiKey, 0, 10) . '...' : 'EMPTY',
        ]);
        
        return $apiKey;
    }

    /**
     * Obtém nome da instância
     */
    private function getInstance(): string
    {
        $settings = $this->getIntegrationSettings();
        
        // Usa uma instância padrão ou configurada via feature settings
        $instance = $settings['evolution_instance'] ?? 'default';
        
        // Debug: Log para verificar se a instância está sendo carregada
        $this->logger->info('Evolution API - getInstance Debug', [
            'evolution_instance' => $instance,
            'has_instance' => !empty($instance),
        ]);
        
        return $instance;
    }

    /**
     * Obtém timeout das requisições
     */
    private function getTimeout(): int
    {
        $settings = $this->getIntegrationSettings();
        return (int) ($settings['evolution_timeout'] ?? 30);
    }

    /**
     * Verifica se a configuração está válida
     */
    public function isConfigured(): bool
    {
        return !empty($this->getApiUrl()) && 
               !empty($this->getApiKey());
    }

    /**
     * Verifica status da instância
     */
    public function getInstanceStatus(CampaignExecutionEvent $event = null): array
    {
        return $this->makeRequest('GET', '/instance/connectionState/' . $this->getInstance(), [], null, $event);
    }

    /**
     * Testa conexão com a Evolution API
     */
    public function testConnection(CampaignExecutionEvent $event = null): array
    {
        if (!$this->isConfigured()) {
            $errorMessage = 'Configuração incompleta. Verifique URL, API Key e Instância.';
            
            // Registra falha no evento de campanha se disponível
            if ($event instanceof CampaignExecutionEvent) {
                $event->setFailed($errorMessage);
            }
            
            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        }

        return $this->getInstanceStatus($event);
    }

    /**
     * Registra evento de falha no timeline do contato
     */
    private function logFailureEvent(Lead $contact, string $action, string $errorMessage, array $context = []): void
    {
        try {
            $user = $this->userHelper->getUser();
            
            $eventLog = new LeadEventLog();
            $eventLog->setLead($contact);
            $eventLog->setBundle('EvolutionBundle');
            $eventLog->setObject('evolution_api');
            $eventLog->setObjectId($contact->getId());
            $eventLog->setAction('evolution_api_failure');
            $eventLog->setProperties([
                'action' => $action,
                'error' => $errorMessage,
                'context' => $context,
                'timestamp' => new \DateTime(),
            ]);
            $eventLog->setUserId($user ? $user->getId() : null);
            $eventLog->setUserName($user ? $user->getName() : 'System');
            $eventLog->setDateAdded(new \DateTime());

            $this->entityManager->persist($eventLog);
            $this->entityManager->flush();

            $this->logger->info('Evolution API failure event logged for contact', [
                'contact_id' => $contact->getId(),
                'action' => $action,
                'error' => $errorMessage,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to log Evolution API failure event', [
                'contact_id' => $contact->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}