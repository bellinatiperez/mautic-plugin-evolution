<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEvolutionBundle\EventListener;

use MauticPlugin\MauticEvolutionBundle\Model\MessageModel;
use MauticPlugin\MauticEvolutionBundle\Model\TemplateModel;
use MauticPlugin\MauticEvolutionBundle\Service\EvolutionApiService;
use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class CampaignSubscriber
 * 
 * Event listener para integração com campanhas do Mautic
 */
class CampaignSubscriber implements EventSubscriberInterface
{
    private MessageModel $messageModel;
    private TemplateModel $templateModel;
    private EvolutionApiService $evolutionApiService;

    public function __construct(
        MessageModel $messageModel,
        TemplateModel $templateModel,
        EvolutionApiService $evolutionApiService
    ) {
        $this->messageModel = $messageModel;
        $this->templateModel = $templateModel;
        $this->evolutionApiService = $evolutionApiService;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
            'mautic.evolution.send_message' => ['onSendMessage', 0],
            'mautic.evolution.send_template' => ['onSendTemplate', 0],
        ];
    }

    /**
     * Adiciona actions do Evolution API ao campaign builder
     */
    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        // Action para enviar mensagem simples
        $event->addAction(
            'evolution.send_message',
            [
                'label' => 'mautic.evolution.campaign.action.send_message',
                'description' => 'mautic.evolution.campaign.action.send_message.tooltip',
                'eventName' => 'mautic.evolution.send_message',
                'formType' => 'MauticPlugin\MauticEvolutionBundle\Form\Type\SendMessageActionType',
                'formTheme' => '@MauticEvolution/FormTheme/SendMessageAction/_sendmessageaction_widget.html.twig',
                'channel' => 'whatsapp',
                'channelIdField' => 'phone',
            ]
        );

        // Action para enviar template
        $event->addAction(
            'evolution.send_template',
            [
                'label' => 'mautic.evolution.campaign.action.send_template',
                'description' => 'mautic.evolution.campaign.action.send_template.tooltip',
                'eventName' => 'mautic.evolution.send_template',
                'formType' => 'MauticPlugin\MauticEvolutionBundle\Form\Type\SendTemplateActionType',
                'formTheme' => '@MauticEvolution/FormTheme/SendTemplateAction/_sendtemplateaction_widget.html.twig',
                'channel' => 'whatsapp',
                'channelIdField' => 'phone',
            ]
        );
    }

    /**
     * Executa action de envio de mensagem simples
     */
    public function onSendMessage(CampaignExecutionEvent $event): void
    {
        $config = $event->getConfig();
        $lead = $event->getLead();

        try {
            // Obtém configurações da action
            $message = $config['message'] ?? '';
            $phoneField = $config['phone_field'] ?? 'mobile';
            $groupAlias = $config['group_alias'] ?? null;

            if (empty($message)) {
                $event->setResult(false);
                $event->setFailed('Mensagem não configurada');
                return;
            }
            if (empty($groupAlias)) {
                $event->setResult(false);
                $event->setFailed('Seleção de grupo não configurada');
                return;
            }

            // Envia mensagem com suporte a group alias e phone field
            $result = $this->messageModel->sendMessage($lead, $message, null, $groupAlias, $phoneField);

            if ($result) {
                $event->setResult(true);
                $event->setChannel('whatsapp', $lead->getId());
            } else {
                $event->setResult(false);
                $event->setFailed('Erro ao enviar mensagem');
            }

        } catch (\Exception $e) {
            $event->setResult(false);
            $event->setFailed('Erro ao enviar mensagem: ' . $e->getMessage());
        }
    }

    /**
     * Executa action de envio de template
     */
    public function onSendTemplate(CampaignExecutionEvent $event): void
    {
        $config = $event->getConfig();
        $lead = $event->getLead();

        try {
            // Obtém configurações da action
            $templateId = $config['template'] ?? null;
            $phoneField = $config['phone_field'] ?? 'mobile';
            $groupAlias = $config['group_alias'] ?? null;

            if (empty($templateId)) {
                $event->setResult(false);
                $event->setFailed('Template não selecionado');
                return;
            }

            if (empty($groupAlias)) {
                $event->setResult(false);
                $event->setFailed('Seleção de grupo não configurada');
                return;
            }

            // Buscar template
            $template = $this->templateModel->getEntity($templateId);
            
            if (!$template) {
                $event->setResult(false);
                $event->setFailed('Template não encontrado');
                return;
            }

            // Obter conteúdo do template
            $templateContent = $template->getContent();

            // Enviar mensagem usando o template, com suporte a group alias e phone field
            $result = $this->messageModel->sendMessage($lead, $templateContent, $template->getName(), $groupAlias, $phoneField);

            if ($result) {
                $event->setResult(true);
                $event->setChannel('whatsapp', $lead->getId());
            } else {
                $event->setResult(false);
                $event->setFailed('Erro ao enviar template');
            }

        } catch (\Exception $e) {
            $event->setResult(false);
            $event->setFailed('Erro ao enviar template: ' . $e->getMessage());
        }
    }
}