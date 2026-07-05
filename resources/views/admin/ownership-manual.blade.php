<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Manual Ownership</title>
    <style>
        :root {
            --bg: #f4f1ea;
            --card: #fffdf8;
            --ink: #172022;
            --muted: #6f776f;
            --accent: #0b6e4f;
            --accent-2: #f2a900;
            --danger: #9d1b1b;
            --line: #d4cdc0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 85% 15%, rgba(242, 169, 0, 0.2), transparent 40%),
                radial-gradient(circle at 10% 90%, rgba(11, 110, 79, 0.18), transparent 45%),
                linear-gradient(120deg, #f8f6f0, var(--bg));
            min-height: 100vh;
            padding: 18px;
        }

        .layout {
            max-width: 1150px;
            margin: 0 auto;
            display: flex;
            gap: 18px;
        }

        .sidebar {
            width: 240px;
            flex-shrink: 0;
            border: 1px solid var(--line);
            background: linear-gradient(160deg, #f7fbf8, #edf6f0);
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(18, 40, 31, 0.08);
            padding: 14px;
            height: fit-content;
            position: sticky;
            top: 12px;
        }

        .sidebar-title {
            font-size: 13px;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 700;
            color: #335347;
            margin: 2px 4px 10px;
        }

        .menu {
            display: grid;
            gap: 8px;
        }

        .menu-link {
            text-decoration: none;
            border: 1px solid #c9dbd0;
            border-radius: 12px;
            padding: 11px 12px;
            color: #133b33;
            background: #f6fffa;
            font-size: 14px;
            font-weight: 600;
        }

        .menu-link:hover {
            border-color: #9fc8b5;
        }

        .menu-link.active {
            background: linear-gradient(135deg, var(--accent), #0b7f5a);
            border-color: #0a7151;
            color: #fff;
            box-shadow: 0 6px 16px rgba(11, 110, 79, 0.28);
        }

        .main {
            flex: 1;
            min-width: 0;
            display: grid;
            gap: 18px;
        }

        .hero {
            border: 1px solid var(--line);
            background: linear-gradient(135deg, #fffdf8, #f8f4ea);
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(25, 30, 28, 0.08);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 28px;
            letter-spacing: 0.2px;
        }

        .sub {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .card {
            border: 1px solid var(--line);
            background: var(--card);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 6px 26px rgba(20, 25, 23, 0.06);
        }

        .grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .field {
            display: grid;
            gap: 6px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #3f4b45;
        }

        select,
        input {
            width: 100%;
            border: 1px solid #b9c1b3;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 14px;
            background: #fff;
            color: var(--ink);
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        select:focus,
        input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(11, 110, 79, 0.18);
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 4px;
        }

        button {
            appearance: none;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), #0b7f5a);
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.3px;
            padding: 12px 18px;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.2s ease, filter 0.2s ease;
            box-shadow: 0 6px 16px rgba(11, 110, 79, 0.3);
        }

        button:hover {
            filter: brightness(1.04);
            transform: translateY(-1px);
        }

        button:disabled {
            opacity: 0.65;
            cursor: wait;
            transform: none;
        }

        .status {
            font-size: 13px;
            min-height: 20px;
        }

        .ok {
            color: var(--accent);
        }

        .err {
            color: var(--danger);
        }

        .preview {
            border: 1px dashed #c8c2b4;
            border-radius: 12px;
            padding: 12px;
            font-size: 13px;
            color: #36403d;
            background: #fcfaf4;
        }

        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .quick-links a {
            text-decoration: none;
            color: #0f4d43;
            font-size: 12px;
            background: #eaf7f1;
            border: 1px solid #b7ddcf;
            border-radius: 999px;
            padding: 6px 10px;
        }

        @media (max-width: 760px) {
            body {
                padding: 14px;
            }

            .layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                position: static;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-title">Admin Sidebar</div>
        <nav class="menu">
            <a class="menu-link active" href="/admin/ownership-manual">Manual Ownership</a>
            <a class="menu-link" href="/api/ownership-records" target="_blank">Ownership Snapshots API</a>
            <a class="menu-link" href="/api/partner-portal/1/companies" target="_blank">Partner Company Cards API</a>
        </nav>
    </aside>

    <main class="main">
        <section class="hero">
            <h1>Manual Ownership Setter</h1>
            <p class="sub">Admin tool to set one partner ownership percentage in one click and push it to partner company cards.</p>
            <div class="quick-links">
                <a href="/api/ownership-records" target="_blank">Check ownership snapshots</a>
                <a href="/api/partner-portal/1/companies" target="_blank">Sample partner cards</a>
            </div>
        </section>

        <section class="card">
            <div class="grid">
                <div class="field">
                    <label for="company">Company</label>
                    <select id="company">
                        <option value="">Loading companies...</option>
                    </select>
                </div>

                <div class="field">
                    <label for="partner">Partner</label>
                    <select id="partner">
                        <option value="">Loading partners...</option>
                    </select>
                </div>

                <div class="field">
                    <label for="ownership">Ownership Percentage</label>
                    <input id="ownership" type="number" min="0" max="100" step="0.0001" placeholder="Ex: 12.5">
                </div>

                <div class="field">
                    <label for="effective">Effective Date (Optional)</label>
                    <input id="effective" type="date">
                </div>

                <div class="field full preview" id="preview">
                    Waiting for your inputs.
                </div>
            </div>

            <div class="actions">
                <button id="saveBtn" type="button">Save Ownership</button>
                <div class="status" id="status"></div>
            </div>
        </section>
    </main>
</div>

<script>
    const companyEl = document.getElementById('company');
    const partnerEl = document.getElementById('partner');
    const ownershipEl = document.getElementById('ownership');
    const effectiveEl = document.getElementById('effective');
    const saveBtn = document.getElementById('saveBtn');
    const statusEl = document.getElementById('status');
    const previewEl = document.getElementById('preview');

    function setStatus(message, type) {
        statusEl.textContent = message || '';
        statusEl.className = 'status ' + (type || '');
    }

    function updatePreview() {
        const companyText = companyEl.options[companyEl.selectedIndex]?.text || 'No company selected';
        const partnerText = partnerEl.options[partnerEl.selectedIndex]?.text || 'No partner selected';
        const ownershipText = ownershipEl.value ? ownershipEl.value + '%' : 'No percentage';
        const effectiveText = effectiveEl.value || 'Today';

        previewEl.textContent = 'Will set ' + ownershipText + ' for ' + partnerText + ' in ' + companyText + ' effective ' + effectiveText + '.';
    }

    async function loadSelects() {
        setStatus('Loading data...', '');

        try {
            const [companiesRes, partnersRes] = await Promise.all([
                fetch('/api/companies'),
                fetch('/api/investors')
            ]);

            if (!companiesRes.ok || !partnersRes.ok) {
                throw new Error('Could not load companies/partners from API.');
            }

            const companiesJson = await companiesRes.json();
            const partnersJson = await partnersRes.json();

            const companies = companiesJson.data || [];
            const partners = partnersJson.data || [];

            companyEl.innerHTML = '<option value="">Select company</option>' +
                companies.map(c => '<option value="' + c.id + '">' + c.name + ' (#' + c.id + ')</option>').join('');

            partnerEl.innerHTML = '<option value="">Select partner</option>' +
                partners.map(p => '<option value="' + p.id + '">' + p.name + ' (#' + p.id + ')</option>').join('');

            setStatus('Ready', 'ok');
            updatePreview();
        } catch (error) {
            setStatus(error.message, 'err');
            companyEl.innerHTML = '<option value="">No companies loaded</option>';
            partnerEl.innerHTML = '<option value="">No partners loaded</option>';
        }
    }

    async function submitManualOwnership() {
        const companyId = companyEl.value;
        const partnerId = partnerEl.value;
        const ownership = ownershipEl.value;

        if (!companyId || !partnerId || ownership === '') {
            setStatus('Company, partner, and ownership percentage are required.', 'err');
            return;
        }

        const ownershipNumber = Number(ownership);
        if (Number.isNaN(ownershipNumber) || ownershipNumber < 0 || ownershipNumber > 100) {
            setStatus('Ownership must be a number from 0 to 100.', 'err');
            return;
        }

        const payload = {
            company_id: Number(companyId),
            investor_id: Number(partnerId),
            ownership_percentage: ownershipNumber
        };

        if (effectiveEl.value) {
            payload.effective_date = effectiveEl.value;
        }

        saveBtn.disabled = true;
        setStatus('Saving...', '');

        try {
            const res = await fetch('/api/ownership-records/manual-set', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const json = await res.json();

            if (!res.ok) {
                const message = json.message || 'Save failed.';
                setStatus(message, 'err');
                return;
            }

            setStatus('Saved. Ownership is now available in partner company cards.', 'ok');
        } catch (error) {
            setStatus('Network error while saving ownership.', 'err');
        } finally {
            saveBtn.disabled = false;
        }
    }

    [companyEl, partnerEl, ownershipEl, effectiveEl].forEach(el => {
        el.addEventListener('change', updatePreview);
        el.addEventListener('input', updatePreview);
    });

    saveBtn.addEventListener('click', submitManualOwnership);

    loadSelects();
</script>
</body>
</html>
