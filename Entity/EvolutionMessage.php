<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEvolutionBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\CommonEntity;
use Mautic\LeadBundle\Entity\Lead;

/**
 * Class EvolutionMessage
 * 
 * Entidade para armazenar mensagens enviadas via Evolution API
 */
#[ORM\Entity]
#[ORM\Table(name: 'evolution_messages')]
class EvolutionMessage extends CommonEntity
{
    /**
     * @var int|null
     */
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    /**
     * @var Lead|null
     */
    #[ORM\ManyToOne(targetEntity: Lead::class)]
    #[ORM\JoinColumn(name: 'lead_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    protected ?Lead $lead = null;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'phone_number', type: 'string', length: 20)]
    protected ?string $phoneNumber = null;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'message_content', type: 'text')]
    protected ?string $messageContent = null;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'template_name', type: 'string', length: 100, nullable: true)]
    protected ?string $templateName = null;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'message_id', type: 'string', length: 64, nullable: true)]
    protected ?string $messageId = null;

    /**
     * @var string
     */
    #[ORM\Column(name: 'status', type: 'string', length: 20)]
    protected string $status = 'pending';

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    protected ?string $errorMessage = null;

    /**
     * @var \DateTime|null
     */
    #[ORM\Column(name: 'sent_at', type: 'datetime', nullable: true)]
    protected ?\DateTime $sentAt = null;

    /**
     * @var array|null
     */
    #[ORM\Column(name: 'sent_receipt', type: 'json', nullable: true)]
    protected ?array $sentReceipt = null;

    /**
     * @var \DateTime|null
     */
    #[ORM\Column(name: 'delivered_at', type: 'datetime', nullable: true)]
    protected ?\DateTime $deliveredAt = null;

    /**
     * @var array|null
     */
    #[ORM\Column(name: 'delivered_receipt', type: 'json', nullable: true)]
    protected ?array $deliveredReceipt = null;

    /**
     * @var \DateTime|null
     */
    #[ORM\Column(name: 'read_at', type: 'datetime', nullable: true)]
    protected ?\DateTime $readAt = null;

    /**
     * @var array|null
     */
    #[ORM\Column(name: 'read_receipt', type: 'json', nullable: true)]
    protected ?array $readReceipt = null;

    /**
     * @var \DateTime|null
     */
    protected ?\DateTime $dateAdded = null;

    /**
     * @var array|null
     */
    #[ORM\Column(name: 'metadata', type: 'json', nullable: true)]
    protected ?array $metadata = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLead(): ?Lead
    {
        return $this->lead;
    }

    public function setLead(?Lead $lead): self
    {
        $this->lead = $lead;
        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getMessageContent(): ?string
    {
        return $this->messageContent;
    }

    public function setMessageContent(?string $messageContent): self
    {
        $this->messageContent = $messageContent;
        return $this;
    }

    public function getTemplateName(): ?string
    {
        return $this->templateName;
    }

    public function setTemplateName(?string $templateName): self
    {
        $this->templateName = $templateName;
        return $this;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function setMessageId(?string $messageId): self
    {
        $this->messageId = $messageId;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getSentAt(): ?\DateTime
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTime $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getSentReceipt(): ?Array
    {
        return $this->sentReceipt;
    }

    public function setSentReceipt(?Array $sentReceipt): self
    {
        $this->sentReceipt = $sentReceipt;
        return $this;
    }

    public function getDeliveredAt(): ?\DateTime
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTime $deliveredAt): self
    {
        $this->deliveredAt = $deliveredAt;
        return $this;
    }

    public function getDeliveredReceipt(): ?Array
    {
        return $this->deliveredReceipt;
    }

    public function setDeliveredReceipt(?Array $deliveredReceipt): self
    {
        $this->deliveredReceipt = $deliveredReceipt;
        return $this;
    }

    public function getReadAt(): ?\DateTime
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTime $readAt): self
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getReadReceipt(): ?Array
    {
        return $this->readReceipt;
    }

    public function setReadReceipt(?Array $readReceipt): self
    {
        $this->readReceipt = $readReceipt;
        return $this;
    }

    /**
     * Get dateAdded
     */
    public function getDateAdded(): ?\DateTimeInterface
    {
        return $this->dateAdded;
    }

    /**
     * Set dateAdded
     */
    public function setDateAdded(?\DateTimeInterface $dateAdded): self
    {
        $this->dateAdded = $dateAdded;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Configura os metadados da entidade
     */
    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('evolution_messages')
            ->setCustomRepositoryClass('MauticPlugin\MauticEvolutionBundle\Entity\EvolutionMessageRepository');

        $builder->addId();

        $builder->createManyToOne('lead', 'Mautic\LeadBundle\Entity\Lead')
            ->addJoinColumn('lead_id', 'id', true, false, 'CASCADE')
            ->build();

        $builder->createField('phoneNumber', Types::STRING)
            ->columnName('phone_number')
            ->length(20)
            ->build();

        $builder->createField('messageContent', Types::TEXT)
            ->columnName('message_content')
            ->build();

        $builder->createField('templateName', Types::STRING)
            ->columnName('template_name')
            ->length(100)
            ->nullable()
            ->build();

        $builder->createField('messageId', Types::STRING)
            ->columnName('message_id')
            ->length(64)
            ->nullable()
            ->build();

        $builder->createField('status', Types::STRING)
            ->length(20)
            ->build();

        $builder->createField('errorMessage', Types::TEXT)
            ->columnName('error_message')
            ->nullable()
            ->build();

        $builder->createField('sentAt', Types::DATETIME_MUTABLE)
            ->columnName('sent_at')
            ->nullable()
            ->build();

        $builder->createField('sentReceipt', Types::JSON)
            ->columnName('sent_receipt')
            ->nullable()
            ->build();

        $builder->createField('deliveredAt', Types::DATETIME_MUTABLE)
            ->columnName('delivered_at')
            ->nullable()
            ->build();

        $builder->createField('deliveredReceipt', Types::JSON)
            ->columnName('delivered_receipt')
            ->nullable()
            ->build();

        $builder->createField('readAt', Types::DATETIME_MUTABLE)
            ->columnName('read_at')
            ->nullable()
            ->build();

        $builder->createField('readReceipt', Types::JSON)
            ->columnName('read_receipt')
            ->nullable()
            ->build();

        $builder->createField('metadata', Types::JSON)
            ->nullable()
            ->build();

        $builder->addDateAdded();
    }
}