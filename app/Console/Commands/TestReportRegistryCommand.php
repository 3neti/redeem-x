<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use LBHurtado\ReportRegistry\Contracts\ReportResolverInterface;
use LBHurtado\ReportRegistry\Services\ReportExecutor;
use LBHurtado\ReportRegistry\Services\ReportRegistry;

class TestReportRegistryCommand extends Command
{
    protected $signature = 'test:report-registry
        {--report= : Test a specific report driver ID}
        {--format= : Test a specific output format}
        {--user=lester@hurtado.ph : User email for auth context}
        {--detailed : Show data preview and verbose output}';

    protected $description = 'End-to-end validation of report registry: drivers, resolvers, formatters';

    private int $errors = 0;

    private int $driverCount = 0;

    public function handle(ReportRegistry $registry, ReportExecutor $executor): int
    {
        $this->info('═════════════════════════════════════════════════════════');
        $this->info('  Report Registry Test');
        $this->info('═════════════════════════════════════════════════════════');
        $this->newLine();

        // Authenticate
        $user = User::where('email', $this->option('user'))->first();
        if ($user) {
            Auth::login($user);
            $this->detail("Authenticated as: {$user->email}");
        } else {
            $this->warn("User not found: {$this->option('user')} — running without auth context");
        }
        $this->newLine();

        // ── PHASE 1: DRIVER DISCOVERY ──────────────────────────────────
        $this->phase('DRIVER DISCOVERY');

        $entries = $registry->list();
        if (empty($entries)) {
            $this->error('No report drivers found on disk.');
            $this->detail('Expected location: storage/app/report-drivers/');

            return self::FAILURE;
        }

        $this->step("Found ".count($entries)." report driver(s) on disk");

        $targetReport = $this->option('report');
        $driversToTest = [];

        foreach ($entries as $entry) {
            if ($targetReport && $entry['id'] !== $targetReport) {
                continue;
            }

            try {
                $driver = $registry->driver($entry['id'], $entry['version']);
                $colCount = count($driver->columns);
                $filterCount = count($driver->filters);
                $this->step("{$driver->id}@{$driver->version} — loaded OK ({$colCount} columns, {$filterCount} filters)");
                $driversToTest[] = $driver;
                $this->driverCount++;
            } catch (\Throwable $e) {
                $this->error("  ✗ {$entry['id']}@{$entry['version']} — {$e->getMessage()}");
                $this->errors++;
            }
        }

        if (empty($driversToTest)) {
            $this->warn($targetReport ? "Report not found: {$targetReport}" : 'No valid drivers loaded.');

            return self::FAILURE;
        }
        $this->newLine();

        // ── PHASE 2: RESOLVER VALIDATION ───────────────────────────────
        $this->phase('RESOLVER VALIDATION');

        $resolvedDrivers = [];
        foreach ($driversToTest as $driver) {
            if (! $driver->resolver) {
                $this->warn("  ○ {$driver->id} — no resolver defined (metadata-only driver)");

                continue;
            }

            if (! class_exists($driver->resolver)) {
                $this->error("  ✗ {$driver->id} → {$driver->resolver} [class not found]");
                $this->errors++;

                continue;
            }

            $resolver = app($driver->resolver);
            if (! $resolver instanceof ReportResolverInterface) {
                $this->error("  ✗ {$driver->id} → {$driver->resolver} [does not implement ReportResolverInterface]");
                $this->errors++;

                continue;
            }

            $this->step("{$driver->id} → {$driver->resolver} [class exists]");

            try {
                $result = $resolver->resolve(
                    sort: $driver->defaultSort,
                    sortDirection: $driver->defaultSortDirection,
                    perPage: $driver->defaultPerPage,
                );
                $rowCount = count($result['data']);
                $total = $result['meta']['total'] ?? 0;
                $this->step("Executing resolver... returned {$rowCount} rows ({$total} total)");

                // Validate row shape
                if ($rowCount > 0) {
                    $columnKeys = array_map(fn ($col) => $col->key, $driver->columns);
                    $rowKeys = array_keys($result['data'][0]);
                    $missing = array_diff($columnKeys, $rowKeys);
                    if (empty($missing)) {
                        $this->step('Row shape matches column definition');
                    } else {
                        $this->warn('  ⚠ Missing keys in row: '.implode(', ', $missing));
                    }
                }

                $resolvedDrivers[$driver->id] = ['driver' => $driver, 'result' => $result];
            } catch (\Throwable $e) {
                $this->error("  ✗ Resolver execution failed: {$e->getMessage()}");
                $this->errors++;
            }
        }
        $this->newLine();

        // ── PHASE 3: FORMAT OUTPUT ─────────────────────────────────────
        $this->phase('FORMAT OUTPUT');

        $formatsToTest = $this->option('format')
            ? [$this->option('format')]
            : $registry->formatters();

        foreach ($resolvedDrivers as $driverId => $info) {
            $driver = $info['driver'];
            $data = $info['result']['data'];
            $meta = $info['result']['meta'];

            foreach ($formatsToTest as $format) {
                try {
                    $formatter = $registry->formatter($format);
                    $output = $formatter->format($driver, $data, $meta);
                    $size = is_array($output)
                        ? strlen(json_encode($output))
                        : strlen($output);
                    $sizeStr = $size > 1024
                        ? number_format($size / 1024, 1).' KB'
                        : "{$size} B";

                    $extra = '';
                    if ($format === 'csv') {
                        $lines = substr_count(is_string($output) ? $output : '', "\n");
                        $extra = ", {$lines} lines";
                    }

                    $this->step("{$format}: {$sizeStr} output{$extra} ({$driverId})");
                } catch (\Throwable $e) {
                    $this->error("  ✗ {$format} failed for {$driverId}: {$e->getMessage()}");
                    $this->errors++;
                }
            }
        }
        $this->newLine();

        // ── PHASE 4: DATA PREVIEW ──────────────────────────────────────
        if ($this->option('detailed') && ! empty($resolvedDrivers)) {
            $this->phase('DATA PREVIEW');

            foreach ($resolvedDrivers as $driverId => $info) {
                $driver = $info['driver'];
                $data = $info['result']['data'];
                $meta = $info['result']['meta'];

                $this->line("  <fg=cyan>{$driver->title}</> ({$driverId})");

                try {
                    $textFormatter = $registry->formatter('text');
                    $this->line($textFormatter->format($driver, $data, $meta));
                } catch (\Throwable $e) {
                    $this->warn("  ⚠ Preview failed: {$e->getMessage()}");
                }
            }
        }

        // ── SUMMARY ────────────────────────────────────────────────────
        $this->info('═════════════════════════════════════════════════════════');
        $formatCount = count($formatsToTest);
        if ($this->errors === 0) {
            $this->info("✓ All checks passed ({$this->driverCount} drivers, {$formatCount} formats, 0 errors)");
        } else {
            $this->error("✗ Completed with {$this->errors} error(s) ({$this->driverCount} drivers, {$formatCount} formats)");
        }

        return $this->errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function phase(string $title): void
    {
        $this->info("─── {$title} ".str_repeat('─', 55 - strlen($title)));
    }

    private function step(string $message): void
    {
        $this->line("  <fg=green>✓</> {$message}");
    }

    private function detail(string $message): void
    {
        $this->line("    <fg=gray>{$message}</>");
    }
}
