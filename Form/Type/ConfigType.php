<?php

namespace MauticPlugin\EvolutionAnalyticsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Configuration Form Type
 * 
 * Form for Evolution Analytics plugin configuration
 */
class ConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // API Configuration
        $builder->add('api_url', UrlType::class, [
            'label' => 'mautic.evolution.config.api_url',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'https://your-evolution-api.com',
                'tooltip' => 'mautic.evolution.config.api_url.tooltip'
            ],
            'constraints' => [
                new NotBlank([
                    'message' => 'mautic.evolution.config.api_url.required'
                ]),
                new Url([
                    'message' => 'mautic.evolution.config.api_url.invalid'
                ])
            ]
        ]);

        $builder->add('api_key', PasswordType::class, [
            'label' => 'mautic.evolution.config.api_key',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'Your API Key',
                'tooltip' => 'mautic.evolution.config.api_key.tooltip'
            ],
            'constraints' => [
                new NotBlank([
                    'message' => 'mautic.evolution.config.api_key.required'
                ])
            ]
        ]);

        $builder->add('instance_name', TextType::class, [
            'label' => 'mautic.evolution.config.instance_name',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'default_instance',
                'tooltip' => 'mautic.evolution.config.instance_name.tooltip'
            ],
            'required' => false
        ]);

        // Webhook Configuration
        $builder->add('enable_webhooks', CheckboxType::class, [
            'label' => 'mautic.evolution.config.enable_webhooks',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'tooltip' => 'mautic.evolution.config.enable_webhooks.tooltip'
            ],
            'required' => false
        ]);

        $builder->add('webhook_events', ChoiceType::class, [
            'label' => 'mautic.evolution.config.webhook_events',
            'label_attr' => ['class' => 'control-label'],
            'choices' => [
                'mautic.evolution.config.webhook_events.messages_upsert' => 'MESSAGES_UPSERT',
                'mautic.evolution.config.webhook_events.messages_update' => 'MESSAGES_UPDATE',
                'mautic.evolution.config.webhook_events.contacts_upsert' => 'CONTACTS_UPSERT',
                'mautic.evolution.config.webhook_events.contacts_update' => 'CONTACTS_UPDATE',
                'mautic.evolution.config.webhook_events.chats_upsert' => 'CHATS_UPSERT',
                'mautic.evolution.config.webhook_events.connection_update' => 'CONNECTION_UPDATE',
                'mautic.evolution.config.webhook_events.presence_update' => 'PRESENCE_UPDATE'
            ],
            'multiple' => true,
            'expanded' => true,
            'attr' => [
                'tooltip' => 'mautic.evolution.config.webhook_events.tooltip'
            ],
            'required' => false
        ]);

        $builder->add('webhook_secret', PasswordType::class, [
            'label' => 'mautic.evolution.config.webhook_secret',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'Optional webhook secret',
                'tooltip' => 'mautic.evolution.config.webhook_secret.tooltip'
            ],
            'required' => false
        ]);

        // Sync Configuration
        $builder->add('enable_contact_sync', CheckboxType::class, [
            'label' => 'mautic.evolution.config.enable_contact_sync',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'tooltip' => 'mautic.evolution.config.enable_contact_sync.tooltip'
            ],
            'required' => false
        ]);

        $builder->add('sync_interval', NumberType::class, [
            'label' => 'mautic.evolution.config.sync_interval',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'class' => 'form-control',
                'min' => 1,
                'max' => 1440,
                'tooltip' => 'mautic.evolution.config.sync_interval.tooltip'
            ],
            'constraints' => [
                new Range([
                    'min' => 1,
                    'max' => 1440,
                    'notInRangeMessage' => 'mautic.evolution.config.sync_interval.range'
                ])
            ],
            'required' => false
        ]);

        $builder->add('contact_field_mapping', TextareaType::class, [
            'label' => 'mautic.evolution.config.contact_field_mapping',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'class' => 'form-control',
                'rows' => 5,
                'placeholder' => '{"whatsapp_name": "firstname", "whatsapp_phone": "mobile"}',
                'tooltip' => 'mautic.evolution.config.contact_field_mapping.tooltip'
            ],
            'required' => false
        ]);

        // Analytics Configuration
        $builder->add('enable_analytics', CheckboxType::class, [
            'label' => 'mautic.evolution.config.enable_analytics',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'tooltip' => 'mautic.evolution.config.enable_analytics.tooltip'
            ],
            'required' => false
        ]);

        $builder->add('analytics_retention_days', NumberType::class, [
            'label' => 'mautic.evolution.config.analytics_retention_days',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'class' => 'form-control',
                'min' => 1,
                'max' => 365,
                'tooltip' => 'mautic.evolution.config.analytics_retention_days.tooltip'
            ],
            'constraints' => [
                new Range([
                    'min' => 1,
                    'max' => 365,
                    'notInRangeMessage' => 'mautic.evolution.config.analytics_retention_days.range'
                ])
            ],
            'required' => false
        ]);

        $builder->add('enable_real_time_updates', CheckboxType::class, [
            'label' => 'mautic.evolution.config.enable_real_time_updates',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'tooltip' => 'mautic.evolution.config.enable_real_time_updates.tooltip'
            ],
            'required' => false
        ]);

        // Message Configuration
        $builder->add('enable_message_logging', CheckboxType::class, [
            'label' => 'mautic.evolution.config.enable_message_logging',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'tooltip' => 'mautic.evolution.config.enable_message_logging.tooltip'
            ],
            'required' => false
        ]);

        $builder->add('log_message_content', CheckboxType::class, [
            'label' => 'mautic.evolution.config.log_message_content',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'tooltip' => 'mautic.evolution.config.log_message_content.tooltip'
            ],
            'required' => false
        ]);

        $builder->add('message_content_max_length', NumberType::class, [
            'label' => 'mautic.evolution.config.message_content_max_length',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'class' => 'form-control',
                'min' => 50,
                'max' => 5000,
                'tooltip' => 'mautic.evolution.config.message_content_max_length.tooltip'
            ],
            'constraints' => [
                new Range([
                    'min' => 50,
                    'max' => 5000,
                    'notInRangeMessage' => 'mautic.evolution.config.message_content_max_length.range'
                ])
            ],
            'required' => false
        ]);

        // Advanced Configuration
        $builder->add('request_timeout', NumberType::class, [
            'label' => 'mautic.evolution.config.request_timeout',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'class' => 'form-control',
                'min' => 5,
                'max' => 300,
                'tooltip' => 'mautic.evolution.config.request_timeout.tooltip'
            ],
            'constraints' => [
                new Range([
                    'min' => 5,
                    'max' => 300,
                    'notInRangeMessage' => 'mautic.evolution.config.request_timeout.range'
                ])
            ],
            'required' => false
        ]);

        $builder->add('max_retries', NumberType::class, [
            'label' => 'mautic.evolution.config.max_retries',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'class' => 'form-control',
                'min' => 0,
                'max' => 10,
                'tooltip' => 'mautic.evolution.config.max_retries.tooltip'
            ],
            'constraints' => [
                new Range([
                    'min' => 0,
                    'max' => 10,
                    'notInRangeMessage' => 'mautic.evolution.config.max_retries.range'
                ])
            ],
            'required' => false
        ]);

        $builder->add('enable_debug_logging', CheckboxType::class, [
            'label' => 'mautic.evolution.config.enable_debug_logging',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'tooltip' => 'mautic.evolution.config.enable_debug_logging.tooltip'
            ],
            'required' => false
        ]);

        // Queue Configuration
        $builder->add('enable_queue', CheckboxType::class, [
            'label' => 'mautic.evolution.config.enable_queue',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'tooltip' => 'mautic.evolution.config.enable_queue.tooltip'
            ],
            'required' => false
        ]);

        $builder->add('queue_batch_size', NumberType::class, [
            'label' => 'mautic.evolution.config.queue_batch_size',
            'label_attr' => ['class' => 'control-label'],
            'attr' => [
                'class' => 'form-control',
                'min' => 1,
                'max' => 1000,
                'tooltip' => 'mautic.evolution.config.queue_batch_size.tooltip'
            ],
            'constraints' => [
                new Range([
                    'min' => 1,
                    'max' => 1000,
                    'notInRangeMessage' => 'mautic.evolution.config.queue_batch_size.range'
                ])
            ],
            'required' => false
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'messages'
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'evolution_config';
    }
}