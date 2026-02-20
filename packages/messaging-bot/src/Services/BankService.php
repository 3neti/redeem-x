<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Services;

use Illuminate\Support\Facades\Cache;

class BankService
{
    // Default bank for mobile-based redemption
    public const DEFAULT_BANK_CODE = 'GXCHPHM2XXX';
    public const DEFAULT_BANK_NAME = 'GCash';

    // Common aliases for fuzzy matching
    protected static array $aliases = [
        // E-wallets
        'gcash' => 'GXCHPHM2XXX',
        'g-cash' => 'GXCHPHM2XXX',
        'g cash' => 'GXCHPHM2XXX',
        'maya' => 'PAPHPHM1XXX',
        'paymaya' => 'PAPHPHM1XXX',
        'pay maya' => 'PAPHPHM1XXX',
        'grab' => 'GHPESGSGXXX',
        'grabpay' => 'GHPESGSGXXX',
        'grab pay' => 'GHPESGSGXXX',
        'shopee' => 'SHPHPHM2XXX',
        'shopeepay' => 'SHPHPHM2XXX',
        'shopee pay' => 'SHPHPHM2XXX',
        'coins' => 'DCPHPHM1XXX',
        'coins.ph' => 'DCPHPHM1XXX',
        'coinsph' => 'DCPHPHM1XXX',
        
        // Major banks - short names
        'bpi' => 'BOPIPHMMXXX',
        'bdo' => 'BNORPHMMXXX',
        'metrobank' => 'MBTCPHMMXXX',
        'metro bank' => 'MBTCPHMMXXX',
        'unionbank' => 'UBPHPHMMXXX',
        'union bank' => 'UBPHPHMMXXX',
        'rcbc' => 'RCBCPHMMXXX',
        'landbank' => 'TLBPPHMMXXX',
        'land bank' => 'TLBPPHMMXXX',
        'pnb' => 'PNBMPHMMTOD',
        'chinabank' => 'CHBKPHMMXXX',
        'china bank' => 'CHBKPHMMXXX',
        'security bank' => 'SETCPHMMXXX',
        'securitybank' => 'SETCPHMMXXX',
        'eastwest' => 'EWBCPHMMXXX',
        'east west' => 'EWBCPHMMXXX',
        'cimb' => 'CIPHPHMMXXX',
        
        // Digital banks
        'gotyme' => 'GOTYPHM2XXX',
        'go tyme' => 'GOTYPHM2XXX',
        'tonik' => 'TDBIPHM2XXX',
        'seabank' => 'LAUIPHM2XXX',
        'sea bank' => 'LAUIPHM2XXX',
        'unobank' => 'UNOBPHM2XXX',
        'uno bank' => 'UNOBPHM2XXX',
        'komo' => 'EAWRPHM2XXX',
        
        // Other common
        'palawan' => 'PPSFPHM2XXX',
        'palawanpay' => 'PPSFPHM2XXX',
        'cebuana' => 'CELRPHM1XXX',
    ];

    protected array $banks;

    public function __construct()
    {
        $this->banks = $this->loadBanks();
    }

    /**
     * Load banks from JSON file.
     */
    protected function loadBanks(): array
    {
        return Cache::remember('banks_data', 3600, function () {
            $path = resource_path('documents/banks.json');
            
            if (! file_exists($path)) {
                return [];
            }
            
            $data = json_decode(file_get_contents($path), true);
            
            return $data['banks'] ?? [];
        });
    }

    /**
     * Get the default bank.
     */
    public function getDefault(): array
    {
        return [
            'code' => self::DEFAULT_BANK_CODE,
            'name' => self::DEFAULT_BANK_NAME,
            'full_name' => $this->banks[self::DEFAULT_BANK_CODE]['full_name'] ?? 'G-Xchange / GCash',
        ];
    }

    /**
     * Get bank by SWIFT/BIC code.
     */
    public function getByCode(string $code): ?array
    {
        $code = strtoupper($code);
        
        if (! isset($this->banks[$code])) {
            return null;
        }
        
        $bank = $this->banks[$code];
        
        return [
            'code' => $code,
            'name' => $this->getShortName($bank['full_name']),
            'full_name' => $bank['full_name'],
            'rails' => array_keys($bank['settlement_rail'] ?? []),
        ];
    }

    /**
     * Fuzzy match bank by name.
     * Returns array of matches sorted by relevance.
     */
    public function fuzzyMatch(string $query, int $limit = 5): array
    {
        $query = strtolower(trim($query));
        
        if (empty($query)) {
            return [];
        }
        
        // Check aliases first (exact match)
        if (isset(self::$aliases[$query])) {
            $code = self::$aliases[$query];
            $bank = $this->getByCode($code);
            return $bank ? [$bank] : [];
        }
        
        // Check if it's a SWIFT code
        if (preg_match('/^[A-Z]{4}PHM?\d?XXX$/i', $query)) {
            $bank = $this->getByCode($query);
            return $bank ? [$bank] : [];
        }
        
        // Fuzzy search through banks
        $matches = [];
        
        foreach ($this->banks as $code => $bank) {
            $fullName = strtolower($bank['full_name']);
            $shortName = strtolower($this->getShortName($bank['full_name']));
            
            // Check alias keys that map to this bank
            $aliasMatch = false;
            foreach (self::$aliases as $alias => $aliasCode) {
                if ($aliasCode === $code && str_contains($alias, $query)) {
                    $aliasMatch = true;
                    break;
                }
            }
            
            // Calculate relevance score
            $score = 0;
            
            // Exact alias match
            if ($aliasMatch) {
                $score = 100;
            }
            // Full name starts with query
            elseif (str_starts_with($fullName, $query)) {
                $score = 90;
            }
            // Short name starts with query
            elseif (str_starts_with($shortName, $query)) {
                $score = 85;
            }
            // Full name contains query
            elseif (str_contains($fullName, $query)) {
                $score = 70;
            }
            // Short name contains query
            elseif (str_contains($shortName, $query)) {
                $score = 65;
            }
            // Word boundary match
            elseif (preg_match('/\b' . preg_quote($query, '/') . '/i', $fullName)) {
                $score = 60;
            }
            
            if ($score > 0) {
                $matches[] = [
                    'code' => $code,
                    'name' => $this->getShortName($bank['full_name']),
                    'full_name' => $bank['full_name'],
                    'rails' => array_keys($bank['settlement_rail'] ?? []),
                    'score' => $score,
                ];
            }
        }
        
        // Sort by score (descending) and limit
        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);
        
        return array_slice($matches, 0, $limit);
    }

    /**
     * Extract short name from full bank name.
     */
    protected function getShortName(string $fullName): string
    {
        // Handle special cases
        $shortcuts = [
            'G-Xchange / GCash' => 'GCash',
            'PayMaya Philippines Inc' => 'Maya',
            'GrabPay Philippines' => 'GrabPay',
            'ShopeePay Philippines Inc' => 'ShopeePay',
            'DCPay / COINS.PH' => 'Coins.ph',
            'Bank of the Philippine Islands (BPI)' => 'BPI',
            'Banco de Oro Unibank Inc (BDO)' => 'BDO',
            'Metrobank' => 'Metrobank',
            'Unionbank of the Philippines' => 'UnionBank',
            'LAND BANK OF THE PHILIPPINES' => 'LandBank',
            'Security Bank Corporation' => 'Security Bank',
            'East West Banking Corporation' => 'EastWest',
            'PHILIPPINE NATIONAL BANK' => 'PNB',
            'China Banking Corporation' => 'China Bank',
            'CIMB BANK PHILIPPINES INC' => 'CIMB',
            'GoTyme Bank' => 'GoTyme',
            'Tonik Bank' => 'Tonik',
            'Seabank Philippines, Inc.' => 'SeaBank',
            'UNObank' => 'UNObank',
            'East West Rural Bank / Komo' => 'Komo',
            'PalawanPay' => 'PalawanPay',
            'Cebuana Lhuillier Rural Bank' => 'Cebuana',
            'Maya Bank, Inc.' => 'Maya Bank',
        ];
        
        foreach ($shortcuts as $full => $short) {
            if (stripos($fullName, $full) !== false || strtolower($fullName) === strtolower($full)) {
                return $short;
            }
        }
        
        // Extract acronym in parentheses
        if (preg_match('/\(([A-Z]+)\)/', $fullName, $matches)) {
            return $matches[1];
        }
        
        // Handle "RURAL BANK OF X" pattern - extract the location
        if (preg_match('/RURAL BANK OF ([A-Z][A-Za-z]+)/i', $fullName, $matches)) {
            return 'RB ' . ucfirst(strtolower($matches[1]));
        }
        
        // Handle "X RURAL BANK" pattern
        if (preg_match('/^([A-Z][A-Za-z]+)\s+RURAL BANK/i', $fullName, $matches)) {
            return $matches[1] . ' RB';
        }
        
        // Handle "X BANK" pattern - take the name before BANK
        if (preg_match('/^([A-Z][A-Za-z]+(?:\s+[A-Z][A-Za-z]+)?)\s+BANK/i', $fullName, $matches)) {
            $name = $matches[1];
            // Truncate if too long
            if (strlen($name) > 15) {
                $name = substr($name, 0, 12) . '...';
            }
            return $name;
        }
        
        // Handle "X / Y" pattern - take Y (the common name)
        if (preg_match('/\/\s*([A-Za-z]+)/', $fullName, $matches)) {
            return ucfirst(strtolower($matches[1]));
        }
        
        // Default: take first two meaningful words (skip articles)
        $parts = preg_split('/\s+/', $fullName);
        $skip = ['THE', 'OF', 'AND', 'INC', 'INC.', 'CORPORATION', 'CORP', 'CORP.', 'LTD', 'LTD.'];
        $words = [];
        foreach ($parts as $part) {
            if (! in_array(strtoupper($part), $skip) && strlen($part) > 2) {
                $words[] = ucfirst(strtolower($part));
                if (count($words) >= 2) {
                    break;
                }
            }
        }
        
        return implode(' ', $words) ?: $fullName;
    }

    /**
     * Check if a bank supports INSTAPAY (for EMIs this is required).
     */
    public function supportsInstapay(string $code): bool
    {
        $code = strtoupper($code);
        
        if (! isset($this->banks[$code])) {
            return false;
        }
        
        return isset($this->banks[$code]['settlement_rail']['INSTAPAY']);
    }

    /**
     * Format bank display for confirmation message.
     */
    public function formatForDisplay(string $code, string $account): string
    {
        $bank = $this->getByCode($code);
        $name = $bank ? $bank['name'] : 'Unknown Bank';
        
        return "{$name}:{$account}";
    }

    /**
     * Get popular banks for quick selection.
     */
    public function getPopular(): array
    {
        $popularCodes = [
            'GXCHPHM2XXX',  // GCash
            'PAPHPHM1XXX',  // Maya
            'BOPIPHMMXXX',  // BPI
            'BNORPHMMXXX',  // BDO
            'MBTCPHMMXXX',  // Metrobank
            'UBPHPHMMXXX',  // UnionBank
            'LAUIPHM2XXX',  // SeaBank
            'GOTYPHM2XXX',  // GoTyme
        ];
        
        $popular = [];
        foreach ($popularCodes as $code) {
            $bank = $this->getByCode($code);
            if ($bank) {
                $popular[] = $bank;
            }
        }
        
        return $popular;
    }
}
