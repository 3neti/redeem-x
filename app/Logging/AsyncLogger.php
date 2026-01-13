<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger as Monolog;

class AsyncLogger
{
    public function __invoke(array $config): Monolog
    {
        $logger = new Monolog('async');
        $logger->pushHandler(new AsyncLogHandler($config['channels'] ?? ['daily']));

        return $logger;
    }
}

class AsyncLogHandler extends AbstractProcessingHandler
{
    protected array $channels;

    public function __construct(array $channels = ['daily'], $level = Monolog::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->channels = $channels;
    }

    protected function write(array $record): void
    {
        // Dispatch log writing to queue to avoid blocking
        dispatch(function () use ($record) {
            foreach ($this->channels as $channel) {
                try {
                    Log::channel($channel)->log(
                        strtolower($record['level_name']),
                        $record['message'],
                        $record['context'] ?? []
                    );
                } catch (\Throwable $e) {
                    // Silently fail - we don't want logging errors to crash the app
                    error_log("Async log write failed: {$e->getMessage()}");
                }
            }
        })->onQueue('logs');
    }
}
