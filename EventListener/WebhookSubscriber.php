<?php

namespace MauticPlugin\EvolutionAnalyticsBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\WebhookBundle\Event\WebhookBuilderEvent;
use Mautic\WebhookBundle\Event\WebhookEvent;
use Mautic\WebhookBundle\WebhookEvents;
use MauticPlugin\EvolutionAnalyticsBundle\Service\WebhookService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class WebhookSubscriber
 */
class WebhookSubscriber implements EventSubscriberInterface
{
    /**
     * WebhookSubscriber constructor.
     */
    public function __construct()
    {
        // Dependencies will be injected as needed
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WebhookEvents::WEBHOOK_ON_BUILD => ['onWebhookBuild', 0],
            WebhookEvents::WEBHOOK_KILL    => ['onWebhookKill', 0],
        ];
    }

    /**
     * Add Evolution events to webhook builder
     */
    public function onWebhookBuild(WebhookBuilderEvent $event): void
    {
        // Add Evolution WhatsApp events
        $evolutionEvents = [
            'evolution.message.received' => [
                'label'       => 'mautic.evolution.webhook.event.message.received',
                'description' => 'mautic.evolution.webhook.event.message.received.desc',
            ],
            'evolution.message.sent' => [
                'label'       => 'mautic.evolution.webhook.event.message.sent',
                'description' => 'mautic.evolution.webhook.event.message.sent.desc',
            ],
            'evolution.message.delivered' => [
                'label'       => 'mautic.evolution.webhook.event.message.delivered',
                'description' => 'mautic.evolution.webhook.event.message.delivered.desc',
            ],
            'evolution.message.read' => [
                'label'       => 'mautic.evolution.webhook.event.message.read',
                'description' => 'mautic.evolution.webhook.event.message.read.desc',
            ],
            'evolution.contact.connected' => [
                'label'       => 'mautic.evolution.webhook.event.contact.connected',
                'description' => 'mautic.evolution.webhook.event.contact.connected.desc',
            ],
            'evolution.contact.disconnected' => [
                'label'       => 'mautic.evolution.webhook.event.contact.disconnected',
                'description' => 'mautic.evolution.webhook.event.contact.disconnected.desc',
            ],
            'evolution.group.joined' => [
                'label'       => 'mautic.evolution.webhook.event.group.joined',
                'description' => 'mautic.evolution.webhook.event.group.joined.desc',
            ],
            'evolution.group.left' => [
                'label'       => 'mautic.evolution.webhook.event.group.left',
                'description' => 'mautic.evolution.webhook.event.group.left.desc',
            ],
            'evolution.status.updated' => [
                'label'       => 'mautic.evolution.webhook.event.status.updated',
                'description' => 'mautic.evolution.webhook.event.status.updated.desc',
            ],
        ];

        foreach ($evolutionEvents as $eventKey => $eventData) {
            $event->addEvent($eventKey, $eventData);
        }
    }

    /**
     * Handle webhook kill event
     */
    public function onWebhookKill(WebhookEvent $event): void
    {
        $webhook = $event->getWebhook();
        
        try {
            // Check if this webhook is related to Evolution
            $events = $webhook->getEvents();
            $hasEvolutionEvents = false;

            foreach ($events as $webhookEvent) {
                if (strpos($webhookEvent, 'evolution.') === 0) {
                    $hasEvolutionEvents = true;
                    break;
                }
            }

            if ($hasEvolutionEvents) {
                // Clean up Evolution webhook registrations
                $this->webhookService->unregisterWebhook($webhook);

                // Store analytics data
                $this->analyticsModel->storeWebhookEvent([
                    'webhook_id'   => $webhook->getId(),
                    'event_type'   => 'webhook_deleted',
                    'event_data'   => [
                        'webhook_url' => $webhook->getWebhookUrl(),
                        'events'      => $events,
                        'deleted_at'  => new \DateTime(),
                    ],
                    'created_at'   => new \DateTime(),
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Evolution Integration: Error handling webhook kill event', [
                'webhook_id' => $webhook->getId(),
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process incoming Evolution webhook
     */
    public function processEvolutionWebhook(array $data): void
    {
        try {
            $eventType = $data['event'] ?? null;
            $instanceName = $data['instance'] ?? null;
            $messageData = $data['data'] ?? [];

            if (!$eventType || !$instanceName) {
                throw new \InvalidArgumentException('Missing required webhook data');
            }

            // Validate instance
            if (!$this->webhookService->isValidInstance($instanceName)) {
                throw new \InvalidArgumentException('Invalid instance name');
            }

            // Process based on event type
            switch ($eventType) {
                case 'messages.upsert':
                    $this->processMessageEvent($messageData, $instanceName);
                    break;

                case 'messages.update':
                    $this->processMessageUpdateEvent($messageData, $instanceName);
                    break;

                case 'presence.update':
                    $this->processPresenceEvent($messageData, $instanceName);
                    break;

                case 'contacts.upsert':
                    $this->processContactEvent($messageData, $instanceName);
                    break;

                case 'groups.upsert':
                    $this->processGroupEvent($messageData, $instanceName);
                    break;

                case 'connection.update':
                    $this->processConnectionEvent($messageData, $instanceName);
                    break;

                default:
                    $this->logger->warning('Evolution Integration: Unknown webhook event type', [
                        'event_type' => $eventType,
                        'instance'   => $instanceName,
                    ]);
            }

            // Store webhook analytics
            $this->storeWebhookAnalytics($eventType, $messageData, $instanceName);

        } catch (\Exception $e) {
            $this->logger->error('Evolution Integration: Error processing webhook', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);

            // Store error analytics
            $this->analyticsModel->storeWebhookEvent([
                'event_type'   => 'webhook_error',
                'event_data'   => [
                    'error'        => $e->getMessage(),
                    'webhook_data' => $data,
                ],
                'instance'     => $instanceName ?? 'unknown',
                'created_at'   => new \DateTime(),
            ]);
        }
    }

    /**
     * Process message event
     */
    private function processMessageEvent(array $data, string $instance): void
    {
        foreach ($data as $message) {
            $phone = $this->extractPhoneFromMessage($message);
            if (!$phone) {
                continue;
            }

            // Find or create contact
            $contact = $this->webhookService->findOrCreateContact($phone, $message);

            // Process message content
            $this->processMessageContent($message, $contact, $instance);

            // Trigger webhook events
            $this->triggerWebhookEvent('evolution.message.received', [
                'contact'  => $contact,
                'message'  => $message,
                'instance' => $instance,
            ]);
        }
    }

    /**
     * Process message update event
     */
    private function processMessageUpdateEvent(array $data, string $instance): void
    {
        foreach ($data as $update) {
            $messageId = $update['key']['id'] ?? null;
            $status = $update['update']['status'] ?? null;

            if (!$messageId || !$status) {
                continue;
            }

            // Update message status
            $this->webhookService->updateMessageStatus($messageId, $status);

            // Trigger appropriate webhook event
            $eventType = $this->getMessageEventType($status);
            if ($eventType) {
                $this->triggerWebhookEvent($eventType, [
                    'message_id' => $messageId,
                    'status'     => $status,
                    'instance'   => $instance,
                    'timestamp'  => $update['update']['messageTimestamp'] ?? time(),
                ]);
            }
        }
    }

    /**
     * Process presence event
     */
    private function processPresenceEvent(array $data, string $instance): void
    {
        $phone = $this->normalizePhoneNumber($data['id'] ?? '');
        if (!$phone) {
            return;
        }

        $presence = $data['presences'][0] ?? [];
        $lastKnownPresence = $presence['lastKnownPresence'] ?? null;

        if ($lastKnownPresence) {
            // Update contact presence
            $contact = $this->webhookService->findContactByPhone($phone);
            if ($contact) {
                $this->webhookService->updateContactPresence($contact, $lastKnownPresence);
            }
        }
    }

    /**
     * Process contact event
     */
    private function processContactEvent(array $data, string $instance): void
    {
        foreach ($data as $contactData) {
            $phone = $this->normalizePhoneNumber($contactData['id'] ?? '');
            if (!$phone) {
                continue;
            }

            // Update or create contact
            $contact = $this->webhookService->updateOrCreateContact($phone, $contactData);

            // Trigger webhook event
            $this->triggerWebhookEvent('evolution.contact.connected', [
                'contact'  => $contact,
                'instance' => $instance,
                'data'     => $contactData,
            ]);
        }
    }

    /**
     * Process group event
     */
    private function processGroupEvent(array $data, string $instance): void
    {
        foreach ($data as $groupData) {
            $groupId = $groupData['id'] ?? null;
            if (!$groupId) {
                continue;
            }

            // Process group members
            $participants = $groupData['participants'] ?? [];
            foreach ($participants as $participant) {
                $phone = $this->normalizePhoneNumber($participant['id'] ?? '');
                if (!$phone) {
                    continue;
                }

                $contact = $this->webhookService->findOrCreateContact($phone, $participant);
                
                // Add to group segment if configured
                $this->webhookService->addContactToGroupSegment($contact, $groupData);

                // Trigger webhook event
                $this->triggerWebhookEvent('evolution.group.joined', [
                    'contact'  => $contact,
                    'group'    => $groupData,
                    'instance' => $instance,
                ]);
            }
        }
    }

    /**
     * Process connection event
     */
    private function processConnectionEvent(array $data, string $instance): void
    {
        $state = $data['state'] ?? null;
        
        if ($state) {
            // Update instance status
            $this->webhookService->updateInstanceStatus($instance, $state);

            // Trigger webhook event
            $this->triggerWebhookEvent('evolution.status.updated', [
                'instance' => $instance,
                'state'    => $state,
                'data'     => $data,
            ]);
        }
    }

    /**
     * Extract phone from message
     */
    private function extractPhoneFromMessage(array $message): ?string
    {
        $remoteJid = $message['key']['remoteJid'] ?? '';
        
        // Extract phone number from JID
        if (strpos($remoteJid, '@') !== false) {
            $phone = explode('@', $remoteJid)[0];
            return $this->normalizePhoneNumber($phone);
        }

        return null;
    }

    /**
     * Normalize phone number
     */
    private function normalizePhoneNumber(string $phone): ?string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) < 10) {
            return null;
        }

        // Add country code if missing (assuming Brazil +55)
        if (strlen($phone) === 10 || strlen($phone) === 11) {
            $phone = '55' . $phone;
        }

        return $phone;
    }

    /**
     * Process message content
     */
    private function processMessageContent(array $message, $contact, string $instance): void
    {
        $messageType = $message['message']['messageType'] ?? 'text';
        $content = '';

        switch ($messageType) {
            case 'conversation':
            case 'extendedTextMessage':
                $content = $message['message']['conversation'] ?? 
                          $message['message']['extendedTextMessage']['text'] ?? '';
                break;

            case 'imageMessage':
                $content = '[Image] ' . ($message['message']['imageMessage']['caption'] ?? '');
                break;

            case 'videoMessage':
                $content = '[Video] ' . ($message['message']['videoMessage']['caption'] ?? '');
                break;

            case 'audioMessage':
                $content = '[Audio Message]';
                break;

            case 'documentMessage':
                $fileName = $message['message']['documentMessage']['fileName'] ?? 'Document';
                $content = '[Document] ' . $fileName;
                break;

            case 'locationMessage':
                $content = '[Location Shared]';
                break;

            default:
                $content = '[' . ucfirst($messageType) . ']';
        }

        // Store message in contact timeline
        $this->webhookService->addContactTimelineEntry($contact, [
            'type'       => 'whatsapp_message',
            'content'    => $content,
            'message_id' => $message['key']['id'] ?? '',
            'timestamp'  => $message['messageTimestamp'] ?? time(),
            'instance'   => $instance,
            'raw_data'   => $message,
        ]);
    }

    /**
     * Get message event type from status
     */
    private function getMessageEventType(int $status): ?string
    {
        switch ($status) {
            case 1:
                return 'evolution.message.sent';
            case 2:
                return 'evolution.message.delivered';
            case 3:
                return 'evolution.message.read';
            default:
                return null;
        }
    }

    /**
     * Trigger webhook event
     */
    private function triggerWebhookEvent(string $eventType, array $data): void
    {
        // Get registered webhooks for this event
        $webhooks = $this->webhookService->getWebhooksForEvent($eventType);

        foreach ($webhooks as $webhook) {
            try {
                $this->webhookService->sendWebhook($webhook, $eventType, $data);
            } catch (\Exception $e) {
                $this->logger->error('Evolution Integration: Error sending webhook', [
                    'webhook_id' => $webhook->getId(),
                    'event_type' => $eventType,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Store webhook analytics
     */
    private function storeWebhookAnalytics(string $eventType, array $data, string $instance): void
    {
        $this->analyticsModel->storeWebhookEvent([
            'event_type'   => $eventType,
            'event_data'   => $data,
            'instance'     => $instance,
            'created_at'   => new \DateTime(),
        ]);
    }
}