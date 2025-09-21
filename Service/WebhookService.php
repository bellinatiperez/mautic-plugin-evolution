<?php

namespace MauticPlugin\EvolutionWhatsAppBundle\Service;

use MauticPlugin\EvolutionWhatsAppBundle\Model\AnalyticsModel;
use Mautic\LeadBundle\Model\LeadModel;
use Psr\Log\LoggerInterface;

/**
 * Webhook Service
 * 
 * Processes incoming webhooks from Evolution API
 */
class WebhookService
{
    private AnalyticsModel $analyticsModel;
    private LeadModel $leadModel;
    private LoggerInterface $logger;

    public function __construct(
        AnalyticsModel $analyticsModel,
        LeadModel $leadModel,
        LoggerInterface $logger
    ) {
        $this->analyticsModel = $analyticsModel;
        $this->leadModel = $leadModel;
        $this->logger = $logger;
    }

    /**
     * Process incoming webhook data
     */
    public function processWebhook(array $data): array
    {
        try {
            $this->logger->info('Processing Evolution webhook', ['data' => $data]);

            $event = $data['event'] ?? null;
            $instanceName = $data['instance'] ?? null;
            $webhookData = $data['data'] ?? [];

            if (!$event || !$instanceName) {
                throw new \InvalidArgumentException('Invalid webhook data: missing event or instance');
            }

            $result = [];

            switch ($event) {
                case 'MESSAGES_UPSERT':
                    $result = $this->processMessageUpsert($webhookData, $instanceName);
                    break;

                case 'MESSAGES_UPDATE':
                    $result = $this->processMessageUpdate($webhookData, $instanceName);
                    break;

                case 'CONTACTS_UPSERT':
                    $result = $this->processContactUpsert($webhookData, $instanceName);
                    break;

                case 'CONTACTS_UPDATE':
                    $result = $this->processContactUpdate($webhookData, $instanceName);
                    break;

                case 'CHATS_UPSERT':
                    $result = $this->processChatUpsert($webhookData, $instanceName);
                    break;

                case 'CONNECTION_UPDATE':
                    $result = $this->processConnectionUpdate($webhookData, $instanceName);
                    break;

                case 'PRESENCE_UPDATE':
                    $result = $this->processPresenceUpdate($webhookData, $instanceName);
                    break;

                default:
                    $this->logger->warning('Unhandled webhook event', ['event' => $event]);
                    $result = ['status' => 'ignored', 'event' => $event];
            }

            $this->logger->info('Webhook processed successfully', ['result' => $result]);
            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to process webhook', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Process message upsert event
     */
    private function processMessageUpsert(array $data, string $instanceName): array
    {
        $messages = $data['messages'] ?? [];
        $processedCount = 0;

        foreach ($messages as $message) {
            try {
                $this->processMessage($message, $instanceName, 'upsert');
                $processedCount++;
            } catch (\Exception $e) {
                $this->logger->error('Failed to process message upsert', [
                    'error' => $e->getMessage(),
                    'message' => $message
                ]);
            }
        }

        return [
            'status' => 'processed',
            'event' => 'MESSAGES_UPSERT',
            'processed_count' => $processedCount,
            'total_count' => count($messages)
        ];
    }

    /**
     * Process message update event
     */
    private function processMessageUpdate(array $data, string $instanceName): array
    {
        $messages = $data['messages'] ?? [];
        $processedCount = 0;

        foreach ($messages as $message) {
            try {
                $this->processMessage($message, $instanceName, 'update');
                $processedCount++;
            } catch (\Exception $e) {
                $this->logger->error('Failed to process message update', [
                    'error' => $e->getMessage(),
                    'message' => $message
                ]);
            }
        }

        return [
            'status' => 'processed',
            'event' => 'MESSAGES_UPDATE',
            'processed_count' => $processedCount,
            'total_count' => count($messages)
        ];
    }

    /**
     * Process individual message
     */
    private function processMessage(array $message, string $instanceName, string $action): void
    {
        $messageId = $message['key']['id'] ?? null;
        $remoteJid = $message['key']['remoteJid'] ?? null;
        $fromMe = $message['key']['fromMe'] ?? false;
        $messageType = $message['messageType'] ?? 'text';
        $messageContent = $this->extractMessageContent($message);
        $timestamp = $message['messageTimestamp'] ?? time();

        if (!$messageId || !$remoteJid) {
            throw new \InvalidArgumentException('Invalid message data');
        }

        // Extract phone number from remoteJid
        $phone = $this->extractPhoneFromJid($remoteJid);

        // Find or create contact in Mautic
        $contact = $this->findOrCreateContact($phone, $message);

        // Store message analytics data
        $this->analyticsModel->storeMessageData([
            'message_id' => $messageId,
            'instance_name' => $instanceName,
            'contact_id' => $contact->getId(),
            'phone' => $phone,
            'from_me' => $fromMe,
            'message_type' => $messageType,
            'content' => $messageContent,
            'timestamp' => $timestamp,
            'action' => $action,
            'raw_data' => json_encode($message)
        ]);

        // Update contact activity
        $this->updateContactActivity($contact, $message, $fromMe);
    }

    /**
     * Process contact upsert event
     */
    private function processContactUpsert(array $data, string $instanceName): array
    {
        $contacts = $data['contacts'] ?? [];
        $processedCount = 0;

        foreach ($contacts as $contactData) {
            try {
                $phone = $this->extractPhoneFromJid($contactData['id'] ?? '');
                if ($phone) {
                    $contact = $this->findOrCreateContact($phone, $contactData);
                    $this->updateContactFromWhatsApp($contact, $contactData);
                    $processedCount++;
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to process contact upsert', [
                    'error' => $e->getMessage(),
                    'contact' => $contactData
                ]);
            }
        }

        return [
            'status' => 'processed',
            'event' => 'CONTACTS_UPSERT',
            'processed_count' => $processedCount,
            'total_count' => count($contacts)
        ];
    }

    /**
     * Process contact update event
     */
    private function processContactUpdate(array $data, string $instanceName): array
    {
        return $this->processContactUpsert($data, $instanceName);
    }

    /**
     * Process chat upsert event
     */
    private function processChatUpsert(array $data, string $instanceName): array
    {
        $chats = $data['chats'] ?? [];
        $processedCount = 0;

        foreach ($chats as $chat) {
            try {
                $phone = $this->extractPhoneFromJid($chat['id'] ?? '');
                if ($phone) {
                    $contact = $this->findOrCreateContact($phone, $chat);
                    $this->analyticsModel->storeChatData([
                        'instance_name' => $instanceName,
                        'contact_id' => $contact->getId(),
                        'phone' => $phone,
                        'chat_data' => json_encode($chat)
                    ]);
                    $processedCount++;
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to process chat upsert', [
                    'error' => $e->getMessage(),
                    'chat' => $chat
                ]);
            }
        }

        return [
            'status' => 'processed',
            'event' => 'CHATS_UPSERT',
            'processed_count' => $processedCount,
            'total_count' => count($chats)
        ];
    }

    /**
     * Process connection update event
     */
    private function processConnectionUpdate(array $data, string $instanceName): array
    {
        $state = $data['state'] ?? 'unknown';
        
        $this->analyticsModel->storeConnectionData([
            'instance_name' => $instanceName,
            'state' => $state,
            'timestamp' => time(),
            'data' => json_encode($data)
        ]);

        return [
            'status' => 'processed',
            'event' => 'CONNECTION_UPDATE',
            'state' => $state
        ];
    }

    /**
     * Process presence update event
     */
    private function processPresenceUpdate(array $data, string $instanceName): array
    {
        $presences = $data['presences'] ?? [];
        $processedCount = 0;

        foreach ($presences as $presence) {
            try {
                $phone = $this->extractPhoneFromJid($presence['id'] ?? '');
                if ($phone) {
                    $contact = $this->findOrCreateContact($phone, []);
                    $this->analyticsModel->storePresenceData([
                        'instance_name' => $instanceName,
                        'contact_id' => $contact->getId(),
                        'phone' => $phone,
                        'presence' => $presence['presences'] ?? 'unavailable',
                        'timestamp' => time()
                    ]);
                    $processedCount++;
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to process presence update', [
                    'error' => $e->getMessage(),
                    'presence' => $presence
                ]);
            }
        }

        return [
            'status' => 'processed',
            'event' => 'PRESENCE_UPDATE',
            'processed_count' => $processedCount,
            'total_count' => count($presences)
        ];
    }

    /**
     * Extract phone number from WhatsApp JID
     */
    private function extractPhoneFromJid(string $jid): string
    {
        // Remove @s.whatsapp.net or @g.us suffix
        $phone = preg_replace('/@[sg]\.whatsapp\.net$/', '', $jid);
        
        // Clean and format phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        return $phone;
    }

    /**
     * Extract message content based on message type
     */
    private function extractMessageContent(array $message): string
    {
        $messageType = $message['messageType'] ?? 'text';
        
        switch ($messageType) {
            case 'conversation':
            case 'extendedTextMessage':
                return $message['message']['conversation'] ?? 
                       $message['message']['extendedTextMessage']['text'] ?? '';
            
            case 'imageMessage':
                return '[Image]' . ($message['message']['imageMessage']['caption'] ?? '');
            
            case 'videoMessage':
                return '[Video]' . ($message['message']['videoMessage']['caption'] ?? '');
            
            case 'audioMessage':
                return '[Audio]';
            
            case 'documentMessage':
                return '[Document] ' . ($message['message']['documentMessage']['fileName'] ?? '');
            
            default:
                return '[' . ucfirst($messageType) . ']';
        }
    }

    /**
     * Find or create contact in Mautic
     */
    private function findOrCreateContact(string $phone, array $data): \Mautic\LeadBundle\Entity\Lead
    {
        // Try to find existing contact by phone
        $contact = $this->leadModel->getRepository()->findOneBy(['mobile' => $phone]);
        
        if (!$contact) {
            $contact = $this->leadModel->getRepository()->findOneBy(['phone' => $phone]);
        }

        if (!$contact) {
            // Create new contact
            $contact = new \Mautic\LeadBundle\Entity\Lead();
            $contact->setMobile($phone);
            
            // Extract name from WhatsApp data if available
            $name = $data['name'] ?? $data['pushName'] ?? $data['verifiedName'] ?? null;
            if ($name) {
                $contact->setFirstname($name);
            }
            
            $this->leadModel->saveEntity($contact);
        }

        return $contact;
    }

    /**
     * Update contact with WhatsApp data
     */
    private function updateContactFromWhatsApp(\Mautic\LeadBundle\Entity\Lead $contact, array $data): void
    {
        $updated = false;

        // Update name if available and not already set
        $name = $data['name'] ?? $data['pushName'] ?? $data['verifiedName'] ?? null;
        if ($name && !$contact->getFirstname()) {
            $contact->setFirstname($name);
            $updated = true;
        }

        // Update profile picture URL if available
        $profilePicUrl = $data['profilePicUrl'] ?? null;
        if ($profilePicUrl) {
            $contact->setFieldValue('whatsapp_profile_pic', $profilePicUrl);
            $updated = true;
        }

        if ($updated) {
            $this->leadModel->saveEntity($contact);
        }
    }

    /**
     * Update contact activity based on message
     */
    private function updateContactActivity(\Mautic\LeadBundle\Entity\Lead $contact, array $message, bool $fromMe): void
    {
        // Update last activity timestamp
        $contact->setLastActive(new \DateTime());
        
        // Add tags based on message activity
        if (!$fromMe) {
            // Message received from contact
            $contact->addTag('whatsapp-active');
        }

        $this->leadModel->saveEntity($contact);
    }
}