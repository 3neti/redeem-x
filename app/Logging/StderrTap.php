<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\StreamHandler;

class StderrTap
{
    /**
     * Customize the stderr handler for large payloads (location, signature data).
     */
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof StreamHandler) {
                // Increase stream buffer to 8MB (default is usually 4KB)
                // This allows larger log entries to be written in one chunk
                $handler->setStreamContext([
                    'socket' => [
                        'timeout' => 60, // 60 seconds timeout
                    ],
                ]);

                // Set buffer to 8MB for large signature/location payloads
                if ($stream = $handler->getStream()) {
                    stream_set_write_buffer($stream, 8 * 1024 * 1024);
                    stream_set_timeout($stream, 60); // 60 seconds
                }
            }
        }
    }
}
