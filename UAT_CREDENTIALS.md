# UAT (User Acceptance Testing) - Partner Credentials

**Date:** July 3, 2026  
**Status:** ✅ Ready for Testing  
**Application:** Equity Portal - Private Investments Management System

---

## Partner Login Accounts

### Partner 1

| Field | Value |
|-------|-------|
| **Partner Name** | mahmoud |
| **Email** | abdelghany.mahmoud@yahoo.com |
| **Temporary Password** | `TempPass@2026!` |
| **User ID** | 1 |

---

## Authentication Endpoints

### Login
**Endpoint:** `POST /api/auth/login`

**Request:**
```json
{
  "email": "abdelghany.mahmoud@yahoo.com",
  "password": "TempPass@2026!"
}
```

**Response (200 OK):**
```json
{
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "mahmoud",
      "email": "abdelghany.mahmoud@yahoo.com"
    }
  }
}
```

### Verify Credentials
**Endpoint:** `POST /api/auth/verify-credentials`

**Request:**
```json
{
  "email": "abdelghany.mahmoud@yahoo.com",
  "password": "TempPass@2026!"
}
```

**Response (200 OK):**
```json
{
  "message": "Credentials verified successfully",
  "data": {
    "valid": true,
    "user": {
      "id": 1,
      "name": "mahmoud",
      "email": "abdelghany.mahmoud@yahoo.com"
    }
  }
}
```

---

## System Verification Checklist

| Item | Status | Notes |
|------|--------|-------|
| All transactional data cleared | ✅ | 18 transactions deleted, schema preserved |
| Partner user account created | ✅ | mahmoud account active and ready |
| Temporary password generated | ✅ | `TempPass@2026!` - meets complexity requirements |
| Authentication endpoint working | ✅ | POST /api/auth/login returns 200 |
| Credentials verification working | ✅ | POST /api/auth/verify-credentials returns 200 |
| Database schema intact | ✅ | No schema changes, only data cleared |
| Application logic preserved | ✅ | No business logic modifications |

---

## Testing Instructions

### 1. Login Test
```bash
curl -X POST http://127.0.0.1:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "abdelghany.mahmoud@yahoo.com",
    "password": "TempPass@2026!"
  }'
```

**Expected Result:** Status 200, returns user data

### 2. Invalid Password Test
```bash
curl -X POST http://127.0.0.1:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "abdelghany.mahmoud@yahoo.com",
    "password": "WrongPassword123"
  }'
```

**Expected Result:** Status 422, error message "The provided credentials are incorrect."

### 3. Non-Existent User Test
```bash
curl -X POST http://127.0.0.1:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "nonexistent@example.com",
    "password": "TempPass@2026!"
  }'
```

**Expected Result:** Status 422, error message "The provided credentials are incorrect."

### 4. View Partner Ledger
```bash
curl http://127.0.0.1:8080/api/ledger/investor/1
```

**Expected Result:** Status 200, empty ledger (no transactions)

---

## Database Status

### Cleared Tables
- **statement_of_accounts:** 18 transactions removed ✅
- **Schema:** Preserved ✅

### Preserved Tables
- **users:** 1 partner account (mahmoud) ✅
- **companies:** All intact ✅
- **investors:** All intact (1 active partner) ✅
- **investments:** All intact ✅
- All other tables: Untouched ✅

---

## Security Notes

1. **Temporary Password:** Partners should change this password on first login
2. **Password Policy:** Meet complexity requirements (uppercase, lowercase, numbers, symbols)
3. **API Endpoints:** Currently open for testing (no rate limiting or additional validation)
4. **Data Privacy:** All historical transaction data cleared - fresh start for UAT

---

## Files Modified

### Backend
- **Created:** `app/Http/Controllers/Api/AuthController.php` - Authentication logic
- **Modified:** `routes/api.php` - Added auth routes
- **Database:** Cleared `statement_of_accounts` table (18 records)

### Migration
- No new migrations - uses existing schema

---

## Next Steps (After UAT)

1. Partner logs in with temporary password
2. Partner changes password to permanent password
3. Partner can access their financial dashboard
4. Partner can view their statement of account
5. Administrator can register dividends/withdrawals
6. System tracks all transactions

---

**Generated:** 2026-07-03  
**System Status:** ✅ Ready for UAT  
**Contact:** Development Team
