#!/usr/bin/env bash

# Test Workflow Verification Script
# Tests the complete Dividend and Withdrawal registration workflow

set -e

BASE_URL="http://127.0.0.1:8080/api"

echo "=== Equity System - Workflow Verification ==="
echo ""

# Test 1: Register a Dividend
echo "TEST 1: Register a Dividend"
echo "============================="
DIVIDEND_JSON=$(cat <<'EOF'
{
  "company_id": 8,
  "investor_id": 1,
  "amount": 3000,
  "status": "Pending",
  "transaction_date": "2026-07-03",
  "transaction_type": "Dividend",
  "notes": "Test dividend registration"
}
EOF
)

echo "Payload:"
echo "$DIVIDEND_JSON" | jq .
echo ""

DIVIDEND_RESPONSE=$(curl -s -X POST "$BASE_URL/statement-of-accounts" \
  -H "Content-Type: application/json" \
  -d "$DIVIDEND_JSON")

echo "Response:"
echo "$DIVIDEND_RESPONSE" | jq .
DIVIDEND_ID=$(echo "$DIVIDEND_RESPONSE" | jq -r '.data.id')
echo "Dividend ID: $DIVIDEND_ID"
echo ""

# Test 2: Register a Withdrawal
echo "TEST 2: Register a Withdrawal"
echo "=============================="
WITHDRAWAL_JSON=$(cat <<'EOF'
{
  "company_id": 8,
  "investor_id": 1,
  "amount": 500,
  "status": "Pending",
  "transaction_date": "2026-07-03",
  "transaction_type": "Withdrawal",
  "bank_name": "Test Bank",
  "transfer_reference": "TEST001",
  "notes": "Test withdrawal registration"
}
EOF
)

echo "Payload:"
echo "$WITHDRAWAL_JSON" | jq .
echo ""

WITHDRAWAL_RESPONSE=$(curl -s -X POST "$BASE_URL/statement-of-accounts" \
  -H "Content-Type: application/json" \
  -d "$WITHDRAWAL_JSON")

echo "Response:"
echo "$WITHDRAWAL_RESPONSE" | jq .
WITHDRAWAL_ID=$(echo "$WITHDRAWAL_RESPONSE" | jq -r '.data.id')
echo "Withdrawal ID: $WITHDRAWAL_ID"
echo ""

# Test 3: Verify in Partner Ledger
echo "TEST 3: Verify in Partner Statement of Account (Ledger)"
echo "======================================================"
LEDGER_RESPONSE=$(curl -s -X GET "$BASE_URL/ledger/investor/1")
echo "Ledger Response (last 3 transactions):"
echo "$LEDGER_RESPONSE" | jq '.ledger[-3:]'
echo ""

echo "Ledger Summary:"
echo "$LEDGER_RESPONSE" | jq '.summary'
echo ""

# Test 4: Verify Transaction Properties
echo "TEST 4: Transaction Properties Verification"
echo "=========================================="

# Check Dividend
echo "Dividend (ID $DIVIDEND_ID):"
curl -s -X GET "$BASE_URL/statement-of-accounts/$DIVIDEND_ID" | jq '.data | {id, company_id, investor_id, transaction_type, amount, status, investment_id}'
echo ""

# Check Withdrawal
echo "Withdrawal (ID $WITHDRAWAL_ID):"
curl -s -X GET "$BASE_URL/statement-of-accounts/$WITHDRAWAL_ID" | jq '.data | {id, company_id, investor_id, transaction_type, amount, status, investment_id, bank_name, transfer_reference}'
echo ""

echo "=== All Tests Complete ==="
