<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use Illuminate\Console\Command;
use LBHurtado\PaymentGateway\Models\DisbursementAttempt;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Recover collected redemption data for a voucher whose redemption may have
 * been rolled back or whose disbursement failed.
 *
 * Checks multiple data sources in priority order:
 * 1. Voucher inputs table (PersistInputs pipeline stage)
 * 2. Settlement envelope payload (SyncEnvelopeData pipeline stage)
 * 3. DisbursementAttempt.request_payload (gateway audit trail)
 * 4. Voucher metadata (disbursement section)
 * 5. Redeemer metadata (contact/redemption data)
 */
class RecoverRedemptionData
{
    use AsAction;

    public string $commandSignature = 'disbursement:recover
                            {code : The voucher code}
                            {--json : Output as JSON}';

    public string $commandDescription = 'Recover collected redemption data from all available sources';

    /**
     * Core logic — scan all data sources for recoverable data.
     *
     * @return array{voucher_found: bool, sources: array, summary: string}
     */
    public function handle(string $code): array
    {
        $result = [
            'voucher_found' => false,
            'sources' => [],
            'summary' => '',
        ];

        $voucher = Voucher::where('code', $code)->first();

        if (! $voucher) {
            $result['summary'] = "Voucher not found: {$code}. Checking disbursement attempts only.";

            // Even without a voucher, check attempt records (they survive independently)
            $this->checkDisbursementAttempts($code, $result);

            return $result;
        }

        $result['voucher_found'] = true;

        // Source 1: Voucher inputs table
        $this->checkVoucherInputs($voucher, $result);

        // Source 2: Settlement envelope payload
        $this->checkEnvelopePayload($voucher, $result);

        // Source 3: DisbursementAttempt records
        $this->checkDisbursementAttempts($code, $result);

        // Source 4: Voucher metadata (disbursement section)
        $this->checkVoucherMetadata($voucher, $result);

        // Source 5: Redeemer metadata
        $this->checkRedeemerMetadata($voucher, $result);

        // Build summary
        $found = collect($result['sources'])->filter(fn ($s) => $s['has_data'])->count();
        $total = count($result['sources']);
        $result['summary'] = "{$found} of {$total} data sources contain recoverable data.";

        return $result;
    }

    private function checkVoucherInputs(Voucher $voucher, array &$result): void
    {
        $source = [
            'name' => 'Voucher Inputs Table',
            'description' => 'Data saved by PersistInputs pipeline stage',
            'has_data' => false,
            'data' => [],
        ];

        try {
            // HasInputs trait provides getInputs() or similar
            $inputs = $voucher->inputs ?? collect();

            if ($inputs->isNotEmpty()) {
                $source['has_data'] = true;
                $source['data'] = $inputs->mapWithKeys(function ($input) {
                    return [$input->name => $input->value];
                })->all();
            }
        } catch (\Throwable $e) {
            $source['error'] = $e->getMessage();
        }

        $result['sources'][] = $source;
    }

    private function checkEnvelopePayload(Voucher $voucher, array &$result): void
    {
        $source = [
            'name' => 'Settlement Envelope Payload',
            'description' => 'Data synced by SyncEnvelopeData pipeline stage',
            'has_data' => false,
            'data' => [],
        ];

        try {
            $envelope = $voucher->envelope;

            if ($envelope && ! empty($envelope->payload)) {
                $source['has_data'] = true;
                $source['data'] = $envelope->payload;

                // Also check for envelope documents
                $documents = $envelope->documents ?? collect();
                if ($documents->isNotEmpty()) {
                    $source['documents'] = $documents->map(fn ($doc) => [
                        'type' => $doc->document_type ?? 'unknown',
                        'status' => $doc->status ?? 'unknown',
                        'filename' => $doc->original_filename ?? 'unknown',
                    ])->all();
                }
            }
        } catch (\Throwable $e) {
            $source['error'] = $e->getMessage();
        }

        $result['sources'][] = $source;
    }

    private function checkDisbursementAttempts(string $code, array &$result): void
    {
        $source = [
            'name' => 'Disbursement Attempt Records',
            'description' => 'Gateway audit trail with request/response payloads',
            'has_data' => false,
            'data' => [],
        ];

        $attempts = DisbursementAttempt::byVoucherCode($code)
            ->orderBy('attempted_at', 'desc')
            ->get();

        if ($attempts->isNotEmpty()) {
            $source['has_data'] = true;
            $source['data'] = $attempts->map(fn ($a) => [
                'status' => $a->status,
                'reference_id' => $a->reference_id,
                'amount' => $a->amount,
                'currency' => $a->currency,
                'mobile' => $a->mobile,
                'bank_code' => $a->bank_code,
                'account_number' => $a->account_number,
                'settlement_rail' => $a->settlement_rail,
                'gateway_transaction_id' => $a->gateway_transaction_id,
                'attempted_at' => $a->attempted_at?->toDateTimeString(),
                'request_payload' => $a->request_payload,
            ])->all();
        }

        $result['sources'][] = $source;
    }

    private function checkVoucherMetadata(Voucher $voucher, array &$result): void
    {
        $source = [
            'name' => 'Voucher Metadata (Disbursement)',
            'description' => 'Disbursement data stored in voucher metadata by DisburseCash pipeline',
            'has_data' => false,
            'data' => [],
        ];

        $disbursement = $voucher->metadata['disbursement'] ?? null;

        if ($disbursement) {
            $source['has_data'] = true;
            $source['data'] = $disbursement;
        }

        $result['sources'][] = $source;
    }

    private function checkRedeemerMetadata(Voucher $voucher, array &$result): void
    {
        $source = [
            'name' => 'Redeemer Metadata',
            'description' => 'Redemption data from the redeemer pivot (contact, inputs)',
            'has_data' => false,
            'data' => [],
        ];

        try {
            $redeemer = $voucher->redeemers->first();

            if ($redeemer) {
                $metadata = $redeemer->metadata ?? [];

                if (! empty($metadata)) {
                    $source['has_data'] = true;
                    $source['data'] = [
                        'redemption' => $metadata['redemption'] ?? null,
                        'redeemer_type' => $redeemer->redeemer_type,
                        'redeemer_id' => $redeemer->redeemer_id,
                    ];
                }

                // Also get contact info
                $contact = $redeemer->redeemer;
                if ($contact) {
                    $source['contact'] = [
                        'class' => get_class($contact),
                        'id' => $contact->getKey(),
                        'mobile' => $contact->mobile ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            $source['error'] = $e->getMessage();
        }

        $result['sources'][] = $source;
    }

    /**
     * Artisan command output.
     */
    public function asCommand(Command $command): int
    {
        $code = strtoupper($command->argument('code'));
        $asJson = $command->option('json');

        $result = $this->handle($code);

        if ($asJson) {
            $command->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        // Human-readable output
        $command->newLine();
        $command->info("Data Recovery Report for: {$code}");
        $command->line($result['summary']);
        $command->newLine();

        if (! $result['voucher_found']) {
            $command->warn('Voucher record not found in database.');
            $command->line('This may mean the redemption rolled back completely.');
            $command->newLine();
        }

        foreach ($result['sources'] as $source) {
            $icon = $source['has_data'] ? '✓' : '✗';
            $style = $source['has_data'] ? 'info' : 'comment';

            $command->line("<{$style}>  [{$icon}] {$source['name']}</{$style}>");
            $command->line("      {$source['description']}");

            if (isset($source['error'])) {
                $command->error("      Error: {$source['error']}");
            }

            if ($source['has_data']) {
                $data = $source['data'];

                if (is_array($data) && ! empty($data)) {
                    // For arrays of records (like attempts), show count
                    if (isset($data[0]) && is_array($data[0])) {
                        $command->line('      Records: '.count($data));
                        foreach ($data as $i => $record) {
                            $num = $i + 1;
                            $command->line("      [{$num}]");
                            $this->renderData($command, $record, 10);
                        }
                    } else {
                        $this->renderData($command, $data, 6);
                    }
                }

                // Show documents if present
                if (isset($source['documents'])) {
                    $command->line('      Documents: '.count($source['documents']));
                    foreach ($source['documents'] as $doc) {
                        $command->line("        - [{$doc['type']}] {$doc['filename']} ({$doc['status']})");
                    }
                }

                // Show contact if present
                if (isset($source['contact'])) {
                    $c = $source['contact'];
                    $command->line("      Contact: {$c['class']}#{$c['id']}".($c['mobile'] ? " ({$c['mobile']})" : ''));
                }
            }

            $command->newLine();
        }

        return Command::SUCCESS;
    }

    /**
     * Render a key-value array with indentation.
     */
    private function renderData(Command $command, array $data, int $indent): void
    {
        $pad = str_repeat(' ', $indent);

        foreach ($data as $key => $value) {
            if (is_null($value)) {
                continue;
            }

            if (is_array($value)) {
                $json = json_encode($value, JSON_UNESCAPED_UNICODE);
                $display = strlen($json) > 80 ? substr($json, 0, 77).'...' : $json;
                $command->line("{$pad}{$key}: {$display}");
            } elseif (is_string($value) && strlen($value) > 80) {
                $command->line("{$pad}{$key}: ".substr($value, 0, 77).'... ('.strlen($value).' chars)');
            } else {
                $command->line("{$pad}{$key}: {$value}");
            }
        }
    }
}
