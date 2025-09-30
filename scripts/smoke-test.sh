#!/usr/bin/env bash
set -euo pipefail

if ! command -v curl >/dev/null 2>&1; then
  echo "curl is required to run this script." >&2
  exit 1
fi

BASE_URL=${BASE_URL:-http://localhost:8000}
SUPPLIER=${SUPPLIER:-blue-dolphin-charters}
ACTIVITY=${ACTIVITY:-deluxe-am-napali-snorkel}
DATE=${DATE:-$(date +%F)}
TIMESLOT_ID=${TIMESLOT_ID:-timeslot-101}
TRANSPORTATION_ROUTE_ID=${TRANSPORTATION_ROUTE_ID:-route-shuttle}
GUEST_TYPE_ID=${GUEST_TYPE_ID:-214}
GUEST_COUNT=${GUEST_COUNT:-2}
UPGRADE_ID=${UPGRADE_ID:-upgrade-photos}
UPGRADE_QUANTITY=${UPGRADE_QUANTITY:-1}
CHECKLIST_ID=${CHECKLIST_ID:-10}

print_section() {
  echo
  echo "===================================================="
  echo "$1"
  echo "===================================================="
}

print_section "GET transportation"
curl -sS "${BASE_URL}/api/get-transportation.php?supplier=${SUPPLIER}&activity=${ACTIVITY}" || true

print_section "GET upgrades"
curl -sS "${BASE_URL}/api/get-upgrades.php?supplier=${SUPPLIER}&activity=${ACTIVITY}" || true

print_section "POST init-checkout"
POST_BODY=$(cat <<JSON
{
  "supplier": "${SUPPLIER}",
  "activity": "${ACTIVITY}",
  "date": "${DATE}",
  "timeslotId": "${TIMESLOT_ID}",
  "guestCounts": { "${GUEST_TYPE_ID}": ${GUEST_COUNT} },
  "upgrades": { "${UPGRADE_ID}": ${UPGRADE_QUANTITY} },
  "contact": {
    "firstName": "Test",
    "lastName": "Guest",
    "email": "test@example.com",
    "phone": "555-123-4567"
  },
  "transportationRouteId": "${TRANSPORTATION_ROUTE_ID}",
  "checklist": [ { "id": ${CHECKLIST_ID}, "value": "Yes" } ],
  "metadata": {
    "notes": "Smoke test booking"
  }
}
JSON
)

curl -sS \
  -H "Content-Type: application/json" \
  -X POST \
  --data "${POST_BODY}" \
  "${BASE_URL}/api/init-checkout.php" || true

print_section "Smoke test completed"
