<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEvolutionBundle\Service;

use MauticPlugin\MauticEvolutionBundle\Entity\EvolutionMessage;
use MauticPlugin\MauticEvolutionBundle\Entity\EvolutionMessageRepository;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Model\NoteModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadNote;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class WebhookService
 * 
 * Serviço para processar webhooks recebidos da Evolution API
 */
class WebhookService
{
    private LeadModel $leadModel;
    private NoteModel $noteModel;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;

    public function __construct(
        LeadModel $leadModel,
        NoteModel $noteModel,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager
    ) {
        $this->leadModel = $leadModel;
        $this->noteModel = $noteModel;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
    }

    /**
     * Processa webhook recebido
     */
    public function processWebhook(array $payload): array
    {
        try {
            $this->logger->info('Processando webhook Evolution API', ['payload' => $payload]);

            $event = $payload['event'] ?? '';
            
            switch ($event) {
                case 'messages.upsert':
                    return $this->processIncomingMessage($payload);
                    
                case 'messages.update':
                    return $this->processMessageUpdate($payload);
                    
                case 'connection.update':
                    return $this->processConnectionUpdate($payload);
                    
                default:
                    $this->logger->info('Evento de webhook não processado', ['event' => $event]);
                    return ['success' => true, 'data' => ['message' => 'Evento não processado']];
            }
        } catch (\Exception $e) {
            $this->logger->error('Erro ao processar webhook', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Processa mensagem recebida
     */
    public function processIncomingMessage(array $payload): array
    {
        $data = $payload['data'] ?? [];
        
        if (empty($data)) {
            return ['success' => false, 'error' => 'Dados da mensagem não encontrados'];
        }

        // Se data é um array associativo (uma única mensagem), processa diretamente
        if (isset($data['key'])) {
            $this->processMessage($data);
        } else {
            // Se data é um array de mensagens, processa cada uma
            foreach ($data as $messageData) {
                if (is_array($messageData)) {
                    $this->processMessage($messageData);
                }
            }
        }

        return ['success' => true, 'data' => ['message' => 'Mensagens processadas']];
    }

    /**
     * Processa atualização de status de mensagem
     */
    public function processMessageUpdate(array $payload): array
    {
        $data = $payload['data'] ?? [];
        
        if (empty($data)) {
            return ['success' => false, 'error' => 'Dados da atualização não encontrados'];
        }

        // Garantir que $data seja um array
        if (!is_array($data)) {
            $this->logger->warning('Dados de atualização não são um array', [
                'data_type' => gettype($data),
                'data_value' => $data
            ]);
            return ['success' => false, 'error' => 'Formato de dados inválido'];
        }

        // Se $data não é um array multidimensional, transformar em um
        if (!isset($data[0]) || !is_array($data[0])) {
            $data = [$data];
        }

        foreach ($data as $updateData) {
            if (is_array($updateData)) {
                $this->updateMessageStatus($updateData);
            } else {
                $this->logger->warning('Item de atualização não é um array', [
                    'item_type' => gettype($updateData),
                    'item_value' => $updateData
                ]);
            }
        }

        return ['success' => true, 'data' => ['message' => 'Status das mensagens atualizados']];
    }

    /**
     * Processa atualização de conexão
     */
    public function processConnectionUpdate(array $payload): array
    {
        $data = $payload['data'] ?? [];
        
        if (empty($data)) {
            return ['success' => false, 'error' => 'Dados da conexão não encontrados'];
        }

        $state = $data['state'] ?? 'unknown';
        $instance = $payload['instance'] ?? 'unknown';
        
        $this->logger->info('Status de conexão atualizado', [
            'instance' => $instance,
            'state' => $state,
        ]);

        return ['success' => true, 'data' => ['message' => 'Status de conexão atualizado']];
    }

    /**
     * Processa uma mensagem individual
     */
    private function processMessage(array $messageData): void
    {
        $key = $messageData['key'] ?? [];
        $message = $messageData['message'] ?? [];
        
        // Verifica se é mensagem recebida (não enviada por nós)
        if (($key['fromMe'] ?? false) === true) {
            return;
        }

        $phoneNumber = $this->extractPhoneNumber($key['remoteJid'] ?? '');
        $messageContent = $this->extractMessageContent($message);
        
        if (empty($phoneNumber) || empty($messageContent)) {
            return;
        }

        // Busca ou cria lead baseado no número de telefone
        $lead = $this->findOrCreateLead($phoneNumber);
        
        if ($lead) {
            $this->addLeadNote($lead, $messageContent, $messageData);
            
            // Dispara evento para possível automação
            // $this->eventDispatcher->dispatch(new IncomingMessageEvent($lead, $messageContent));
        }
    }

    /**
     * Atualiza status de mensagem enviada
     */
    private function updateMessageStatus(array $updateData): void
    {
        try {
            $this->logger->info('Processando atualização de status de mensagem', [
                'updateData' => $updateData
            ]);

            // Extrai o keyId que corresponde ao message_id
            $messageId = $updateData['keyId'] ?? null;
            $status = $updateData['status'] ?? null;

            if (empty($messageId)) {
                $this->logger->warning('KeyId não encontrado nos dados de atualização', [
                    'updateData' => $updateData
                ]);
                return;
            }

            if (empty($status)) {
                $this->logger->warning('Status não encontrado nos dados de atualização', [
                    'updateData' => $updateData
                ]);
                return;
            }

            // Busca a mensagem pelo message_id
            /** @var EvolutionMessageRepository $repository */
            $repository = $this->entityManager->getRepository(EvolutionMessage::class);
            $evolutionMessage = $repository->findByEvolutionMessageId($messageId);

            if (!$evolutionMessage) {
                $this->logger->warning('Mensagem não encontrada na base de dados', [
                    'message_id' => $messageId,
                    'status' => $status
                ]);
                return;
            }

            $updated = false;
            $currentDateTime = new \DateTime();

            // Atualiza campos baseado no status
            switch ($status) {
                case 'DELIVERY_ACK':
                    if (!$evolutionMessage->getDeliveredAt()) {
                        $evolutionMessage->setStatus('delivered');
                        $evolutionMessage->setDeliveredAt($currentDateTime);
                        $evolutionMessage->setDeliveredReceipt($updateData);
                        $updated = true;
                        
                        $this->logger->info('Mensagem marcada como entregue', [
                            'message_id' => $messageId,
                            'delivered_at' => $currentDateTime->format('Y-m-d H:i:s')
                        ]);
                    }
                    break;

                case 'READ':
                    if (!$evolutionMessage->getReadAt()) {
                        $evolutionMessage->setStatus('read');
                        $evolutionMessage->setReadAt($currentDateTime);
                        $evolutionMessage->setReadReceipt($updateData);
                        $updated = true;
                        
                        $this->logger->info('Mensagem marcada como lida', [
                            'message_id' => $messageId,
                            'read_at' => $currentDateTime->format('Y-m-d H:i:s')
                        ]);
                    }
                    break;

                default:
                    $this->logger->info('Status não processado', [
                        'message_id' => $messageId,
                        'status' => $status
                    ]);
                    break;
            }

            // Salva as alterações se houve atualização
            if ($updated) {
                $this->entityManager->persist($evolutionMessage);
                $this->entityManager->flush();
                
                $this->logger->info('Status da mensagem atualizado com sucesso', [
                    'message_id' => $messageId,
                    'status' => $status,
                    'delivered_at' => $evolutionMessage->getDeliveredAt()?->format('Y-m-d H:i:s'),
                    'read_at' => $evolutionMessage->getReadAt()?->format('Y-m-d H:i:s')
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Erro ao atualizar status da mensagem', [
                'error' => $e->getMessage(),
                'updateData' => $updateData,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Extrai número de telefone do JID
     */
    private function extractPhoneNumber(string $jid): string
    {
        // Remove sufixo @s.whatsapp.net ou @g.us
        $phone = preg_replace('/@.*$/', '', $jid);
        
        // Remove caracteres não numéricos
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Extrai conteúdo da mensagem
     */
    private function extractMessageContent(array $message): string
    {
        // Mensagem de texto
        if (isset($message['conversation'])) {
            return $message['conversation'];
        }
        
        // Mensagem de texto estendida
        if (isset($message['extendedTextMessage']['text'])) {
            return $message['extendedTextMessage']['text'];
        }
        
        // Mensagem com mídia
        if (isset($message['imageMessage']['caption'])) {
            return $message['imageMessage']['caption'];
        }
        
        if (isset($message['videoMessage']['caption'])) {
            return $message['videoMessage']['caption'];
        }
        
        if (isset($message['documentMessage']['caption'])) {
            return $message['documentMessage']['caption'];
        }
        
        // Outros tipos de mensagem
        if (isset($message['audioMessage'])) {
            return '[Áudio]';
        }
        
        if (isset($message['imageMessage'])) {
            return '[Imagem]';
        }
        
        if (isset($message['videoMessage'])) {
            return '[Vídeo]';
        }
        
        if (isset($message['documentMessage'])) {
            return '[Documento]';
        }
        
        if (isset($message['stickerMessage'])) {
            return '[Sticker]';
        }
        
        if (isset($message['locationMessage'])) {
            return '[Localização]';
        }
        
        return '[Mensagem não suportada]';
    }

    /**
     * Busca ou cria lead baseado no número de telefone
     */
    private function findOrCreateLead(string $phoneNumber): ?Lead
    {
        // Busca lead existente pelos campos de telefone
        $phoneFields = ['mobile', 'phone'];
        
        foreach ($phoneFields as $field) {
            $leads = $this->leadModel->getRepository()->findBy([$field => $phoneNumber]);
            if (!empty($leads)) {
                return $leads[0];
            }
        }

        // Cria novo lead se não encontrou
        try {
            $lead = new Lead();
            $lead->addUpdatedField('mobile', $phoneNumber);
            
            $this->leadModel->saveEntity($lead);
            
            $this->logger->info('Novo lead criado via WhatsApp', [
                'lead_id' => $lead->getId(),
                'phone' => $phoneNumber,
            ]);
            
            return $lead;
        } catch (\Exception $e) {
            $this->logger->error('Erro ao criar lead via WhatsApp', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Adiciona nota ao lead com a mensagem recebida
     */
    private function addLeadNote(Lead $lead, string $messageContent, array $messageData): void
    {
        try {
            // Criar uma nova nota
            $note = new LeadNote();
            
            // Configurar o conteúdo da nota
            $noteText = sprintf(
                "Mensagem WhatsApp recebida:\n%s\n\nRecebida em: %s",
                $messageContent,
                date('d/m/Y H:i:s')
            );
            
            $note->setText($noteText);
            $note->setType('whatsapp');
            $note->setLead($lead);
            $note->setDateTime(new \DateTime());
            
            // Salvar a nota usando o NoteModel
            $this->noteModel->saveEntity($note);
            
            $this->logger->info('Nota adicionada ao lead com sucesso', [
                'lead_id' => $lead->getId(),
                'note_id' => $note->getId(),
                'message' => $messageContent,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erro ao adicionar nota ao lead', [
                'lead_id' => $lead->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Valida se o webhook é válido
     */
    public function validateWebhook(array $payload): bool
    {
        // Implementar validação de segurança se necessário
        // Por exemplo: verificar assinatura, IP de origem, etc.
        
        return isset($payload['event']) && isset($payload['instance']);
    }
}