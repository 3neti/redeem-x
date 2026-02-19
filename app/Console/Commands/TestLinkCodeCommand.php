<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Messaging\GenerateLinkCode;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Test command to generate a messaging link code.
 *
 * Usage: php artisan test:link-code user@example.com
 */
class TestLinkCodeCommand extends Command
{
    protected $signature = 'test:link-code
        {email? : User email (defaults to first user)}
        {--platform=telegram : Platform to link (telegram, whatsapp, viber)}';

    protected $description = 'Generate a link code for testing messaging account linking';

    public function handle(): int
    {
        $email = $this->argument('email');
        $platform = $this->option('platform');

        $user = $email
            ? User::where('email', $email)->first()
            : User::first();

        if (! $user) {
            $this->error('User not found.');

            return Command::FAILURE;
        }

        $result = GenerateLinkCode::run($user, $platform);

        $this->info("Link code generated for: {$user->email}");
        $this->newLine();

        $this->table(
            ['Property', 'Value'],
            [
                ['Code', $result['code']],
                ['Platform', $result['platform']],
                ['Expires', $result['expires_at']],
                ['Instructions', $result['instructions']],
            ]
        );

        $this->newLine();
        $this->line("Send to bot: <fg=yellow>/link {$result['code']}</>");

        return Command::SUCCESS;
    }
}
