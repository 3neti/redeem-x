#!/bin/bash

###############################################################################
# Test Settlement Voucher Round-Trip Flow
###############################################################################
#
# This script tests the complete lifecycle of a SETTLEMENT voucher:
#
# PHASE 1: LOAN DISBURSEMENT
#   - Query pre-generated settlement voucher (must exist)
#   - Show initial wallet state (owner, voucher cash, system)
#   - Check if already disbursed (skip if yes)
#   - Create borrower contact with GCash bank details
#   - Redeem voucher → Disburse to GCash account (real money if DISBURSE_DISABLE=false)
#
# PHASE 2: LOAN REPAYMENT VIA QR
#   - Create PaymentRequest for partial repayment
#   - Simulate webhook → Credits voucher cash (unconfirmed)
#   - Confirm payment via SMS link
#   - Repeat for full repayment
#   - Verify voucher fully repaid (remaining = ₱0)
#
# Usage:
#   ./test-settlement-voucher-flow.sh <voucher_code> [--mobile=number]
#
# Examples:
#   ./test-settlement-voucher-flow.sh SETT123                    # Use default mobile (09467438575)
#   ./test-settlement-voucher-flow.sh SETT123 --mobile=09171234567
#
# Prerequisites:
#   - Settlement voucher must be generated via UI first (requires auth)
#   - Voucher must have cash wallet funded (done during generation)
#   - GCash disbursement uses mobile number as account number
#
###############################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Parse arguments
if [ $# -lt 1 ]; then
    echo -e "${RED}Error: Missing voucher code${NC}"
    echo "Usage: $0 <voucher_code> [--mobile=number]"
    echo ""
    echo "Examples:"
    echo "  $0 SETT123                       # Use default mobile (09467438575)"
    echo "  $0 SETT123 --mobile=09171234567  # Custom GCash mobile"
    echo ""
    echo "Prerequisites:"
    echo "  - Settlement voucher must be generated via UI first"
    echo "  - Voucher type must be SETTLEMENT"
    echo "  - If DISBURSE_DISABLE=false, real money will be sent to GCash"
    exit 1
fi

VOUCHER_CODE=$1
BORROWER_MOBILE="09467438575"  # Default test GCash number

# Parse remaining arguments
shift
while [ $# -gt 0 ]; do
    case $1 in
        --mobile=*)
            BORROWER_MOBILE="${1#*=}"
            shift
            ;;
        *)
            echo -e "${RED}Unknown argument: $1${NC}"
            exit 1
            ;;
    esac
done

# Kill queue workers
pkill -f "artisan queue:work" 2>/dev/null || true
sleep 1

###############################################################################
# Header
###############################################################################
echo -e "${CYAN}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║  Test Settlement Voucher Round-Trip Flow                      ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}Voucher Code:${NC} $VOUCHER_CODE"
echo -e "${BLUE}Borrower GCash:${NC} $BORROWER_MOBILE"
echo ""

###############################################################################
# INITIAL STATE
###############################################################################
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}  INITIAL STATE (Post-Generation)${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

STATE=$(php artisan tinker --execute="
use LBHurtado\\Voucher\\Models\\Voucher;
\$v = Voucher::where('code', '$VOUCHER_CODE')->with('owner')->first();
if (!\$v) { echo 'ERROR: Voucher not found'; exit(1); }
if (\$v->voucher_type->value !== 'settlement') {
    echo 'ERROR: Voucher is not a settlement type (found: ' . \$v->voucher_type->value . ')';
    exit(1);
}
\$system = app(LBHurtado\\Wallet\\Services\\SystemUserResolverService::class)->resolve();
echo json_encode([
    'owner_email' => \$v->owner ? \$v->owner->email : 'unknown',
    'owner_wallet' => (\$v->owner && \$v->owner->wallet) ? \$v->owner->wallet->balanceFloat : 0,
    'voucher_cash' => \$v->cash ? \$v->cash->balanceFloat : 0,
    'system_wallet' => \$system->balanceFloat,
    'target_amount' => \$v->target_amount ?? 0,
    'paid_total' => \$v->getPaidTotal(),
    'redeemed_total' => \$v->getRedeemedTotal(),
    'remaining' => \$v->getRemaining(),
    'already_disbursed' => \$v->redeemed_at !== null,
    'redeemed_at' => \$v->redeemed_at ? \$v->redeemed_at->toIso8601String() : null,
]);
" 2>&1 | tail -1)

if [[ $STATE == *"ERROR"* ]]; then
    echo -e "${RED}✗ $STATE${NC}"
    exit 1
fi

OWNER_EMAIL=$(echo $STATE | jq -r '.owner_email')
OWNER_WALLET=$(echo $STATE | jq -r '.owner_wallet')
VOUCHER_CASH=$(echo $STATE | jq -r '.voucher_cash')
SYSTEM_WALLET=$(echo $STATE | jq -r '.system_wallet')
TARGET=$(echo $STATE | jq -r '.target_amount')
PAID_TOTAL=$(echo $STATE | jq -r '.paid_total')
REDEEMED_TOTAL=$(echo $STATE | jq -r '.redeemed_total')
REMAINING=$(echo $STATE | jq -r '.remaining')
ALREADY_DISBURSED=$(echo $STATE | jq -r '.already_disbursed')
REDEEMED_AT=$(echo $STATE | jq -r '.redeemed_at')

echo -e "${GREEN}Voucher:${NC} $VOUCHER_CODE (Owner: $OWNER_EMAIL)"
echo -e "${GREEN}Type:${NC} SETTLEMENT (bidirectional - disburse + repay)"
echo ""
printf "%-25s %15s\n" "Target Amount:" "₱$TARGET"
printf "%-25s %15s\n" "Paid Total:" "₱$PAID_TOTAL"
printf "%-25s %15s\n" "Redeemed Total:" "₱$REDEEMED_TOTAL"
printf "%-25s %15s\n" "Remaining:" "₱$REMAINING"
echo ""
printf "%-25s %15s\n" "System Wallet:" "₱$SYSTEM_WALLET"
printf "%-25s %15s\n" "Owner Wallet:" "₱$OWNER_WALLET"
printf "%-25s %15s\n" "Voucher Cash:" "₱$VOUCHER_CASH"
echo ""
###############################################################################
# PHASE 1: LOAN DISBURSEMENT
###############################################################################
if [ "$ALREADY_DISBURSED" == "true" ]; then
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}  PHASE 1: LOAN DISBURSEMENT (ALREADY COMPLETED)${NC}"
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}⚠  Voucher already disbursed at: $REDEEMED_AT${NC}"
    echo -e "${YELLOW}⚠  Redeemed total: ₱$REDEEMED_TOTAL${NC}"
    echo "  Skipping disbursement phase..."
    echo ""
else
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  PHASE 1: LOAN DISBURSEMENT${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    # Create borrower contact
    CONTACT=$(php artisan tinker --execute="
use LBHurtado\\Contact\\Models\\Contact;
try {
    \$c = Contact::create([
        'mobile' => '$BORROWER_MOBILE',
        'bank_account' => 'GXCHPHM2XXX:$BORROWER_MOBILE',
    ]);
    echo json_encode(['contact_id' => \$c->id, 'mobile' => \$c->mobile, 'bank' => 'GCash']);
} catch (\\Exception \$e) {
    echo json_encode(['error' => \$e->getMessage()]);
}
" 2>&1 | tail -1)
    
    # Check for contact creation errors
    if echo "$CONTACT" | jq -e '.error' > /dev/null 2>&1; then
        ERROR=$(echo $CONTACT | jq -r '.error')
        echo -e "${RED}✗ Contact creation failed: $ERROR${NC}"
        exit 1
    fi
    
    CONTACT_ID=$(echo $CONTACT | jq -r '.contact_id')
    echo -e "${GREEN}✓ Borrower contact created${NC}"
    echo "  ID: $CONTACT_ID | Mobile: $BORROWER_MOBILE"
    echo "  Bank: GCash (GXCHPHM2XXX) | Account: $BORROWER_MOBILE"
    echo ""
    
    # Redeem voucher (trigger disbursement)
    echo "Redeeming voucher (disbursing to GCash)..."
    REDEEM=$(php artisan tinker --execute="
use App\\Actions\\Voucher\\ProcessRedemption;
use LBHurtado\\Voucher\\Models\\Voucher;
use LBHurtado\\Contact\\Models\\Contact;
use Propaganistas\\LaravelPhone\\PhoneNumber;
\$v = Voucher::where('code', '$VOUCHER_CODE')->first();
\$c = Contact::find($CONTACT_ID);
\$phoneNumber = new PhoneNumber('$BORROWER_MOBILE', 'PH');
\$bankAccount = [
    'bank_code' => 'GXCHPHM2XXX',
    'account_number' => '$BORROWER_MOBILE',
];
try {
    \$result = ProcessRedemption::run(\$v, \$phoneNumber, [], \$bankAccount);
    \$v->refresh();
    echo json_encode([
        'success' => \$result,
        'cash_balance' => \$v->cash->balanceFloat,
        'redeemed_total' => \$v->getRedeemedTotal(),
    ]);
} catch (\\Exception \$e) {
    echo json_encode(['error' => \$e->getMessage()]);
}
" 2>&1 | tail -1)
    
    if [[ $REDEEM == *"error"* ]]; then
        ERROR=$(echo $REDEEM | jq -r '.error')
        echo -e "${RED}✗ Disbursement failed: $ERROR${NC}"
        exit 1
    fi
    
    CASH_AFTER_REDEEM=$(echo $REDEEM | jq -r '.cash_balance')
    REDEEMED_TOTAL=$(echo $REDEEM | jq -r '.redeemed_total')
    
    echo -e "${GREEN}✓ Voucher redeemed (disbursed)${NC}"
    echo "  Redeemed Total: ₱$REDEEMED_TOTAL"
    echo "  Voucher Cash: ₱$VOUCHER_CASH → ₱$CASH_AFTER_REDEEM"
    echo "  GCash Account: $BORROWER_MOBILE (+₱$REDEEMED_TOTAL)"
    echo ""
    
    # Update VOUCHER_CASH for Phase 2
    VOUCHER_CASH=$CASH_AFTER_REDEEM
fi

###############################################################################
# PHASE 2: LOAN REPAYMENT VIA QR (First Payment)
###############################################################################
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}  PHASE 2: LOAN REPAYMENT VIA QR (Payment 1/2)${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# Calculate payment amount (half of target, or half of redeemed if target is 0)
if [ "$TARGET" == "0" ] || [ -z "$TARGET" ] || [ "$TARGET" == "null" ]; then
    PAYMENT1_AMOUNT=$(echo "scale=0; $REDEEMED_TOTAL / 2" | bc)
else
    PAYMENT1_AMOUNT=$(echo "scale=0; $TARGET / 2" | bc)
fi
PAYMENT1_CENTS=$((PAYMENT1_AMOUNT * 100))

# Get voucher ID
VOUCHER_ID=$(php artisan tinker --execute="
use LBHurtado\\Voucher\\Models\\Voucher;
echo Voucher::where('code', '$VOUCHER_CODE')->first()->id;
" 2>&1 | tail -1)

# Create PaymentRequest
PR1=$(php artisan tinker --execute="
\$pr = App\\Models\\PaymentRequest::create([
    'reference_id' => 'REPAY1-' . now()->timestamp,
    'voucher_id' => $VOUCHER_ID,
    'amount' => $PAYMENT1_CENTS,
    'currency' => 'PHP',
    'status' => 'pending',
    'payer_info' => ['mobile' => '$BORROWER_MOBILE', 'name' => 'Borrower'],
]);
echo json_encode(['id' => \$pr->id, 'ref' => \$pr->reference_id]);
" 2>&1 | tail -1)

PR1_ID=$(echo $PR1 | jq -r '.id')
echo -e "${GREEN}✓ PaymentRequest created${NC} (₱$PAYMENT1_AMOUNT)"
echo ""

# Simulate webhook + confirm
php artisan simulate:deposit $OWNER_EMAIL $PAYMENT1_AMOUNT --sender-name="BORROWER" --force > /dev/null 2>&1
php artisan queue:work --once --stop-when-empty > /dev/null 2>&1

SMS_URL1=$(php artisan tinker --execute="
\$pr = App\\Models\\PaymentRequest::find($PR1_ID);
echo URL::signedRoute('pay.confirm', ['paymentRequest' => \$pr->reference_id], now()->addHour());
" 2>&1 | tail -1)

curl -s -L -o /dev/null "$SMS_URL1"
echo -e "${GREEN}✓ Payment 1 confirmed${NC}"
echo ""

# Get state after payment 1
STATE_P1=$(php artisan tinker --execute="
use LBHurtado\\Voucher\\Models\\Voucher;
\$v = Voucher::where('code', '$VOUCHER_CODE')->first();
echo json_encode([
    'cash' => \$v->cash->balanceFloat,
    'paid_total' => \$v->getPaidTotal(),
    'remaining' => \$v->getRemaining(),
]);
" 2>&1 | tail -1)

CASH_P1=$(echo $STATE_P1 | jq -r '.cash')
PAID_P1=$(echo $STATE_P1 | jq -r '.paid_total')
REMAINING_P1=$(echo $STATE_P1 | jq -r '.remaining')

printf "%-25s %15s\n" "Voucher Cash:" "₱$CASH_P1"
printf "%-25s %15s / ₱$TARGET\n" "Paid Total:" "₱$PAID_P1"
printf "%-25s %15s\n" "Remaining:" "₱$REMAINING_P1"
echo ""

###############################################################################
# PHASE 2: Second Payment (Full Repayment)
###############################################################################
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}  PHASE 2: LOAN REPAYMENT VIA QR (Payment 2/2)${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

PAYMENT2_CENTS=$((PAYMENT1_AMOUNT * 100))  # Same as first

PR2=$(php artisan tinker --execute="
\$pr = App\\Models\\PaymentRequest::create([
    'reference_id' => 'REPAY2-' . now()->timestamp,
    'voucher_id' => $VOUCHER_ID,
    'amount' => $PAYMENT2_CENTS,
    'currency' => 'PHP',
    'status' => 'pending',
    'payer_info' => ['mobile' => '$BORROWER_MOBILE', 'name' => 'Borrower'],
]);
echo json_encode(['id' => \$pr->id]);
" 2>&1 | tail -1)

PR2_ID=$(echo $PR2 | jq -r '.id')
echo -e "${GREEN}✓ PaymentRequest created${NC} (₱$PAYMENT1_AMOUNT)"
echo ""

php artisan simulate:deposit $OWNER_EMAIL $PAYMENT1_AMOUNT --sender-name="BORROWER" --force > /dev/null 2>&1
php artisan queue:work --once --stop-when-empty > /dev/null 2>&1

SMS_URL2=$(php artisan tinker --execute="
\$pr = App\\Models\\PaymentRequest::find($PR2_ID);
echo URL::signedRoute('pay.confirm', ['paymentRequest' => \$pr->reference_id], now()->addHour());
" 2>&1 | tail -1)

curl -s -L -o /dev/null "$SMS_URL2"
echo -e "${GREEN}✓ Payment 2 confirmed${NC}"
echo ""

###############################################################################
# FINAL STATE
###############################################################################
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  FINAL STATE (Round-Trip Complete)${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

FINAL=$(php artisan tinker --execute="
use LBHurtado\\Voucher\\Models\\Voucher;
\$v = Voucher::where('code', '$VOUCHER_CODE')->first();
echo json_encode([
    'cash' => \$v->cash->balanceFloat,
    'paid_total' => \$v->getPaidTotal(),
    'redeemed_total' => \$v->getRedeemedTotal(),
    'remaining' => \$v->getRemaining(),
]);
" 2>&1 | tail -1)

CASH_FINAL=$(echo $FINAL | jq -r '.cash')
PAID_FINAL=$(echo $FINAL | jq -r '.paid_total')
REDEEMED_FINAL=$(echo $FINAL | jq -r '.redeemed_total')
REMAINING_FINAL=$(echo $FINAL | jq -r '.remaining')

printf "%-25s %15s\n" "Voucher Cash:" "₱$CASH_FINAL"
printf "%-25s %15s / ₱$TARGET  ${GREEN}✓${NC}\n" "Paid Total:" "₱$PAID_FINAL"
printf "%-25s %15s / ₱$TARGET  ${GREEN}✓${NC}\n" "Redeemed Total:" "₱$REDEEMED_FINAL"
printf "%-25s %15s  ${GREEN}✓${NC}\n" "Remaining:" "₱$REMAINING_FINAL"
echo ""

if [ "$REMAINING_FINAL" == "0" ]; then
    echo -e "${GREEN}✓✓✓ LOAN FULLY REPAID - SETTLEMENT COMPLETE ✓✓✓${NC}"
else
    echo -e "${YELLOW}⚠  Partial repayment - ₱$REMAINING_FINAL still remaining${NC}"
fi
echo ""
