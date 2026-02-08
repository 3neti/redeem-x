<?php

declare(strict_types=1);

namespace App\Actions\Api\Redemption;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Validate redemption code and return voucher details.
 *
 * Endpoint: POST /api/v1/redemption/validate
 */
class ValidateRedemptionCode
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $code = strtoupper(trim($request->input('code')));

        // Find voucher
        $voucher = Voucher::where('code', $code)->first();

        if (! $voucher) {
            return ApiResponse::error('Invalid voucher code.', 404);
        }

        // Load voucher data
        $voucherData = VoucherData::fromModel($voucher);

        // Extract required validation and inputs
        $requiredValidation = [];
        $requiredInputs = [];

        try {
            $instructions = $voucher->instructions;

            // Cash validation requirements
            if ($instructions->cash->validation) {
                $validation = $instructions->cash->validation;
                if ($validation->secret) {
                    $requiredValidation['secret'] = true;
                }
                if ($validation->mobile) {
                    $requiredValidation['mobile'] = $validation->mobile;
                }
                if ($validation->location) {
                    $requiredValidation['location'] = [
                        'lat_lng' => $validation->location,
                        'radius' => $validation->radius ?? '100m',
                    ];
                }
            }

            // Input fields required
            if ($instructions->inputs && $instructions->inputs->fields) {
                $requiredInputs = $instructions->inputs->fields;
            }
        } catch (\Exception $e) {
            // Instructions might not exist
        }

        return ApiResponse::success([
            'voucher' => $voucherData,
            'can_redeem' => $voucherData->can_redeem,
            'required_validation' => $requiredValidation,
            'required_inputs' => $requiredInputs,
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:4'],
        ];
    }
}
