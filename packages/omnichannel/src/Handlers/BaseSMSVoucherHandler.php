<?php

namespace LBHurtado\OmniChannel\Handlers;

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
}
