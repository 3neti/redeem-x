<?php

namespace App\Services;

use App\Actions\Voucher\ProcessRedemption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Exceptions\RedemptionException;
use LBHurtado\Voucher\Guards\RedemptionGuard;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Specifications\InputsSpecification;
use LBHurtado\Voucher\Specifications\KycSpecification;
use LBHurtado\Voucher\Specifications\LocationSpecification;
use LBHurtado\Voucher\Specifications\MobileSpecification;
use LBHurtado\Voucher\Specifications\PayableSpecification;
use LBHurtado\Voucher\Specifications\SecretSpecification;
use LBHurtado\Voucher\Specifications\TimeLimitSpecification;
use LBHurtado\Voucher\Specifications\TimeWindowSpecification;
use Propaganistas\LaravelPhone\PhoneNumber;

class VoucherRedemptionService
{
    private RedemptionGuard $guard;

    private InputsSpecification $inputsSpec;

    public function __construct()
    {
        // Initialize specifications
        $this->inputsSpec = new InputsSpecification;

        // Initialize guard with specifications
        $this->guard = new RedemptionGuard(
            new SecretSpecification,
            new MobileSpecification,
            new PayableSpecification,
            $this->inputsSpec,
            new KycSpecification,
            new LocationSpecification,
            new TimeWindowSpecification,
            new TimeLimitSpecification
        );
    }

    /**
     * Redeem a voucher with the given context.
     *
     * @param  Voucher|object  $voucher
     *
     * @throws RedemptionException
     */
    public function redeem(object $voucher, RedemptionContext $context): bool
    {
        // Validate redemption
        $this->validateRedemption($voucher, $context);

        // Process redemption (existing action)
        $action = app(ProcessRedemption::class);

        return $action->handle(
            code: $voucher->code,
            phoneNumber: PhoneNumber::make($context->mobile),
            inputs: $context->inputs,
            bankAccount: $context->bankAccount
        );
    }

    /**
     * Validate redemption using the RedemptionGuard.
     *
     * @param  Voucher|object  $voucher
     *
     * @throws RedemptionException
     */
    public function validateRedemption(object $voucher, RedemptionContext $context): void
    {
        $result = $this->guard->check($voucher, $context);

        if ($result->failed()) {
            $this->throwValidationException($result->failures, $voucher, $context);
        }
    }

    /**
     * Resolve redemption context from HTTP Request.
     */
    public function resolveContextFromRequest(Request $request): RedemptionContext
    {
        $user = Auth::user();
        $mobile = $request->input('mobile') ?? $request->input('phone') ?? $request->input('phone_number');
        $inputs = $request->input('inputs', []);

        return new RedemptionContext(
            mobile: $mobile,
            secret: $request->input('secret'),
            vendorAlias: $user?->primaryVendorAlias?->alias,
            inputs: $inputs,
            bankAccount: $request->input('bank_account', [])
        );
    }

    /**
     * Resolve redemption context from array data (API).
     */
    public function resolveContextFromArray(array $data, ?\App\Models\User $user = null): RedemptionContext
    {
        $mobile = $data['mobile'] ?? $data['phone'] ?? $data['phone_number'] ?? '';
        $inputs = $data['inputs'] ?? [];

        return new RedemptionContext(
            mobile: $mobile,
            secret: $data['secret'] ?? null,
            vendorAlias: $user?->primaryVendorAlias?->alias,
            inputs: $inputs,
            bankAccount: $data['bank_account'] ?? []
        );
    }

    /**
     * Throw appropriate validation exception based on failures.
     *
     * @throws RedemptionException
     */
    private function throwValidationException(array $failures, object $voucher, RedemptionContext $context): void
    {
        $messages = [];

        foreach ($failures as $failure) {
            $messages[] = match ($failure) {
                'secret' => 'Invalid secret code provided.',
                'mobile' => 'This voucher is restricted to a specific mobile number.',
                'payable' => sprintf(
                    'This voucher is payable to merchant "%s". Please log in with the correct merchant account.',
                    $voucher->instructions->cash->validation->payable
                ),
                'inputs' => $this->buildInputsErrorMessage($voucher, $context),
                'kyc' => 'KYC verification is required but not approved. Please complete identity verification.',
                'location' => 'Location data is required for this voucher.',
                'time_window' => 'This voucher can only be redeemed during specific time periods. Please try again later.',
                'time_limit' => 'Redemption time limit exceeded. Please start the redemption process again.',
                default => "Validation failed: {$failure}",
            };
        }

        throw new RedemptionException(implode(' ', $messages));
    }

    /**
     * Build detailed error message for missing input fields.
     */
    private function buildInputsErrorMessage(object $voucher, RedemptionContext $context): string
    {
        $missingFields = $this->inputsSpec->getMissingFields($voucher, $context);

        if (empty($missingFields)) {
            return 'Required information is missing. Please complete all required fields.';
        }

        // Format field names nicely (convert snake_case to Title Case)
        $formattedFields = array_map(
            fn ($field) => str_replace('_', ' ', ucwords($field, '_')),
            $missingFields
        );

        // Join with commas and 'and' for the last item
        if (count($formattedFields) === 1) {
            $fieldsList = $formattedFields[0];
        } else {
            $lastField = array_pop($formattedFields);
            $fieldsList = implode(', ', $formattedFields).' and '.$lastField;
        }

        return sprintf('Missing required fields: %s.', $fieldsList);
    }

    /**
     * Get KYC status for a mobile number.
     */
}
