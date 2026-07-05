# Investment Transactions API

## Overview

The Investment Transactions API provides CRUD operations for managing individual investment transactions. Each transaction tracks a specific investment activity (Initial or Additional Investment) with associated amount, date, and notes.

**Key Design Principles:**
- Transactions are immutable events (editable for corrections, but represent historical records)
- Investment balance is calculated dynamically as the sum of all transaction amounts
- No duplicate balance storage in the investments table
- Supports two transaction types: Initial Investment, Additional Investment

## Base URL

```
http://127.0.0.1:8080/api/investments/{investment_id}/transactions
```

## Authentication

Currently no authentication required. Token-based auth can be added via Laravel Sanctum in future phases.

## Endpoints

### 1. List All Transactions for an Investment

**Endpoint:** `GET /api/investments/{investment_id}/transactions`

**Description:** Retrieve all transactions for a specific investment with summary statistics.

**Parameters:**
- `investment_id` (URL path, required): The ID of the investment

**Response:** `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "investment_id": 4,
      "transaction_type": "Initial Investment",
      "amount": "52000.00",
      "transaction_date": "2026-06-01T00:00:00.000000Z",
      "notes": "Initial investment",
      "created_at": "2026-07-03T14:01:13.000000Z",
      "updated_at": "2026-07-03T14:05:22.000000Z"
    }
  ],
  "summary": {
    "total_transactions": 1,
    "total_amount": 52000
  }
}
```

**Example Request:**
```bash
curl -X GET http://127.0.0.1:8080/api/investments/4/transactions
```

**Notes:**
- Always returns data in descending order by created_at
- Summary provides quick totals without recalculating manually
- Empty array if no transactions exist for investment

---

### 2. Create a New Transaction

**Endpoint:** `POST /api/investments/{investment_id}/transactions`

**Description:** Create a new transaction for an investment. Used when tracking new investment or additional investment activities.

**Parameters:**
- `investment_id` (URL path, required): The ID of the investment

**Request Body:**

```json
{
  "transaction_type": "Initial Investment",
  "amount": 50000,
  "transaction_date": "2026-06-01",
  "notes": "Initial investment from partner"
}
```

**Field Specifications:**

| Field | Type | Required | Validation | Description |
|-------|------|----------|-----------|-------------|
| `transaction_type` | string | Yes | Must be "Initial Investment" or "Additional Investment" | Type of investment activity |
| `amount` | decimal | Yes | Minimum 0.01 | Investment amount in currency |
| `transaction_date` | date | No | Valid date format YYYY-MM-DD | Date when transaction occurred (defaults to today if omitted) |
| `notes` | string | No | Max 500 chars | Additional notes about transaction |

**Response:** `201 Created`

```json
{
  "data": {
    "id": 1,
    "investment_id": 4,
    "transaction_type": "Initial Investment",
    "amount": "50000.00",
    "transaction_date": "2026-06-01T00:00:00.000000Z",
    "notes": "Initial investment from partner",
    "created_at": "2026-07-03T14:01:13.000000Z",
    "updated_at": "2026-07-03T14:01:13.000000Z"
  }
}
```

**Error Response:** `422 Unprocessable Content`

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "transaction_type": ["The transaction type field is required.", "Transaction type must be one of: Initial Investment, Additional Investment"],
    "amount": ["The amount must be at least 0.01"]
  }
}
```

**Example Requests:**

```bash
# Create initial investment
curl -X POST http://127.0.0.1:8080/api/investments/4/transactions \
  -H "Content-Type: application/json" \
  -d '{
    "transaction_type": "Initial Investment",
    "amount": 50000,
    "transaction_date": "2026-06-01",
    "notes": "Initial investment"
  }'

# Create additional investment
curl -X POST http://127.0.0.1:8080/api/investments/4/transactions \
  -H "Content-Type: application/json" \
  -d '{
    "transaction_type": "Additional Investment",
    "amount": 10000,
    "transaction_date": "2026-06-15"
  }'
```

**Notes:**
- Investment must exist before creating transactions
- Transaction date defaults to current date if not provided
- Amount must be positive (minimum 0.01)

---

### 3. Retrieve a Single Transaction

**Endpoint:** `GET /api/investments/{investment_id}/transactions/{id}`

**Description:** Get details of a specific transaction.

**Parameters:**
- `investment_id` (URL path, required): The ID of the investment
- `id` (URL path, required): The ID of the transaction

**Response:** `200 OK`

```json
{
  "data": {
    "id": 1,
    "investment_id": 4,
    "transaction_type": "Initial Investment",
    "amount": "52000.00",
    "transaction_date": "2026-06-01T00:00:00.000000Z",
    "notes": "Initial investment",
    "created_at": "2026-07-03T14:01:13.000000Z",
    "updated_at": "2026-07-03T14:05:22.000000Z"
  }
}
```

**Error Response:** `404 Not Found`

```json
{
  "message": "Not Found"
}
```

**Example Request:**
```bash
curl -X GET http://127.0.0.1:8080/api/investments/4/transactions/1
```

**Notes:**
- Returns 404 if transaction does not exist
- Returns 404 if transaction belongs to a different investment

---

### 4. Update a Transaction

**Endpoint:** `PATCH /api/investments/{investment_id}/transactions/{id}`

**Description:** Update transaction details (amount, date, notes, or type). Use for corrections to previously recorded transactions.

**Parameters:**
- `investment_id` (URL path, required): The ID of the investment
- `id` (URL path, required): The ID of the transaction

**Request Body:** (All fields optional)

```json
{
  "transaction_type": "Additional Investment",
  "amount": 55000,
  "transaction_date": "2026-06-02",
  "notes": "Corrected initial investment amount"
}
```

**Field Specifications:** Same as Create endpoint

**Response:** `200 OK`

```json
{
  "data": {
    "id": 1,
    "investment_id": 4,
    "transaction_type": "Additional Investment",
    "amount": "55000.00",
    "transaction_date": "2026-06-02T00:00:00.000000Z",
    "notes": "Corrected initial investment amount",
    "created_at": "2026-07-03T14:01:13.000000Z",
    "updated_at": "2026-07-03T14:06:45.000000Z"
  }
}
```

**Error Response:** `422 Unprocessable Content`

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "amount": ["The amount must be at least 0.01"]
  }
}
```

**Example Requests:**

```bash
# Update amount only
curl -X PATCH http://127.0.0.1:8080/api/investments/4/transactions/1 \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 55000
  }'

# Update with correction notes
curl -X PATCH http://127.0.0.1:8080/api/investments/4/transactions/1 \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 55000,
    "notes": "Corrected from 50000"
  }'
```

**Notes:**
- Partial updates allowed (only include fields to modify)
- Investment balance automatically recalculated after update
- `updated_at` timestamp changes on every update

---

### 5. Delete a Transaction

**Endpoint:** `DELETE /api/investments/{investment_id}/transactions/{id}`

**Description:** Remove a transaction. Used when a transaction was recorded in error.

**Parameters:**
- `investment_id` (URL path, required): The ID of the investment
- `id` (URL path, required): The ID of the transaction

**Response:** `200 OK`

```json
{
  "message": "Investment transaction deleted successfully"
}
```

**Error Response:** `404 Not Found`

```json
{
  "message": "Not Found"
}
```

**Example Request:**
```bash
curl -X DELETE http://127.0.0.1:8080/api/investments/4/transactions/1
```

**Notes:**
- Deletion is permanent and cannot be undone
- Investment balance automatically recalculated after deletion
- Cascades on investment deletion (transactions deleted when parent investment is deleted)

---

## Balance Calculation

The investment balance is calculated dynamically from transactions and is **not stored** in the database.

**Formula:**
```
Investment Balance = SUM(all transaction amounts for investment)
```

**Example:**
```
Transaction 1: Initial Investment + 50000
Transaction 2: Additional Investment + 10000
Transaction 3: Additional Investment + 5000

Total Balance = 50000 + 10000 + 5000 = 65000
```

**Accessing Balance in Code:**
```php
// Get investment with balance calculation
$investment = Investment::find(4);
$currentBalance = $investment->getCurrentBalance(); // Returns 65000 as float
```

---

## Data Model

### InvestmentTransaction

```php
class InvestmentTransaction {
  int $id;
  int $investment_id;
  string $transaction_type;    // "Initial Investment" | "Additional Investment"
  decimal $amount;              // decimal(15, 2)
  date $transaction_date;       // Date when transaction occurred
  string $notes;                // Optional notes
  timestamp $created_at;        // When record was created
  timestamp $updated_at;        // When record was last updated
  
  // Constants
  const TYPE_INITIAL = "Initial Investment"
  const TYPE_ADDITIONAL = "Additional Investment"
  const TYPES = ["Initial Investment", "Additional Investment"]
  
  // Relationships
  function investment(): BelongsTo
}
```

### Database Schema

```sql
CREATE TABLE investment_transactions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  investment_id BIGINT NOT NULL REFERENCES investments(id) ON DELETE CASCADE,
  transaction_type ENUM('Initial Investment', 'Additional Investment'),
  amount DECIMAL(15, 2),
  transaction_date DATE,
  notes TEXT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  INDEX idx_investment_id (investment_id),
  INDEX idx_transaction_date (transaction_date)
);
```

---

## Error Handling

All errors follow a consistent error envelope format:

**Validation Errors (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message 1", "Error message 2"]
  }
}
```

**Not Found (404):**
```json
{
  "message": "Not Found"
}
```

**Server Error (500):**
```json
{
  "message": "Server error occurred"
}
```

---

## Usage Examples

### Complete Workflow Example

```bash
# 1. Create an investment
curl -X POST http://127.0.0.1:8080/api/investments \
  -H "Content-Type: application/json" \
  -d '{
    "investor_id": 1,
    "company_id": 10,
    "amount": 0,
    "status": "Active"
  }' # Returns investment_id: 4

# 2. Record initial investment
curl -X POST http://127.0.0.1:8080/api/investments/4/transactions \
  -H "Content-Type: application/json" \
  -d '{
    "transaction_type": "Initial Investment",
    "amount": 50000,
    "transaction_date": "2026-06-01",
    "notes": "Initial capital"
  }'

# 3. Record additional investment
curl -X POST http://127.0.0.1:8080/api/investments/4/transactions \
  -H "Content-Type: application/json" \
  -d '{
    "transaction_type": "Additional Investment",
    "amount": 15000,
    "transaction_date": "2026-07-01",
    "notes": "Additional funding"
  }'

# 4. List all transactions
curl -X GET http://127.0.0.1:8080/api/investments/4/transactions
# Shows: total_transactions: 2, total_amount: 65000

# 5. Correct first transaction if needed
curl -X PATCH http://127.0.0.1:8080/api/investments/4/transactions/1 \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 52000,
    "notes": "Corrected amount"
  }'

# 6. List again - balance automatically updated
curl -X GET http://127.0.0.1:8080/api/investments/4/transactions
# Shows: total_transactions: 2, total_amount: 67000
```

---

## Implementation Notes

### Routes

```php
// In routes/api.php
Route::apiResource('investments.transactions', InvestmentTransactionController::class);

// Generates:
// GET    /api/investments/{investment}/transactions              (index)
// POST   /api/investments/{investment}/transactions              (store)
// GET    /api/investments/{investment}/transactions/{id}         (show)
// PATCH  /api/investments/{investment}/transactions/{id}         (update)
// DELETE /api/investments/{investment}/transactions/{id}         (destroy)
```

### Controller

File: [app/Http/Controllers/Api/InvestmentTransactionController.php](app/Http/Controllers/Api/InvestmentTransactionController.php)

Key methods:
- `index()` - List transactions with summary
- `store()` - Create transaction with validation
- `show()` - Get single transaction
- `update()` - Update transaction with validation
- `destroy()` - Delete transaction

### Model

File: [app/Models/InvestmentTransaction.php](app/Models/InvestmentTransaction.php)

Key features:
- Belongs to Investment model
- Validates transaction_type against TYPES constant
- Cascades delete when parent investment deleted
- Casts amount to decimal

---

## Testing

### Automated Tests

Tests located in `tests/Feature/Api/InvestmentTransactionControllerTest.php`

Run tests:
```bash
php artisan test tests/Feature/Api/InvestmentTransactionControllerTest.php
```

### Manual Testing via curl

See Usage Examples section above for curl commands.

### Manual Testing via Tinker

```php
php artisan tinker

# Create test data
$inv = App\Models\Investment::create([
  'investor_id' => 1,
  'company_id' => 10,
  'amount' => 0,
  'status' => 'Active'
]);

# Create transactions
$t1 = App\Models\InvestmentTransaction::create([
  'investment_id' => $inv->id,
  'transaction_type' => 'Initial Investment',
  'amount' => 50000,
  'transaction_date' => '2026-06-01'
]);

# Check balance
$inv->getCurrentBalance(); // Returns 50000

# Delete transaction
$t1->delete();
$inv->getCurrentBalance(); // Returns 0
```

---

## Future Enhancements

1. **Token-Based Authentication** - Add Laravel Sanctum for JWT tokens
2. **Investment Statement API** - Endpoint combining investments + transactions + calculations
3. **Dividend/Withdrawal Tracking** - Separate transaction types for payouts
4. **Audit Trail** - Track who modified transactions and when
5. **Batch Operations** - Create/update multiple transactions in single request
6. **Filtering & Pagination** - Filter by date range, type, amount
7. **Export** - CSV/PDF export of transactions
