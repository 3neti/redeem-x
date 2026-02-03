<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Notification Interface
 * 
 * Defines the contract that all notifications must implement for
 * consistent structure, type safety, and centralized configuration.
 * 
 * This interface ensures:
 * - Unique type identifier for each notification
 * - Standardized data structure for database storage
 * - Audit trail metadata for tracking and debugging
 */
interface NotificationInterface
{
    /**
     * Get unique notification type identifier.
     * 
     * This identifier is used for:
     * - Channel configuration lookup (config/notifications.php)
     * - Queue priority assignment
     * - Database indexing and filtering
     * - Analytics and reporting
     * 
     * @return string Unique type (e.g., 'balance', 'voucher_redeemed')
     */
    public function getNotificationType(): string;
    
    /**
     * Get core notification data for database storage.
     * 
     * This data is stored in the `data` key of the toArray() output
     * and represents the notification-specific information.
     * 
     * @return array Notification-specific data
     */
    public function getNotificationData(): array;
    
    /**
     * Get additional audit trail metadata.
     * 
     * This metadata is stored in the `audit` key of the toArray() output
     * and provides information about how the notification was sent.
     * 
     * @return array Audit metadata (channels, queue, timestamps, etc.)
     */
    public function getAuditMetadata(): array;
}
