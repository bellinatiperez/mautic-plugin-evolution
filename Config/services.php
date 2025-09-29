<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    // Evolution API Service
    $services->set('mautic.evolution.service.evolution_api', \MauticPlugin\MauticEvolutionBundle\Service\EvolutionApiService::class)
        ->class(\MauticPlugin\MauticEvolutionBundle\Service\EvolutionApiService::class)
        ->args([
            service('mautic.helper.integration'),
            service('mautic.http.client'),
            service('monolog.logger.mautic'),
            service('mautic.helper.user'),
            service('doctrine.orm.entity_manager')
        ]);

    // Webhook Service
    $services->set('mautic.evolution.service.webhook', \MauticPlugin\MauticEvolutionBundle\Service\WebhookService::class)
        ->class(\MauticPlugin\MauticEvolutionBundle\Service\WebhookService::class)
        ->args([
            service('mautic.lead.model.lead'),
            service('mautic.lead.model.note'),
            service('event_dispatcher'),
            service('monolog.logger.mautic')
        ]);

    // Message Model
    $services->set('mautic.evolution.model.message', \MauticPlugin\MauticEvolutionBundle\Model\MessageModel::class)
        ->class(\MauticPlugin\MauticEvolutionBundle\Model\MessageModel::class)
        ->args([
            service('mautic.lead.model.lead'),
            service('mautic.evolution.service.evolution_api'),
            service('doctrine.orm.entity_manager'),
            service('mautic.security'),
            service('event_dispatcher'),
            service('router'),
            service('translator'),
            service('mautic.helper.user'),
            service('monolog.logger.mautic'),
            service('mautic.helper.core_parameters')
        ]);

    // Template Model
    $services->set('mautic.evolution.model.template', \MauticPlugin\MauticEvolutionBundle\Model\TemplateModel::class)
        ->class(\MauticPlugin\MauticEvolutionBundle\Model\TemplateModel::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('mautic.security'),
            service('event_dispatcher'),
            service('router'),
            service('translator'),
            service('mautic.helper.user'),
            service('monolog.logger.mautic'),
            service('mautic.helper.core_parameters')
        ]);

    // Controllers
    $services->set(\MauticPlugin\MauticEvolutionBundle\Controller\WebhookController::class)
        ->class(\MauticPlugin\MauticEvolutionBundle\Controller\WebhookController::class)
        ->args([
            service('mautic.evolution.service.webhook'),
            service('monolog.logger.mautic'),
            service('doctrine'),
            service('mautic.model.factory'),
            service('mautic.helper.user'),
            service('mautic.helper.core_parameters'),
            service('event_dispatcher'),
            service('translator'),
            service('mautic.core.service.flashbag'),
            service('request_stack'),
            service('mautic.security'),
        ]);

    $services->set(\MauticPlugin\MauticEvolutionBundle\Controller\TemplateController::class)
        ->class(\MauticPlugin\MauticEvolutionBundle\Controller\TemplateController::class)
        ->args([
            service('form.factory'),
            service('mautic.helper.form.field_helper'),
            service('doctrine'),
            service('mautic.model.factory'),
            service('mautic.helper.user'),
            service('mautic.helper.core_parameters'),
            service('event_dispatcher'),
            service('translator'),
            service('mautic.core.service.flashbag'),
            service('request_stack'),
            service('mautic.security')
        ]);

    // Event Subscribers
    $services->set(\MauticPlugin\MauticEvolutionBundle\EventListener\CampaignSubscriber::class)
        ->class(\MauticPlugin\MauticEvolutionBundle\EventListener\CampaignSubscriber::class)
        ->args([
            service('mautic.evolution.model.message'),
            service('mautic.evolution.model.template'),
            service('mautic.evolution.service.evolution_api')
        ])
        ->tag('kernel.event_subscriber');

    $services->set(\MauticPlugin\MauticEvolutionBundle\EventListener\LeadSubscriber::class)
        ->class(\MauticPlugin\MauticEvolutionBundle\EventListener\LeadSubscriber::class)
        ->args([
            service('mautic.evolution.service.evolution_api'),
            service('monolog.logger.mautic')
        ])
        ->tag('kernel.event_subscriber');


    // Repository Services - Configuração específica para repositórios Doctrine
    $services->set(\MauticPlugin\MauticEvolutionBundle\Entity\EvolutionTemplateRepository::class)
        ->class(\MauticPlugin\MauticEvolutionBundle\Entity\EvolutionTemplateRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set(\MauticPlugin\MauticEvolutionBundle\Entity\EvolutionMessageRepository::class)
        ->class(\MauticPlugin\MauticEvolutionBundle\Entity\EvolutionMessageRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    // Form Types
    $services->set(\MauticPlugin\MauticEvolutionBundle\Form\Type\SendTemplateActionType::class)
        ->class(\MauticPlugin\MauticEvolutionBundle\Form\Type\SendTemplateActionType::class)
        ->args([
            service('mautic.evolution.model.template')
        ]);

    // Repository Aliases
    $services->alias('mautic.evolution.repository.template', \MauticPlugin\MauticEvolutionBundle\Entity\EvolutionTemplateRepository::class);
    $services->alias('mautic.evolution.repository.message', \MauticPlugin\MauticEvolutionBundle\Entity\EvolutionMessageRepository::class);
};