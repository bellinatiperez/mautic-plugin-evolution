<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEvolutionBundle\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\PluginBundle\Event\PluginInstallEvent;
use Mautic\PluginBundle\PluginEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class PluginSubscriber
 * 
 * Event listener para instalação e configuração do plugin Evolution
 */
class PluginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Connection $db,
        private FieldModel $fieldModel,
        private LoggerInterface $logger
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::ON_PLUGIN_INSTALL => ['onInstall', 0],
        ];
    }

    /**
     * Processa a instalação do plugin Evolution
     */
    public function onInstall(PluginInstallEvent $event): void
    {
        if (!$event->checkContext('MauticEvolution')) {
            return;
        }

        $this->installSchema();
        $this->createEvolutionLeadFields();
    }

    /**
     * Instala o schema do banco de dados
     */
    private function installSchema(): void
    {
        $metadata = $this->getMetadata();
        
        foreach ($metadata as $meta) {
            $tableName = $meta->table['name'] ?? $meta->getTableName();
            
            if (!$this->db->getSchemaManager()->tablesExist([$tableName])) {
                $this->generateSchema($metadata);
                break;
            }
        }
    }

    /**
     * Obtém os metadados das entidades
     */
    private function getMetadata(): array
    {
        return $this->em->getMetadataFactory()->getAllMetadata();
    }

    /**
     * Gera o schema do banco de dados
     */
    private function generateSchema(array $metadata): void
    {
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($metadata);
    }
}