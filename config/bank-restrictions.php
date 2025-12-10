<?php

/**
 * Bank Rail Restrictions Configuration
 * 
 * This file defines restrictions on settlement rails for specific banks,
 * particularly EMIs (Electronic Money Issuers) that only support INSTAPAY.
 * 
 * This is a temporary override layer on top of banks.json to prevent
 * failed transactions while maintaining backward compatibility.
 * 
 * Context: EMIs like GCash and PayMaya only support real-time INSTAPAY transfers,
 * but banks.json shows them supporting both INSTAPAY and PESONET. This causes
 * failed transactions when PESONET is selected for EMI disbursements.
 */

return [
    /**
     * EMI Rail Restrictions
     * 
     * Lists EMIs that have limited rail support (INSTAPAY only).
     * These overrides take precedence over banks.json data.
     * 
     * Format:
     * 'SWIFT_BIC' => [
     *     'allowed_rails' => ['RAIL1', 'RAIL2'],
     *     'name' => 'Display Name',
     * ]
     */
    'emi_restrictions' => [
        'GXCHPHM2XXX' => [
            'allowed_rails' => ['INSTAPAY'],
            'name' => 'GCash',
            'reason' => 'EMI - Real-time transfers only',
        ],
        'PYMYPHM2XXX' => [
            'allowed_rails' => ['INSTAPAY'],
            'name' => 'PayMaya',
            'reason' => 'EMI - Real-time transfers only',
        ],
        'APHIPHM2XXX' => [
            'allowed_rails' => ['INSTAPAY'],
            'name' => 'Alipay / Lazada Wallet',
            'reason' => 'EMI - Real-time transfers only',
        ],
        'BFSRPHM2XXX' => [
            'allowed_rails' => ['INSTAPAY'],
            'name' => 'Banana Fintech / BananaPay',
            'reason' => 'EMI - Real-time transfers only',
        ],
        'DCPHPHM1XXX' => [
            'allowed_rails' => ['INSTAPAY'],
            'name' => 'DragonPay',
            'reason' => 'EMI - Real-time transfers only',
        ],
        'GHPEPHM1XXX' => [
            'allowed_rails' => ['INSTAPAY'],
            'name' => 'GrabPay',
            'reason' => 'EMI - Real-time transfers only',
        ],
        'SHPHPHM1XXX' => [
            'allowed_rails' => ['INSTAPAY'],
            'name' => 'ShopeePay',
            'reason' => 'EMI - Real-time transfers only',
        ],
    ],

    /**
     * Amount Limits Per Rail
     * 
     * Defines transaction amount limits for each settlement rail.
     * These are default limits; specific banks may have different limits.
     */
    'amount_limits' => [
        'default' => [
            'INSTAPAY' => [
                'min' => 1,
                'max' => 50000,
                'currency' => 'PHP',
            ],
            'PESONET' => [
                'min' => 1,
                'max' => 1000000,
                'currency' => 'PHP',
            ],
        ],
    ],

    /**
     * Future: Bank-specific overrides
     * 
     * If certain banks have different limits or restrictions,
     * they can be added here:
     * 
     * 'bank_specific' => [
     *     'BNORPHMMXXX' => [
     *         'INSTAPAY' => ['max' => 100000], // Custom BDO limit
     *     ],
     * ],
     */
];
