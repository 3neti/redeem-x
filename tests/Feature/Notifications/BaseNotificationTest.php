<?php

use App\Contracts\NotificationInterface;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class)->group('notifications');

beforeEach(function () {
    // Reset config before each test
    Config::set('notifications.channels', [
        'test' => ['mail', 'database'],
        'test_sms' => ['engage_spark'],
        'test_all' => ['mail', 'engage_spark', 'database'],
    ]);

    Config::set('notifications.queue.queues', [
        'high' => ['test_high'],
        'normal' => ['test_normal'],
        'low' => ['test_low'],
    ]);

    Config::set('notifications.database_logging', [
        'enabled' => true,
        'always_log_for' => ['App\Models\User', 'User'],
        'never_log_for' => ['Illuminate\Notifications\AnonymousNotifiable', 'AnonymousNotifiable'],
    ]);
});

/**
 * Concrete implementation of BaseNotification for testing
 */
class TestConcreteNotification extends BaseNotification
{
    public function __construct(
        public string $message = 'Test message',
        public ?string $type = null
    ) {}

    public function getNotificationType(): string
    {
        return $this->type ?? 'test';
    }

    public function getNotificationData(): array
    {
        return ['message' => $this->message];
    }

    public function getAuditMetadata(): array
    {
        return ['test_metadata' => 'test_value'];
    }
}

describe('BaseNotification', function () {

    it('implements NotificationInterface', function () {
        $notification = new TestConcreteNotification;
        expect($notification)->toBeInstanceOf(NotificationInterface::class);
    });

    describe('via() method', function () {

        it('returns channels from config for notification type', function () {
            $notification = new TestConcreteNotification(type: 'test');
            $notifiable = User::factory()->make();

            $channels = $notification->via($notifiable);

            expect($channels)->toBe(['mail', 'database']);
        });

        it('adds database channel for User models when not already present', function () {
            Config::set('notifications.channels.test_sms', ['engage_spark']);

            $notification = new TestConcreteNotification(type: 'test_sms');
            $notifiable = User::factory()->make();

            $channels = $notification->via($notifiable);

            expect($channels)->toContain('database')
                ->and($channels)->toContain('engage_spark');
        });

        it('does not add database channel for AnonymousNotifiable', function () {
            Config::set('notifications.channels.test_sms', ['engage_spark']);

            $notification = new TestConcreteNotification(type: 'test_sms');
            $notifiable = new AnonymousNotifiable;

            $channels = $notification->via($notifiable);

            expect($channels)->toBe(['engage_spark'])
                ->and($channels)->not->toContain('database');
        });

        it('returns empty array if notification type not in config for AnonymousNotifiable', function () {
            $notification = new TestConcreteNotification(type: 'nonexistent');
            $notifiable = new AnonymousNotifiable;

            $channels = $notification->via($notifiable);

            expect($channels)->toBe([]);
        });

        it('adds database channel for User even if notification type not in config', function () {
            $notification = new TestConcreteNotification(type: 'nonexistent');
            $notifiable = User::factory()->make();

            $channels = $notification->via($notifiable);

            expect($channels)->toBe(['database']);
        });

        it('handles string channels from config by splitting on comma', function () {
            Config::set('notifications.channels.test', 'mail,engage_spark,database');

            $notification = new TestConcreteNotification(type: 'test');
            $notifiable = new AnonymousNotifiable;

            $channels = $notification->via($notifiable);

            expect($channels)->toBe(['mail', 'engage_spark', 'database']);
        });
    });

    describe('toArray() method', function () {

        it('returns standardized array structure', function () {
            $notification = new TestConcreteNotification('Hello world');

            $array = $notification->toArray(User::factory()->make());

            expect($array)
                ->toHaveKeys(['type', 'timestamp', 'data', 'audit'])
                ->and($array['type'])->toBe('test')
                ->and($array['data'])->toBe(['message' => 'Hello world'])
                ->and($array['audit'])->toBe(['test_metadata' => 'test_value'])
                ->and($array['timestamp'])->toBeString();
        });

        it('includes ISO 8601 timestamp', function () {
            $notification = new TestConcreteNotification;

            $array = $notification->toArray(User::factory()->make());

            // Verify timestamp is valid ISO 8601 format
            $timestamp = $array['timestamp'];
            expect($timestamp)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
        });
    });

    describe('shouldLogToDatabase() method', function () {

        it('returns true for User models when database logging enabled', function () {
            Config::set('notifications.database_logging.enabled', true);

            $notification = new TestConcreteNotification;
            $notifiable = User::factory()->make();

            expect($notification->shouldLogToDatabase($notifiable))->toBeTrue();
        });

        it('returns false for AnonymousNotifiable when in never_log_for list', function () {
            Config::set('notifications.database_logging.enabled', true);

            $notification = new TestConcreteNotification;
            $notifiable = new AnonymousNotifiable;

            expect($notification->shouldLogToDatabase($notifiable))->toBeFalse();
        });

        it('returns false when database logging is disabled', function () {
            Config::set('notifications.database_logging.enabled', false);

            $notification = new TestConcreteNotification;
            $notifiable = User::factory()->make();

            expect($notification->shouldLogToDatabase($notifiable))->toBeFalse();
        });

        it('checks class name without namespace', function () {
            Config::set('notifications.database_logging.always_log_for', ['User']);

            $notification = new TestConcreteNotification;
            $notifiable = User::factory()->make();

            expect($notification->shouldLogToDatabase($notifiable))->toBeTrue();
        });
    });

    describe('getQueueName() method', function () {

        it('returns high queue for high priority notification types', function () {
            $notification = new TestConcreteNotification(type: 'test_high');

            expect($notification->getQueueName())->toBe('high');
        });

        it('returns normal queue for normal priority notification types', function () {
            $notification = new TestConcreteNotification(type: 'test_normal');

            expect($notification->getQueueName())->toBe('normal');
        });

        it('returns low queue for low priority notification types', function () {
            $notification = new TestConcreteNotification(type: 'test_low');

            expect($notification->getQueueName())->toBe('low');
        });

        it('returns default queue for unlisted notification types', function () {
            Config::set('notifications.queue.default_queue', 'default');

            $notification = new TestConcreteNotification(type: 'unlisted');

            expect($notification->getQueueName())->toBe('default');
        });
    });

    describe('viaQueues() method', function () {

        it('returns queue name for mail channel', function () {
            $notification = new TestConcreteNotification(type: 'test_high');

            $queues = $notification->viaQueues();

            expect($queues)->toHaveKey('mail')
                ->and($queues['mail'])->toBe('high');
        });

        it('returns queue name for engage_spark channel', function () {
            $notification = new TestConcreteNotification(type: 'test_normal');

            $queues = $notification->viaQueues();

            expect($queues)->toHaveKey('engage_spark')
                ->and($queues['engage_spark'])->toBe('normal');
        });

        it('returns sync for database channel', function () {
            $notification = new TestConcreteNotification;

            $queues = $notification->viaQueues();

            expect($queues)->toHaveKey('database')
                ->and($queues['database'])->toBe('sync');
        });
    });

    describe('formatMoney() method', function () {

        it('formats amount with PHP currency symbol', function () {
            $notification = new TestConcreteNotification;

            $formatted = $notification->formatMoney(100.50);

            expect($formatted)->toBe('₱100.50');
        });

        it('formats zero amount', function () {
            $notification = new TestConcreteNotification;

            $formatted = $notification->formatMoney(0);

            expect($formatted)->toBe('₱0.00');
        });

        it('formats large amounts with commas', function () {
            $notification = new TestConcreteNotification;

            $formatted = $notification->formatMoney(1234567.89);

            expect($formatted)->toBe('₱1,234,567.89');
        });

        it('handles different currencies', function () {
            $notification = new TestConcreteNotification;

            $formatted = $notification->formatMoney(100, 'USD');

            expect($formatted)->toBe('$100.00');
        });
    });

    describe('buildTemplateContext() method', function () {

        it('builds context from notifiable user', function () {
            $user = User::factory()->make([
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]);

            $notification = new TestConcreteNotification;
            $context = $notification->buildTemplateContext($user);

            expect($context)
                ->toHaveKey('user_name')
                ->and($context['user_name'])->toBe('John Doe')
                ->and($context)->toHaveKey('user_email')
                ->and($context['user_email'])->toBe('john@example.com');
        });

        it('includes notification type in context', function () {
            $notification = new TestConcreteNotification(type: 'test');
            $context = $notification->buildTemplateContext(User::factory()->make());

            expect($context)
                ->toHaveKey('notification_type')
                ->and($context['notification_type'])->toBe('test');
        });
    });
});
