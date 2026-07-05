# Register Dividend & Withdrawal Workflows - Documentation

## Business Rules

### General
- Both Dividend and Withdrawal are **manual registrations** by the administrator
- Administrator selects **Company** and **Partner** only
- Backend **automatically determines** the correct investment
- **No investment_id** required or exposed in UI
- Transactions stored with **single record** (no duplication)
- **Ledger generated dynamically** from transactions

### Dividend
- **Credit** to the partner's account
- Fields: Company, Partner, Amount, Date, Status, Notes
- Automatically adds to partner's balance

### Withdrawal  
- **Debit** from the partner's account
- Fields: Company, Partner, Amount, Date, Status, Bank Name (optional), Transfer Reference (optional), Notes, Attachments (optional)
- Automatically subtracts from partner's balance

## Backend API Specification

### Register Dividend
**Endpoint:** `POST /api/statement-of-accounts`

**Required Fields:**
```json
{
  "company_id": 8,
  "investor_id": 1,
  "amount": 3000,
  "status": "Pending",
  "transaction_date": "2026-07-03",
  "transaction_type": "Dividend",
  "notes": "Dividend payment"
}
```

**Response (201 Created):**
```json
{
  "data": {
    "id": 20,
    "company_id": 8,
    "investor_id": 1,
    "investment_id": null,
    "transaction_type": "Dividend",
    "amount": "3000.00",
    "status": "Pending",
    "transaction_date": "2026-07-03",
    "notes": "Dividend payment",
    "created_at": "2026-07-03T...",
    "updated_at": "2026-07-03T..."
  }
}
```

### Register Withdrawal
**Endpoint:** `POST /api/statement-of-accounts`

**Required Fields:**
```json
{
  "company_id": 8,
  "investor_id": 1,
  "amount": 1200,
  "status": "Pending",
  "transaction_date": "2026-07-03",
  "transaction_type": "Withdrawal",
  "notes": "Withdrawal request"
}
```

**Optional Fields:**
```json
{
  "bank_name": "Test Bank",
  "transfer_reference": "WF001",
  "attachments": [file1, file2, ...]
}
```

**Response (201 Created):** Same structure as Dividend

### Get Partner Statement of Account (Ledger)
**Endpoint:** `GET /api/ledger/investor/{investor_id}`

**Response:**
```json
{
  "investor": {
    "id": 1,
    "name": "mahmoud"
  },
  "ledger": [
    {
      "id": 20,
      "date": "2026-07-03",
      "company": "carfix",
      "transaction_type": "Dividend",
      "reference": "REF-000020",
      "description": "Dividend Payment from carfix",
      "credit": 3000,
      "debit": 0,
      "running_balance": 20001.25,
      "status": "Pending"
    },
    {
      "id": 21,
      "date": "2026-07-03",
      "company": "carfix",
      "transaction_type": "Withdrawal",
      "reference": "REF-000021",
      "description": "Withdrawal Request to Workflow Test Bank",
      "credit": 0,
      "debit": 1200,
      "running_balance": 18801.25,
      "status": "Pending"
    }
  ],
  "summary": {
    "total_credits": 43501.75,
    "total_debits": 4450.5,
    "balance": 39051.25,
    "transaction_count": 17
  }
}
```

## Validation Rules

### Backend Validation (StatementOfAccountController)
```php
'company_id'         => ['required', 'integer', Rule::exists('companies', 'id')],
'investor_id'        => ['required', 'integer', Rule::exists('investors', 'id')],
'investment_id'      => ['nullable', 'integer', Rule::exists('investments', 'id')],
'transaction_type'   => ['required', 'string', Rule::in(['Dividend', 'Withdrawal'])],
'amount'             => 'required|numeric|min:0.01',
'status'             => ['required', 'string', Rule::in(['Pending', 'Paid'])],
'transaction_date'   => 'required|date',
'notes'              => 'nullable|string',
'bank_name'          => 'nullable|string',
'transfer_reference' => 'nullable|string',
'attachments'        => ['nullable', 'array'],
'attachments.*'      => ['nullable', 'file', 'max:10240']
```

**Constraints:**
- `investment_id` is **NOT required** - auto-determined by backend
- `attachments` only allowed for Withdrawal transactions
- Each attachment: max 10MB

## Error Responses

### Missing Required Field
**Status:** 422 Unprocessable Content
```json
{
  "message": "The investor id field is required.",
  "errors": {
    "investor_id": ["The investor id field is required."]
  }
}
```

### Invalid Company/Partner
**Status:** 422 Unprocessable Content
```json
{
  "message": "The selected company id is invalid.",
  "errors": {
    "company_id": ["The selected company id is invalid."]
  }
}
```

### Attachment on Dividend
**Status:** 422 Unprocessable Content
```json
{
  "message": "Attachments are only allowed for withdrawal transactions."
}
```

## Frontend Form Requirements

### Dividend Form
- **Company** (required, dropdown)
- **Partner** (required, dropdown)
- **Amount** (required, number, min 0.01)
- **Date** (required, date picker, default today)
- **Status** (required, dropdown: Pending, Paid)
- **Notes** (optional, text area)
- **Submit Button** (disabled until all required fields filled)

### Withdrawal Form
- **Company** (required, dropdown)
- **Partner** (required, dropdown)
- **Amount** (required, number, min 0.01)
- **Date** (required, date picker, default today)
- **Status** (required, dropdown: Pending, Paid)
- **Bank Name** (optional, text input)
- **Transfer Reference** (optional, text input)
- **Notes** (optional, text area)
- **Attachments** (optional, multiple file upload, max 10MB each)
- **Submit Button** (disabled until all required fields filled)

## Workflow Verification

### Test Case 1: Register Dividend
```
Request:
POST /api/statement-of-accounts
{
  "company_id": 8,
  "investor_id": 1,
  "amount": 3000,
  "status": "Pending",
  "transaction_date": "2026-07-03",
  "transaction_type": "Dividend",
  "notes": "Workflow Test Dividend"
}

Response: 201 Created (ID: 20)

Verification:
GET /api/ledger/investor/1
→ Shows ledger entry with credit: 3000, debit: 0
→ Running balance updated correctly
✅ PASSED
```

### Test Case 2: Register Withdrawal
```
Request:
POST /api/statement-of-accounts
{
  "company_id": 8,
  "investor_id": 1,
  "amount": 1200,
  "status": "Pending",
  "transaction_date": "2026-07-03",
  "transaction_type": "Withdrawal",
  "bank_name": "Workflow Test Bank",
  "transfer_reference": "WF001",
  "notes": "Workflow Test Withdrawal"
}

Response: 201 Created (ID: 21)

Verification:
GET /api/ledger/investor/1
→ Shows ledger entry with credit: 0, debit: 1200
→ Running balance updated correctly (18801.25)
→ Bank name shown in description
✅ PASSED
```

## Files Involved

### Backend (PHP/Laravel)
- `app/Http/Controllers/Api/StatementOfAccountController.php` - Handles registration
- `app/Http/Controllers/Api/LedgerController.php` - Generates dynamic ledger
- `app/Models/StatementOfAccount.php` - Transaction model
- `routes/api.php` - Route definitions

### Frontend (Next.js - next-frontend)
- `components/DividendForm.js` - Dividend registration form
- `components/WithdrawalForm.js` - Withdrawal registration form
- `components/Layout.js` - Navigation with links to forms
- `pages/statement-of-accounts/create.js` - Form selection page

### Official Frontend (localhost:3001)
- Integration with `/api/statement-of-accounts` endpoint
- Display of partner's statement via `/api/ledger/investor/{id}`

## Important Notes

1. **No investment_id in UI**: Backend automatically determines investment from company_id + investor_id
2. **Statement is Read-Only**: Generated dynamically from transactions
3. **No Duplicate Records**: One record per transaction, not per partner
4. **Ledger Updates Automatically**: No cache, always current data
5. **Running Balance**: Chronologically calculated on-the-fly
6. **Reference Numbers**: Auto-generated format REF-XXXXXX based on transaction ID
