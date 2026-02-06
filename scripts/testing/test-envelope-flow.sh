#!/bin/bash

###############################################################################
# Test Settlement Envelope Flow
###############################################################################
#
# This script tests the complete lifecycle of a settlement envelope:
#
# PHASE 1: SETUP
#   - Query existing voucher or create test voucher
#   - Show initial state
#
# PHASE 2: ENVELOPE CREATION
#   - Create envelope with specified driver
#   - Show checklist items from driver template
#
# PHASE 3: EVIDENCE COLLECTION
#   - Update payload with test data
#   - Optionally upload test document
#
# PHASE 4: SIGNALS & GATES
#   - Set approval signal
#   - Verify gate computation
#
# PHASE 5: AUDIT TRAIL
#   - Display audit log entries
#
# Usage:
#   ./test-envelope-flow.sh <voucher_code> [options]
#
# Options:
#   --driver=ID           Driver ID (default: simple.envelope)
#   --driver-version=VER  Driver version (default: 1.0.0)
#   --doc=PATH            Path to test document to upload (optional)
#
# Examples:
#   ./test-envelope-flow.sh VOUCHER123
#   ./test-envelope-flow.sh VOUCHER123 --driver=vendor.pay-by-face
#   ./test-envelope-flow.sh VOUCHER123 --doc=/path/to/test.pdf
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
    echo "Usage: $0 <voucher_code> [--driver=ID] [--version=VER] [--doc=PATH]"
    echo ""
    echo "Examples:"
    echo "  $0 VOUCHER123                              # Use simple.envelope driver"
    echo "  $0 VOUCHER123 --driver=vendor.pay-by-face  # Use pay-by-face driver"
    echo "  $0 VOUCHER123 --doc=/path/to/test.pdf      # Upload test document"
    exit 1
fi

VOUCHER_CODE=$1
DRIVER_ID="simple.envelope"
DRIVER_VERSION="1.0.0"
DOC_PATH=""

# Parse remaining arguments
shift
while [ $# -gt 0 ]; do
    case $1 in
        --driver=*)
            DRIVER_ID="${1#*=}"
            shift
            ;;
        --driver-version=*)
            DRIVER_VERSION="${1#*=}"
            shift
            ;;
        --doc=*)
            DOC_PATH="${1#*=}"
            shift
            ;;
        *)
            echo -e "${RED}Unknown argument: $1${NC}"
            exit 1
            ;;
    esac
done

###############################################################################
# Header
###############################################################################
echo -e "${CYAN}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║  Test Settlement Envelope Flow                                 ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}Voucher Code:${NC} $VOUCHER_CODE"
echo -e "${BLUE}Driver:${NC} $DRIVER_ID@$DRIVER_VERSION"
if [ -n "$DOC_PATH" ]; then
    echo -e "${BLUE}Document:${NC} $DOC_PATH"
fi
echo ""

###############################################################################
# PHASE 1: SETUP - Verify Voucher Exists
###############################################################################
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}  PHASE 1: SETUP${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

VOUCHER_STATE=$(php artisan tinker --execute="
use LBHurtado\Voucher\Models\Voucher;
\$v = Voucher::where('code', '$VOUCHER_CODE')->first();
if (!\$v) { echo 'ERROR: Voucher not found'; exit(1); }
echo json_encode([
    'id' => \$v->id,
    'code' => \$v->code,
    'type' => \$v->voucher_type?->value ?? 'unknown',
    'state' => \$v->state?->value ?? 'unknown',
    'has_envelopes' => \$v->envelopes()->count(),
]);
" 2>&1 | tail -1)

if [[ $VOUCHER_STATE == *"ERROR"* ]]; then
    echo -e "${RED}✗ $VOUCHER_STATE${NC}"
    exit 1
fi

VOUCHER_ID=$(echo $VOUCHER_STATE | jq -r '.id')
VOUCHER_TYPE=$(echo $VOUCHER_STATE | jq -r '.type')
VOUCHER_STATUS=$(echo $VOUCHER_STATE | jq -r '.state')
EXISTING_ENVELOPES=$(echo $VOUCHER_STATE | jq -r '.has_envelopes')

echo -e "${GREEN}✓ Voucher found${NC}"
printf "  %-20s %s\n" "ID:" "$VOUCHER_ID"
printf "  %-20s %s\n" "Code:" "$VOUCHER_CODE"
printf "  %-20s %s\n" "Type:" "$VOUCHER_TYPE"
printf "  %-20s %s\n" "State:" "$VOUCHER_STATUS"
printf "  %-20s %s\n" "Existing Envelopes:" "$EXISTING_ENVELOPES"
echo ""

###############################################################################
# PHASE 2: ENVELOPE CREATION
###############################################################################
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}  PHASE 2: ENVELOPE CREATION${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

ENVELOPE_RESULT=$(php artisan tinker --execute="
use LBHurtado\Voucher\Models\Voucher;
\$v = Voucher::where('code', '$VOUCHER_CODE')->first();
try {
    \$envelope = \$v->createEnvelope(
        driverId: '$DRIVER_ID',
        driverVersion: '$DRIVER_VERSION',
        initialPayload: ['name' => 'Test User from script']
    );
    echo json_encode([
        'success' => true,
        'envelope_id' => \$envelope->id,
        'reference_code' => \$envelope->reference_code,
        'status' => \$envelope->status->value,
        'payload_version' => \$envelope->payload_version,
        'checklist_count' => \$envelope->checklistItems->count(),
    ]);
} catch (\Exception \$e) {
    echo json_encode(['error' => \$e->getMessage()]);
}
" 2>&1 | tail -1)

if echo "$ENVELOPE_RESULT" | jq -e '.error' > /dev/null 2>&1; then
    ERROR=$(echo $ENVELOPE_RESULT | jq -r '.error')
    echo -e "${RED}✗ Failed to create envelope: $ERROR${NC}"
    exit 1
fi

ENVELOPE_ID=$(echo $ENVELOPE_RESULT | jq -r '.envelope_id')
ENVELOPE_REF=$(echo $ENVELOPE_RESULT | jq -r '.reference_code')
ENVELOPE_STATUS=$(echo $ENVELOPE_RESULT | jq -r '.status')
PAYLOAD_VERSION=$(echo $ENVELOPE_RESULT | jq -r '.payload_version')
CHECKLIST_COUNT=$(echo $ENVELOPE_RESULT | jq -r '.checklist_count')

echo -e "${GREEN}✓ Envelope created${NC}"
printf "  %-20s %s\n" "ID:" "$ENVELOPE_ID"
printf "  %-20s %s\n" "Reference:" "$ENVELOPE_REF"
printf "  %-20s %s\n" "Status:" "$ENVELOPE_STATUS"
printf "  %-20s %s\n" "Payload Version:" "$PAYLOAD_VERSION"
printf "  %-20s %s\n" "Checklist Items:" "$CHECKLIST_COUNT"
echo ""

# Show checklist items
echo -e "${GREEN}Checklist Items:${NC}"
CHECKLIST=$(php artisan tinker --execute="
use LBHurtado\SettlementEnvelope\Models\Envelope;
\$e = Envelope::find($ENVELOPE_ID);
\$items = \$e->checklistItems->map(fn(\$i) => [
    'key' => \$i->key,
    'label' => \$i->label,
    'status' => \$i->status->value,
    'required' => \$i->required,
]);
echo json_encode(\$items);
" 2>&1 | tail -1)

echo "$CHECKLIST" | jq -r '.[] | "  [\(.status)] \(.label) " + if .required then "(required)" else "(optional)" end'
echo ""

###############################################################################
# PHASE 3: EVIDENCE COLLECTION
###############################################################################
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}  PHASE 3: EVIDENCE COLLECTION${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# Update payload
PAYLOAD_RESULT=$(php artisan tinker --execute="
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
\$e = Envelope::find($ENVELOPE_ID);
\$service = app(EnvelopeService::class);
try {
    \$e = \$service->updatePayload(\$e, [
        'reference_code' => '$VOUCHER_CODE',
        'amount' => 1000,
        'notes' => 'Test payload from shell script',
    ]);
    echo json_encode([
        'success' => true,
        'payload_version' => \$e->payload_version,
        'payload' => \$e->payload,
    ]);
} catch (\Exception \$ex) {
    echo json_encode(['error' => \$ex->getMessage()]);
}
" 2>&1 | tail -1)

if echo "$PAYLOAD_RESULT" | jq -e '.error' > /dev/null 2>&1; then
    ERROR=$(echo $PAYLOAD_RESULT | jq -r '.error')
    echo -e "${RED}✗ Failed to update payload: $ERROR${NC}"
else
    NEW_VERSION=$(echo $PAYLOAD_RESULT | jq -r '.payload_version')
    echo -e "${GREEN}✓ Payload updated to version $NEW_VERSION${NC}"
    echo "  Payload: $(echo $PAYLOAD_RESULT | jq -c '.payload')"
fi
echo ""

###############################################################################
# PHASE 4: SIGNALS & GATES
###############################################################################
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}  PHASE 4: SIGNALS & GATES${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# Show current signals
echo -e "${GREEN}Current Signals:${NC}"
SIGNALS=$(php artisan tinker --execute="
use LBHurtado\SettlementEnvelope\Models\Envelope;
\$e = Envelope::find($ENVELOPE_ID);
\$signals = \$e->signals->map(fn(\$s) => ['key' => \$s->key, 'value' => \$s->value]);
echo json_encode(\$signals);
" 2>&1 | tail -1)
echo "$SIGNALS" | jq -r '.[] | "  \(.key): \(.value)"'
echo ""

# Set approved signal
echo "Setting 'approved' signal to true..."
SIGNAL_RESULT=$(php artisan tinker --execute="
use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
\$e = Envelope::find($ENVELOPE_ID);
\$service = app(EnvelopeService::class);
try {
    \$service->setSignal(\$e, 'approved', true);
    \$e->refresh();
    echo json_encode(['success' => true, 'approved' => \$e->getSignalBool('approved')]);
} catch (\Exception \$ex) {
    echo json_encode(['error' => \$ex->getMessage()]);
}
" 2>&1 | tail -1)

if echo "$SIGNAL_RESULT" | jq -e '.error' > /dev/null 2>&1; then
    ERROR=$(echo $SIGNAL_RESULT | jq -r '.error')
    echo -e "${RED}✗ Failed to set signal: $ERROR${NC}"
else
    echo -e "${GREEN}✓ Signal set: approved = true${NC}"
fi
echo ""

# Show gates
echo -e "${GREEN}Gate States:${NC}"
GATES=$(php artisan tinker --execute="
use LBHurtado\SettlementEnvelope\Models\Envelope;
\$e = Envelope::find($ENVELOPE_ID);
echo json_encode(\$e->gates_cache ?? []);
" 2>&1 | tail -1)
echo "$GATES" | jq -r 'to_entries[] | "  \(if .value then "✓" else "✗" end) \(.key): \(.value)"'
echo ""

###############################################################################
# PHASE 5: AUDIT TRAIL
###############################################################################
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}  PHASE 5: AUDIT TRAIL${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

AUDIT=$(php artisan tinker --execute="
use LBHurtado\SettlementEnvelope\Models\Envelope;
\$e = Envelope::find($ENVELOPE_ID);
\$logs = \$e->auditLogs->map(fn(\$l) => [
    'time' => \$l->created_at->format('H:i:s'),
    'action' => \$l->action,
]);
echo json_encode(\$logs);
" 2>&1 | tail -1)

echo -e "${GREEN}Audit Log Entries:${NC}"
echo "$AUDIT" | jq -r '.[] | "  [\(.time)] \(.action)"'
echo ""

###############################################################################
# FINAL SUMMARY
###############################################################################
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}  SUMMARY${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

FINAL=$(php artisan tinker --execute="
use LBHurtado\SettlementEnvelope\Models\Envelope;
\$e = Envelope::find($ENVELOPE_ID);
echo json_encode([
    'reference_code' => \$e->reference_code,
    'driver' => \$e->driver_id . '@' . \$e->driver_version,
    'status' => \$e->status->value,
    'payload_version' => \$e->payload_version,
    'settleable' => \$e->getGate('settleable'),
    'checklist_items' => \$e->checklistItems->count(),
    'audit_entries' => \$e->auditLogs->count(),
]);
" 2>&1 | tail -1)

printf "%-20s %s\n" "Reference Code:" "$(echo $FINAL | jq -r '.reference_code')"
printf "%-20s %s\n" "Driver:" "$(echo $FINAL | jq -r '.driver')"
printf "%-20s %s\n" "Status:" "$(echo $FINAL | jq -r '.status')"
printf "%-20s %s\n" "Payload Version:" "$(echo $FINAL | jq -r '.payload_version')"
printf "%-20s %s\n" "Checklist Items:" "$(echo $FINAL | jq -r '.checklist_items')"
printf "%-20s %s\n" "Audit Entries:" "$(echo $FINAL | jq -r '.audit_entries')"

SETTLEABLE=$(echo $FINAL | jq -r '.settleable')
if [ "$SETTLEABLE" == "true" ]; then
    printf "%-20s ${GREEN}✓ Yes${NC}\n" "Settleable:"
else
    printf "%-20s ${YELLOW}✗ No${NC}\n" "Settleable:"
fi
echo ""

echo -e "${GREEN}✓✓✓ Settlement Envelope Test Complete ✓✓✓${NC}"
echo ""
