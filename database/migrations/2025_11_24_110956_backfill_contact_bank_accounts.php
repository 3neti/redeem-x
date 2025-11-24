<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use LBHurtado\Contact\Models\Contact;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backfill null bank_account values for existing contacts.
     * This ensures all contacts have a valid bank_account string,
     * preventing errors in ContactData DTO transformation.
     */
    public function up(): void
    {
        // Find contacts with null or empty bank_account
        Contact::whereNull('bank_account')
            ->orWhere('bank_account', '')
            ->chunk(100, function ($contacts) {
                foreach ($contacts as $contact) {
                    $defaultCode = config('contact.default.bank_code', 'DFLT');
                    $contact->bank_account = "{$defaultCode}:{$contact->mobile}";
                    $contact->save();
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * No down migration needed - we don't want to revert to null values.
     */
    public function down(): void
    {
        // No rollback needed
    }
};
