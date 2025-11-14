<?php

namespace LBHurtado\PaymentGateway\Support;

use LBHurtado\PaymentGateway\Enums\SettlementRail;
use Illuminate\Support\Collection;

class BankRegistry
{
    protected array $banks;

    public function __construct()
    {
        $path = documents_path('banks.json'); // Use the helper here

        if (!file_exists($path)) {
            throw new \RuntimeException("Bank directory file not found at: {$path}");
        }

        $data = json_decode(file_get_contents($path), true);

        if (!isset($data['banks']) || !is_array($data['banks'])) {
            throw new \UnexpectedValueException("Invalid format in banks.json. Expected 'banks' root key.");
        }

        $this->banks = $data['banks'];
    }


    public function all(): array
    {
        return $this->banks;
    }

    public function find(string $swiftBic): ?array
    {
        return $this->banks[$swiftBic] ?? null;
    }

    public function supportedSettlementRails(string $swiftBic): array
    {
        return $this->banks[$swiftBic]['settlement_rail'] ?? [];
    }
    
    /**
     * Check if bank supports a specific settlement rail
     */
    public function supportsRail(string $swiftBic, SettlementRail $rail): bool
    {
        $supportedRails = $this->supportedSettlementRails($swiftBic);
        return isset($supportedRails[$rail->value]);
    }
    
    /**
     * Get all banks supporting a specific rail
     */
    public function byRail(SettlementRail $rail): Collection
    {
        return collect($this->banks)->filter(function ($bank) use ($rail) {
            return isset($bank['settlement_rail'][$rail->value]);
        });
    }
    
    /**
     * Get all EMIs (electronic money issuers)
     * Identified by specific SWIFT code patterns
     */
    public function getEMIs(): Collection
    {
        return collect($this->banks)->filter(function ($bank, $code) {
            // EMIs often have specific patterns or are in a known list
            $emiPatterns = ['GXCH', 'PAPH', 'DCPH', 'GHPE', 'SHPH', 'TAGC'];
            
            foreach ($emiPatterns as $pattern) {
                if (str_starts_with($code, $pattern)) {
                    return true;
                }
            }
            
            return false;
        });
    }
    
    /**
     * Check if SWIFT code is an EMI
     */
    public function isEMI(string $swiftBic): bool
    {
        return $this->getEMIs()->has($swiftBic);
    }

    public function toCollection(): Collection
    {
        return collect($this->banks);
    }
}
