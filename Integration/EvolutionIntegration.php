<?php

namespace MauticPlugin\EvolutionWhatsAppBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * Evolution API Integration
 */
class EvolutionIntegration extends AbstractIntegration
{
    public const INTEGRATION_NAME = 'Evolution';

    /**
     * @return string
     */
    public function getName(): string
    {
        return self::INTEGRATION_NAME;
    }

    /**
     * @return string
     */
    public function getDisplayName(): string
    {
        return 'Evolution API';
    }

    /**
     * @return string
     */
    public function getAuthenticationType(): string
    {
        return 'api_key';
    }

    /**
     * @return array
     */
    public function getRequiredKeyFields(): array
    {
        return [
            'api_url' => 'mautic.evolution.config.api_url',
            'api_key' => 'mautic.evolution.config.api_key',
            'instance_name' => 'mautic.evolution.config.instance_name',
        ];
    }

    /**
     * @return array
     */
    public function getFormSettings(): array
    {
        return [
            'requires_callback'      => false,
            'requires_authorization' => false,
        ];
    }

    /**
     * @return array
     */
    public function getSupportedFeatures(): array
    {
        return [
            'push_lead',
            'get_leads',
            'push_leads_to_integration',
            'get_leads_from_integration',
        ];
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array                                        $data
     * @param string                                       $formArea
     */
    public function appendToForm(&$builder, $data, $formArea): void
    {
        if ($formArea === 'keys') {
            $builder->add(
                'api_url',
                UrlType::class,
                [
                    'label'      => 'mautic.evolution.config.api_url',
                    'label_attr' => ['class' => 'control-label'],
                    'required'   => true,
                    'attr'       => [
                        'class'       => 'form-control',
                        'placeholder' => 'https://api.evolution.com',
                    ],
                ]
            );

            $builder->add(
                'api_key',
                TextType::class,
                [
                    'label'      => 'mautic.evolution.config.api_key',
                    'label_attr' => ['class' => 'control-label'],
                    'required'   => true,
                    'attr'       => [
                        'class' => 'form-control',
                    ],
                ]
            );

            $builder->add(
                'instance_name',
                TextType::class,
                [
                    'label'      => 'mautic.evolution.config.instance_name',
                    'label_attr' => ['class' => 'control-label'],
                    'required'   => true,
                    'attr'       => [
                        'class'       => 'form-control',
                        'placeholder' => 'my-whatsapp-instance',
                    ],
                ]
            );
        }

        if ($formArea === 'features') {
            $builder->add(
                'sync_enabled',
                ChoiceType::class,
                [
                    'choices' => [
                        'mautic.core.form.yes' => true,
                        'mautic.core.form.no'  => false,
                    ],
                    'label'      => 'mautic.evolution.config.sync_enabled',
                    'label_attr' => ['class' => 'control-label'],
                    'required'   => false,
                    'attr'       => [
                        'class' => 'form-control',
                    ],
                ]
            );

            $builder->add(
                'analytics_enabled',
                ChoiceType::class,
                [
                    'choices' => [
                        'mautic.core.form.yes' => true,
                        'mautic.core.form.no'  => false,
                    ],
                    'label'      => 'mautic.evolution.config.analytics_enabled',
                    'label_attr' => ['class' => 'control-label'],
                    'required'   => false,
                    'attr'       => [
                        'class' => 'form-control',
                    ],
                ]
            );
        }
    }

    /**
     * Test the API connection
     *
     * @param array $settings
     * @return bool
     */
    public function testConnection($settings = []): bool
    {
        $apiUrl = $settings['api_url'] ?? $this->getApiKey('api_url');
        $apiKey = $settings['api_key'] ?? $this->getApiKey('api_key');
        $instanceName = $settings['instance_name'] ?? $this->getApiKey('instance_name');

        if (empty($apiUrl) || empty($apiKey) || empty($instanceName)) {
            return false;
        }

        try {
            $url = rtrim($apiUrl, '/') . '/instance/connectionState/' . $instanceName;
            
            $response = $this->makeRequest($url, [], 'GET', [
                'headers' => [
                    'apikey' => $apiKey,
                    'Content-Type' => 'application/json',
                ],
            ]);

            return isset($response['instance']['state']) && $response['instance']['state'] === 'open';
        } catch (\Exception $e) {
            $this->getLogger()->error('Evolution API connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get available form fields for mapping
     *
     * @param array $settings
     * @return array
     */
    public function getFormFieldsForMapping($settings = []): array
    {
        return [
            'phone' => [
                'label' => 'mautic.evolution.field.phone',
                'required' => true,
            ],
            'name' => [
                'label' => 'mautic.evolution.field.name',
                'required' => false,
            ],
            'email' => [
                'label' => 'mautic.evolution.field.email',
                'required' => false,
            ],
        ];
    }
}