<?php

namespace MauticPlugin\EvolutionWhatsAppBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractStandardFormController;
use MauticPlugin\EvolutionWhatsAppBundle\Service\WebhookService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

/**
 * Webhook Controller
 * 
 * Handles incoming webhooks from Evolution API
 */
class WebhookController extends AbstractStandardFormController
{
    private WebhookService $webhookService;
    private LoggerInterface $logger;

    public function __construct(
        WebhookService $webhookService,
        LoggerInterface $logger
    ) {
        $this->webhookService = $webhookService;
        $this->logger = $logger;
    }

    /**
     * Get model name for AbstractStandardFormController
     */
    protected function getModelName(): string
    {
        return 'evolution.webhook';
    }

    /**
     * Handle incoming webhook from Evolution API
     */
    public function receiveAction(Request $request): JsonResponse
    {
        try {
            // Log incoming webhook
            $this->logger->info('Received Evolution webhook', [
                'method' => $request->getMethod(),
                'content_type' => $request->headers->get('Content-Type'),
                'user_agent' => $request->headers->get('User-Agent'),
                'ip' => $request->getClientIp()
            ]);

            // Validate request method
            if (!in_array($request->getMethod(), ['POST', 'PUT'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Method not allowed'
                ], 405);
            }

            // Get request data
            $contentType = $request->headers->get('Content-Type');
            
            if (strpos($contentType, 'application/json') !== false) {
                $data = json_decode($request->getContent(), true);
            } else {
                $data = $request->request->all();
            }

            if (empty($data)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Empty request data'
                ], 400);
            }

            // Validate webhook signature if configured
            if (!$this->validateWebhookSignature($request)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Invalid webhook signature'
                ], 401);
            }

            // Process webhook data
            $result = $this->webhookService->processWebhook($data);

            // Log successful processing
            $this->logger->info('Webhook processed successfully', [
                'result' => $result,
                'data_size' => strlen(json_encode($data))
            ]);

            return new JsonResponse([
                'success' => true,
                'result' => $result,
                'timestamp' => time()
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Invalid webhook data', [
                'error' => $e->getMessage(),
                'data' => $data ?? null
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            $this->logger->error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data ?? null
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Webhook status endpoint
     */
    public function statusAction(Request $request): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'status' => 'active',
            'timestamp' => time(),
            'version' => '1.0.0'
        ]);
    }

    /**
     * Test webhook endpoint
     */
    public function testAction(Request $request): JsonResponse
    {
        try {
            // Create test webhook data
            $testData = [
                'event' => 'TEST_EVENT',
                'instance' => 'test_instance',
                'data' => [
                    'test' => true,
                    'timestamp' => time(),
                    'message' => 'This is a test webhook'
                ]
            ];

            // Process test webhook
            $result = $this->webhookService->processWebhook($testData);

            return new JsonResponse([
                'success' => true,
                'message' => 'Test webhook processed successfully',
                'result' => $result
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get webhook logs
     */
    public function logsAction(Request $request): JsonResponse
    {
        // Check permissions
        if (!$this->get('mautic.security')->isGranted('plugin:evolution_analytics:view')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        try {
            $limit = (int) $request->get('limit', 50);
            $offset = (int) $request->get('offset', 0);
            $level = $request->get('level', 'all');

            $logs = $this->getWebhookLogs($limit, $offset, $level);

            return new JsonResponse([
                'success' => true,
                'logs' => $logs,
                'total' => count($logs)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear webhook logs
     */
    public function clearLogsAction(Request $request): JsonResponse
    {
        // Check permissions
        if (!$this->get('mautic.security')->isGranted('plugin:evolution_analytics:manage')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        if ($request->getMethod() !== 'POST') {
            return new JsonResponse(['error' => 'Method not allowed'], 405);
        }

        try {
            $this->clearWebhookLogs();

            return new JsonResponse([
                'success' => true,
                'message' => 'Webhook logs cleared successfully'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate webhook signature
     */
    private function validateWebhookSignature(Request $request): bool
    {
        // Get webhook secret from configuration
        $webhookSecret = $this->getParameter('evolution_webhook_secret');
        
        if (!$webhookSecret) {
            // If no secret is configured, skip validation
            return true;
        }

        $signature = $request->headers->get('X-Evolution-Signature');
        
        if (!$signature) {
            return false;
        }

        // Calculate expected signature
        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);

        // Compare signatures
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get webhook logs from database or log files
     */
    private function getWebhookLogs(int $limit, int $offset, string $level): array
    {
        // This is a simplified implementation
        // In a real scenario, you might want to read from log files or a dedicated log table
        
        $connection = $this->getDoctrine()->getConnection();
        
        try {
            // Try to get logs from a webhook_logs table if it exists
            $qb = $connection->createQueryBuilder()
                ->select(['*'])
                ->from('evolution_webhook_logs')
                ->orderBy('created_at', 'DESC')
                ->setMaxResults($limit)
                ->setFirstResult($offset);

            if ($level !== 'all') {
                $qb->andWhere('level = :level')
                   ->setParameter('level', $level);
            }

            return $qb->execute()->fetchAll();

        } catch (\Exception $e) {
            // If table doesn't exist, return empty array
            return [];
        }
    }

    /**
     * Clear webhook logs
     */
    private function clearWebhookLogs(): void
    {
        $connection = $this->getDoctrine()->getConnection();
        
        try {
            $connection->executeStatement('TRUNCATE TABLE evolution_webhook_logs');
        } catch (\Exception $e) {
            // Table might not exist, which is fine
        }
    }

    /**
     * Log webhook activity
     */
    private function logWebhookActivity(string $level, string $message, array $context = []): void
    {
        try {
            $connection = $this->getDoctrine()->getConnection();
            
            $connection->insert('evolution_webhook_logs', [
                'level' => $level,
                'message' => $message,
                'context' => json_encode($context),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // If logging fails, just continue
            $this->logger->warning('Failed to log webhook activity', [
                'error' => $e->getMessage()
            ]);
        }
    }
}