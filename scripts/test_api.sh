#!/bin/bash
API="http://127.0.0.1:8001/api"

echo "=== TEST 1: List Statement of Accounts ==="
curl -sS "$API/statement-of-accounts" | jq '.data | length'

echo ""
echo "=== TEST 2: Get Single Statement of Account ==="
curl -sS "$API/statement-of-accounts/2" | jq '.data.id'

echo ""
echo "=== TEST 3: Create Dividend via declareDividend endpoint ==="
curl -sS -X POST "$API/statement-of-accounts/declarations/dividend" \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": 3,
    "total_amount": 5000,
    "transaction_date": "2026-07-04",
    "notes": "Test dividend declaration",
    "status": "Pending"
  }' | jq '.data | length'

echo ""
echo "=== TEST 4: Create Withdrawal ==="
curl -sS -X POST "$API/statement-of-accounts" \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": 3,
    "investment_id": 1,
    "investor_id": 1,
    "transaction_type": "Withdrawal",
    "amount": 250,
    "status": "Pending",
    "transaction_date": "2026-07-04",
    "notes": "Test withdrawal"
  }' | jq '.data.id'

echo ""
echo "=== TEST 5: Update Dividend ==="
curl -sS -X PUT "$API/statement-of-accounts/2" \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": 3,
    "investment_id": 1,
    "investor_id": 1,
    "transaction_type": "Dividend",
    "amount": 1500,
    "status": "Paid",
    "transaction_date": "2026-07-03",
    "notes": "Updated dividend"
  }' | jq '.data.status'

echo ""
echo "=== TEST 6: List All (should have multiple records) ==="
curl -sS "$API/statement-of-accounts" | jq '.data | length'

echo ""
echo "All tests completed!"
