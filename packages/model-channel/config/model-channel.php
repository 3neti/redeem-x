<?php

return [
    'rules' => [
        'mobile' => [
            'required',
            'phone:PH,mobile', // String format instead of object for serialization
        ],
        'webhook' => ['required', 'url'],
        'telegram' => ['required', 'string', 'regex:/^-?\d+$/'], // Chat ID (can be negative for groups)
        'whatsapp' => ['required', 'string'],
        'viber' => ['required', 'string'],
    ],
];
