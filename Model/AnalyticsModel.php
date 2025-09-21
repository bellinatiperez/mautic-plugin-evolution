<?php

namespace MauticPlugin\EvolutionWhatsAppBundle\Model;

use Doctrine\DBAL\Connection;
use Mautic\CoreBundle\Model\AbstractCommonModel;
use Psr\Log\LoggerInterface;

/**
 * Analytics Model
 * 
 * Manages WhatsApp analytics data storage and retrieval
 */
class AnalyticsModel extends AbstractCommonModel
{
    private Connection $connection;
    protected LoggerInterface $logger;

    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    /**
     * Store message data for analytics
     */
    public function storeMessageData(array $data): void
    {
        try {
            $this->connection->insert('evolution_messages', [
                'message_id' => $data['message_id'],
                'instance_name' => $data['instance_name'],
                'contact_id' => $data['contact_id'],
                'phone' => $data['phone'],
                'from_me' => (int) $data['from_me'],
                'message_type' => $data['message_type'],
                'content' => $data['content'],
                'timestamp' => $data['timestamp'],
                'action' => $data['action'],
                'raw_data' => $data['raw_data'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to store message data', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Store chat data
     */
    public function storeChatData(array $data): void
    {
        try {
            $this->connection->insert('evolution_chats', [
                'instance_name' => $data['instance_name'],
                'contact_id' => $data['contact_id'],
                'phone' => $data['phone'],
                'chat_data' => $data['chat_data'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                // Update existing record
                $this->connection->update('evolution_chats', [
                    'chat_data' => $data['chat_data'],
                    'updated_at' => date('Y-m-d H:i:s')
                ], [
                    'instance_name' => $data['instance_name'],
                    'phone' => $data['phone']
                ]);
            } else {
                $this->logger->error('Failed to store chat data', [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
                throw $e;
            }
        }
    }

    /**
     * Store connection data
     */
    public function storeConnectionData(array $data): void
    {
        try {
            $this->connection->insert('evolution_connections', [
                'instance_name' => $data['instance_name'],
                'state' => $data['state'],
                'timestamp' => $data['timestamp'],
                'data' => $data['data'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to store connection data', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Store presence data
     */
    public function storePresenceData(array $data): void
    {
        try {
            $this->connection->insert('evolution_presence', [
                'instance_name' => $data['instance_name'],
                'contact_id' => $data['contact_id'],
                'phone' => $data['phone'],
                'presence' => $data['presence'],
                'timestamp' => $data['timestamp'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to store presence data', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Get message statistics
     */
    public function getMessageStats(array $filters = []): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select([
                'COUNT(*) as total_messages',
                'SUM(CASE WHEN from_me = 1 THEN 1 ELSE 0 END) as sent_messages',
                'SUM(CASE WHEN from_me = 0 THEN 1 ELSE 0 END) as received_messages',
                'COUNT(DISTINCT contact_id) as unique_contacts',
                'COUNT(DISTINCT instance_name) as active_instances'
            ])
            ->from('evolution_messages');

        $this->applyFilters($qb, $filters);

        return $qb->execute()->fetch();
    }

    /**
     * Get message statistics by date
     */
    public function getMessageStatsByDate(array $filters = []): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select([
                'DATE(FROM_UNIXTIME(timestamp)) as date',
                'COUNT(*) as total_messages',
                'SUM(CASE WHEN from_me = 1 THEN 1 ELSE 0 END) as sent_messages',
                'SUM(CASE WHEN from_me = 0 THEN 1 ELSE 0 END) as received_messages',
                'COUNT(DISTINCT contact_id) as unique_contacts'
            ])
            ->from('evolution_messages')
            ->groupBy('DATE(FROM_UNIXTIME(timestamp))')
            ->orderBy('date', 'DESC');

        $this->applyFilters($qb, $filters);

        return $qb->execute()->fetchAll();
    }

    /**
     * Get message statistics by type
     */
    public function getMessageStatsByType(array $filters = []): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select([
                'message_type',
                'COUNT(*) as count',
                'ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage'
            ])
            ->from('evolution_messages')
            ->groupBy('message_type')
            ->orderBy('count', 'DESC');

        $this->applyFilters($qb, $filters);

        return $qb->execute()->fetchAll();
    }

    /**
     * Get top active contacts
     */
    public function getTopActiveContacts(array $filters = [], int $limit = 10): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select([
                'em.contact_id',
                'em.phone',
                'l.firstname',
                'l.lastname',
                'COUNT(*) as message_count',
                'SUM(CASE WHEN em.from_me = 0 THEN 1 ELSE 0 END) as received_count',
                'SUM(CASE WHEN em.from_me = 1 THEN 1 ELSE 0 END) as sent_count',
                'MAX(em.timestamp) as last_message_timestamp'
            ])
            ->from('evolution_messages', 'em')
            ->leftJoin('em', 'leads', 'l', 'l.id = em.contact_id')
            ->groupBy('em.contact_id', 'em.phone', 'l.firstname', 'l.lastname')
            ->orderBy('message_count', 'DESC')
            ->setMaxResults($limit);

        $this->applyFilters($qb, $filters, 'em');

        return $qb->execute()->fetchAll();
    }

    /**
     * Get instance statistics
     */
    public function getInstanceStats(array $filters = []): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select([
                'instance_name',
                'COUNT(*) as message_count',
                'COUNT(DISTINCT contact_id) as unique_contacts',
                'SUM(CASE WHEN from_me = 1 THEN 1 ELSE 0 END) as sent_messages',
                'SUM(CASE WHEN from_me = 0 THEN 1 ELSE 0 END) as received_messages',
                'MAX(timestamp) as last_activity'
            ])
            ->from('evolution_messages')
            ->groupBy('instance_name')
            ->orderBy('message_count', 'DESC');

        $this->applyFilters($qb, $filters);

        return $qb->execute()->fetchAll();
    }

    /**
     * Get connection status history
     */
    public function getConnectionHistory(string $instanceName = null, int $limit = 50): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select([
                'instance_name',
                'state',
                'timestamp',
                'created_at'
            ])
            ->from('evolution_connections')
            ->orderBy('timestamp', 'DESC')
            ->setMaxResults($limit);

        if ($instanceName) {
            $qb->where('instance_name = :instance_name')
               ->setParameter('instance_name', $instanceName);
        }

        return $qb->execute()->fetchAll();
    }

    /**
     * Get hourly message distribution
     */
    public function getHourlyMessageDistribution(array $filters = []): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select([
                'HOUR(FROM_UNIXTIME(timestamp)) as hour',
                'COUNT(*) as message_count',
                'SUM(CASE WHEN from_me = 1 THEN 1 ELSE 0 END) as sent_count',
                'SUM(CASE WHEN from_me = 0 THEN 1 ELSE 0 END) as received_count'
            ])
            ->from('evolution_messages')
            ->groupBy('HOUR(FROM_UNIXTIME(timestamp))')
            ->orderBy('hour', 'ASC');

        $this->applyFilters($qb, $filters);

        return $qb->execute()->fetchAll();
    }

    /**
     * Get response time analytics
     */
    public function getResponseTimeAnalytics(array $filters = []): array
    {
        $sql = "
            SELECT 
                AVG(response_time) as avg_response_time,
                MIN(response_time) as min_response_time,
                MAX(response_time) as max_response_time,
                COUNT(*) as total_responses
            FROM (
                SELECT 
                    (m2.timestamp - m1.timestamp) as response_time
                FROM evolution_messages m1
                JOIN evolution_messages m2 ON m1.contact_id = m2.contact_id 
                    AND m1.phone = m2.phone
                    AND m2.timestamp > m1.timestamp
                    AND m1.from_me = 0 
                    AND m2.from_me = 1
                WHERE m2.timestamp - m1.timestamp < 3600
                ORDER BY m1.timestamp, m2.timestamp
            ) as response_times
        ";

        return $this->connection->executeQuery($sql)->fetch();
    }

    /**
     * Apply filters to query builder
     */
    private function applyFilters($qb, array $filters, string $alias = null): void
    {
        $prefix = $alias ? $alias . '.' : '';

        if (!empty($filters['instance_name'])) {
            $qb->andWhere($prefix . 'instance_name = :instance_name')
               ->setParameter('instance_name', $filters['instance_name']);
        }

        if (!empty($filters['contact_id'])) {
            $qb->andWhere($prefix . 'contact_id = :contact_id')
               ->setParameter('contact_id', $filters['contact_id']);
        }

        if (!empty($filters['phone'])) {
            $qb->andWhere($prefix . 'phone = :phone')
               ->setParameter('phone', $filters['phone']);
        }

        if (!empty($filters['from_date'])) {
            $qb->andWhere($prefix . 'timestamp >= :from_date')
               ->setParameter('from_date', strtotime($filters['from_date']));
        }

        if (!empty($filters['to_date'])) {
            $qb->andWhere($prefix . 'timestamp <= :to_date')
               ->setParameter('to_date', strtotime($filters['to_date'] . ' 23:59:59'));
        }

        if (!empty($filters['message_type'])) {
            $qb->andWhere($prefix . 'message_type = :message_type')
               ->setParameter('message_type', $filters['message_type']);
        }

        if (isset($filters['from_me'])) {
            $qb->andWhere($prefix . 'from_me = :from_me')
               ->setParameter('from_me', (int) $filters['from_me']);
        }
    }

    /**
     * Create database tables if they don't exist
     */
    public function createTables(): void
    {
        $this->createMessagesTable();
        $this->createChatsTable();
        $this->createConnectionsTable();
        $this->createPresenceTable();
    }

    /**
     * Create messages table
     */
    private function createMessagesTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS evolution_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message_id VARCHAR(255) NOT NULL,
                instance_name VARCHAR(100) NOT NULL,
                contact_id INT,
                phone VARCHAR(20) NOT NULL,
                from_me TINYINT(1) NOT NULL DEFAULT 0,
                message_type VARCHAR(50) NOT NULL,
                content TEXT,
                timestamp INT NOT NULL,
                action VARCHAR(20) NOT NULL DEFAULT 'upsert',
                raw_data LONGTEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_instance_phone (instance_name, phone),
                INDEX idx_contact_id (contact_id),
                INDEX idx_timestamp (timestamp),
                INDEX idx_message_type (message_type),
                UNIQUE KEY unique_message (message_id, instance_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->connection->executeStatement($sql);
    }

    /**
     * Create chats table
     */
    private function createChatsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS evolution_chats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                instance_name VARCHAR(100) NOT NULL,
                contact_id INT,
                phone VARCHAR(20) NOT NULL,
                chat_data LONGTEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_instance_phone (instance_name, phone),
                INDEX idx_contact_id (contact_id),
                UNIQUE KEY unique_chat (instance_name, phone)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->connection->executeStatement($sql);
    }

    /**
     * Create connections table
     */
    private function createConnectionsTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS evolution_connections (
                id INT AUTO_INCREMENT PRIMARY KEY,
                instance_name VARCHAR(100) NOT NULL,
                state VARCHAR(50) NOT NULL,
                timestamp INT NOT NULL,
                data TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_instance_name (instance_name),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->connection->executeStatement($sql);
    }

    /**
     * Create presence table
     */
    private function createPresenceTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS evolution_presence (
                id INT AUTO_INCREMENT PRIMARY KEY,
                instance_name VARCHAR(100) NOT NULL,
                contact_id INT,
                phone VARCHAR(20) NOT NULL,
                presence VARCHAR(20) NOT NULL,
                timestamp INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_instance_phone (instance_name, phone),
                INDEX idx_contact_id (contact_id),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->connection->executeStatement($sql);
    }
}