<?php

declare(strict_types=1);

use App\Support\PhoneFormatter;

// ── Default format: international_grouped ──────────────────────────────

it('formats E.164 as international grouped', function () {
    expect(PhoneFormatter::forDisplay('+639173011987'))->toBe('+63 (917) 301-1987');
});

it('formats national as international grouped', function () {
    expect(PhoneFormatter::forDisplay('09173011987'))->toBe('+63 (917) 301-1987');
});

it('formats stripped E.164 as international grouped', function () {
    expect(PhoneFormatter::forDisplay('639173011987'))->toBe('+63 (917) 301-1987');
});

// ── Configurable formats ───────────────────────────────────────────────

it('formats as international when configured', function () {
    config(['app.phone_display_format' => 'international']);

    expect(PhoneFormatter::forDisplay('+639173011987'))->toBe('+63 917 301 1987');
});

it('formats as national when configured', function () {
    config(['app.phone_display_format' => 'national']);

    expect(PhoneFormatter::forDisplay('+639173011987'))->toBe('09173011987');
});

it('formats as E.164 when configured', function () {
    config(['app.phone_display_format' => 'e164']);

    expect(PhoneFormatter::forDisplay('+639173011987'))->toBe('+639173011987');
});

// ── Edge cases ─────────────────────────────────────────────────────────

it('returns raw value for unparseable input', function () {
    expect(PhoneFormatter::forDisplay('not-a-number'))->toBe('not-a-number');
});

it('accepts explicit country parameter', function () {
    expect(PhoneFormatter::forDisplay('09173011987', 'PH'))->toBe('+63 (917) 301-1987');
});
