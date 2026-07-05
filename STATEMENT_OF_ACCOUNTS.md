# Statement of Accounts API & Frontend

## Backend Status

✅ **All API endpoints implemented and tested**
- GET /api/statement-of-accounts (list all)
- GET /api/statement-of-accounts/{id} (get single)
- POST /api/statement-of-accounts (create dividend or withdrawal)
- PUT /api/statement-of-accounts/{id} (update)
- DELETE /api/statement-of-accounts/{id} (delete)
- POST /api/statement-of-accounts/declarations/dividend (declare company-wide dividend)
- GET /api/statement-of-accounts/{id}/attachments/{index} (download attachment)

✅ **Database**
- statement_of_accounts table created with all required fields
- Supports both Dividend and Withdrawal transaction types
- Attachment storage with secure file serving

✅ **Features**
- Full CRUD for Statement of Accounts
- Dividend and Withdrawal record types
- Multiple attachments per withdrawal record
- Secure file download URLs
- Validation for transaction types and attachments
- Route-model binding for safe URL handling

## Frontend Status

✅ **Next.js Frontend Module Created**
- `pages/statement-of-accounts/index.js` - List all records
- `pages/statement-of-accounts/create.js` - Create new record
- `pages/statement-of-accounts/[id]/index.js` - View record details
- `pages/statement-of-accounts/[id]/edit.js` - Edit existing record
- `components/StatementOfAccountForm.js` - Reusable form component

## Running the Backend

```bash
cd c:\Users\abdel\Documents\equity-backend
php artisan serve --port=8000
# or for explicit tmp dir:
php -d upload_tmp_dir="C:\Windows\Temp" artisan serve --port=8000
```

## Running the Frontend

```bash
cd c:\Users\abdel\Documents\equity-backend\next-frontend
npm install
npm run dev
# Frontend runs on http://localhost:3001
```

The frontend proxies all /api/* requests to http://localhost:8000 via next.config.js.

## API Endpoints Quick Reference

### List Statement of Accounts
```
GET /api/statement-of-accounts
Response: { "data": [...] }
```

### Get Single Record
```
GET /api/statement-of-accounts/1
Response: { "data": {...} }
```

### Create Dividend
```
POST /api/statement-of-accounts
Content-Type: application/json

{
  "company_id": 3,
  "investment_id": 1,
  "investor_id": 1,
  "transaction_type": "Dividend",
  "amount": 500.00,
  "status": "Pending",
  "transaction_date": "2026-07-05",
  "notes": "Optional notes"
}
```

### Create Withdrawal (without attachments)
```
POST /api/statement-of-accounts
Content-Type: application/json

{
  "company_id": 3,
  "investment_id": 1,
  "investor_id": 1,
  "transaction_type": "Withdrawal",
  "amount": 250.00,
  "status": "Pending",
  "transaction_date": "2026-07-05"
}
```

### Create Withdrawal with Attachments
```
POST /api/statement-of-accounts
Content-Type: multipart/form-data

Form Fields:
- company_id: 3
- investment_id: 1
- investor_id: 1
- transaction_type: Withdrawal
- amount: 250.00
- status: Pending
- transaction_date: 2026-07-05
- attachments[]: file1.pdf
- attachments[]: file2.pdf
```

### Update Record
```
PUT /api/statement-of-accounts/1
(same content as POST)
```

### Delete Record
```
DELETE /api/statement-of-accounts/1
```

### Declare Company-Wide Dividend
```
POST /api/statement-of-accounts/declarations/dividend
Content-Type: application/json

{
  "company_id": 3,
  "total_amount": 10000,
  "transaction_date": "2026-07-05",
  "notes": "Q3 dividend",
  "status": "Pending"
}

# Creates one Statement of Account per investor (proportionally split)
```

### Download Attachment
```
GET /api/statement-of-accounts/1/attachments/0
# Returns file stream (streams attachment at index 0)
# Returns 404 if attachment doesn't exist
```

## Response Format

All responses follow this format:

Success (2xx):
```json
{
  "data": {
    "id": 1,
    "company_id": 3,
    "investment_id": 1,
    "investor_id": 1,
    "transaction_type": "Dividend",
    "amount": "500.00",
    "status": "Pending",
    "transaction_date": "2026-07-05T00:00:00.000000Z",
    "notes": "...",
    "attachment_urls": ["http://localhost:8000/api/statement-of-accounts/1/attachments/0"],
    "company": {...},
    "investment": {...},
    "investor": {...},
    "created_at": "...",
    "updated_at": "..."
  }
}
```

Error (4xx):
```json
{
  "message": "Validation error...",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

## Known Limitations

### File Uploads
- Direct multipart file uploads may fail due to PHP temporary directory configuration on some systems
- **Workaround**: Use pre-stored files with `attachment_paths` JSON field, or configure PHP `upload_tmp_dir`
- The frontend gracefully handles upload failures and displays user-friendly messages
- All other features (create, read, update, delete) work without file uploads

## Database Schema

```sql
CREATE TABLE statement_of_accounts (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  company_id BIGINT NOT NULL,
  investment_id BIGINT NOT NULL,
  investor_id BIGINT NOT NULL,
  transaction_type VARCHAR(255) NOT NULL, -- 'Dividend' or 'Withdrawal'
  amount DECIMAL(16,2) NOT NULL,
  status VARCHAR(255) NOT NULL DEFAULT 'Pending', -- 'Pending' or 'Paid'
  transaction_date DATETIME,
  notes TEXT,
  attachment_paths TEXT, -- JSON array of file paths
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  FOREIGN KEY (company_id) REFERENCES companies(id),
  FOREIGN KEY (investment_id) REFERENCES investments(id),
  FOREIGN KEY (investor_id) REFERENCES investors(id)
);
```

## Frontend Form Features

- ✅ Company, Investor, Investment selection
- ✅ Transaction type toggle (Dividend/Withdrawal)
- ✅ Amount and status input
- ✅ Transaction date picker
- ✅ Notes textarea
- ✅ File upload for withdrawals (with fallback handling)
- ✅ Real-time validation feedback
- ✅ Create, Update, View, Delete operations
- ✅ List view with sortable columns
- ✅ Download attachments from details view

## Testing

### API Tests
```bash
# Comprehensive API test
php scripts/test_api_complete.php

# Result: 10/10 tests pass ✓
```

### Integration Test
```bash
# Test form submissions
php scripts/test_integration.php
```

## Modules Not Modified

As requested, the following modules were not modified:
- Companies (app/Models/Company.php, app/Http/Controllers/Api/CompanyController.php)
- Investors (app/Models/Investor.php, app/Http/Controllers/Api/InvestorController.php)
- Investments (app/Models/Investment.php, app/Http/Controllers/Api/InvestmentController.php)
- Legacy Dividend model/controller (kept for backward compatibility)

