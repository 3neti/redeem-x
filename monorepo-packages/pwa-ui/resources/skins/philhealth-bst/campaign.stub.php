<?php

/**
 * PhilHealth BST Campaign Migration
 *
 * Creates a system-level campaign for PhilHealth BST voucher issuance.
 * This campaign can be referenced by kiosk via ?campaign=philhealth-bst
 *
 * Published to: database/migrations/xxxx_xx_xx_xxxxxx_create_philhealth_bst_campaign.php
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if campaign already exists (idempotent)
        $exists = DB::table('campaigns')
            ->where('slug', 'philhealth-bst')
            ->exists();

        if ($exists) {
            return;
        }

        // Create the PhilHealth BST campaign
        DB::table('campaigns')->insert([
            'user_id' => null, // System campaign - available to all users
            'name' => 'PhilHealth BST',
            'slug' => 'philhealth-bst',
            'description' => 'Benefit Support Token for PhilHealth reimbursements. Issues settlement vouchers with target amount for patient claims.',
            'status' => 'active',
            'instructions' => json_encode([
                'voucher_type' => 'settlement',
                'amount' => 0, // Deposit amount entered at kiosk
                'target_amount' => null, // Reimbursement amount entered at kiosk
                'input_fields' => ['mobile', 'name'],
                'feedback_channels' => [],
                'rider' => [
                    'message' => 'Present this voucher to the PhilHealth window for processing.',
                ],
                // Kiosk UI overrides
                'kiosk' => [
                    'title' => 'PhilHealth BST',
                    'subtitle' => 'Benefit Support Token',
                    'amount_label' => 'Deposit Amount',
                    'target_label' => 'PhilHealth Reimbursement',
                    'button_text' => 'Issue BST',
                    'success_title' => 'BST Issued!',
                    'success_message' => 'Present QR to cashier for payment',
                ],
            ]),
            'envelope_config' => json_encode([
                'enabled' => true,
                'driver' => 'philhealth-bst@1.0.0',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('campaigns')
            ->where('slug', 'philhealth-bst')
            ->delete();
    }
};
