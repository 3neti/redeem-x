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
     * Get allowed settlement rails for a bank.
     * 
     * Checks EMI restrictions config first (override), then falls back to banks.json.
     * This prevents invalid rail selections for EMIs that only support INSTAPAY.
     * 
     * @param string $swiftBic Bank SWIFT/BIC code
     * @return array List of allowed rail names (e.g., ['INSTAPAY'])
     */
    public function getAllowedRails(string $swiftBic): array
    {
        // Check EMI restrictions config first (takes precedence)
        $restrictions = config('bank-restrictions.emi_restrictions', []);
        if (isset($restrictions[$swiftBic])) {
            return $restrictions[$swiftBic]['allowed_rails'];
        }
        
        // Fallback: Return all rails from banks.json
        $supportedRails = $this->supportedSettlementRails($swiftBic);
        return array_keys($supportedRails);
    }
    
    /**
     * Check if bank supports a specific settlement rail.
     * 
     * Now uses getAllowedRails() which respects EMI restrictions.
     * 
     * @param string $swiftBic Bank SWIFT/BIC code
     * @param SettlementRail $rail Settlement rail to check
     * @return bool True if bank supports the rail
     */
    public function supportsRail(string $swiftBic, SettlementRail $rail): bool
    {
        return in_array($rail->value, $this->getAllowedRails($swiftBic), true);
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

    /**
     * Get human-readable bank name
     */
    public function getBankName(string $swiftBic): string
    {
        $bank = $this->find($swiftBic);
        
        if ($bank && isset($bank['name'])) {
            return $bank['name'];
        }
        
        // Fallback map for known banks
        $knownBanks = [
            'GXCHPHM2XXX' => 'GCash',
            'PYMYPHM2XXX' => 'PayMaya',
            'MBTCPHM2XXX' => 'Metrobank',
            'BPIAPHM2XXX' => 'BPI',
            'BNORPHM2XXX' => 'BDO',
            'UBPHPHMM XXX' => 'UnionBank',
            'SECBPHM2XXX' => 'Security Bank',
            'RCBCPHM2XXX' => 'RCBC',
            'PNBMPHM2XXX' => 'PNB',
            'LBPAPHM2XXX' => 'Landbank',
        ];
        
        return $knownBanks[$swiftBic] ?? $swiftBic;
    }
    
    /**
     * Get bank logo path
     */
    public function getBankLogo(string $swiftBic): ?string
    {
        // Map of bank codes to logo paths
        $logos = [
            'GXCHPHM2XXX' => '/images/banks/gcash.svg',
            'PYMYPHM2XXX' => '/images/banks/paymaya.svg',
            'MBTCPHM2XXX' => '/images/banks/metrobank.svg',
            'BPIAPHM2XXX' => '/images/banks/bpi.svg',
            'BNORPHM2XXX' => '/images/banks/bdo.svg',
        ];
        
        return $logos[$swiftBic] ?? null;
    }

    public function toCollection(): Collection
    {
        return collect($this->banks);
    }
}
