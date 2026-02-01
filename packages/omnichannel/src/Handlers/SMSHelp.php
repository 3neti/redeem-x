<?php

namespace LBHurtado\OmniChannel\Handlers;

use App\Models\User;
use App\Notifications\HelpNotification;
use Illuminate\Http\JsonResponse;

class SMSHelp extends BaseSMSHandler
{
    /**
     * Handle HELP SMS command.
     *
     * Provides comprehensive syntax guide for all available SMS commands.
     * Requires authentication - user must be registered.
     *
     * Syntax:
     *   HELP           - General help (all commands)
     *   HELP {command} - Command-specific help
     */
    protected function handle(?User $user, array $values, string $from, string $to): JsonResponse
    {
        // Get command-specific help if requested
        $command = $values['command'] ?? null;

        if ($command) {
            return $this->getCommandHelp($user, $command);
        }

        // Return general help
        return $this->getGeneralHelp($user);
    }

    /**
     * Get general help message with all commands.
     */
    protected function getGeneralHelp(User $user): JsonResponse
    {
        $message = <<<'MSG'
Commands:
BALANCE
GENERATE amt
PAYABLE amt
SETTLEMENT amt target

Examples:
GENERATE 500
GENERATE --campaign="Name"
GENERATE 100 --inputs=loc,sel

Flags:
--campaign="Name"
--inputs=loc,sig,sel,kyc
--count=5 --ttl=30

Aliases:
loc=location sig=signature
sel=selfie kyc=identity

HELP [cmd] for details
MSG;

        $this->logInfo('General help sent');

        // Send notification (SMS)
        $this->sendNotification($user, new HelpNotification($message));

        return response()->json(['message' => $message]);
    }

    /**
     * Get command-specific help.
     */
    protected function getCommandHelp(User $user, string $command): JsonResponse
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
            // Send notification (SMS)
            $this->sendNotification($user, new HelpNotification($helpText));
            
            return response()->json(['message' => $helpText]);
        }

        // Command not found - return general help
        return $this->getGeneralHelp($user);
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
