<?php

namespace MauticPlugin\EvolutionWhatsAppBundle\EventListener;

use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\Event\LeadMergeEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\EvolutionWhatsAppBundle\Model\AnalyticsModel;
use MauticPlugin\EvolutionWhatsAppBundle\Service\EvolutionApiService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class LeadSubscriber
 */
class LeadSubscriber implements EventSubscriberInterface
{
    /**
     * LeadSubscriber constructor.
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
            LeadEvents::LEAD_POST_SAVE   => ['onLeadPostSave', 0],
            LeadEvents::LEAD_POST_DELETE => ['onLeadPostDelete', 0],
            LeadEvents::LEAD_POST_MERGE  => ['onLeadPostMerge', 0],
        ];
    }

    /**
     * Handle lead post save event
     */
    public function onLeadPostSave(LeadEvent $event): void
    {
        $lead = $event->getLead();
        $changes = $event->getChanges();

        // Skip if no relevant changes
        if (empty($changes) || !$this->hasWhatsAppRelevantChanges($changes)) {
            return;
        }

        try {
            // Check if lead has WhatsApp phone number
            $phone = $lead->getPhone();
            if (!$phone) {
                return;
            }

            // Normalize phone number
            $normalizedPhone = $this->normalizePhoneNumber($phone);
            if (!$normalizedPhone) {
                return;
            }

            // Check if this is a new lead or updated lead
            if ($event->isNew()) {
                $this->handleNewLead($lead, $normalizedPhone);
            } else {
                $this->handleUpdatedLead($lead, $normalizedPhone, $changes);
            }

            // Store analytics data
            $this->storeLeadAnalytics($lead, $changes, $event->isNew());

        } catch (\Exception $e) {
            $this->logger->error('Evolution Integration: Error handling lead save event', [
                'lead_id' => $lead->getId(),
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle lead post delete event
     */
    public function onLeadPostDelete(LeadEvent $event): void
    {
        $lead = $event->getLead();

        try {
            // Clean up WhatsApp related data
            $this->cleanupWhatsAppData($lead);

            // Store analytics data
            $this->analyticsModel->storeContactEvent([
                'contact_id'   => $lead->getId(),
                'phone'        => $lead->getPhone(),
                'event_type'   => 'contact_deleted',
                'event_data'   => [
                    'deleted_at' => new \DateTime(),
                    'reason'     => 'lead_deleted',
                ],
                'instance'     => $this->apiService->getDefaultInstance(),
                'created_at'   => new \DateTime(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Evolution Integration: Error handling lead delete event', [
                'lead_id' => $lead->getId(),
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle lead post merge event
     */
    public function onLeadPostMerge(LeadMergeEvent $event): void
    {
        $winner = $event->getWinner();
        $loser = $event->getLoser();

        try {
            // Merge WhatsApp data from loser to winner
            $this->mergeWhatsAppData($winner, $loser);

            // Store analytics data
            $this->analyticsModel->storeContactEvent([
                'contact_id'   => $winner->getId(),
                'phone'        => $winner->getPhone(),
                'event_type'   => 'contact_merged',
                'event_data'   => [
                    'merged_from' => $loser->getId(),
                    'merged_at'   => new \DateTime(),
                ],
                'instance'     => $this->apiService->getDefaultInstance(),
                'created_at'   => new \DateTime(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Evolution Integration: Error handling lead merge event', [
                'winner_id' => $winner->getId(),
                'loser_id'  => $loser->getId(),
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if changes are relevant to WhatsApp integration
     */
    private function hasWhatsAppRelevantChanges(array $changes): bool
    {
        $relevantFields = [
            'phone',
            'mobile',
            'firstname',
            'lastname',
            'email',
            'whatsapp_id',
            'whatsapp_status',
            'whatsapp_verified',
        ];

        foreach ($relevantFields as $field) {
            if (isset($changes[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize phone number for WhatsApp
     */
    private function normalizePhoneNumber(string $phone): ?string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Must have at least 10 digits
        if (strlen($phone) < 10) {
            return null;
        }

        // Add country code if missing (assuming Brazil +55)
        if (strlen($phone) === 10 || strlen($phone) === 11) {
            $phone = '55' . $phone;
        }

        // Remove leading zeros after country code
        $phone = preg_replace('/^55(0+)/', '55', $phone);

        return $phone;
    }

    /**
     * Handle new lead
     */
    private function handleNewLead($lead, string $phone): void
    {
        // Check if contact exists in WhatsApp
        $whatsappContact = $this->apiService->getContactByPhone($phone);

        if ($whatsappContact) {
            // Update lead with WhatsApp data
            $this->updateLeadWithWhatsAppData($lead, $whatsappContact);

            // Send welcome message if configured
            $this->sendWelcomeMessage($phone, $lead);
        }

        // Add to WhatsApp contact list
        $this->apiService->addContact([
            'phone'      => $phone,
            'name'       => $lead->getName(),
            'mautic_id'  => $lead->getId(),
        ]);
    }

    /**
     * Handle updated lead
     */
    private function handleUpdatedLead($lead, string $phone, array $changes): void
    {
        // Update WhatsApp contact if phone changed
        if (isset($changes['phone']) || isset($changes['mobile'])) {
            $oldPhone = $changes['phone'][0] ?? $changes['mobile'][0] ?? null;
            if ($oldPhone) {
                $this->apiService->updateContactPhone($oldPhone, $phone);
            }
        }

        // Update contact information in WhatsApp
        $this->apiService->updateContact($phone, [
            'name'       => $lead->getName(),
            'mautic_id'  => $lead->getId(),
        ]);

        // Send notification if important fields changed
        if ($this->shouldNotifyOfChanges($changes)) {
            $this->sendChangeNotification($phone, $lead, $changes);
        }
    }

    /**
     * Update lead with WhatsApp data
     */
    private function updateLeadWithWhatsAppData($lead, array $whatsappData): void
    {
        if (isset($whatsappData['name']) && !$lead->getFirstname()) {
            $lead->setFirstname($whatsappData['name']);
        }

        if (isset($whatsappData['profile_picture'])) {
            $lead->addUpdatedField('whatsapp_profile_picture', $whatsappData['profile_picture']);
        }

        if (isset($whatsappData['status'])) {
            $lead->addUpdatedField('whatsapp_status', $whatsappData['status']);
        }

        if (isset($whatsappData['verified'])) {
            $lead->addUpdatedField('whatsapp_verified', $whatsappData['verified']);
        }

        if (isset($whatsappData['last_seen'])) {
            $lead->addUpdatedField('whatsapp_last_seen', $whatsappData['last_seen']);
        }

        $lead->addUpdatedField('whatsapp_id', $whatsappData['id'] ?? '');
        $lead->addUpdatedField('whatsapp_sync_date', new \DateTime());
    }

    /**
     * Send welcome message
     */
    private function sendWelcomeMessage(string $phone, $lead): void
    {
        $config = $this->apiService->getIntegrationSettings();
        
        if (!$config['send_welcome_message'] ?? false) {
            return;
        }

        $message = $config['welcome_message_template'] ?? 'Olá {name}, bem-vindo(a)!';
        $message = str_replace('{name}', $lead->getName(), $message);

        $this->apiService->sendMessage($phone, [
            'type' => 'text',
            'text' => $message,
        ]);
    }

    /**
     * Check if should notify of changes
     */
    private function shouldNotifyOfChanges(array $changes): bool
    {
        $notifiableFields = ['email', 'firstname', 'lastname'];
        
        foreach ($notifiableFields as $field) {
            if (isset($changes[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send change notification
     */
    private function sendChangeNotification(string $phone, $lead, array $changes): void
    {
        $config = $this->apiService->getIntegrationSettings();
        
        if (!$config['notify_profile_changes'] ?? false) {
            return;
        }

        $changedFields = array_keys($changes);
        $message = sprintf(
            'Seus dados foram atualizados: %s',
            implode(', ', $changedFields)
        );

        $this->apiService->sendMessage($phone, [
            'type' => 'text',
            'text' => $message,
        ]);
    }

    /**
     * Store lead analytics
     */
    private function storeLeadAnalytics($lead, array $changes, bool $isNew): void
    {
        $eventType = $isNew ? 'contact_created' : 'contact_updated';
        
        $this->analyticsModel->storeContactEvent([
            'contact_id'   => $lead->getId(),
            'phone'        => $lead->getPhone(),
            'event_type'   => $eventType,
            'event_data'   => [
                'changes'    => $changes,
                'is_new'     => $isNew,
                'name'       => $lead->getName(),
                'email'      => $lead->getEmail(),
            ],
            'instance'     => $this->apiService->getDefaultInstance(),
            'created_at'   => new \DateTime(),
        ]);
    }

    /**
     * Cleanup WhatsApp data
     */
    private function cleanupWhatsAppData($lead): void
    {
        $phone = $lead->getPhone();
        if (!$phone) {
            return;
        }

        $normalizedPhone = $this->normalizePhoneNumber($phone);
        if ($normalizedPhone) {
            // Remove from WhatsApp contact list
            $this->apiService->removeContact($normalizedPhone);
        }
    }

    /**
     * Merge WhatsApp data from loser to winner
     */
    private function mergeWhatsAppData($winner, $loser): void
    {
        // Get WhatsApp fields from loser
        $whatsappFields = [
            'whatsapp_id',
            'whatsapp_status',
            'whatsapp_verified',
            'whatsapp_profile_picture',
            'whatsapp_last_seen',
        ];

        foreach ($whatsappFields as $field) {
            $loserValue = $loser->getFieldValue($field);
            $winnerValue = $winner->getFieldValue($field);

            // Only update if winner doesn't have the field or loser has more recent data
            if ($loserValue && (!$winnerValue || $this->isMoreRecentWhatsAppData($loser, $winner))) {
                $winner->addUpdatedField($field, $loserValue);
            }
        }

        // Update WhatsApp contact mapping
        $loserPhone = $loser->getPhone();
        $winnerPhone = $winner->getPhone();

        if ($loserPhone && $winnerPhone && $loserPhone !== $winnerPhone) {
            $this->apiService->mergeContacts($loserPhone, $winnerPhone, $winner->getId());
        }
    }

    /**
     * Check if loser has more recent WhatsApp data
     */
    private function isMoreRecentWhatsAppData($loser, $winner): bool
    {
        $loserSyncDate = $loser->getFieldValue('whatsapp_sync_date');
        $winnerSyncDate = $winner->getFieldValue('whatsapp_sync_date');

        if (!$loserSyncDate || !$winnerSyncDate) {
            return (bool) $loserSyncDate;
        }

        return $loserSyncDate > $winnerSyncDate;
    }
}