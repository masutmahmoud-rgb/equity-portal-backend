# Quick Start Guide

## Prerequisites
- PHP 8.4+ with Laravel 13
- Node.js 16+ for Next.js frontend
- SQLite (included in Laravel)

## Step 1: Start the Backend Server

```bash
cd c:\Users\abdel\Documents\equity-backend

# Terminal 1: Start Laravel server
php artisan serve --port=8000
```

You should see:
```
INFO Server running on [http://127.0.0.1:8000].
```

## Step 2: Start the Frontend Server

```bash
cd c:\Users\abdel\Documents\equity-backend\next-frontend

# Terminal 2: Install dependencies (first time only)
npm install

# Terminal 2: Start development server
npm run dev
```

You should see:
```
ready - started server on 0.0.0.0:3001, url: http://localhost:3001
```

## Step 3: Access the Application

Open browser to: **http://localhost:3001**

## Navigation

- **Statement of Accounts** → `/statement-of-accounts`
  - List all records
  - Create new Dividend or Withdrawal
  - View, edit, delete records
  - Download attachments

## Testing the API

### Test 1: List All Statement of Accounts
```bash
curl http://localhost:8000/api/statement-of-accounts
```

Expected: Array of records with status 200

### Test 2: Create a Dividend
```bash
curl -X POST http://localhost:8000/api/statement-of-accounts \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": 3,
    "investment_id": 1,
    "investor_id": 1,
    "transaction_type": "Dividend",
    "amount": 500,
    "status": "Pending",
    "transaction_date": "2026-07-05"
  }'
```

Expected: Status 201, record created

### Test 3: Create a Withdrawal
```bash
curl -X POST http://localhost:8000/api/statement-of-accounts \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": 3,
    "investment_id": 1,
    "investor_id": 1,
    "transaction_type": "Withdrawal",
    "amount": 250,
    "status": "Pending",
    "transaction_date": "2026-07-05"
  }'
```

### Test 4: Run Full API Test Suite
```bash
php scripts/test_api_complete.php
```

Expected: 10/10 tests pass ✓

## File Structure

```
equity-backend/
├── app/Models/
│   ├── Company.php
│   ├── Investor.php
│   ├── Investment.php
│   └── StatementOfAccount.php
├── app/Http/Controllers/Api/
│   ├── CompanyController.php
│   ├── InvestorController.php
│   ├── InvestmentController.php
│   └── StatementOfAccountController.php
├── database/migrations/
│   ├── 2026_07_03_000001_create_statement_of_accounts_table.php
│   ├── 2026_07_03_100000_rename_dividends_to_statement_of_accounts.php
│   └── 2026_07_03_110000_add_attachment_paths_to_statement_of_accounts.php
├── routes/api.php
├── next-frontend/
│   ├── pages/
│   │   ├── statement-of-accounts/
│   │   │   ├── index.js (List)
│   │   │   ├── create.js (Create form)
│   │   │   └── [id]/
│   │   │       ├── index.js (View)
│   │   │       └── edit.js (Edit form)
│   ├── components/
│   │   └── StatementOfAccountForm.js
│   └── next.config.js
└── STATEMENT_OF_ACCOUNTS.md (Full documentation)
```

## Troubleshooting

### Backend Not Starting
```bash
# Check if port 8000 is in use
netstat -ano | findstr :8000

# Use different port
php artisan serve --port=8001
```

### Frontend Can't Connect to Backend
Check that:
1. Backend is running on http://localhost:8000
2. next.config.js has correct API URL in rewrites
3. No CORS errors in browser console

### Database Issues
```bash
# Check database connection
php artisan tinker
# Then: DB::connection()->getPdo()
```

### File Upload Issues
The frontend handles file uploads gracefully. If uploads fail:
1. Check PHP upload_tmp_dir configuration
2. Try JSON-based requests instead of multipart
3. See STATEMENT_OF_ACCOUNTS.md for workarounds

## What's Implemented

✅ **Statement of Accounts Module**
- Create Dividend records (company-wide or single investor)
- Create Withdrawal records with optional attachments
- Update existing records
- Delete records with automatic file cleanup
- Download attachments
- Full validation with error messages

✅ **Frontend Pages**
- List view with auto-refresh
- Create form with company/investor/investment selection
- View page with attachment downloads
- Edit page for updating records
- Proper error handling and user feedback

✅ **API Endpoints** (10 total)
- 5 CRUD endpoints (index, show, store, update, destroy)
- 1 special declaration endpoint (declare company-wide dividend)
- 1 download endpoint (stream attachments)
- 3 reference endpoints (companies, investors, investments)

## Next Steps

1. Test creating records through UI
2. Test attachment downloads
3. Test form validation
4. Customize styling in `next-frontend/styles/` as needed
5. Add more features (filtering, sorting, export, etc.)

