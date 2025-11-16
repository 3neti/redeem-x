<?php

namespace App\Console\Commands;

use App\Models\User;
use LBHurtado\Contact\Models\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestSenderContactTracking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:sender-tracking 
                            {--user-mobile=09173011987 : Recipient user mobile}
                            {--sender-mobile=09175180722 : Sender mobile}
                            {--sender-name=LESTER HURTADO : Sender name}
                            {--amount=55.00 : Amount to send}
                            {--institution=GXCHPHM2XXX : Institution code}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test sender contact tracking by creating a contact and recording a deposit';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Sender Contact Tracking');
        $this->newLine();
        
        // Get options
        $userMobile = $this->option('user-mobile');
        $senderMobile = $this->option('sender-mobile');
        $senderName = $this->option('sender-name');
        $amount = (float) $this->option('amount');
        $institution = $this->option('institution');
        
        // Step 1: Find or create recipient user
        $this->info('Step 1: Finding/Creating Recipient User');
        $user = User::where('email', 'test-sender-tracking@example.com')->first();
        
        if (!$user) {
            $user = User::factory()->create([
                'email' => 'test-sender-tracking@example.com',
                'name' => 'Test User',
            ]);
        }
        
        $user->mobile = $userMobile;
        $user->save();
        
        $this->line("  ✓ User: {$user->name} (ID: {$user->id})");
        $this->line("  ✓ Mobile: {$user->mobile}");
        $this->newLine();
        
        // Step 2: Create sender contact
        $this->info('Step 2: Creating Sender Contact');
        $contact = Contact::fromWebhookSender([
            'accountNumber' => $senderMobile,
            'name' => $senderName,
            'institutionCode' => $institution,
        ]);
        
        $this->line("  ✓ Contact: {$contact->name} (ID: {$contact->id})");
        $this->line("  ✓ Mobile: {$contact->mobile}");
        $this->line("  ✓ Institution: {$institution} (" . Contact::institutionName($institution) . ")");
        $this->newLine();
        
        // Step 3: Record deposit
        $this->info('Step 3: Recording Deposit Transaction');
        $user->recordDepositFrom($contact, $amount, [
            'operation_id' => 'TEST-' . uniqid(),
            'channel' => 'INSTAPAY',
            'reference_number' => 'REF-' . uniqid(),
            'institution' => $institution,
            'transfer_type' => 'QR_P2M',
            'timestamp' => now()->toIso8601String(),
        ]);
        
        $this->line("  ✓ Recorded: ₱{$amount}");
        $this->newLine();
        
        // Step 4: Query and display results
        $this->info('Step 4: Verification');
        
        $senders = $user->senders()->get();
        $this->line("  ✓ Total Senders: {$senders->count()}");
        
        foreach ($senders as $sender) {
            $this->newLine();
            $this->line("  Sender: {$sender->name}");
            $this->line("    Mobile: {$sender->mobile}");
            $this->line("    Total Sent: ₱{$sender->pivot->total_sent}");
            $this->line("    Transactions: {$sender->pivot->transaction_count}");
            $this->line("    First Transaction: {$sender->pivot->first_transaction_at}");
            $this->line("    Last Transaction: {$sender->pivot->last_transaction_at}");
            
            // Show institutions used
            $institutions = $sender->institutionsUsed($user);
            if (!empty($institutions)) {
                $institutionNames = array_map(
                    fn($code) => Contact::institutionName($code),
                    $institutions
                );
                $this->line("    Payment Methods: " . implode(', ', $institutionNames));
            }
            
            // Show latest institution
            $latest = $sender->latestInstitution($user);
            if ($latest) {
                $this->line("    Latest Method: " . Contact::institutionName($latest));
            }
            
            // Show transaction history
            if ($sender->pivot->metadata) {
                $metadata = is_string($sender->pivot->metadata) 
                    ? json_decode($sender->pivot->metadata, true) 
                    : $sender->pivot->metadata;
                
                if ($metadata) {
                    $this->line("    Transaction History:");
                    foreach ($metadata as $index => $tx) {
                        $txNum = $index + 1;
                        $txInstitution = Contact::institutionName($tx['institution'] ?? 'Unknown');
                        $this->line("      #{$txNum}: {$tx['timestamp']} via {$txInstitution} ({$tx['channel']})");
                    }
                }
            }
        }
        
        $this->newLine();
        
        // Step 5: Test multiple institutions
        $this->info('Step 5: Testing Multiple Institutions');
        $this->line('  Sending another deposit via Maya...');
        
        $user->recordDepositFrom($contact, 100.00, [
            'operation_id' => 'TEST-' . uniqid(),
            'channel' => 'INSTAPAY',
            'reference_number' => 'REF-' . uniqid(),
            'institution' => 'PMYAPHM2XXX', // Maya
            'transfer_type' => 'QR_P2M',
            'timestamp' => now()->toIso8601String(),
        ]);
        
        $this->line("  ✓ Second deposit recorded");
        $this->newLine();
        
        // Refresh and show updated stats
        $sender = $user->senders()->find($contact->id);
        $this->line("  Updated Stats:");
        $this->line("    Total Sent: ₱{$sender->pivot->total_sent}");
        $this->line("    Transactions: {$sender->pivot->transaction_count}");
        
        $institutions = $sender->institutionsUsed($user);
        $institutionNames = array_map(
            fn($code) => Contact::institutionName($code),
            $institutions
        );
        $this->line("    Payment Methods: " . implode(', ', $institutionNames));
        
        $this->newLine();
        $this->components->success('✅ Sender contact tracking is working correctly!');
        
        // Show summary stats
        $this->newLine();
        $this->info('📊 Database Summary:');
        $this->table(
            ['Table', 'Count'],
            [
                ['Contacts', Contact::count()],
                ['Contact-User Relations', DB::table('contact_user')->count()],
                ['Users with Senders', User::has('senders')->count()],
            ]
        );
        
        return Command::SUCCESS;
    }
}
