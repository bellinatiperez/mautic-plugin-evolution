<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEvolutionBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * Class EvolutionTemplateRepository
 * 
 * Repositório para gerenciar consultas da entidade EvolutionTemplate
 */
class EvolutionTemplateRepository extends CommonRepository
{
    /**
     * Busca templates ativos
     */
    public function findActiveTemplates(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca template por nome
     */
    public function findByName(string $name): ?EvolutionTemplate
    {
        return $this->createQueryBuilder('t')
            ->where('t.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Busca templates por tipo
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.type = :type')
            ->andWhere('t.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca templates para select options
     */
    public function getTemplateChoices(): array
    {
        $templates = $this->findActiveTemplates();
        $choices = [];

        foreach ($templates as $template) {
            $choices[$template->getName()] = $template->getId();
        }

        return $choices;
    }

    /**
     * Verifica se nome do template já existe
     */
    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.name = :name')
            ->setParameter('name', $name);

        if ($excludeId) {
            $qb->andWhere('t.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }


}