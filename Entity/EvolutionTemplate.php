<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEvolutionBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class EvolutionTemplate
 * 
 * Entidade para armazenar templates de mensagens WhatsApp
 */
class EvolutionTemplate extends FormEntity
{
    /**
     * @var int|null
     */
    protected ?int $id = null;

    /**
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * @var string|null
     */
    protected ?string $description = null;

    /**
     * @var string|null
     */
    protected ?string $content = null;

    /**
     * @var string
     */
    protected string $type = 'text';

    /**
     * @var array|null
     */
    protected ?array $variables = null;

    /**
     * @var bool
     */
    protected bool $isActive = true;

    /**
     * @var array|null
     */
    protected ?array $metadata = null;

    /**
     * @var \Mautic\CategoryBundle\Entity\Category|null
     */
    protected $category = null;

    /**
     * Método para configurar metadados do Doctrine
     */
    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('evolution_templates')
            ->setCustomRepositoryClass('MauticPlugin\MauticEvolutionBundle\Entity\EvolutionTemplateRepository');

        $builder->addId();

        $builder->createField('name', Types::STRING)
            ->length(100)
            ->unique()
            ->build();

        $builder->createField('description', Types::TEXT)
            ->nullable()
            ->build();

        $builder->createField('content', Types::TEXT)
            ->build();

        $builder->createField('type', Types::STRING)
            ->length(20)
            ->build();

        $builder->createField('variables', Types::JSON)
            ->nullable()
            ->build();

        $builder->createField('isActive', Types::BOOLEAN)
            ->columnName('is_active')
            ->build();

        $builder->createField('metadata', Types::JSON)
            ->nullable()
            ->build();

        $builder->addCategory();
    }

    /**
     * Método para configurar validações
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('name', new Assert\NotBlank([
            'message' => 'mautic.evolution.template.name.notblank',
        ]));

        $metadata->addPropertyConstraint('name', new Assert\Length([
            'max' => 100,
            'maxMessage' => 'mautic.evolution.template.name.maxlength',
        ]));

        $metadata->addPropertyConstraint('content', new Assert\NotBlank([
            'message' => 'mautic.evolution.template.content.notblank',
        ]));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getVariables(): ?array
    {
        return $this->variables;
    }

    public function setVariables(?array $variables): self
    {
        $this->variables = $variables;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
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

    public function getDateAdded(): ?\DateTimeInterface
    {
        return parent::getDateAdded();
    }

    public function getDateModified(): ?\DateTimeInterface
    {
        return parent::getDateModified();
    }

    /**
     * Método para compatibilidade com Mautic
     */
    public function __toString(): string
    {
        return $this->name ?? '';
    }

    /**
     * @return \Mautic\CategoryBundle\Entity\Category|null
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param \Mautic\CategoryBundle\Entity\Category|null $category
     */
    public function setCategory($category): self
    {
        $this->category = $category;
        return $this;
    }
}