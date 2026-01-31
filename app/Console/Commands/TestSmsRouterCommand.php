<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LBHurtado\OmniChannel\Services\SMSRouterService;

class TestSmsRouterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:sms-router {message} {--mobile=09173011987}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test SMS command routing locally (simulates incoming SMS)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $message = $this->argument('message');
        $mobile = $this->option('mobile');
        
        // Normalize mobile to E.164 format
        if (!str_starts_with($mobile, '+')) {
            $mobile = '+63' . ltrim($mobile, '0');
        }
        
        $this->info("📱 Processing SMS command: \"{$message}\"");
        $this->line("   From: {$mobile}");
        $this->newLine();
        
        // Find user by mobile number (via channels relationship)
        $user = User::whereHas('channels', function ($q) use ($mobile) {
            $q->where('name', 'mobile')
              ->where(function ($sub) use ($mobile) {
                  // Match both formats: 09173011987 and +639173011987
                  $sub->where('value', 'LIKE', "%{$mobile}%")
                      ->orWhere('value', 'LIKE', "%" . ltrim($mobile, '+0') . "%");
              });
        })->first();
        
        if ($user) {
            $this->line("✅ Found user: {$user->name} ({$user->email})");
        } else {
            $this->warn("⚠️  User not found with mobile: {$mobile}");
            $this->line("   Testing in unauthenticated mode (public endpoint)");
        }
        
        // Mock authenticated request
        $request = Request::create(
            '/sms',
            'POST',
            ['from' => $mobile, 'to' => '2929', 'message' => $message]
        );
        
        // Set authenticated user if found (simulates auth:sanctum middleware)
        if ($user) {
            $request->setUserResolver(function () use ($user) {
                return $user;
            });
            Auth::setUser($user);
        }
        
        $this->laravel->instance('request', $request);
        
        // Route through SMS router
        try {
            $router = $this->laravel->make(SMSRouterService::class);
            $response = $router->handle($message, $mobile, '2929');
            
            $this->newLine();
            $this->info('📤 SMS Response:');
            $this->line('   ' . ($response->getData()->message ?? '(No message)'));
            
            return 0;
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Routing failed: ' . $e->getMessage());
            
            if ($this->output->isVerbose()) {
                $this->newLine();
                $this->line('Stack trace:');
                $this->line($e->getTraceAsString());
            }
            
            return 1;
        }
    }
}
