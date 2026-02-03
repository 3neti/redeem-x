# Whitepaper: Revolutionizing Utility Billing with SMS‑Confirmed QR Payments

## Executive Summary
This whitepaper documents an innovative payment collection system that uses payable vouchers with QR PH codes and SMS confirmation to modernize utility billing. The system eliminates the need for physical payment centers, reduces collection costs, and provides real‑time payment tracking for both utilities and consumers.

Key Innovation: SMS‑based payment confirmation creates a trustless, two‑party verification system where neither the payer nor the utility company can dispute legitimate payments.

In contrast to gateways that provide T+1 reconciliation, this design collects and posts funds in real time using regulated bank payment rails (e.g., QR PH/InstaPay), eliminating next‑day settlement waiting and reconciliation drift.

Scope: This platform enhances billing and collection; it does not replace a utility’s ERP or general ledger. It integrates via webhooks/exports to existing back‑office systems.

## Problem Statement: The Current State of Utility Billing

### Traditional Challenges
- Physical Payment Centers: High operational costs (staff, rent, security)
- Payment Delays: 3–7 day processing times for bank deposits
- Reconciliation Burden: Manual matching of payments to account numbers
- Consumer Friction: Long queues, limited operating hours, travel costs
- Proof of Payment: Paper receipts easily lost, disputes common

### Why Existing Digital Solutions Fall Short
- Bank Transfers: Require manual reference input, prone to errors
- Credit Cards: High merchant fees (2.5–3.5%), not accessible to all
- Over‑the‑Counter: Still requires physical presence
- Traditional QR Codes: No confirmation mechanism, classification uncertainty

## Solution: Payable Vouchers with SMS Confirmation

### System Architecture

#### 1) Voucher Generation (Utility Company Side)
API Endpoint: POST /api/v1/vouchers

Example request
```json
{
  "voucher_type": "payable",
  "target_amount": 2500.00,
  "external_metadata": {
    "account_number": "12345-6789",
    "billing_period": "2026-01",
    "consumer_name": "Juan Dela Cruz",
    "due_date": "2026-02-10"
  },
  "attachments": ["bill.pdf"],
  "count": 1,
  "prefix": "ELEC",
  "ttl_days": 30
}
```

Key features
- Zero cash disbursement (amount: 0; target_amount: required)
- Freeform metadata for billing details
- PDF attachments (bill copy, consumption chart)
- Batch generation via CSV upload (thousands of bills)

#### 2) Bill Delivery (Multiple Channels)
- SMS: “Your electric bill: ₱2,500. Pay via https://pay.utility.ph?code=ELEC-AB12”
- Email: PDF bill + payment link
- Printed invoice: QR code on paper statement
- Mobile app: In‑app payment button

#### 3) Payment Flow (Consumer Side)
Public page: GET /pay?code={VOUCHER_CODE}

Journey
1. Enter voucher code → system validates voucher
2. View bill details → shows external_metadata + attachments
3. Enter amount → partial or full payment
4. Generate QR PH → InstaPay QR code appears
5. Scan with GCash/Maya → real money transfer
6. Webhook notification → bank notifies the system of deposit
7. SMS confirmation link → consumer receives SMS
8. Click confirmation → transaction confirmed

### Settlement and Reconciliation Model (Real‑Time on Bank Rails)
- Funds flow directly from the customer’s wallet/bank app to the utility’s bank account over QR PH rails; there is no intermediary wallet custody.
- Posting is real‑time: upon successful deposit webhook and payer SMS confirmation, the voucher’s wallet is credited and reflected in dashboards immediately.
- Reconciliation bypasses T+1 batch files: the system correlates bank deposits to PaymentRequests and persists an immutable audit trail; exports feed the ERP for official posting.
- Compliance: We operate on the bank’s regulated rails and do not act as a money issuer; the platform records metadata and confirmations, while settlement occurs at the bank.

### Printed Invoice QR: On‑Demand Wallet‑App Payments
Embedding a dynamic payment URL and printable QR in each invoice unlocks:
- Pay‑on‑demand: customers can pay anytime by scanning with GCash/Maya or any QR PH app—no login or account creation required.
- Lower friction and wider reach: works even when the customer only has a default camera/QR scanner.
- Error‑free references: the QR encodes needed context, eliminating mistyped account numbers and mismatched deposits.
- Faster collections: customers pay at the moment of intent (while viewing the bill), improving DSO and cash flow.
- Omnichannel support: the same code appears in SMS, email, and paper—whichever the customer uses first.

## The SMS Innovation: Trustless Verification

### Problem: Payment Classification Ambiguity
When a bank webhook arrives with “₱500 deposited,” the system must determine:
- Is this a voucher payment or a wallet top‑up?
- If voucher, which voucher? (Multiple ₱500 QRs might exist.)

Traditional QR PH codes do not encode merchant reference data in the QR itself.

### Solution: Three‑Strategy Classification + SMS Guard
- Strategy 1: Merchant Metadata (95% accuracy, not bank‑standard yet)
- Strategy 2: Amount + Time Matching (≈80% accuracy, currently used)
- Strategy 3: FIFO Fallback (≈70% accuracy)

The SMS safety net
1. Webhook arrives → payment classified → unconfirmed transaction created
2. Consumer receives SMS: “Payment received! ₱500 for ELEC‑AB12. Confirm: [signed URL]”
3. Consumer clicks link → transaction confirmed → wallet credited
4. Unconfirmed payments are invisible to the utility until SMS confirmed

Why this works
- The consumer won’t confirm if they didn’t pay
- The utility isn’t credited for mis‑classified payments
- The SMS link is signed (cryptographic proof, expiry)
- Creates an audit trail with explicit consumer confirmation

## Technical Implementation (Highlights)

Webhook processing (conceptual)
```php
// classify → deposit as UNCONFIRMED → send SMS
$voucher->cash->wallet->deposit($amountMinor, [
  'flow' => 'pay',
  'payment_id' => $paymentId,
  'confirmed' => false,
]);
Notification::route('engage_spark', $payerMobile)
  ->notify(new PaymentConfirmationNotification($paymentRequest));
```

SMS confirmation (conceptual)
```php
// find unconfirmed transaction → confirm → redirect to thank‑you
$voucher->cash->confirm($transaction);
return redirect()->route('pay.confirmed', $paymentRequest->id);
```

Wallet balance tracking (conceptual)
```php
$paidTotal = $voucher->getPaidTotal(); // confirmed only
$remaining = $voucher->target_amount - $paidTotal;
```

## Use Case: Electric Utility Company

### Monthly Billing Cycle (Example)
Day 1: bill generation
```text
POST /api/v1/vouchers (batch: 50,000 consumers)
- Generate 50k payable vouchers
```
Output CSV
```csv
account_number,voucher_code,amount,payment_url
12345-6789,ELEC-AB12CD,2500,https://pay.utility.ph?code=ELEC-AB12CD
```

Day 2: bill delivery
- SMS blast via provider
- Email with PDF bill + link
- Printed bill with QR

Day 3–30: payments
- Consumer pays via QR → SMS confirmation
- Utility dashboard updates in real time
- Export payment report for ERP posting

### Additional Benefits
For utilities
- Real‑time collection monitoring
- Automated reconciliation; no manual matching
- Reduced fraud via cryptographic links and audit trails
- Partial payments supported

For consumers
- 24/7 payments, no queues
- Mobile‑first, familiar wallet apps
- Instant confirmation and digital proof of payment

## API Architecture (Selected)

Voucher generation API
```json
{
  "amount": 0,
  "voucher_type": "payable",
  "target_amount": 2500.00,
  "external_metadata": {
    "account_number": "12345-6789",
    "consumer_name": "Juan Dela Cruz",
    "billing_period": "2026-01",
    "due_date": "2026-02-10"
  },
  "rules": {
    "allow_partial_payments": true,
    "min_payment_amount": 100,
    "max_payment_amount": 2500
  },
  "count": 1,
  "prefix": "ELEC",
  "mask": "****-****",
  "ttl_days": 30,
  "attachments": ["bill.pdf"]
}
```

Payment QR generation API
```json
{
  "voucher_code": "ELEC-AB12CD34",
  "amount": 2500
}
```

Payment history API (confirmed only)
```json
{
  "voucher_code": "ELEC-AB12CD34",
  "target_amount": 2500,
  "paid_total": 1000,
  "remaining": 1500,
  "payments": [
    { "id": 12345, "amount": 500, "status": "confirmed" },
    { "id": 12346, "amount": 500, "status": "confirmed" }
  ]
}
```

Webhook endpoint (bank → platform)
```json
{
  "reference_no": "ELEC-AB12CD34",
  "payment_id": "NETBANK-987654",
  "payment_status": "PAID",
  "amount": { "value": 2500, "currency": "PHP" },
  "paid_at": "2026-01-15T14:05:32Z"
}
```

## Scaling Considerations
- Voucher generation: high throughput with batching and queues
- SMS sending: horizontally scalable
- Webhooks: ingest via queue workers; async processing
- Storage: S3 for attachments, CDN for QR caching
- Database: read replicas; sharded or partitioned histories if needed

## Security & Compliance
Payment security
- Signed URLs for SMS links; short expiry
- TLS for all endpoints; rate limits
- No card credentials handled; banks/wallets handle auth

Data privacy
- PII minimization
- Encrypted storage for PDFs
- Immutable audit logs; subject access and deletion workflows

Financial compliance
- BSP/QR PH rails; bank‑grade settlement
- AML limits and monitoring via policy
- ERP exports for official posting and GL sync

## Automated Revenue Sharing (Waterfall Distribution)

### The Multi‑Party Collection Problem
A typical bill contains components payable to multiple service providers (illustrative breakdown for a ₱2,500 bill):

| Component | Percentage | Amount | Beneficiary |
|---|---:|---:|---|
| Generation Charge | 58% | ₱1,450 | Independent Power Producers (IPPs) |
| Distribution Charge | 18% | ₱450 | Distribution Utility |
| Transmission Charge | 10% | ₱250 | NGCP |
| System Loss | 8% | ₱200 | Distribution Utility |
| Taxes & Subsidies | 6% | ₱150 | Government (FIT‑All, VAT, Universal Charge) |

Today this splitting is largely manual, slow, and error‑prone.

### Split Settlement with Digital Ledger
Using internal wallets, payments are automatically distributed to beneficiary wallets in real time or in scheduled batches.

Configuration example (attached to voucher instructions)
```json
{
  "settlement_rules": {
    "split_enabled": true,
    "distribution": [
      { "beneficiary": "IPP_CORP", "category": "generation", "percentage": 58, "wallet_id": 123 },
      { "beneficiary": "MERALCO", "category": "distribution", "percentage": 18, "wallet_id": 456 },
      { "beneficiary": "NGCP", "category": "transmission", "percentage": 10, "wallet_id": 789 },
      { "beneficiary": "MERALCO", "category": "system_loss", "percentage": 8, "wallet_id": 456 },
      { "beneficiary": "BIR", "category": "taxes", "percentage": 6, "wallet_id": 999 }
    ]
  }
}
```

Operational models
- Real‑time internal ledger (recommended): instant splits; weekly/monthly cash‑outs to banks
- Daily batch settlement: EOD split posting; monthly bank transfers
- Real‑time bank transfers (not recommended due to fees and partial‑failure risk)

Audit trail
```json
{
  "settlement_id": "SETTLE-2026-01-21-00123",
  "voucher_code": "ELEC-AB12CD",
  "payment_amount": 2500,
  "splits": [
    { "beneficiary": "IPP_CORP", "amount": 1450 },
    { "beneficiary": "MERALCO", "amount": 650 },
    { "beneficiary": "NGCP", "amount": 250 },
    { "beneficiary": "BIR", "amount": 150 }
  ],
  "settlement_status": "completed"
}
```

Benefits
- Utilities: eliminate manual splits; real‑time visibility; zero reconciliation drift
- IPPs: same‑day revenue vs. long delays; dashboards; API access
- NGCP/Gov’t: predictable cash‑outs; transparent audit trails

## Conclusion
The SMS‑confirmed QR payment system represents a step change in utility billing:
- Cost: major reduction vs. physical payment centers
- Speed: real‑time confirmation vs. T+1/T+3 clearing
- Trust: cryptographic SMS verification and immutable logs
- Accessibility: 24/7 payments via familiar wallet apps
- Scalability: 1M+ consumers with minimal infrastructure

We enhance billing and collection while your ERP and accounting systems remain the system of record for financial posting. By riding the bank’s QR PH rails and introducing SMS‑guarded confirmation, utilities can collect faster, reconcile automatically, and optionally auto‑distribute collections via waterfall rules to multiple beneficiaries.
