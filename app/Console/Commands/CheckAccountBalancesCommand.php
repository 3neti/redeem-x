<?php

namespace App\Console\Commands;

use App\Models\AccountBalance;
use App\Services\BalanceService;
use Illuminate\Console\Command;

class CheckAccountBalancesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'balances:check
                            {--account= : Specific account to check}
                            {--all : Check all configured accounts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check account balances and update records';

    /**
     * Execute the console command.
     */
    public function handle(BalanceService $service): int
    {
        $accounts = $this->getAccountsToCheck();

        if (empty($accounts)) {
            $this->error('No accounts to check. Use --account or --all option.');
            $this->newLine();
            $this->info('Examples:');
            $this->line('  php artisan balances:check --account=113-001-00001-9');
            $this->line('  php artisan balances:check --all');

            return self::FAILURE;
        }

        $this->info('Checking balances for '.count($accounts).' account(s)...');
        $this->newLine();

        $bar = $this->output->createProgressBar(count($accounts));
        $bar->start();

        $success = 0;
        $failed = 0;
        $results = [];

        foreach ($accounts as $account) {
            try {
                $balance = $service->checkAndUpdate($account);
                $success++;
                $results[] = [
                    'account' => $account,
                    'balance' => $balance->formatted_balance,
                    'available' => $balance->formatted_available_balance,
                    'status' => '✓ Success',
                ];
            } catch (\Throwable $e) {
                $failed++;
                $results[] = [
                    'account' => $account,
                    'balance' => 'N/A',
                    'available' => 'N/A',
                    'status' => '✗ Failed: '.$e->getMessage(),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Display results table
        $this->table(
            ['Account', 'Balance', 'Available', 'Status'],
            $results
        );

        $this->newLine();
        $this->info("✓ Success: {$success}");
        if ($failed > 0) {
            $this->error("✗ Failed: {$failed}");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Get list of accounts to check.
     */
    protected function getAccountsToCheck(): array
    {
        if ($account = $this->option('account')) {
            return [$account];
        }

        if ($this->option('all')) {
            // Get all accounts from database
            $accounts = AccountBalance::query()
                ->select('account_number')
                ->distinct()
                ->pluck('account_number')
                ->toArray();

            if (empty($accounts)) {
                // If no accounts in DB, use configured default
                $defaultAccount = $this->getDefaultAccount();

                return $defaultAccount ? [$defaultAccount] : [];
            }

            return $accounts;
        }

        // Default: check configured primary account
        $defaultAccount = $this->getDefaultAccount();

        return $defaultAccount ? [$defaultAccount] : [];
    }

    /**
     * Get default account from configuration.
     */
    protected function getDefaultAccount(): ?string
    {
        // Try various config keys
        return config('omnipay.test_account')
            ?? config('disbursement.account_number')
            ?? config('payment-gateway.default_account')
            ?? null;
    }
}
