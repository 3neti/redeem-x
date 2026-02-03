<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\NotificationInterface;
use App\Services\TemplateProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;

/**
 * Base Notification Abstract Class
 * 
 * Provides standardized functionality for all notifications:
 * - Config-driven channel resolution
 * - Automatic database logging for audit trail
 * - Queue priority management
 * - Localization template helpers
 * - Common formatting utilities
 * 
 * All notifications should extend this class for consistency.
 */
abstract class BaseNotification extends Notification implements ShouldQueue, NotificationInterface
{
    use Queueable;
    
    /**
     * Get the notification's delivery channels.
     * 
     * Channels are determined by:
     * 1. Notification type from getNotificationType()
     * 2. Config from config/notifications.php
     * 3. Notifiable type (User = add database, AnonymousNotifiable = config only)
     */
    public function via(object $notifiable): array
    {
        $type = $this->getNotificationType();
        $configChannels = config("notifications.channels.{$type}", []);
        
        // Ensure channels is always an array
        if (is_string($configChannels)) {
            $configChannels = array_filter(explode(',', $configChannels));
        }
        
        // For AnonymousNotifiable, use config channels only (no database)
        if ($notifiable instanceof AnonymousNotifiable) {
            return $configChannels;
        }
        
        // For models, add database if configured
        if ($this->shouldLogToDatabase($notifiable)) {
            return array_unique(array_merge($configChannels, ['database']));
        }
        
        return $configChannels;
    }
    
    /**
     * Get the array representation of the notification for database storage.
     * 
     * Standardized structure:
     * - type: Notification type identifier
     * - timestamp: ISO 8601 timestamp
     * - data: Notification-specific data from getNotificationData()
     * - audit: Metadata from getAuditMetadata()
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->getNotificationType(),
            'timestamp' => now()->format('Y-m-d\\TH:i:s.u\\Z'),
            'data' => $this->getNotificationData(),
            'audit' => $this->getAuditMetadata(),
        ];
    }
    
    /**
     * Get audit trail metadata.
     * 
     * Default implementation includes channels, queue, and queued status.
     * Child classes can override to add custom audit data.
     */
    public function getAuditMetadata(): array
    {
        // Create temporary AnonymousNotifiable to get channels without notifiable dependency
        $tempNotifiable = new AnonymousNotifiable();
        
        return [
            'sent_via' => implode(',', $this->via($tempNotifiable)),
            'queued' => true,
            'queue' => $this->getQueueName(),
        ];
    }
    
    /**
     * Determine if notification should be logged to database.
     */
    public function shouldLogToDatabase(object $notifiable): bool
    {
        $enabled = config('notifications.database_logging.enabled', true);
        
        if (!$enabled) {
            return false;
        }
        
        $alwaysLog = config('notifications.database_logging.always_log_for', []);
        $neverLog = config('notifications.database_logging.never_log_for', []);
        
        $class = get_class($notifiable);
        $shortClass = class_basename($class);
        
        // Check never log list first
        if (in_array($class, $neverLog) || in_array($shortClass, $neverLog)) {
            return false;
        }
        
        // Check always log list
        if (in_array($class, $alwaysLog) || in_array($shortClass, $alwaysLog)) {
            return true;
        }
        
        // Default: log for all model notifications
        return true;
    }
    
    /**
     * Format money amount with proper currency formatting.
     * 
     * Uses Brick\Money for accurate currency handling.
     */
    public function formatMoney(float $amount, string $currency = 'PHP'): string
    {
        try {
            $money = \Brick\Money\Money::of($amount, $currency);
            return $money->formatTo('en_PH');
        } catch (\Throwable $e) {
            // Fallback to simple formatting if Brick\Money fails
            return '₱' . number_format($amount, 2);
        }
    }
    
    /**
     * Get queue name based on notification type priority.
     * 
     * Queue priorities:
     * - high: Critical alerts (disbursement failures, low balance)
     * - normal: User-facing notifications (payment confirmations, redemptions)
     * - low: Informational (balance queries, help, generation summaries)
     */
    public function getQueueName(): string
    {
        $type = $this->getNotificationType();
        $queues = config('notifications.queue.queues', []);
        
        foreach ($queues as $queue => $types) {
            if (in_array($type, $types)) {
                return $queue;
            }
        }
        
        return config('notifications.queue.default_queue', 'default');
    }
    
    /**
     * Specify which queue to use for each channel.
     * 
     * Database notifications are always sent immediately (sync)
     * for accurate audit trail timestamps.
     */
    public function viaQueues(): array
    {
        $queueName = $this->getQueueName();
        
        return [
            'mail' => $queueName,
            'engage_spark' => $queueName,
            'database' => 'sync', // Always immediate for audit trail
        ];
    }
    
    /**
     * Get localized template with variable substitution.
     * 
     * Uses Laravel's translation system and TemplateProcessor for
     * variable replacement with {{ variable }} syntax.
     * 
     * @param string $key Translation key (e.g., 'notifications.balance.user.sms')
     * @param array $context Variables for template substitution
     * @return string Processed template with variables replaced
     */
    public function getLocalizedTemplate(string $key, array $context = []): string
    {
        $template = __($key);
        
        // If translation not found, return key as fallback
        if ($template === $key) {
            return $key;
        }
        
        // If TemplateProcessor is available, use it
        if (class_exists(TemplateProcessor::class)) {
            return TemplateProcessor::process($template, $context);
        }
        
        // Fallback: simple str_replace for basic variables
        foreach ($context as $variable => $value) {
            if (is_scalar($value)) {
                $template = str_replace("{{ {$variable} }}", (string) $value, $template);
                $template = str_replace("{{{$variable}}}", (string) $value, $template); // Without spaces
            }
        }
        
        return $template;
    }
    
    /**
     * Build template context for notification.
     * 
     * Default implementation includes global variables and notifiable data.
     * Child classes should override and call parent::buildTemplateContext()
     * to add notification-specific context.
     * 
     * @param object|null $notifiable The notifiable object (optional for backwards compatibility)
     * @return array Template variables
     */
    public function buildTemplateContext(?object $notifiable = null): array
    {
        $context = [
            'timestamp' => now()->toIso8601String(),
            'app_name' => config('app.name', 'Redeem-X'),
            'app_url' => config('app.url', 'http://localhost'),
            'notification_type' => $this->getNotificationType(),
        ];
        
        // Add notifiable data if provided
        if ($notifiable) {
            // User-specific data
            if (method_exists($notifiable, 'getAttribute')) {
                if ($name = $notifiable->getAttribute('name')) {
                    $context['user_name'] = $name;
                }
                if ($email = $notifiable->getAttribute('email')) {
                    $context['user_email'] = $email;
                }
            }
        }
        
        return $context;
    }
    
    /**
     * Abstract methods that child classes must implement.
     * 
     * These are defined by NotificationInterface but repeated here
     * for clarity and IDE support.
     */
    abstract public function getNotificationType(): string;
    abstract public function getNotificationData(): array;
}
