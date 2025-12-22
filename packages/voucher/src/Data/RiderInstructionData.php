<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use Spatie\LaravelData\Data;

class RiderInstructionData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public ?string $message,
        public ?string $url,
        public ?int $redirect_timeout = null,
    ) { $this->applyRulesAndDefaults(); }

    protected function rulesAndDefaults(): array
    {
        return [
            'message' => [
                ['required', 'string', 'max:4096'],
                config('instructions.rider.message')
            ],
            'url' => [
                ['required', 'url', 'max:2048'],
                config('instructions.rider.url')
            ],
            'redirect_timeout' => [
                ['nullable', 'integer', 'min:0', 'max:300'],
                config('instructions.rider.redirect_timeout')
            ]
        ];
    }
}
