<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEvolutionBundle\EventListener;

use Mautic\LeadBundle\Event\LeadTimelineEvent;
use Mautic\LeadBundle\LeadEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Mautic\LeadBundle\Entity\LeadEventLogRepository;
use Symfony\Contracts\Translation\TranslatorInterface;
use Mautic\LeadBundle\EventListener\TimelineEventLogTrait;

class LeadTimelineSubscriber implements EventSubscriberInterface
{
    use TimelineEventLogTrait;

    public function __construct(
        LeadEventLogRepository $eventLogRepository,
        TranslatorInterface $translator,
    ) {
        $this->eventLogRepository = $eventLogRepository;
        $this->translator = $translator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::TIMELINE_ON_GENERATE => ['onTimelineGenerate', 0],
        ];
    }

    public function onTimelineGenerate(LeadTimelineEvent $event): void
    {
        // Evento de envio bem-sucedido
        $this->addEvents(
            $event,
            'evolution.sent',
            'mautic.lead.timeline.evolution.sent',
            'ri-message-2-line',
            'EvolutionBundle',
            'evolution_api',
            'evolution_api_success',
            '@MauticEvolution/SubscribedEvents/Timeline/evolution_message.html.twig'
        );

        // Evento de falha (registrado pelo serviço)
        $this->addEvents(
            $event,
            'evolution.failed',
            'mautic.evolution.timeline.failed',
            'ri-error-warning-line',
            'EvolutionBundle',
            'evolution_api',
            'evolution_api_failure',
            '@MauticEvolution/SubscribedEvents/Timeline/evolution_message.html.twig'
        );
    }
}