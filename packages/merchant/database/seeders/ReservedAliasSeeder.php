<?php

namespace LBHurtado\Merchant\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReservedAliasSeeder extends Seeder
{
    /**
     * Seed reserved vendor aliases.
     */
    public function run(): void
    {
        $reserved = [
            // System / Admin
            'ADMIN', 'ROOT', 'SYSTEM', 'NULL', 'DEFAULT',

            // Government
            'GOV', 'GOVT', 'DOF', 'DBM', 'DICT', 'DTI', 'DOH',

            // Banks
            'BSP', 'BDO', 'BPI', 'LANDBANK', 'RCBC', 'METRO',
            'PNB', 'UCPB', 'SECURITY', 'UNION',

            // EMIs / Fintech
            'GCASH', 'MAYA', 'PAYMAYA', 'COINS', 'GRAB',
            'SHOPEEP', 'LAZADA',
        ];

        foreach ($reserved as $alias) {
            DB::table('reserved_vendor_aliases')->insert([
                'alias' => $alias,
                'reason' => 'Protected system/institution name',
                'reserved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
