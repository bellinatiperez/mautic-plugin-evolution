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
    public function sendTextMessage(string $number, string $message, Lead $contact = null, CampaignExecutionEvent $event = null, array $customHeaders = [], array $metadata = []): array
    {
        $data = $this->buildTextPayload($number, $message, $metadata);

        return $this->makeRequest('POST', '/message/sendText/' . $this->getInstance(), $data, $contact, $event, $customHeaders);
    }

    /**
     * Envia mensagem de texto with balancing via Evolution API
     */
    public function sendTextWithBalancing(string $number, string $message, Lead $contact = null, CampaignExecutionEvent $event = null, array $customHeaders = [], array $metadata = []): array
    {
        $data = $this->buildTextPayload($number, $message, $metadata);

        return $this->makeRequest('POST', '/message/sendTextWithBalancing/', $data, $contact, $event, $customHeaders);
    }

    /**
     * Envia texto utilizando balanceamento por grupo (usa alias do grupo)
     */
    public function sendTextWithGroupBalancing(string $alias, string $number, string $text, array $options = [], Lead $contact = null, CampaignExecutionEvent $event = null, array $customHeaders = [], array $metadata = []): array
    {
        $data = [
            'alias' => $alias,
            'number' => $number,
            'text' => $text,
        ];

        // Campos opcionais do payload
        if (isset($options['delay'])) {
            $data['delay'] = (int) $options['delay'];
        }
        if (isset($options['mentionsEveryOne'])) {
            $data['mentionsEveryOne'] = (bool) $options['mentionsEveryOne'];
        }
        if (isset($options['mentioned']) && is_array($options['mentioned'])) {
            $data['mentioned'] = $options['mentioned'];
        }

        // Metadados opcionais
        if (!empty($metadata)) {
            $data['metadata'] = $this->sanitizeKeyValueMap($metadata);
        }
        return $this->makeRequest('POST', '/message/sendTextWithGroupBalancing', $data, $contact, $event, $customHeaders);
    }

    /**
     * Obtém lista de grupos da Evolution API e filtra apenas habilitados
     * @return array{success: bool, groups: array<int, array{ id: string, name: string, alias: string, enabled: bool }>, error?: string}
     */
    public function getInstanceGroups(): array
    {
        $result = $this->makeRequest('GET', '/instance-group');

        if (!$result['success']) {
            return [
                'success' => false,
                'groups' => [],
                'error' => $result['error'] ?? 'Falha ao obter grupos da Evolution API',
            ];
        }

        $groups = [];
        foreach (($result['data'] ?? []) as $item) {
            if (!isset($item['enabled']) || $item['enabled'] !== true) {
                continue;
            }
            $groups[] = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'alias' => (string) ($item['alias'] ?? ''),
                'enabled' => (bool) ($item['enabled'] ?? false),
            ];
        }

        // Log para depuração
        $this->logger->info('Evolution API - getInstanceGroups', [
            'count' => count($groups),
        ]);

        return [
            'success' => true,
            'groups' => $groups,
        ];
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
        // Sanitiza/normaliza número para formato aceito pela API
        $normalized = $this->formatPhoneNumber($number);
        $data = [
            'numbers' => [$normalized],
        ];

        $instance = $this->getInstance();
        $attempts = [
            // Sem instância na rota
            '/chat/whatsappNumbers',
            // Com instância em path
            '/chat/whatsappNumbers/' . $instance,
            // Com instância em query param
            '/chat/whatsappNumbers?instance=' . urlencode($instance),
            // Variação de rota
            '/chat/checkWhatsappNumbers/' . $instance,
        ];

        $lastResult = null;
        foreach ($attempts as $endpoint) {
            $this->logger->info('Evolution API - checkWhatsAppNumber attempt', [
                'endpoint' => $endpoint,
                'numbers' => $data['numbers'],
            ]);

            $result = $this->makeRequest('POST', $endpoint, $data, $contact, $event);
            $lastResult = $result;
            if (isset($result['success']) && $result['success'] === true) {
                return $result;
            }

            // Se não for 404, encerra tentativas
            if (isset($result['status_code']) && (int) $result['status_code'] !== 404) {
                break;
            }
        }

        // Retorna último resultado ou erro padrão
        return $lastResult ?? [
            'success' => false,
            'error' => 'WhatsApp check failed for all endpoints',
            'status_code' => 404,
        ];
    }

    /**
     * Faz requisição para a Evolution API
     */
    private function makeRequest(string $method, string $endpoint, array $data = [], Lead $contact = null, CampaignExecutionEvent $event = null, array $customHeaders = []): array
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
        // Merge custom headers (without removing required defaults)
        foreach ($customHeaders as $hKey => $hVal) {
            if (is_string($hKey) && $hKey !== '') {
                $headers[$hKey] = (string) $hVal;
            }
        }

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
                $result = [
                    'success' => true,
                    'status_code' => $response->getStatusCode(),
                    'data' => $responseData,
                ];

                // Log de sucesso no timeline do contato quando aplicável
                if ($contact instanceof Lead) {
                    $action = $this->getActionFromEndpoint($endpoint);
                    if (in_array($action, ['Send Text Message', 'Send Media Message', 'Send Text With Group Balancing'])) {
                        $details = [
                            'action' => $action,
                            'status' => 'sent',
                            'timestamp' => new \DateTime(),
                            'request' => $data,
                            'response' => $responseData,
                            'messageId' => $responseData['key']['id'] ?? ($responseData['data']['key']['id'] ?? null),
                            'phone' => $data['number'] ?? ($data['phoneNumber'] ?? null),
                            'template' => $data['template'] ?? ($data['mediaMessage']['caption'] ?? null),
                        ];
                        $this->logSuccessEvent($contact, $action, $details);
                    }
                }

                return $result;
            } 

            // Exceção personalizada para erro HTTP (status 4xx ou 5xx)
            $errorDetails = [
                'method' => $method,
                'endpoint' => $endpoint,
                'data' => (string) $response->getBody(),
                'status_code' => $response->getStatusCode()
            ];
            
            // Caso o código de status não seja 2xx, retorna erro estruturado (sem lançar exceção)
            if ((int) $response->getStatusCode() === 404) {
                $this->logger->warning('Evolution API HTTP 404', $errorDetails);
            } else {
                $this->logger->error('Evolution API HTTP error', $errorDetails);
            }
            return [
                'success' => false,
                'error' => 'HTTP error',
                'status_code' => $response->getStatusCode(),
                'response' => $errorDetails['data'],
            ];
            
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
     * Constrói payload para envio de texto, incluindo metadata
     * @return array{number: string, text: string, metadata?: array<string, mixed>}
     */
    public function buildTextPayload(string $number, string $text, array $metadata = []): array
    {
        $payload = [
            'number' => $this->formatPhoneNumber($number),
            'text' => $text,
        ];

        if (!empty($metadata)) {
            $payload['metadata'] = $this->sanitizeKeyValueMap($metadata);
        }

        // Log para depuração do payload
        $this->logger->info('Evolution API - Payload construído', [
            'payload_preview' => [
                'number' => $payload['number'],
                'text_len' => strlen($payload['text'] ?? ''),
                'has_metadata' => isset($payload['metadata']) && is_array($payload['metadata']) && count($payload['metadata']) > 0,
                'metadata_keys' => isset($payload['metadata']) ? array_keys($payload['metadata']) : [],
            ],
        ]);

        return $payload;
    }

    /**
     * Sanitiza e tipa valores para mapa chave-valor do payload/headers
     * Garante chaves string e valores escalares coerentes (bool/int/float/null/string)
     * @param array<string,mixed> $map
     * @return array<string,mixed>
     */
    private function sanitizeKeyValueMap(array $map): array
    {
        $result = [];
        foreach ($map as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                continue;
            }
            $result[trim($key)] = $this->castScalar($value);
        }
        return $result;
    }

    /**
     * Converte string para tipo escalar apropriado
     * - "true"/"false" -> bool
     * - números -> int/float
     * - "null" -> null
     * - JSON objects/arrays -> mantém string (compatibilidade) a menos que parsing seja estritamente necessário
     */
    private function castScalar(mixed $value): mixed
    {
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }
        if (is_string($value)) {
            $trim = trim($value);
            if (strcasecmp($trim, 'true') === 0) {
                return true;
            }
            if (strcasecmp($trim, 'false') === 0) {
                return false;
            }
            if (strcasecmp($trim, 'null') === 0) {
                return null;
            }
            // numérico
            if (is_numeric($trim)) {
                // int vs float
                return strpos($trim, '.') !== false ? (float) $trim : (int) $trim;
            }
            return $value; // mantém string
        }
        // arrays/objects: mantém como está (API pode aceitar)
        return $value;
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
        } elseif (strpos($endpoint, '/message/sendTextWithGroupBalancing') !== false) {
            return 'Send Text With Group Balancing';
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
     * Indica se a checagem de número WhatsApp deve ser realizada
     * Controlado via feature settings (ex.: check_whatsapp_on_save)
     */
    public function shouldCheckWhatsapp(): bool
    {
        $settings = $this->getIntegrationSettings();
        $enabled = (bool) ($settings['check_whatsapp_on_save'] ?? false);
        $this->logger->info('Evolution API - shouldCheckWhatsapp', [
            'check_whatsapp_on_save' => $enabled,
        ]);
        return $enabled;
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

    /**
     * Registra evento de sucesso no timeline do contato
     */
    private function logSuccessEvent(Lead $contact, string $action, array $details = []): void
    {
        try {
            $user = $this->userHelper->getUser();

            $eventLog = new LeadEventLog();
            $eventLog->setLead($contact);
            $eventLog->setBundle('EvolutionBundle');
            $eventLog->setObject('evolution_api');
            $eventLog->setObjectId($contact->getId());
            $eventLog->setAction('evolution_api_success');
            // Garantir propriedades com carimbo de data/hora
            $props = array_merge([
                'timestamp' => new \DateTime(),
            ], $details);
            $props['action'] = $action;
            $eventLog->setProperties($props);
            $eventLog->setUserId($user ? $user->getId() : null);
            $eventLog->setUserName($user ? $user->getName() : 'System');
            $eventLog->setDateAdded(new \DateTime());

            $this->entityManager->persist($eventLog);
            $this->entityManager->flush();

            $this->logger->info('Evolution API success event logged for contact', [
                'contact_id' => $contact->getId(),
                'action' => $action,
                'message_id' => $props['messageId'] ?? null,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to log Evolution API success event', [
                'contact_id' => $contact->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}