<?php

namespace MauticPlugin\MauticEvolutionBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Helper\TokenHelper;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticEvolutionBundle\Entity\EvolutionMessage;
use MauticPlugin\MauticEvolutionBundle\Entity\EvolutionMessageRepository;
use MauticPlugin\MauticEvolutionBundle\Service\EvolutionApiService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @extends FormModel<EvolutionMessage>
 */
class MessageModel extends FormModel
{
    public function __construct(
        protected LeadModel $leadModel,
        protected EvolutionApiService $evolutionApiService,
        EntityManagerInterface $em,
        CorePermissions $security,
        EventDispatcherInterface $dispatcher,
        UrlGeneratorInterface $router,
        Translator $translator,
        UserHelper $userHelper,
        LoggerInterface $mauticLogger,
        CoreParametersHelper $coreParametersHelper
    ) {
        parent::__construct($em, $security, $dispatcher, $router, $translator, $userHelper, $mauticLogger, $coreParametersHelper);
    }

    /**
     * {@inheritdoc}
     */
    public function getRepository(): EvolutionMessageRepository
    {
        return $this->em->getRepository(EvolutionMessage::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissionBase(): string
    {
        return 'evolution:messages';
    }

    /**
     * Send message via Evolution API
     */
    public function sendMessage(Lead $lead, string $message, ?string $templateName = null, ?string $groupAlias = null, string $phoneField = 'mobile', array $headers = [], array $metadata = []): ?EvolutionMessage
    {
        $phoneNumber = $this->getLeadPhoneNumber($lead, $phoneField);
        
        if (empty($phoneNumber)) {
            $this->logger->warning('Cannot send Evolution message: Lead has no phone number', ['leadId' => $lead->getId()]);
            return null;
        }

        try {
            // Interpolate tokens in message content using lead data
            $leadData = $lead->getProfileFields();
            
            // First, handle simple tokens like {firstname} by converting them to {contactfield=firstname}
            $message = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '{contactfield=$1}', $message);
            
            // Then use TokenHelper to replace the tokens
            $interpolatedMessage = TokenHelper::findLeadTokens($message, $leadData, true);

            // Create message entity
            $evolutionMessage = new EvolutionMessage();
            $evolutionMessage->setLead($lead);
            $evolutionMessage->setPhoneNumber($phoneNumber);
            $evolutionMessage->setMessageContent($interpolatedMessage);
            $evolutionMessage->setTemplateName($templateName);
            $evolutionMessage->setStatus('pending');
            if (!empty($metadata)) {
                $evolutionMessage->setMetadata($metadata);
            }

            // Send via API
            $response = !empty($groupAlias)
                ? $this->evolutionApiService->sendTextWithGroupBalancing($groupAlias, $phoneNumber, $interpolatedMessage, [], $lead, null, $headers, $metadata)
                : $this->evolutionApiService->sendTextWithBalancing($phoneNumber, $interpolatedMessage, $lead, null, $headers, $metadata);

            $messageId = $response['data']['key']['id'] ?? null;

            if ($messageId) {
                $evolutionMessage->setMessageId($messageId);
                $evolutionMessage->setStatus('sent');
                $evolutionMessage->setSentAt(new \DateTime());
            } else {
                $evolutionMessage->setStatus('failed');
                $evolutionMessage->setErrorMessage($response['error'] ?? 'Failed to send message via Evolution API');
            }

            $this->saveEntity($evolutionMessage);
            
            return $evolutionMessage;
        } catch (\Exception $e) {
            $this->logger->error('Error sending Evolution message', [
                'leadId' => $lead->getId(),
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Get messages by lead
     */
    public function getMessagesByLead(Lead $lead): array
    {
        return $this->getRepository()->findByLead($lead);
    }

    /**
     * Get messages by status
     */
    public function getMessagesByStatus(string $status): array
    {
        return $this->getRepository()->findByStatus($status);
    }

    /**
     * Get pending messages
     */
    public function getPendingMessages(): array
    {
        return $this->getRepository()->findPendingMessages();
    }

    /**
     * Update message status from webhook
     */
    public function updateMessageStatus(string $MessageId, string $status, ?\DateTime $timestamp = null): bool
    {
        $message = $this->getRepository()->findByMessageId($MessageId);
        
        if (!$message) {
            return false;
        }

        $message->setStatus($status);
        
        switch ($status) {
            case 'delivered':
                $message->setDeliveredAt($timestamp ?: new \DateTime());
                break;
            case 'read':
                $message->setReadAt($timestamp ?: new \DateTime());
                break;
        }

        $this->saveEntity($message);
        
        return true;
    }

    /**
     * Get message statistics
     */
    public function getMessageStats(): array
    {
        return $this->getRepository()->getMessageStats();
    }

    /**
     * Get the contact's phone number honoring selected field
     */
    private function getLeadPhoneNumber(Lead $lead, string $phoneField = 'mobile'): ?string
    {
        $fieldsOrder = array_unique(array_filter([$phoneField, 'mobile', 'phone', 'whatsapp']));
        foreach ($fieldsOrder as $field) {
            $phone = method_exists($lead, 'getFieldValue') ? $lead->getFieldValue($field) : null;
            if (!empty($phone)) {
                $clean = preg_replace('/[^0-9]/', '', (string) $phone);
                if (strlen($clean) >= 10 && substr($clean, 0, 2) !== '55') {
                    $clean = '55' . $clean;
                }
                return $clean;
            }
        }
        // fallback to lead helper
        $fallback = $lead->getLeadPhoneNumber();
        return $fallback ? preg_replace('/[^0-9]/', '', $fallback) : null;
    }
}