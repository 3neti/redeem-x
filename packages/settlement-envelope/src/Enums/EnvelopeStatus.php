<?php

namespace LBHurtado\SettlementEnvelope\Enums;

enum EnvelopeStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case LOCKED = 'locked';
    case SETTLED = 'settled';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::LOCKED => 'Locked',
            self::SETTLED => 'Settled',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT, self::ACTIVE]);
    }

    public function canSettle(): bool
    {
        return $this === self::LOCKED;
    }
}
