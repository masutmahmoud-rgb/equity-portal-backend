# Admin SOP: Financial Data and Notifications

## Purpose
This guide explains how admin users can:
- Add half-year Profit data
- Add half-year Indicative Value data
- Create announcements (notifications)

## Environment and URLs
- Admin frontend: http://localhost:3001
- Backend API (must be running): http://localhost:8000

If the admin pages load but save fails, verify the backend is running on port 8000.

## 1. Add Half-Year Profit Data
1. Open http://localhost:3001/financial-data
2. Click the Profit tab.
3. Fill the form:
- Year: e.g., 2026
- Half Year: H1 or H2
- Profit Amount
- Currency (for example USD)
- Notes (optional)
4. Click Create.
5. Confirm the new row appears in the records table.

## 2. Add Half-Year Indicative Value Data
1. Open http://localhost:3001/financial-data
2. Click the Indicative Value tab.
3. Fill the form:
- Year: e.g., 2026
- Half Year: H1 or H2
- Indicative Value amount
- Currency
- Notes (optional)
4. Click Create.
5. Confirm the new row appears in the records table.

## 3. Edit or Delete Financial Data
1. Go to http://localhost:3001/financial-data
2. In the records table, click Edit for a row.
3. Update values and click Update.
4. To remove a row, click Delete and confirm.

## 4. Create an Announcement (Notification)
1. Open http://localhost:3001/notifications
2. Fill the form:
- Notification Type
- Title
- Message
- Important Notes (optional)
- Publish Date
- Expiry Date (optional)
- Is Active (checked to publish)
3. Click Create.
4. Confirm the new row appears in Notification Records.

## 5. Edit, Deactivate, or Delete an Announcement
1. Go to http://localhost:3001/notifications
2. Click Edit for a row.
3. To deactivate, uncheck Is Active and click Update.
4. To delete, click Delete and confirm.

## Visibility Rules
- Financial data is period-based and consumed by year plus half-year.
- Notifications are not period-based.
- A notification is visible to partner endpoints only when:
- Is Active is true
- Publish Date is today or earlier
- Expiry Date is empty, or today is before/equal to Expiry Date

## Common Troubleshooting
- Problem: Cannot save from admin pages.
- Check: Backend API is running on http://localhost:8000.

- Problem: New routes are missing.
- Fix command:
```bash
php artisan route:clear
```

- Problem: Changes not reflected after code updates.
- Fix command:
```bash
php artisan optimize:clear
```
