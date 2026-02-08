# Home Loan Driver Specifications

This directory contains the canonical CSV specifications for the home loan driver family.

## Files

### `home-loan-documents-signals.csv`
Defines documents, signals, and payload fields for each driver.

**Columns:**
- `row_type` - DOC, SIG, or PAYLOAD
- `driver_id` - Unique driver identifier
- `variant_family` - Driver family (bank_home_loan, pagibig_home_loan)
- `category` / `sub_category` - Organizational grouping
- `item_key` - Unique key within driver
- `item_kind` - document, signal, or payload_field
- `doc_type` - Document type code (UPPER_SNAKE_CASE)
- `doc_title` - Human-readable document title
- `doc_required` - true/false
- `review_mode` - none, optional, required
- `multiple` - Allow multiple uploads (true/false)
- `max_size_mb` - Maximum file size
- `signal_key` - Signal key (lower_snake_case)
- `signal_required` - true/false
- `signal_source` - host or system
- `payload_pointer` - JSON Pointer path
- `payload_required` - true/false
- `notes` - Implementation notes

### `home-loan-gates.csv`
Defines gate expressions for each driver.

**Columns:**
- `driver_id` - Unique driver identifier
- `variant_family` - Driver family
- `gate_key` - Gate identifier
- `gate_category` - payload, checklist, signals, composite
- `gate_purpose` - Human description
- `expression` - Gate rule expression
- `blocking_if_false` - yes/no
- `notes` - Implementation notes

## Driver Summary

| Driver ID | Type | Docs | Signals | Payload | Gates |
|-----------|------|------|---------|---------|-------|
| `bank.home-loan.eligible.single` | Base | 12 | 4 | 5 | 6 |
| `bank.home-loan.eligible.married` | Overlay | +2 | +1 | - | 6 |
| `bank.home-loan.eligible.widower` | Overlay | +2 | - | - | 6 |
| `bank.home-loan.eligible.separated` | Overlay | +1 | +1 | - | 7 |
| `bank.home-loan.eligible.with-co-borrower` | Overlay | +2 | +2 | - | 7 |
| `bank.home-loan.eligible.with-co-borrower.married` | Composite | - | - | - | 7 |
| `bank.home-loan.income.self-employed` | Overlay | 4 | 1 | - | 7 |
| `bank.home-loan.income.ofw` | Overlay | 3 | 1 | - | 7 |
| `bank.home-loan.property.rfo` | Overlay | 1 | - | - | 6 |
| `bank.home-loan.property.non-rfo` | Overlay | 2 | 1 | - | 7 |
| `pagibig.home-loan.takeout.standard` | Base | 1 | 5 | - | 9 |
| `pagibig.home-loan.takeout.enhanced` | Overlay | - | +1 | - | 9 |

## Notes

**Composite overlays** (`with-co-borrower.married`, `enhanced`) - These are gate-only definitions that combine multiple overlays. They inherit docs/signals from their constituent overlays via the `extends` mechanism.

## Validation

Run the sanity check:
```bash
php artisan driver:validate-csv docs/reference/driver-specs/
```

## Usage

These CSVs are the source of truth for generating YAML driver files:
```bash
php artisan driver:generate-from-csv docs/reference/driver-specs/ --output=storage/app/envelope-drivers/
```
