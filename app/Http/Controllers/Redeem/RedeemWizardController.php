<?php

declare(strict_types=1);

namespace App\Http\Controllers\Redeem;

use App\Http\Controllers\Controller;
use App\Http\Requests\Redeem\{WalletFormRequest, PluginFormRequest};
use App\Support\{RedeemPluginMap, RedeemPluginSelector};
use LBHurtado\Voucher\Data\VoucherData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Log, Session};
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Redemption wizard controller.
 *
 * Handles the multi-step redemption flow:
 * 1. Collect bank account (wallet)
 * 2. Dynamic plugin-based input collection
 * 3. Finalize and review
 */
class RedeemWizardController extends Controller
{
    /**
     * Step 1: Collect bank account information.
     *
     * @param  Voucher  $voucher
     * @return Response
     */
    public function wallet(Voucher $voucher): Response
    {
        Log::info('[RedeemWizard] Showing wallet form', [
            'voucher' => $voucher->code,
        ]);

        return Inertia::render('Redeem/Wallet', [
            'voucher_code' => $voucher->code,
            'voucher' => VoucherData::fromModel($voucher),
            'country' => config('x-change.redeem.default_country', 'PH'),
            'banks' => $this->getBanksList(),
            'has_secret' => ! empty($voucher->cash?->secret),
        ]);
    }

    /**
     * Store wallet/bank account information.
     *
     * @param  WalletFormRequest  $request
     * @param  Voucher  $voucher
     * @return RedirectResponse
     */
    public function storeWallet(WalletFormRequest $request, Voucher $voucher): RedirectResponse
    {
        $voucherCode = $voucher->code;
        $validated = $request->validated();

        Log::info('[RedeemWizard] Storing wallet info', [
            'voucher' => $voucherCode,
            'mobile' => $validated['mobile'],
        ]);

        // Store in session
        Session::put("redeem.{$voucherCode}.mobile", $validated['mobile']);
        Session::put("redeem.{$voucherCode}.country", $validated['country']);
        
        // Store wallet as bank code string for backward compatibility
        if (! empty($validated['bank_code'])) {
            Session::put("redeem.{$voucherCode}.wallet", $validated['bank_code']);
            Session::put("redeem.{$voucherCode}.bank_code", $validated['bank_code']);
        }
        
        if (! empty($validated['account_number'])) {
            Session::put("redeem.{$voucherCode}.account_number", $validated['account_number']);
        }

        // Determine plugins needed for this voucher
        $plugins = RedeemPluginSelector::fromVoucher($voucher);
        Session::put("redeem.{$voucherCode}.plugins", $plugins->all());

        Log::info('[RedeemWizard] Plugins determined', [
            'voucher' => $voucherCode,
            'plugins' => $plugins->all(),
        ]);

        // Redirect to first plugin or finalize
        $firstPlugin = $plugins->first();

        if (! $firstPlugin) {
            return redirect()->route('redeem.finalize', $voucher);
        }

        return redirect()->route('redeem.plugin', [
            'voucher' => $voucher,
            'plugin' => $firstPlugin,
        ]);
    }

    /**
     * Show plugin form (dynamic).
     *
     * @param  Voucher  $voucher
     * @param  string  $plugin
     * @return Response
     */
    public function plugin(Voucher $voucher, string $plugin): Response
    {
        $voucherCode = $voucher->code;

        // Get plugin configuration
        $pluginConfig = RedeemPluginMap::configFor($plugin);

        if (! $pluginConfig) {
            abort(404, "Plugin '{$plugin}' not found or disabled.");
        }

        Log::info('[RedeemWizard] Showing plugin form', [
            'voucher' => $voucherCode,
            'plugin' => $plugin,
        ]);

        // Get fields this plugin should collect for this voucher
        $requestedFields = RedeemPluginSelector::requestedFieldsFor($plugin, $voucher);

        // Get default values from contact (if mobile exists)
        $defaultValues = $this->getDefaultValues($voucherCode, $requestedFields);

        return Inertia::render($pluginConfig['page'], [
            'voucher_code' => $voucherCode,
            'voucher' => VoucherData::fromModel($voucher),
            'plugin' => $plugin,
            'requested_fields' => $requestedFields,
            'default_values' => $defaultValues,
        ]);
    }

    /**
     * Store plugin input data.
     *
     * @param  PluginFormRequest  $request
     * @param  Voucher  $voucher
     * @param  string  $plugin
     * @return RedirectResponse
     */
    public function storePlugin(
        PluginFormRequest $request,
        Voucher $voucher,
        string $plugin
    ): RedirectResponse {
        $voucherCode = $voucher->code;
        $validated = $request->validated();

        Log::info('[RedeemWizard] Storing plugin data', [
            'voucher' => $voucherCode,
            'plugin' => $plugin,
            'fields' => array_keys($validated),
        ]);

        // Get session key for this plugin
        $sessionKey = RedeemPluginMap::sessionKeyFor($plugin);

        if (! $sessionKey) {
            Log::error('[RedeemWizard] No session key for plugin', [
                'voucher' => $voucherCode,
                'plugin' => $plugin,
            ]);

            return back()->with('error', 'Plugin configuration error.');
        }

        // Store validated data in session
        Session::put("redeem.{$voucherCode}.{$sessionKey}", $validated);

        // Determine next plugin
        $nextPlugin = RedeemPluginSelector::nextPluginFor($voucher, $plugin);

        Log::info('[RedeemWizard] Determining next step', [
            'voucher' => $voucherCode,
            'current_plugin' => $plugin,
            'next_plugin' => $nextPlugin,
        ]);

        // Redirect to next plugin or finalize
        if (! $nextPlugin) {
            return redirect()->route('redeem.finalize', $voucher);
        }

        return redirect()->route('redeem.plugin', [
            'voucher' => $voucher,
            'plugin' => $nextPlugin,
        ]);
    }

    /**
     * Show finalization/review page.
     *
     * @param  Voucher  $voucher
     * @return Response
     */
    public function finalize(Voucher $voucher): Response
    {
        $voucherCode = $voucher->code;

        Log::info('[RedeemWizard] Showing finalization page', [
            'voucher' => $voucherCode,
        ]);

        // Gather all collected data for review
        $mobile = Session::get("redeem.{$voucherCode}.mobile");
        $wallet = Session::get("redeem.{$voucherCode}.wallet", []);
        $inputs = Session::get("redeem.{$voucherCode}.inputs", []);
        $signature = Session::get("redeem.{$voucherCode}.signature");

        // Format bank account for display
        $bankAccount = null;
        if (! empty($wallet['bank_code']) && ! empty($wallet['account_number'])) {
            $bankAccount = $this->formatBankAccount(
                $wallet['bank_code'],
                $wallet['account_number']
            );
        }

        return Inertia::render('Redeem/Finalize', [
            'voucher' => VoucherData::fromModel($voucher),
            'mobile' => $mobile,
            'bank_account' => $bankAccount,
            'inputs' => $inputs,
            'has_signature' => ! empty($signature),
        ]);
    }

    /**
     * Get default values for fields from contact.
     *
     * @param  string  $voucherCode
     * @param  array  $fields
     * @return array
     */
    protected function getDefaultValues(string $voucherCode, array $fields): array
    {
        $mobile = Session::get("redeem.{$voucherCode}.mobile");

        if (! $mobile) {
            return [];
        }

        try {
            $contact = Contact::fromPhoneNumber(phone($mobile, 'PH'));

            return collect($fields)
                ->mapWithKeys(fn ($field) => [
                    $field => $contact->{$field} ?? null,
                ])
                ->filter()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('[RedeemWizard] Could not load contact defaults', [
                'voucher' => $voucherCode,
                'mobile' => $mobile,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get banks list for dropdown.
     *
     * @return array
     */
    protected function getBanksList(): array
    {
        // TODO: Get from payment-gateway package's BankRegistry
        return [
            ['code' => 'BDO', 'name' => 'BDO Unibank'],
            ['code' => 'BPI', 'name' => 'Bank of the Philippine Islands'],
            ['code' => 'MBTC', 'name' => 'Metrobank'],
            ['code' => 'UBP', 'name' => 'UnionBank'],
            ['code' => 'SECB', 'name' => 'Security Bank'],
            ['code' => 'RCBC', 'name' => 'RCBC'],
            ['code' => 'PNB', 'name' => 'Philippine National Bank'],
            ['code' => 'LBP', 'name' => 'Land Bank of the Philippines'],
            ['code' => 'DBP', 'name' => 'Development Bank of the Philippines'],
            ['code' => 'CBC', 'name' => 'Chinabank'],
        ];
    }

    /**
     * Format bank account for display.
     *
     * @param  string  $bankCode
     * @param  string  $accountNumber
     * @return string
     */
    protected function formatBankAccount(string $bankCode, string $accountNumber): string
    {
        $banks = collect($this->getBanksList());
        $bank = $banks->firstWhere('code', $bankCode);
        $bankName = $bank['name'] ?? $bankCode;

        return "{$bankName} ({$accountNumber})";
    }
}
