<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEvolutionBundle\Service;

use MauticPlugin\MauticEvolutionBundle\Entity\EvolutionMessage;
use MauticPlugin\MauticEvolutionBundle\Entity\EvolutionTemplate;
use MauticPlugin\MauticEvolutionBundle\Model\TemplateModel;
use Mautic\CoreBundle\Helper\TemplatingHelper;
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
    private TemplatingHelper $templatingHelper;
    private LoggerInterface $logger;

    public function __construct(
        EvolutionApiService $evolutionApiService,
        TemplateModel $templateModel,
        TemplatingHelper $templatingHelper,
        LoggerInterface $logger
    ) {
        $this->evolutionApiService = $evolutionApiService;
        $this->templateModel = $templateModel;
        $this->templatingHelper = $templatingHelper;
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

        // Cria registro da mensagem
        $evolutionMessage = new EvolutionMessage();
        $evolutionMessage->setLead($lead);
        $evolutionMessage->setPhoneNumber($phoneNumber);
        $evolutionMessage->setMessageContent($processedMessage);
        $evolutionMessage->setStatus('pending');

        try {
            // Envia mensagem via Evolution API
            $response = $this->evolutionApiService->sendTextMessage($phoneNumber, $processedMessage, $options);

            if ($response['success']) {
                $evolutionMessage->setStatus('sent');
                $evolutionMessage->setSentAt(new \DateTime());
                
                if (isset($response['data']['key']['id'])) {
                    $evolutionMessage->setEvolutionMessageId($response['data']['key']['id']);
                }

                $this->logger->info('Mensagem enviada com sucesso', [
                    'lead_id' => $lead->getId(),
                    'phone' => $phoneNumber,
                    'message_id' => $evolutionMessage->getEvolutionMessageId(),
                ]);
            } else {
                $evolutionMessage->setStatus('failed');
                $evolutionMessage->setErrorMessage($response['error'] ?? 'Erro desconhecido');

                $this->logger->error('Falha ao enviar mensagem', [
                    'lead_id' => $lead->getId(),
                    'phone' => $phoneNumber,
                    'error' => $response['error'] ?? 'Erro desconhecido',
                ]);
            }
        } catch (\Exception $e) {
            $evolutionMessage->setStatus('failed');
            $evolutionMessage->setErrorMessage($e->getMessage());

            $this->logger->error('Exceção ao enviar mensagem', [
                'lead_id' => $lead->getId(),
                'phone' => $phoneNumber,
                'exception' => $e->getMessage(),
            ]);
        }

        return $evolutionMessage;
    }

    /**
     * Envia mensagem simples para um lead
     */
    public function sendMessage(Lead $lead, string $message, string $phoneField = 'mobile'): array
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
            $processedMessage = $this->processTokens($message, $lead);

            // Envia mensagem via Evolution API
            $result = $this->evolutionApiService->sendTextMessage($phoneNumber, $processedMessage);

            // Registra mensagem no banco
            $this->logMessage($lead, $phoneNumber, $processedMessage, null, $result);

            return [
                'success' => true,
                'message_id' => $result['key']['id'] ?? null,
                'data' => $result,
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
            if (!$template || !$template->getIsActive()) {
                return [
                    'success' => false,
                    'error' => 'Template não encontrado ou inativo',
                ];
            }

            // Renderiza template com dados do lead
            $renderedContent = $this->templateModel->renderTemplate($template, $lead);

            // Envia mensagem via Evolution API
            $result = $this->evolutionApiService->sendTextMessage($phoneNumber, $renderedContent);

            // Registra mensagem no banco
            $this->logMessage($lead, $phoneNumber, $renderedContent, $template->getName(), $result);

            return [
                'success' => true,
                'message_id' => $result['key']['id'] ?? null,
                'data' => $result,
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
            $response = $this->evolutionApiService->sendTextMessage($phoneNumber, $processedMessage);

            if ($response['success']) {
                $evolutionMessage->setStatus('sent');
                $evolutionMessage->setSentAt(new \DateTime());
                
                if (isset($response['data']['key']['id'])) {
                    $evolutionMessage->setEvolutionMessageId($response['data']['key']['id']);
                }

                $this->logger->info('Mensagem de template enviada com sucesso', [
                    'lead_id' => $lead->getId(),
                    'phone' => $phoneNumber,
                    'template' => $templateName,
                    'message_id' => $evolutionMessage->getEvolutionMessageId(),
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
            $response = $this->evolutionApiService->sendMediaMessage($phoneNumber, $mediaUrl, $processedCaption, $mediaType);

            if ($response['success']) {
                $evolutionMessage->setStatus('sent');
                $evolutionMessage->setSentAt(new \DateTime());
                
                if (isset($response['data']['key']['id'])) {
                    $evolutionMessage->setEvolutionMessageId($response['data']['key']['id']);
                }

                $this->logger->info('Mídia enviada com sucesso', [
                    'lead_id' => $lead->getId(),
                    'phone' => $phoneNumber,
                    'media_type' => $mediaType,
                    'message_id' => $evolutionMessage->getEvolutionMessageId(),
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
    private function getLeadPhoneNumber(Lead $lead): ?string
    {
        // Tenta diferentes campos de telefone
        $phoneFields = ['mobile', 'phone', 'whatsapp'];
        
        foreach ($phoneFields as $field) {
            $phone = $lead->getFieldValue($field);
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
        foreach ($lead->getFields() as $field) {
            $alias = $field->getField()->getAlias();
            $value = $field->getValue() ?? '';
            $tokens['{contactfield=' . $alias . '}'] = $value;
        }

        // Adiciona tokens de data/hora
        $tokens['{date}'] = date('d/m/Y');
        $tokens['{time}'] = date('H:i');
        $tokens['{datetime}'] = date('d/m/Y H:i');

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
        foreach ($lead->getFields() as $field) {
            $alias = $field->getField()->getAlias();
            $value = $field->getValue() ?? '';
            $variables[$alias] = $value;
        }

        // Sobrescreve com variáveis customizadas
        return array_merge($variables, $customVariables);
    }

    /**
     * Verifica se o serviço está configurado
     */
    public function isConfigured(): bool
    {
        return $this->evolutionApiService->isConfigured();
    }
}