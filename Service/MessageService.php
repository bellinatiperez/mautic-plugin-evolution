<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEvolutionBundle\Service;

use MauticPlugin\MauticEvolutionBundle\Entity\EvolutionMessage;
use MauticPlugin\MauticEvolutionBundle\Entity\EvolutionTemplate;
use MauticPlugin\MauticEvolutionBundle\Model\TemplateModel;

use Mautic\LeadBundle\Entity\Lead;
use Psr\Log\LoggerInterface;

/**
 * Class MessageService
 * 
 * Serviço para gerenciar envio de mensagens WhatsApp
 */
class MessageService
{
    private EvolutionApiService $evolutionApiService;
    private TemplateModel $templateModel;
    // removed TemplatingHelper property
    private LoggerInterface $logger;

    public function __construct(
        EvolutionApiService $evolutionApiService,
        TemplateModel $templateModel,
        LoggerInterface $logger
    ) {
        $this->evolutionApiService = $evolutionApiService;
        $this->templateModel = $templateModel;
        // removed $this->templatingHelper assignment
        $this->logger = $logger;
    }

    /**
     * Envia mensagem simples para um lead
     */
    public function sendSimpleMessage(Lead $lead, string $message, ?array $options = []): EvolutionMessage
    {
        $phoneNumber = $this->getLeadPhoneNumber($lead);
        
        if (empty($phoneNumber)) {
            throw new \InvalidArgumentException('Lead não possui número de telefone válido');
        }

        // Processa tokens no conteúdo da mensagem
        $processedMessage = $this->processMessageTokens($message, $lead);

        // Extrai headers e metadata das opções e interpola tokens do lead
        $rawHeaders = isset($options['headers']) && is_array($options['headers']) ? $options['headers'] : [];
        $rawMetadata = isset($options['metadata']) && is_array($options['metadata']) ? $options['metadata'] : (isset($options['data']) && is_array($options['data']) ? $options['data'] : []);
        $headers = $this->interpolatePairs($rawHeaders, $lead, true);
        $metadata = $this->interpolatePairs($rawMetadata, $lead, false);

        // Cria registro da mensagem
        $evolutionMessage = new EvolutionMessage();
        $evolutionMessage->setLead($lead);
        $evolutionMessage->setPhoneNumber($phoneNumber);
        $evolutionMessage->setMessageContent($processedMessage);
        $evolutionMessage->setStatus('pending');
        if (!empty($metadata)) {
            $evolutionMessage->setMetadata($metadata);
        }

        try {
            // Envia mensagem via Evolution API
            $response = $this->evolutionApiService->sendTextMessage($phoneNumber, $processedMessage, $lead, null, $headers, $metadata);

            if ($response['success']) {
                $evolutionMessage->setStatus('sent');
                $evolutionMessage->setSentAt(new \DateTime());
                
                if (isset($response['data']['key']['id'])) {
                    $evolutionMessage->setMessageId($response['data']['key']['id']);
                }

                $this->logger->info('Mensagem enviada com sucesso', [
                    'lead_id' => $lead->getId(),
                    'phone' => $phoneNumber,
                    'message_id' => $evolutionMessage->getMessageId(),
                    'headers_keys' => array_keys($headers),
                    'metadata_keys' => array_keys($metadata),
                ]);
            } else {
                $evolutionMessage->setStatus('failed');
                $evolutionMessage->setErrorMessage($response['error'] ?? 'Erro desconhecido');

                $this->logger->error('Falha ao enviar mensagem', [
                    'lead_id' => $lead->getId(),
                    'phone' => $phoneNumber,
                    'error' => $response['error'] ?? 'Erro desconhecido',
                    'headers_keys' => array_keys($headers),
                    'metadata_keys' => array_keys($metadata),
                ]);
            }
        } catch (\Exception $e) {
            $evolutionMessage->setStatus('failed');
            $evolutionMessage->setErrorMessage($e->getMessage());

            $this->logger->error('Exceção ao enviar mensagem', [
                'lead_id' => $lead->getId(),
                'phone' => $phoneNumber,
                'exception' => $e->getMessage(),
                'headers_keys' => array_keys($headers),
                'metadata_keys' => array_keys($metadata),
            ]);
        }

        return $evolutionMessage;
    }

    /**
     * Envia mensagem simples para um lead (com suporte a group alias e phone_field)
     */
    public function sendMessage(Lead $lead, string $message, ?string $groupAlias = null, string $phoneField = 'mobile'): array
    {
        try {
            $phoneNumber = $this->getLeadPhoneNumber($lead, $phoneField);
            
            if (empty($phoneNumber)) {
                return [
                    'success' => false,
                    'error' => 'Número de telefone não encontrado no lead',
                ];
            }

            // Processa tokens na mensagem
            $processedMessage = $this->processMessageTokens($message, $lead);

            // Envia mensagem via Evolution API
            if (!empty($groupAlias)) {
                $result = $this->evolutionApiService->sendTextWithGroupBalancing($groupAlias, $phoneNumber, $processedMessage, [], $lead);
            } else {
                $result = $this->evolutionApiService->sendTextMessage($phoneNumber, $processedMessage, $lead);
            }

            // Registra mensagem no banco
            // removed $this->logMessage call (method not defined)

            return [
                'success' => $result['success'] ?? false,
                'message_id' => $result['data']['key']['id'] ?? null,
                'data' => $result['data'] ?? null,
                'error' => $result['error'] ?? null,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erro ao enviar mensagem', [
                'lead_id' => $lead->getId(),
                'message' => $message,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Envia template para um lead
     */
    public function sendTemplate(Lead $lead, int $templateId, string $phoneField = 'mobile'): array
    {
        try {
            $phoneNumber = $this->getLeadPhoneNumber($lead, $phoneField);
            
            if (empty($phoneNumber)) {
                return [
                    'success' => false,
                    'error' => 'Número de telefone não encontrado no lead',
                ];
            }

            // Busca template
            $template = $this->templateModel->getEntity($templateId);
            if (!$template || !$template->isActive()) {
                return [
                    'success' => false,
                    'error' => 'Template não encontrado ou inativo',
                ];
            }

            // Renderiza template com dados do lead
            $renderedContent = $this->templateModel->renderTemplate($template, $this->prepareTemplateVariables($lead));

            // Envia mensagem via Evolution API
            $result = $this->evolutionApiService->sendTextMessage($phoneNumber, $renderedContent, $lead);

            // Registra mensagem no banco
            // removed $this->logMessage call (method not defined)

            return [
                'success' => $result['success'] ?? false,
                'message_id' => $result['data']['key']['id'] ?? null,
                'data' => $result['data'] ?? null,
                'error' => $result['error'] ?? null,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erro ao enviar template', [
                'lead_id' => $lead->getId(),
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Envia mensagem usando template
     */
    public function sendTemplateMessage(Lead $lead, string $templateName, ?array $customVariables = []): EvolutionMessage
    {
        $template = $this->templateModel->getRepository()->findByName($templateName);
        
        if (!$template) {
            throw new \InvalidArgumentException("Template '{$templateName}' não encontrado");
        }

        if (!$template->isActive()) {
            throw new \InvalidArgumentException("Template '{$templateName}' está inativo");
        }

        $phoneNumber = $this->getLeadPhoneNumber($lead);
        
        if (empty($phoneNumber)) {
            throw new \InvalidArgumentException('Lead não possui número de telefone válido');
        }

        // Prepara variáveis para o template
        $variables = $this->prepareTemplateVariables($lead, $customVariables);
        
        // Valida se todas as variáveis necessárias estão presentes
        $missingVariables = $template->validateVariables($variables);
        if (!empty($missingVariables)) {
            throw new \InvalidArgumentException(
                'Variáveis obrigatórias não fornecidas: ' . implode(', ', $missingVariables)
            );
        }

        // Renderiza o template
        $processedMessage = $template->render($variables);

        // Cria registro da mensagem
        $evolutionMessage = new EvolutionMessage();
        $evolutionMessage->setLead($lead);
        $evolutionMessage->setPhoneNumber($phoneNumber);
        $evolutionMessage->setMessageContent($processedMessage);
        $evolutionMessage->setTemplateName($templateName);
        $evolutionMessage->setStatus('pending');
        $evolutionMessage->setMetadata([
            'template_id' => $template->getId(),
            'variables' => $variables,
        ]);

        try {
            // Envia mensagem via Evolution API
            $response = $this->evolutionApiService->sendTextMessage($phoneNumber, $processedMessage, $lead);

            if ($response['success']) {
                $evolutionMessage->setStatus('sent');
                $evolutionMessage->setSentAt(new \DateTime());
                
                if (isset($response['data']['key']['id'])) {
                    $evolutionMessage->setMessageId($response['data']['key']['id']);
                }

                $this->logger->info('Mensagem enviada com sucesso', [
                    'lead_id' => $lead->getId(),
                    'phone' => $phoneNumber,
                    'message_id' => $evolutionMessage->getMessageId(),
                ]);
            } else {
                $evolutionMessage->setStatus('failed');
                $evolutionMessage->setErrorMessage($response['error'] ?? 'Erro desconhecido');

                $this->logger->error('Falha ao enviar mensagem de template', [
                    'lead_id' => $lead->getId(),
                    'phone' => $phoneNumber,
                    'template' => $templateName,
                    'error' => $response['error'] ?? 'Erro desconhecido',
                ]);
            }
        } catch (\Exception $e) {
            $evolutionMessage->setStatus('failed');
            $evolutionMessage->setErrorMessage($e->getMessage());

            $this->logger->error('Exceção ao enviar mensagem de template', [
                'lead_id' => $lead->getId(),
                'phone' => $phoneNumber,
                'template' => $templateName,
                'exception' => $e->getMessage(),
            ]);
        }

        return $evolutionMessage;
    }

    /**
     * Envia mensagem com mídia
     */
    public function sendMediaMessage(Lead $lead, string $mediaUrl, string $caption = '', string $mediaType = 'image'): EvolutionMessage
    {
        $phoneNumber = $this->getLeadPhoneNumber($lead);
        
        if (empty($phoneNumber)) {
            throw new \InvalidArgumentException('Lead não possui número de telefone válido');
        }

        // Processa tokens no caption
        $processedCaption = $this->processMessageTokens($caption, $lead);

        // Cria registro da mensagem
        $evolutionMessage = new EvolutionMessage();
        $evolutionMessage->setLead($lead);
        $evolutionMessage->setPhoneNumber($phoneNumber);
        $evolutionMessage->setMessageContent($processedCaption);
        $evolutionMessage->setStatus('pending');
        $evolutionMessage->setMetadata([
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
        ]);

        try {
            // Envia mídia via Evolution API
            $response = $this->evolutionApiService->sendMediaMessage($phoneNumber, $mediaUrl, $processedCaption, $lead);

            if ($response['success']) {
                $evolutionMessage->setStatus('sent');
                $evolutionMessage->setSentAt(new \DateTime());
                
                if (isset($response['data']['key']['id'])) {
                    $evolutionMessage->setMessageId($response['data']['key']['id']);
                }

                $this->logger->info('Mídia enviada com sucesso', [
                    'lead_id' => $lead->getId(),
                    'phone' => $phoneNumber,
                    'media_type' => $mediaType,
                    'message_id' => $evolutionMessage->getMessageId(),
                ]);
            } else {
                $evolutionMessage->setStatus('failed');
                $evolutionMessage->setErrorMessage($response['error'] ?? 'Erro desconhecido');

                $this->logger->error('Falha ao enviar mídia', [
                    'lead_id' => $lead->getId(),
                    'phone' => $phoneNumber,
                    'media_type' => $mediaType,
                    'error' => $response['error'] ?? 'Erro desconhecido',
                ]);
            }
        } catch (\Exception $e) {
            $evolutionMessage->setStatus('failed');
            $evolutionMessage->setErrorMessage($e->getMessage());

            $this->logger->error('Exceção ao enviar mídia', [
                'lead_id' => $lead->getId(),
                'phone' => $phoneNumber,
                'media_type' => $mediaType,
                'exception' => $e->getMessage(),
            ]);
        }

        return $evolutionMessage;
    }

    /**
     * Obtém número de telefone do lead
     */
    private function getLeadPhoneNumber(Lead $lead, string $phoneField = 'mobile'): ?string
    {
        // Tenta campo escolhido primeiro, depois outros de fallback
        $fieldsOrder = array_unique(array_filter([$phoneField, 'mobile', 'phone', 'whatsapp']));
        
        foreach ($fieldsOrder as $field) {
            $phone = method_exists($lead, 'getFieldValue') ? $lead->getFieldValue($field) : null;
            if (!empty($phone)) {
                return $this->cleanPhoneNumber($phone);
            }
        }

        return null;
    }

    /**
     * Limpa e formata número de telefone
     */
    private function cleanPhoneNumber(string $phone): string
    {
        // Remove caracteres não numéricos
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Processa tokens na mensagem
     */
    private function processMessageTokens(string $message, Lead $lead): string
    {
        // Substitui tokens do lead
        $tokens = [
            '{contactfield=firstname}' => $lead->getFirstname() ?? '',
            '{contactfield=lastname}' => $lead->getLastname() ?? '',
            '{contactfield=email}' => $lead->getEmail() ?? '',
            '{contactfield=company}' => $lead->getCompany() ?? '',
            '{contactfield=city}' => $lead->getCity() ?? '',
            '{contactfield=state}' => $lead->getState() ?? '',
            '{contactfield=country}' => $lead->getCountry() ?? '',
        ];

        // Adiciona campos personalizados
        if (method_exists($lead, 'getFields')) {
            foreach ($lead->getFields() as $field) {
                $alias = $field->getField()->getAlias();
                $value = $field->getValue() ?? '';
                $tokens['{contactfield=' . $alias . '}'] = $value;
            }
        }

        // Adiciona tokens de data/hora
        $tokens['{date}'] = date('d/m/Y');
        $tokens['{time}'] = date('H:i');
        $tokens['{datetime}'] = date('d/m/Y H:i');

        // Converter tokens simples {firstname} -> {contactfield=firstname}
        $message = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '{contactfield=$1}', $message);

        return str_replace(array_keys($tokens), array_values($tokens), $message);
    }

    /**
     * Prepara variáveis para template
     */
    private function prepareTemplateVariables(Lead $lead, array $customVariables = []): array
    {
        $variables = [
            'firstname' => $lead->getFirstname() ?? '',
            'lastname' => $lead->getLastname() ?? '',
            'email' => $lead->getEmail() ?? '',
            'company' => $lead->getCompany() ?? '',
            'city' => $lead->getCity() ?? '',
            'state' => $lead->getState() ?? '',
            'country' => $lead->getCountry() ?? '',
            'date' => date('d/m/Y'),
            'time' => date('H:i'),
            'datetime' => date('d/m/Y H:i'),
        ];

        // Adiciona campos personalizados do lead
        if (method_exists($lead, 'getFields')) {
            foreach ($lead->getFields() as $field) {
                $alias = $field->getField()->getAlias();
                $value = $field->getValue() ?? '';
                $variables[$alias] = $value;
            }
        }

        // Sobrescreve com variáveis customizadas
        return array_merge($variables, $customVariables);
    }

    /**
     * Interpola tokens do lead em pares chave-valor.
     * Quando $isHeader for true, normaliza chaves e ignora entradas vazias.
     */
    private function interpolatePairs(array $pairs, Lead $lead, bool $isHeader = false): array
    {
        $result = [];
        foreach ($pairs as $key => $value) {
            if (!is_string($key)) {
                $key = (string) $key;
            }
            $resolvedKey = $this->processMessageTokens($key, $lead);
            $resolvedVal = is_string($value) ? $this->processMessageTokens($value, $lead) : $value;

            if ($isHeader) {
                $resolvedKey = trim($resolvedKey);
            }

            if ($resolvedKey === '') {
                continue;
            }

            if (is_string($resolvedVal)) {
                $resolvedVal = $this->castScalar($resolvedVal);
            }

            $result[$resolvedKey] = $resolvedVal;
        }

        return $result;
    }

    /**
     * Converte string em tipo escalar (int, float, bool, null) quando aplicável.
     */
    private function castScalar(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        $lower = strtolower($trimmed);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if ($lower === 'null') {
            return null;
        }
        if (is_numeric($trimmed)) {
            return strpos($trimmed, '.') !== false ? (float) $trimmed : (int) $trimmed;
        }
        return $value;
    }

    /**
     * Verifica se o serviço está configurado
     */
    public function isConfigured(): bool
    {
        return $this->evolutionApiService->isConfigured();
    }
}