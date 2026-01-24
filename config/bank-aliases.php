<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Friendly Bank Code Aliases
    |--------------------------------------------------------------------------
    |
    | Map user-friendly bank codes (e.g., "GCASH", "BDO") to SWIFT/BIC codes
    | for SMS redemption and other messaging channels.
    |
    | Users can send: "ABCD GCASH" instead of "ABCD GXCHPHM2XXX"
    |
    */

    // E-Money Issuers (EMI)
    'GCASH' => 'GXCHPHM2XXX',
    'MAYA' => 'PYMYPHM2XXX',
    'GRABPAY' => 'GHPEPHM1XXX',
    'SHOPEEPAY' => 'SHPHPHM1XXX',

    // Major Banks
    'BDO' => 'BNORPHMMXXX',  // BDO Unibank Network
    'BPI' => 'BOPIPHM2XXX',
    'UNIONBANK' => 'UBPHPHM2XXX',
    'METROBANK' => 'MBTCPHM2XXX',
    'LANDBANK' => 'TLBKPHM2XXX',
    'RCBC' => 'RCBCPHM2XXX',
    'SECURITYBANK' => 'SETCPHM2XXX',
    'PNB' => 'PNBMPHM2XXX',
    'CHINABANK' => 'CHBKPHM2XXX',
    'EASTWEST' => 'EWBCPHM2XXX',
];
