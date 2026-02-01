<?php

namespace App\SMS\Handlers;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Str;
use LBHurtado\OmniChannel\Contracts\SMSHandlerInterface;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;

/**
 * Base handler for SMS voucher generation commands.
 * 
 * Provides common functionality for parsing command-line style SMS commands
 * using Symfony Console's StringInput parser.
 */
abstract class BaseSMSVoucherHandler implements SMSHandlerInterface
{
    /**
     * Parse an SMS command using Symfony Console's StringInput.
     * 
     * This handles quotes, escaping, and all edge cases automatically.
     * Supports both --flag=value and --flag value syntax.
     *
     * @param string $commandText The full SMS text (e.g., "REDEEMABLE 100 --count=3")
     * @param InputDefinition $definition The input definition with arguments and options
     * @return array ['arguments' => [...], 'options' => [...]]
     */
    protected function parseCommand(string $commandText, InputDefinition $definition): array
    {
        // Use Symfony Console's StringInput for robust parsing
        // Handles quotes, escaping, and all edge cases automatically
        $input = new StringInput($commandText);
        $input->bind($definition);
        
        return [
            'arguments' => $input->getArguments(),
            'options' => $input->getOptions(),
        ];
    }
    
    /**
     * Get a campaign by name or slug for the authenticated user.
     *
     * @param User $user The authenticated user
     * @param string $campaignName Campaign name or slug
     * @return Campaign|null
     */
    protected function getCampaign(User $user, string $campaignName): ?Campaign
    {
        return Campaign::where('user_id', $user->id)
            ->where(function ($query) use ($campaignName) {
                $query->where('name', $campaignName)
                      ->orWhere('slug', Str::slug($campaignName));
            })
            ->first();
    }
    
    /**
     * Get the input definition for this command.
     * 
     * Defines the arguments and options that this handler accepts.
     *
     * @return InputDefinition
     */
    abstract protected function getInputDefinition(): InputDefinition;
    
    /**
     * Build voucher instructions from parsed arguments and options.
     *
     * @param array $arguments Parsed command arguments
     * @param array $options Parsed command options
     * @param Campaign|null $campaign Campaign to merge instructions from (optional)
     * @return VoucherInstructionsData
     */
    abstract protected function buildInstructions(array $arguments, array $options, ?Campaign $campaign): VoucherInstructionsData;
    
    /**
     * Parse comma-separated input fields with alias support.
     *
     * @param string $inputsString Comma-separated field names (e.g., "location,selfie" or "loc,sel")
     * @return array Array of valid VoucherInputField values
     */
    protected function parseInputFields(string $inputsString): array
    {
        // Alias mapping for convenience
        $aliases = [
            'loc' => 'location',
            'sig' => 'signature',
            'sel' => 'selfie',
            'ref' => 'reference_code',
            'addr' => 'address',
            'birth' => 'birth_date',
            'income' => 'gross_monthly_income',
        ];
        
        // Parse and normalize
        $fields = array_map('trim', explode(',', strtolower($inputsString)));
        $resolved = array_map(fn($f) => $aliases[$f] ?? $f, $fields);
        
        // Validate against VoucherInputField enum
        $validFields = \LBHurtado\Voucher\Enums\VoucherInputField::values();
        $valid = array_filter($resolved, fn($f) => in_array($f, $validFields));
        
        return array_values($valid);
    }
    
    /**
     * Normalize input fields array - converts enum objects to string values.
     *
     * @param array $fields Array of input fields (may contain VoucherInputField enums or strings)
     * @return array Array of string values
     */
    protected function normalizeInputFields(array $fields): array
    {
        return array_map(
            fn($field) => $field instanceof \LBHurtado\Voucher\Enums\VoucherInputField 
                ? $field->value 
                : $field,
            $fields
        );
    }
}
