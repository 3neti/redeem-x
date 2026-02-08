<?php

namespace App\Console\Commands;

use App\Actions\Api\Vouchers\GenerateContributionLink;
use Illuminate\Console\Command;
use LBHurtado\Voucher\Models\Voucher;

class TestContributionLink extends Command
{
    protected $signature = 'test:contribution-link
        {code : The voucher code}
        {--label= : Label for the contribution link}
        {--recipient= : Recipient name}
        {--email= : Recipient email}
        {--mobile= : Recipient mobile}
        {--password= : Password protection (optional)}
        {--expires=7 : Days until expiration}';

    protected $description = 'Generate a contribution link for a voucher';

    public function handle(): int
    {
        $code = strtoupper($this->argument('code'));
        $voucher = Voucher::where('code', $code)->first();

        if (! $voucher) {
            $this->error("Voucher '{$code}' not found");

            return 1;
        }

        if (! $voucher->envelope) {
            $this->error("Voucher '{$code}' does not have a settlement envelope");

            return 1;
        }

        // Authenticate as voucher owner
        auth()->login($voucher->owner);

        $this->info("Generating contribution link for voucher: {$code}");
        $this->line("  Type: {$voucher->voucher_type->value}");
        $this->line('  Amount: ₱'.number_format($voucher->instructions->cash->amount ?? 0, 2));
        $this->line("  Owner: {$voucher->owner->name}");
        $this->newLine();

        try {
            $token = GenerateContributionLink::run($voucher, [
                'label' => $this->option('label'),
                'recipient_name' => $this->option('recipient'),
                'recipient_email' => $this->option('email'),
                'recipient_mobile' => $this->option('mobile'),
                'password' => $this->option('password'),
                'expires_days' => (int) $this->option('expires'),
            ]);

            $this->info('✓ Contribution link generated!');
            $this->newLine();

            $this->table(
                ['Property', 'Value'],
                [
                    ['Token UUID', $token->token],
                    ['Label', $token->label ?? '-'],
                    ['Recipient', $token->recipient_name ?? '-'],
                    ['Email', $token->recipient_email ?? '-'],
                    ['Mobile', $token->recipient_mobile ?? '-'],
                    ['Password Protected', $token->requiresPassword() ? 'Yes' : 'No'],
                    ['Expires', $token->expires_at->format('Y-m-d H:i:s')],
                ]
            );

            $this->newLine();
            $this->line('<fg=green>Contribution URL:</>');
            $this->line($token->generateUrl($voucher->code));

            $this->newLine();
            $this->info('Share this URL with the external contributor.');

            if ($token->requiresPassword()) {
                $this->warn("Note: Contributor will need the password '{$this->option('password')}' to access.");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to generate link: {$e->getMessage()}");

            return 1;
        }
    }
}
