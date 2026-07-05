Next.js Frontend for Investors module

Run:

```bash
cd next-frontend
npm install
npm run dev
```

App will run on http://localhost:3001 and expects the Laravel API at http://localhost:8000 (set `NEXT_PUBLIC_API_URL` to change).

Notes:
- CORS must be enabled on the Laravel backend for cross-origin requests.
- This is a minimal UI for create/view/edit/delete of investors using the API.
