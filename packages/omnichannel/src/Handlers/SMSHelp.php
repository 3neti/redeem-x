<?php

namespace LBHurtado\OmniChannel\Handlers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use LBHurtado\OmniChannel\Contracts\SMSHandlerInterface;

class SMSHelp implements SMSHandlerInterface
{
    /**
     * Handle HELP SMS command.
     *
     * Provides comprehensive syntax guide for all available SMS commands.
     * Requires authentication - user must be registered.
     *
     * Syntax:
     *   HELP           - General help (all commands)
     *   HELP {command} - Command-specific help (future)
     */
    public function __invoke(array $values, string $from, string $to): JsonResponse
    {
        Log::info('[SMSHelp] Processing HELP command', [
            'from' => $from,
            'to' => $to,
            'values' => $values,
        ]);

        // User already authenticated by middleware
        $user = request()->user();

        if (!$user) {
            Log::warning('[SMSHelp] Unauthenticated help request', ['mobile' => $from]);
            return response()->json([
                'message' => 'No account found. Send REGISTER to create one.',
            ]);
        }

        // Get command-specific help if requested (future enhancement)
        $command = $values['command'] ?? null;

        if ($command) {
            return $this->getCommandHelp($command);
        }

        // Return general help
        return $this->getGeneralHelp();
    }

    /**
     * Get general help message with all commands.
     */
    protected function getGeneralHelp(): JsonResponse
    {
        $message = <<<'HELP'
SMS Commands:

💰 BALANCE
Check wallet balance
BALANCE
BALANCE --system (admin)

🎫 GENERATE amount
Create voucher
GENERATE 500
GENERATE --campaign="Name"
GENERATE 100 --inputs=loc,sel --count=3

📋 PAYABLE amount
Create invoice
PAYABLE 2000

🏦 SETTLEMENT amount target
Create loan
SETTLEMENT 5000 10000

Common flags:
--campaign="Name" (template)
--inputs=loc,sig,sel,kyc
--count=5 --ttl=30

Input aliases:
loc=location sig=signature
sel=selfie kyc=identity

Reply HELP {command} for details
HELP;

        Log::info('[SMSHelp] General help sent', ['user_found' => true]);

        return response()->json(['message' => $message]);
    }

    /**
     * Get command-specific help (future enhancement).
     */
    protected function getCommandHelp(string $command): JsonResponse
    {
        $command = strtoupper(trim($command));

        $helpText = match ($command) {
            'GENERATE', 'REDEEMABLE' => $this->getGenerateHelp(),
            'BALANCE' => $this->getBalanceHelp(),
            'PAYABLE' => $this->getPayableHelp(),
            'SETTLEMENT' => $this->getSettlementHelp(),
            default => null,
        };

        if ($helpText) {
            return response()->json(['message' => $helpText]);
        }

        // Command not found - return general help
        return $this->getGeneralHelp();
    }

    /**
     * GENERATE command help.
     */
    protected function getGenerateHelp(): string
    {
        return <<<'HELP'
GENERATE - Create redeemable voucher

Syntax:
GENERATE amount [flags]
GENERATE --campaign="Name"

Examples:
GENERATE 500
GENERATE 1000 --count=5
GENERATE --campaign="Petty Cash"
GENERATE 100 --inputs=loc,sel --ttl=7

Flags:
--campaign="Name" - Use template
--count=5 - Number of vouchers
--inputs=loc,sig,sel - Required fields
--ttl=30 - Expiry in days
--prefix=PROMO - Code prefix
--settlement-rail=INSTAPAY

Input fields:
loc sig sel kyc email mobile
name addr birth income ref
HELP;
    }

    /**
     * BALANCE command help.
     */
    protected function getBalanceHelp(): string
    {
        return <<<'HELP'
BALANCE - Check wallet balance

Syntax:
BALANCE          - Your balance
BALANCE --system - System balance (admin)

Examples:
BALANCE
Response: Balance: ₱5,000.00

BALANCE --system (requires admin)
Response: Wallet: ₱1M | Products: ₱500K
HELP;
    }

    /**
     * PAYABLE command help.
     */
    protected function getPayableHelp(): string
    {
        return <<<'HELP'
PAYABLE - Create payable voucher (invoice)

Syntax:
PAYABLE amount [flags]
PAYABLE --campaign="Name"

Examples:
PAYABLE 2000
PAYABLE 5000 --count=3
PAYABLE --campaign="Invoice"

Flags: Same as GENERATE
--campaign --inputs --count
--ttl --prefix

Payable vouchers start at ₱0
and accept payments up to target amount.
HELP;
    }

    /**
     * SETTLEMENT command help.
     */
    protected function getSettlementHelp(): string
    {
        return <<<'HELP'
SETTLEMENT - Create settlement voucher (loan)

Syntax:
SETTLEMENT amount target [flags]
SETTLEMENT --campaign="Name"

Examples:
SETTLEMENT 5000 10000
SETTLEMENT 3000 5000 --inputs=kyc,loc
SETTLEMENT --campaign="Loan"

amount = Initial disbursement
target = Total to be repaid

Flags: Same as GENERATE
--campaign --inputs --count --ttl

Settlement vouchers disburse 'amount'
then accept payments up to 'target'.
HELP;
    }
}
