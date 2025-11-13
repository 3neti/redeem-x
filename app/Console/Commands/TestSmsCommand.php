<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\SMS\Facades\SMS;

class TestSmsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:sms 
                            {mobile=09173011987 : Mobile number to send SMS to}
                            {message? : Custom message to send}
                            {--sender= : Sender ID (default: cashless)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test SMS sending via EngageSpark (bypasses notifications)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $mobile = $this->argument('mobile');
        $message = $this->argument('message') ?? 'Test message from Redeem-X at ' . now()->toDateTimeString();
        $sender = $this->option('sender') ?? env('ENGAGESPARK_SENDER_ID', 'cashless');

        $this->info("📱 Sending SMS to {$mobile} from {$sender}...");

        try {
            SMS::channel('engagespark')
                ->from($sender)
                ->to($mobile)
                ->content($message)
                ->send();

            $this->info('✅ SMS sent successfully!');
            $this->newLine();
            $this->line("Check {$mobile} for the message.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to send SMS: ' . $e->getMessage());
            $this->newLine();
            
            if ($this->output->isVerbose()) {
                $this->line('Stack trace:');
                $this->line($e->getTraceAsString());
            } else {
                $this->line('Run with -v for more details');
            }

            return self::FAILURE;
        }
    }
}
