<?php

declare(strict_types=1);

namespace App\Actions\Api\BankAccounts;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use LBHurtado\MoneyIssuer\Support\BankRegistry;

/**
 * Update a bank account for the authenticated user.
 *
 * Endpoint: PUT /api/v1/user/bank-accounts/{id}
 */
class UpdateBankAccount
{
    use AsAction;

    public function asController(ActionRequest $request, string $id): JsonResponse
    {
        $validated = $request->validated();
        
        $user = $request->user();
        
        // Get existing account
        $account = $user->getBankAccountById($id);
        
        if (!$account) {
            return ApiResponse::notFound('Bank account not found.');
        }
        
        // Remove and re-add with updated data
        $user->removeBankAccount($id);
        
        $updatedAccount = $user->addBankAccount(
            bankCode: $validated['bank_code'] ?? $account['bank_code'],
            accountNumber: $validated['account_number'] ?? $account['account_number'],
            label: $validated['label'] ?? $account['label'],
            isDefault: $validated['is_default'] ?? $account['is_default']
        );

        return ApiResponse::success([
            'message' => 'Bank account updated successfully.',
            'bank_account' => $updatedAccount,
        ]);
    }

    public function rules(): array
    {
        return [
            'bank_code' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $registry = app(BankRegistry::class);
                        $bankInfo = $registry->find($value);
                        if (!$bankInfo) {
                            $fail("Invalid bank code. Please provide a valid BIC/SWIFT code.");
                        }
                    }
                },
            ],
            'account_number' => [
                'nullable',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $bankCode = request('bank_code');
                        if ($bankCode) {
                            $registry = app(BankRegistry::class);
                            $bankInfo = $registry->find($bankCode);
                            
                            if ($bankInfo && $registry->isEMI($bankCode)) {
                                if (!preg_match('/^(09|\+639|639)\d{9}$/', $value)) {
                                    $bankName = $registry->getBankName($bankCode);
                                    $fail("Invalid mobile number format for {$bankName}. Use 09XXXXXXXXX format.");
                                }
                            }
                        }
                    }
                },
            ],
            'label' => ['nullable', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
