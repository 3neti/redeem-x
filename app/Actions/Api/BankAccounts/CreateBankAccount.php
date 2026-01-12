<?php

declare(strict_types=1);

namespace App\Actions\Api\BankAccounts;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\MoneyIssuer\Support\BankRegistry;

/**
 * Create a new bank account for the authenticated user.
 *
 * Endpoint: POST /api/v1/user/bank-accounts
 */
class CreateBankAccount
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $user = $request->user();
        
        $account = $user->addBankAccount(
            bankCode: $validated['bank_code'],
            accountNumber: $validated['account_number'],
            label: $validated['label'] ?? null,
            isDefault: $validated['is_default'] ?? false
        );

        return ApiResponse::created([
            'message' => 'Bank account added successfully.',
            'bank_account' => $account,
        ]);
    }

    public function rules(): array
    {
        return [
            'bank_code' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Validate bank code using BankRegistry
                    $registry = app(BankRegistry::class);
                    $bankInfo = $registry->find($value);
                    if (!$bankInfo) {
                        $fail("Invalid bank code. Please provide a valid BIC/SWIFT code.");
                    }
                },
            ],
            'account_number' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    // For EMI (GCash, PayMaya), validate mobile format
                    $bankCode = request('bank_code');
                    if ($bankCode) {
                        $registry = app(BankRegistry::class);
                        $bankInfo = $registry->find($bankCode);
                        
                        if ($bankInfo && $registry->isEMI($bankCode)) {
                            // Validate Philippine mobile number format
                            if (!preg_match('/^(09|\+639|639)\d{9}$/', $value)) {
                                $bankName = $registry->getBankName($bankCode);
                                $fail("Invalid mobile number format for {$bankName}. Use 09XXXXXXXXX format.");
                            }
                        }
                    }
                },
            ],
            'label' => ['nullable', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_code.required' => 'Bank code is required.',
            'account_number.required' => 'Account number is required.',
            'account_number.max' => 'Account number must not exceed 50 characters.',
            'label.max' => 'Label must not exceed 100 characters.',
        ];
    }
}
