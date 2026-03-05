<?php

return [

    'renderer' => env('OG_META_RENDERER', 'screenshot'),

    'resolvers' => [
        'disburse' => \App\OgResolvers\VoucherOgResolver::class,
        'pay' => \App\OgResolvers\PayVoucherOgResolver::class,
    ],

];
