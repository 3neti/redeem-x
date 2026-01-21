#!/bin/bash

###############################################################################
# Test NetBank Webhook Classification & Processing
###############################################################################
#
# This script demonstrates how NetBank webhooks are classified and processed:
#
# FLOW 1: TOP-UP (no voucher code)
#   - No PaymentRequest exists
#   - Webhook classified as "top-up"
#   - Direct credit to user wallet (confirmed immediately)
#
# FLOW 2: PAYMENT (with voucher code)
#   - PaymentRequest exists (QR code generated)
#   - Webhook classified as "payment"
#   - Unconfirmed transfer to voucher cash wallet
#   - SMS sent to payer for confirmation
#   - Payer clicks link → transaction confirmed
#
# Usage:
#   ./test-netbank-webhook-flow.sh <amount> [voucher_code] [--mobile=number] [--send-sms]
#
# Examples:
#   ./test-netbank-webhook-flow.sh 100                           # Top-up flow (default mobile)
#   ./test-netbank-webhook-flow.sh 100 4VPJ                      # Payment flow (default mobile)
#   ./test-netbank-webhook-flow.sh 100 --mobile=09171234567      # Top-up with custom mobile
#   ./test-netbank-webhook-flow.sh 100 4VPJ --mobile=09171234567 # Payment with custom mobile
#   ./test-netbank-webhook-flow.sh 100 4VPJ --send-sms           # Actually send SMS (test end-to-end)
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
    echo -e "${RED}Error: Missing amount${NC}"
    echo "Usage: $0 <amount> [voucher_code] [--mobile=number] [--send-sms]"
    echo ""
    echo "Examples:"
    echo "  $0 100                           # Top-up flow (default mobile)"
    echo "  $0 100 4VPJ                      # Payment flow (default mobile)"
    echo "  $0 100 --mobile=09171234567      # Top-up with custom mobile"
    echo "  $0 100 4VPJ --mobile=09171234567 # Payment with custom mobile"
    echo "  $0 100 4VPJ --send-sms           # Actually send SMS (test end-to-end)"
    exit 1
fi

AMOUNT=$1
VOUCHER_CODE=""
PAYER_MOBILE="09173011987"  # Default
SEND_SMS="false"

# Parse remaining arguments
shift
while [ $# -gt 0 ]; do
    case $1 in
        --mobile=*)
            PAYER_MOBILE="${1#*=}"
            shift
            ;;
        --send-sms)
            SEND_SMS="true"
            shift
            ;;
        *)
            if [ -z "$VOUCHER_CODE" ]; then
                VOUCHER_CODE="$1"
            fi
            shift
            ;;
    esac
done

AMOUNT_CENTS=$((AMOUNT * 100))

# Determine flow type
if [ -z "$VOUCHER_CODE" ]; then
    FLOW_TYPE="topup"
else
    FLOW_TYPE="payment"
fi

# Kill queue workers
pkill -f "artisan queue:work" 2>/dev/null || true
sleep 1

###############################################################################
# Header
###############################################################################
echo -e "${CYAN}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║  Test NetBank Webhook Classification & Processing             ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""
if [ "$FLOW_TYPE" == "topup" ]; then
    echo -e "${BLUE}Flow Type:${NC} TOP-UP (direct user wallet credit)"
else
    echo -e "${BLUE}Flow Type:${NC} PAYMENT (unconfirmed voucher credit + SMS)"
    echo -e "${BLUE}Voucher:${NC} $VOUCHER_CODE"
fi
echo -e "${BLUE}Amount:${NC} ₱$AMOUNT"
echo -e "${BLUE}Recipient:${NC} $PAYER_MOBILE"
echo ""

###############################################################################
# TOP-UP FLOW
###############################################################################
if [ "$FLOW_TYPE" == "topup" ]; then
    
    # Get initial state
    STATE=$(php artisan tinker --execute="
\$user = App\Models\User::findByMobile('$PAYER_MOBILE');
if (!\$user) {
    echo 'ERROR: User not found';
    exit(1);
}
\$system = app(LBHurtado\Wallet\Services\SystemUserResolverService::class)->resolve();
echo json_encode([
    'user_email' => \$user->email,
    'user_balance_before' => \$user->wallet ? \$user->wallet->balanceFloat : 0,
    'system_balance_before' => \$system->balanceFloat,
]);
" 2>&1 | tail -1)
    
    if [[ $STATE == *"ERROR"* ]]; then
        echo -e "${RED}✗ User with mobile '$PAYER_MOBILE' not found${NC}"
        echo "  In production, NetBank deposit would be rejected."
        echo "  User must be registered before they can receive deposits."
        exit 1
    fi
    
    USER_EMAIL=$(echo $STATE | jq -r '.user_email')
    USER_BEFORE=$(echo $STATE | jq -r '.user_balance_before')
    SYSTEM_BEFORE=$(echo $STATE | jq -r '.system_balance_before')
    
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  INITIAL STATE${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}User:${NC} $USER_EMAIL ($PAYER_MOBILE)"
    echo ""
    printf "%-25s %15s\n" "System Wallet:" "₱$SYSTEM_BEFORE"
    printf "%-25s %15s\n" "User Wallet:" "₱$USER_BEFORE"
    echo ""
    
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  WEBHOOK SIMULATION${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    # Simulate webhook
    php artisan simulate:deposit $USER_EMAIL $AMOUNT --sender-name="SENDER" --force > /dev/null 2>&1
    
    echo -e "${GREEN}✓ Webhook processed${NC}"
    echo "  Classification: TOP-UP (no PaymentRequest found)"
    echo "  Action: Direct credit to user wallet (confirmed)"
    echo ""
    
    # Get final state
    STATE=$(php artisan tinker --execute="
\$user = App\Models\User::where('email', '$USER_EMAIL')->first();
\$system = app(LBHurtado\Wallet\Services\SystemUserResolverService::class)->resolve();
echo json_encode([
    'user_balance_after' => \$user->wallet ? \$user->wallet->balanceFloat : 0,
    'system_balance_after' => \$system->balanceFloat,
]);
" 2>&1 | tail -1)
    
    USER_AFTER=$(echo $STATE | jq -r '.user_balance_after')
    SYSTEM_AFTER=$(echo $STATE | jq -r '.system_balance_after')
    
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  FINAL STATE${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    printf "%-25s %15s → %15s  ${GREEN}(+₱%s)${NC}\n" "User Wallet:" "₱$USER_BEFORE" "₱$USER_AFTER" "$AMOUNT"
    printf "%-25s %15s → %15s  ${YELLOW}(-₱%s)${NC}\n" "System Wallet:" "₱$SYSTEM_BEFORE" "₱$SYSTEM_AFTER" "$AMOUNT"
    echo ""
    
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  LEDGER SUMMARY${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    printf "%-25s %12s %12s %12s\n" "ACCOUNT" "INITIAL" "DEBIT" "CREDIT"
    printf "%-25s %12s %12s %12s\n" "-------------------------" "------------" "------------" "------------"
    printf "%-25s %12s %12s %12s\n" "System Wallet" "₱$SYSTEM_BEFORE" "₱$AMOUNT" ""
    printf "%-25s %12s %12s %12s\n" "User Wallet" "₱$USER_BEFORE" "" "₱$AMOUNT"
    printf "%-25s %12s %12s %12s\n" "-------------------------" "------------" "------------" "------------"
    printf "%-25s %12s\n" "System Final:" "₱$SYSTEM_AFTER"
    printf "%-25s %12s\n" "User Final:" "₱$USER_AFTER"
    echo ""
    echo -e "${GREEN}✓ TOP-UP FLOW COMPLETED${NC}"
    echo ""
    
###############################################################################
# PAYMENT FLOW
###############################################################################
else
    
    # Get initial state
    STATE=$(php artisan tinker --execute="
\$v = LBHurtado\Voucher\Models\Voucher::where('code', '$VOUCHER_CODE')->first();
if (!\$v) { echo 'ERROR: Voucher not found'; exit(1); }
\$system = app(LBHurtado\Wallet\Services\SystemUserResolverService::class)->resolve();
echo json_encode([
    'voucher_id' => \$v->id,
    'owner_email' => \$v->owner->email,
    'target' => \$v->target_amount,
    'remaining' => \$v->getRemaining(),
    'cash_before' => \$v->cash ? \$v->cash->balanceFloat : 0,
    'owner_balance' => \$v->owner->wallet ? \$v->owner->wallet->balanceFloat : 0,
    'system_before' => \$system->balanceFloat,
]);
" 2>&1 | tail -1)
    
    if [[ $STATE == *"ERROR"* ]]; then
        echo -e "${RED}✗ Voucher '$VOUCHER_CODE' not found${NC}"
        exit 1
    fi
    
    VOUCHER_ID=$(echo $STATE | jq -r '.voucher_id')
    OWNER_EMAIL=$(echo $STATE | jq -r '.owner_email')
    TARGET=$(echo $STATE | jq -r '.target')
    REMAINING=$(echo $STATE | jq -r '.remaining')
    CASH_BEFORE=$(echo $STATE | jq -r '.cash_before')
    OWNER_BAL=$(echo $STATE | jq -r '.owner_balance')
    SYSTEM_BEFORE=$(echo $STATE | jq -r '.system_before')
    
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  INITIAL STATE${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}Voucher:${NC} $VOUCHER_CODE (Owner: $OWNER_EMAIL)"
    echo "  Target: ₱$TARGET | Remaining: ₱$REMAINING"
    echo ""
    printf "%-25s %15s\n" "System Wallet:" "₱$SYSTEM_BEFORE"
    printf "%-25s %15s\n" "Owner Wallet:" "₱$OWNER_BAL"
    printf "%-25s %15s\n" "Voucher Cash:" "₱$CASH_BEFORE"
    echo ""
    
    # Create PaymentRequest
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  STEP 1: Create PaymentRequest (QR Generation)${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    PR=$(php artisan tinker --execute="
\$pr = App\Models\PaymentRequest::create([
    'reference_id' => 'TEST-' . now()->timestamp,
    'voucher_id' => $VOUCHER_ID,
    'amount' => $AMOUNT_CENTS,
    'currency' => 'PHP',
    'status' => 'pending',
    'payer_info' => ['mobile' => '$PAYER_MOBILE', 'name' => 'TEST PAYER'],
]);
echo json_encode(['id' => \$pr->id, 'ref' => \$pr->reference_id]);
" 2>&1 | tail -1)
    
    PR_ID=$(echo $PR | jq -r '.id')
    PR_REF=$(echo $PR | jq -r '.ref')
    
    echo -e "${GREEN}✓ PaymentRequest created${NC}"
    echo "  ID: $PR_ID | Reference: $PR_REF"
    echo "  Amount: ₱$AMOUNT | Payer: $PAYER_MOBILE"
    echo ""
    
    # Simulate webhook
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  STEP 2: Webhook Simulation (Payment Detection)${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    php artisan simulate:deposit $OWNER_EMAIL $AMOUNT --sender-name="PAYER" --force > /dev/null 2>&1
    
    echo -e "${GREEN}✓ Webhook processed${NC}"
    echo "  Classification: PAYMENT (matched PaymentRequest #$PR_ID)"
    echo "  Action: Unconfirmed transfer to voucher cash wallet"
    echo ""
    
    # Process SMS
    php artisan queue:work --once --stop-when-empty > /dev/null 2>&1
    
    # Get SMS info
    SMS=$(php artisan tinker --execute="
\$pr = App\Models\PaymentRequest::find($PR_ID);
\$url = URL::signedRoute('pay.confirm', ['paymentRequest' => \$pr->id], now()->addHour());
echo json_encode(['url' => \$url, 'msg' => 'Payment of ₱' . \$pr->getAmountInMajorUnits() . ' detected for voucher ' . \$pr->voucher->code . '. Click to confirm: ' . \$url]);
" 2>&1 | tail -1)
    
    SMS_URL=$(echo $SMS | jq -r '.url')
    SMS_MSG=$(echo $SMS | jq -r '.msg')
    
    if [ "$SEND_SMS" == "true" ]; then
        echo -e "${CYAN}📱 Sending REAL SMS to $PAYER_MOBILE...${NC}"
        
        # Actually send the SMS via Notification directly
        SMS_RESULT=$(php artisan tinker --execute="
use Illuminate\Support\Facades\Notification;
use App\Notifications\PaymentConfirmationNotification;
\$pr = App\Models\PaymentRequest::find($PR_ID);
try {
    \$anon = Notification::route('engage_spark', '$PAYER_MOBILE');
    Notification::sendNow(\$anon, new PaymentConfirmationNotification(\$pr));
    echo 'SUCCESS: SMS sent via EngageSpark (sync)';
} catch (\Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage();
}
" 2>&1)
        
        if [[ $SMS_RESULT == *"SUCCESS"* ]]; then
            echo -e "  ${GREEN}✓ SMS sent successfully via EngageSpark${NC}"
            echo "  To: $PAYER_MOBILE"
            echo "  Message: \"$SMS_MSG\""
            echo ""
            echo -e "  ${YELLOW}⚠️  Check your phone for the actual SMS!${NC}"
        else
            echo -e "  ${RED}✗ SMS sending failed${NC}"
            echo "  Error: $SMS_RESULT"
        fi
    else
        echo -e "${CYAN}📱 SMS (simulated - not actually sent):${NC}"
        echo "  To: $PAYER_MOBILE"
        echo "  Message: \"$SMS_MSG\""
        echo ""
        echo -e "  ${YELLOW}Tip: Use --send-sms to actually send the SMS${NC}"
    fi
    echo ""
    
    # Check unconfirmed state
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  STEP 3: Transaction Status (UNCONFIRMED)${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    UNCONF=$(php artisan tinker --execute="
\$pr = App\Models\PaymentRequest::find($PR_ID);
\$tx = Bavix\Wallet\Models\Transaction::where('uuid', \$pr->meta['transaction_uuid'])->first();
\$v = LBHurtado\Voucher\Models\Voucher::find($VOUCHER_ID);
\$system = app(LBHurtado\Wallet\Services\SystemUserResolverService::class)->resolve();
echo json_encode([
    'tx_uuid' => \$tx->uuid,
    'tx_confirmed' => \$tx->confirmed,
    'cash_unconf' => \$v->cash->balanceFloat,
    'system_unconf' => \$system->balanceFloat,
]);
" 2>&1 | tail -1)
    
    TX_UUID=$(echo $UNCONF | jq -r '.tx_uuid')
    TX_CONF=$(echo $UNCONF | jq -r '.tx_confirmed')
    CASH_UNCONF=$(echo $UNCONF | jq -r '.cash_unconf')
    SYSTEM_UNCONF=$(echo $UNCONF | jq -r '.system_unconf')
    
    echo -e "${GREEN}Transaction:${NC} $TX_UUID"
    if [ "$TX_CONF" == "false" ]; then
        echo -e "  Status: ${YELLOW}UNCONFIRMED ⏳${NC}"
    else
        echo -e "  Status: ${GREEN}CONFIRMED ✓${NC}"
    fi
    echo ""
    printf "%-25s %15s  ${YELLOW}(-₱%s)${NC}\n" "System Wallet:" "₱$SYSTEM_UNCONF" "$AMOUNT"
    printf "%-25s %15s  ${YELLOW}(+₱%s unconfirmed)${NC}\n" "Voucher Cash:" "₱$CASH_UNCONF" "$AMOUNT"
    echo ""
    
    # Click SMS link
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  STEP 4: Click SMS Confirmation Link${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    HTTP_STATUS=$(curl -s -L -w "%{http_code}" -o /dev/null "$SMS_URL")
    
    if [ "$HTTP_STATUS" == "200" ]; then
        echo -e "${GREEN}✓ Confirmation successful (HTTP $HTTP_STATUS)${NC}"
    else
        echo -e "${RED}✗ Confirmation failed (HTTP $HTTP_STATUS)${NC}"
        exit 1
    fi
    echo ""
    
    # Final state
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  FINAL STATE (Transaction Confirmed)${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    FINAL=$(php artisan tinker --execute="
\$tx = Bavix\Wallet\Models\Transaction::where('uuid', '$TX_UUID')->first();
\$v = LBHurtado\Voucher\Models\Voucher::find($VOUCHER_ID);
\$system = app(LBHurtado\Wallet\Services\SystemUserResolverService::class)->resolve();
echo json_encode([
    'tx_confirmed' => \$tx->confirmed,
    'cash_after' => \$v->cash->balanceFloat,
    'system_after' => \$system->balanceFloat,
    'remaining' => \$v->getRemaining(),
]);
" 2>&1 | tail -1)
    
    TX_CONF_FINAL=$(echo $FINAL | jq -r '.tx_confirmed')
    CASH_AFTER=$(echo $FINAL | jq -r '.cash_after')
    SYSTEM_AFTER=$(echo $FINAL | jq -r '.system_after')
    REMAINING_AFTER=$(echo $FINAL | jq -r '.remaining')
    
    echo -e "${GREEN}Transaction:${NC} $TX_UUID"
    if [ "$TX_CONF_FINAL" == "true" ]; then
        echo -e "  Status: ${GREEN}CONFIRMED ✓${NC}"
    else
        echo -e "  Status: ${RED}STILL UNCONFIRMED${NC}"
    fi
    echo ""
    printf "%-25s %15s → %15s\n" "System Wallet:" "₱$SYSTEM_BEFORE" "₱$SYSTEM_AFTER"
    printf "%-25s %15s → %15s  ${GREEN}(+₱%s confirmed)${NC}\n" "Voucher Cash:" "₱$CASH_BEFORE" "₱$CASH_AFTER" "$AMOUNT"
    echo "  Remaining: ₱$REMAINING_AFTER"
    echo ""
    
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}  LEDGER SUMMARY${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    printf "%-25s %12s %12s %12s\n" "ACCOUNT" "INITIAL" "DEBIT" "CREDIT"
    printf "%-25s %12s %12s %12s\n" "-------------------------" "------------" "------------" "------------"
    printf "%-25s %12s %12s %12s\n" "System Wallet" "₱$SYSTEM_BEFORE" "₱$AMOUNT" ""
    printf "%-25s %12s %12s %12s\n" "Owner Wallet" "₱$OWNER_BAL" "" ""
    printf "%-25s %12s %12s %12s\n" "Voucher Cash ($VOUCHER_CODE)" "₱$CASH_BEFORE" "" "₱$AMOUNT"
    printf "%-25s %12s %12s %12s\n" "-------------------------" "------------" "------------" "------------"
    printf "%-25s %12s\n" "System Final:" "₱$SYSTEM_AFTER"
    printf "%-25s %12s\n" "Voucher Final:" "₱$CASH_AFTER"
    echo ""
    echo -e "${GREEN}✓ PAYMENT FLOW COMPLETED${NC}"
    echo ""
    
fi

echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  ✓ TEST COMPLETED SUCCESSFULLY${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
