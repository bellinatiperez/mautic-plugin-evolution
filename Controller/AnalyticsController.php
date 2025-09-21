<?php

namespace MauticPlugin\EvolutionWhatsAppBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractStandardFormController;
use MauticPlugin\EvolutionWhatsAppBundle\Model\AnalyticsModel;
use MauticPlugin\EvolutionWhatsAppBundle\Service\EvolutionApiService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Analytics Controller
 * 
 * Handles analytics dashboard and data visualization
 */
class AnalyticsController extends AbstractStandardFormController
{
    private AnalyticsModel $analyticsModel;
    private EvolutionApiService $evolutionApiService;

    public function __construct(
        AnalyticsModel $analyticsModel,
        EvolutionApiService $evolutionApiService
    ) {
        $this->analyticsModel = $analyticsModel;
        $this->evolutionApiService = $evolutionApiService;
    }

    /**
     * Main analytics dashboard
     */
    public function indexAction(Request $request): Response
    {
        // Check permissions
        if (!$this->get('mautic.security')->isGranted('plugin:evolution_analytics:view')) {
            return $this->accessDenied();
        }

        // Get filter parameters
        $filters = $this->getFiltersFromRequest($request);

        // Get analytics data
        $data = [
            'stats' => $this->analyticsModel->getMessageStats($filters),
            'daily_stats' => $this->analyticsModel->getMessageStatsByDate($filters),
            'message_types' => $this->analyticsModel->getMessageStatsByType($filters),
            'top_contacts' => $this->analyticsModel->getTopActiveContacts($filters, 10),
            'instance_stats' => $this->analyticsModel->getInstanceStats($filters),
            'hourly_distribution' => $this->analyticsModel->getHourlyMessageDistribution($filters),
            'response_time' => $this->analyticsModel->getResponseTimeAnalytics($filters)
        ];

        return $this->delegateView([
            'viewParameters' => [
                'data' => $data,
                'filters' => $filters,
                'dateRange' => $this->getDateRange($filters)
            ],
            'contentTemplate' => 'EvolutionAnalyticsBundle:Analytics:index.html.php',
            'passthroughVars' => [
                'activeLink' => '#mautic_evolution_analytics_index',
                'mauticContent' => 'evolutionAnalytics'
            ]
        ]);
    }

    /**
     * Get model name for AbstractStandardFormController
     */
    protected function getModelName(): string
    {
        return 'evolution.analytics';
    }

    /**
     * Get analytics data via AJAX
     */
    public function dataAction(Request $request): JsonResponse
    {
        // Check permissions
        if (!$this->get('mautic.security')->isGranted('plugin:evolution_analytics:view')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $type = $request->get('type', 'stats');
        $filters = $this->getFiltersFromRequest($request);

        try {
            $data = [];

            switch ($type) {
                case 'stats':
                    $data = $this->analyticsModel->getMessageStats($filters);
                    break;

                case 'daily':
                    $data = $this->analyticsModel->getMessageStatsByDate($filters);
                    break;

                case 'types':
                    $data = $this->analyticsModel->getMessageStatsByType($filters);
                    break;

                case 'contacts':
                    $limit = (int) $request->get('limit', 10);
                    $data = $this->analyticsModel->getTopActiveContacts($filters, $limit);
                    break;

                case 'instances':
                    $data = $this->analyticsModel->getInstanceStats($filters);
                    break;

                case 'hourly':
                    $data = $this->analyticsModel->getHourlyMessageDistribution($filters);
                    break;

                case 'response_time':
                    $data = $this->analyticsModel->getResponseTimeAnalytics($filters);
                    break;

                case 'connections':
                    $instanceName = $request->get('instance');
                    $limit = (int) $request->get('limit', 50);
                    $data = $this->analyticsModel->getConnectionHistory($instanceName, $limit);
                    break;

                default:
                    return new JsonResponse(['error' => 'Invalid data type'], 400);
            }

            return new JsonResponse([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export analytics data
     */
    public function exportAction(Request $request): Response
    {
        // Check permissions
        if (!$this->get('mautic.security')->isGranted('plugin:evolution_analytics:view')) {
            return $this->accessDenied();
        }

        $format = $request->get('format', 'csv');
        $type = $request->get('type', 'messages');
        $filters = $this->getFiltersFromRequest($request);

        try {
            $data = [];
            $filename = 'evolution_analytics_' . $type . '_' . date('Y-m-d');

            switch ($type) {
                case 'messages':
                    $data = $this->getMessagesForExport($filters);
                    break;

                case 'contacts':
                    $data = $this->analyticsModel->getTopActiveContacts($filters, 1000);
                    break;

                case 'instances':
                    $data = $this->analyticsModel->getInstanceStats($filters);
                    break;

                default:
                    throw new \InvalidArgumentException('Invalid export type');
            }

            if ($format === 'csv') {
                return $this->exportToCsv($data, $filename);
            } elseif ($format === 'json') {
                return $this->exportToJson($data, $filename);
            } else {
                throw new \InvalidArgumentException('Invalid export format');
            }

        } catch (\Exception $e) {
            $this->addFlash('mautic.core.error.generic', $e->getMessage());
            return $this->redirectToRoute('mautic_evolution_analytics_index');
        }
    }

    /**
     * Real-time dashboard data
     */
    public function realtimeAction(Request $request): JsonResponse
    {
        // Check permissions
        if (!$this->get('mautic.security')->isGranted('plugin:evolution_analytics:view')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        try {
            // Get real-time data (last 5 minutes)
            $filters = [
                'from_date' => date('Y-m-d H:i:s', strtotime('-5 minutes'))
            ];

            $data = [
                'recent_messages' => $this->analyticsModel->getMessageStats($filters),
                'active_instances' => $this->getActiveInstances(),
                'connection_status' => $this->getConnectionStatus(),
                'timestamp' => time()
            ];

            return new JsonResponse([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Evolution API connection
     */
    public function testConnectionAction(Request $request): JsonResponse
    {
        // Check permissions
        if (!$this->get('mautic.security')->isGranted('plugin:evolution_analytics:manage')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $instanceName = $request->get('instance');
        
        if (!$instanceName) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Instance name is required'
            ], 400);
        }

        try {
            $result = $this->evolutionApiService->testConnection($instanceName);
            
            return new JsonResponse([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filters from request
     */
    private function getFiltersFromRequest(Request $request): array
    {
        $filters = [];

        if ($request->get('instance_name')) {
            $filters['instance_name'] = $request->get('instance_name');
        }

        if ($request->get('contact_id')) {
            $filters['contact_id'] = (int) $request->get('contact_id');
        }

        if ($request->get('phone')) {
            $filters['phone'] = $request->get('phone');
        }

        if ($request->get('from_date')) {
            $filters['from_date'] = $request->get('from_date');
        }

        if ($request->get('to_date')) {
            $filters['to_date'] = $request->get('to_date');
        }

        if ($request->get('message_type')) {
            $filters['message_type'] = $request->get('message_type');
        }

        if ($request->get('from_me') !== null) {
            $filters['from_me'] = (bool) $request->get('from_me');
        }

        // Default date range if not specified
        if (empty($filters['from_date']) && empty($filters['to_date'])) {
            $filters['from_date'] = date('Y-m-d', strtotime('-30 days'));
            $filters['to_date'] = date('Y-m-d');
        }

        return $filters;
    }

    /**
     * Get date range for display
     */
    private function getDateRange(array $filters): array
    {
        return [
            'from' => $filters['from_date'] ?? date('Y-m-d', strtotime('-30 days')),
            'to' => $filters['to_date'] ?? date('Y-m-d')
        ];
    }

    /**
     * Get messages for export
     */
    private function getMessagesForExport(array $filters): array
    {
        $qb = $this->getDoctrine()->getConnection()->createQueryBuilder()
            ->select([
                'em.message_id',
                'em.instance_name',
                'em.phone',
                'l.firstname',
                'l.lastname',
                'em.from_me',
                'em.message_type',
                'em.content',
                'FROM_UNIXTIME(em.timestamp) as message_time',
                'em.created_at'
            ])
            ->from('evolution_messages', 'em')
            ->leftJoin('em', 'leads', 'l', 'l.id = em.contact_id')
            ->orderBy('em.timestamp', 'DESC')
            ->setMaxResults(10000);

        // Apply filters
        if (!empty($filters['instance_name'])) {
            $qb->andWhere('em.instance_name = :instance_name')
               ->setParameter('instance_name', $filters['instance_name']);
        }

        if (!empty($filters['from_date'])) {
            $qb->andWhere('em.timestamp >= :from_date')
               ->setParameter('from_date', strtotime($filters['from_date']));
        }

        if (!empty($filters['to_date'])) {
            $qb->andWhere('em.timestamp <= :to_date')
               ->setParameter('to_date', strtotime($filters['to_date'] . ' 23:59:59'));
        }

        return $qb->execute()->fetchAll();
    }

    /**
     * Export data to CSV
     */
    private function exportToCsv(array $data, string $filename): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.csv"');

        $output = fopen('php://temp', 'w');

        if (!empty($data)) {
            // Write headers
            fputcsv($output, array_keys($data[0]));

            // Write data
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }

        rewind($output);
        $response->setContent(stream_get_contents($output));
        fclose($output);

        return $response;
    }

    /**
     * Export data to JSON
     */
    private function exportToJson(array $data, string $filename): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.json"');
        $response->setContent(json_encode($data, JSON_PRETTY_PRINT));

        return $response;
    }

    /**
     * Get active instances
     */
    private function getActiveInstances(): array
    {
        try {
            return $this->evolutionApiService->getActiveInstances();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get connection status for all instances
     */
    private function getConnectionStatus(): array
    {
        $instances = $this->getActiveInstances();
        $status = [];

        foreach ($instances as $instance) {
            try {
                $instanceStatus = $this->evolutionApiService->getInstanceState($instance['name']);
                $status[$instance['name']] = $instanceStatus;
            } catch (\Exception $e) {
                $status[$instance['name']] = ['state' => 'error', 'error' => $e->getMessage()];
            }
        }

        return $status;
    }
}