import { useEffect, useMemo, useState } from 'react';
import Layout from '../../components/Layout';

const HALF_OPTIONS = [
  { value: 'H1', label: 'First Half (H1)' },
  { value: 'H2', label: 'Second Half (H2)' },
];

const YEAR_OPTIONS = Array.from({ length: 16 }, (_, index) => 2020 + index);

const DEFAULT_FORM = {
  investor_id: '',
  valuation_year: new Date().getFullYear(),
  valuation_half: 'H1',
  card_label: '',
  investment_amount: '',
  profit: '',
  status: 'Draft',
  notes: '',
};

export default function AdditionalInvestmentsPage() {
  const [rows, setRows] = useState([]);
  const [partners, setPartners] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [editingId, setEditingId] = useState(null);
  const [form, setForm] = useState(DEFAULT_FORM);

  const loadData = async () => {
    setLoading(true);
    setError('');

    try {
      const [rowsRes, partnersRes] = await Promise.all([
        fetch('/api/additional-investments'),
        fetch('/api/investors'),
      ]);

      const rowsJson = await rowsRes.json();
      const partnersJson = await partnersRes.json();

      if (!rowsRes.ok) throw new Error(rowsJson.message || 'Failed to load additional investments');

      setRows(rowsJson.data || []);
      setPartners(partnersRes.ok ? (partnersJson.data || []) : []);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  const sortedRows = useMemo(() => {
    return [...rows].sort((a, b) => {
      if (a.valuation_year !== b.valuation_year) return b.valuation_year - a.valuation_year;
      if (a.valuation_half !== b.valuation_half) return String(b.valuation_half).localeCompare(String(a.valuation_half));
      return new Date(b.created_at) - new Date(a.created_at);
    });
  }, [rows]);

  const generatedPeriod = useMemo(() => {
    if (!form.valuation_year || !form.valuation_half) return '';
    return `${form.valuation_year}-${form.valuation_half}`;
  }, [form.valuation_year, form.valuation_half]);

  const onChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  const resetForm = () => {
    setEditingId(null);
    setForm(DEFAULT_FORM);
  };

  const onSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');

    try {
      const payload = {
        investor_id: Number(form.investor_id),
        valuation_year: Number(form.valuation_year),
        valuation_half: form.valuation_half,
        card_label: form.card_label || null,
        investment_amount: Number(form.investment_amount),
        profit: Number(form.profit),
        status: form.status,
        notes: form.notes || null,
      };

      const url = editingId ? `/api/additional-investments/${editingId}` : '/api/additional-investments';
      const method = editingId ? 'PUT' : 'POST';

      const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok) {
        if (data?.errors) {
          const first = Object.values(data.errors)[0];
          throw new Error(Array.isArray(first) ? first[0] : String(first));
        }
        throw new Error(data.message || 'Save failed');
      }

      await loadData();
      resetForm();
    } catch (saveError) {
      setError(saveError.message);
    } finally {
      setSaving(false);
    }
  };

  const onEdit = (row) => {
    setEditingId(row.id);
    setForm({
      investor_id: row.investor_id || '',
      valuation_year: row.valuation_year || new Date().getFullYear(),
      valuation_half: row.valuation_half || 'H1',
      card_label: row.card_label || '',
      investment_amount: row.investment_amount || '',
      profit: row.profit || '',
      status: row.status || 'Draft',
      notes: row.notes || '',
    });
  };

  const onDelete = async (id) => {
    if (!confirm('Delete this additional investment?')) return;
    setError('');

    try {
      const res = await fetch(`/api/additional-investments/${id}`, { method: 'DELETE' });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Delete failed');
      await loadData();
      if (editingId === id) resetForm();
    } catch (deleteError) {
      setError(deleteError.message);
    }
  };

  return (
    <Layout>
      <div className="container">
        <h1>Additional Investments</h1>

        {error && (
          <div style={{ marginBottom: 15, color: '#b00020', background: '#ffe8ea', padding: 10, borderRadius: 6 }}>
            {error}
          </div>
        )}

        <section style={{ marginBottom: 24, padding: 16, border: '1px solid #ddd', borderRadius: 8 }}>
          <h2 style={{ marginTop: 0 }}>{editingId ? 'Edit Additional Investment' : 'Add Additional Investment'}</h2>
          <form onSubmit={onSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(220px, 1fr))', gap: 12 }}>
              <div>
                <label>Partner *</label>
                <select name="investor_id" value={form.investor_id} onChange={onChange} required style={{ width: '100%', padding: 8 }}>
                  <option value="">Select a partner...</option>
                  {partners.map((partner) => (
                    <option key={partner.id} value={partner.id}>
                      {partner.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label>Card Label (optional)</label>
                <input
                  name="card_label"
                  value={form.card_label}
                  onChange={onChange}
                  placeholder="e.g. Additional Investment - External"
                  style={{ width: '100%', padding: 8 }}
                />
              </div>

              <div>
                <label>Year *</label>
                <select name="valuation_year" value={form.valuation_year} onChange={onChange} required style={{ width: '100%', padding: 8 }}>
                  {YEAR_OPTIONS.map((year) => (
                    <option key={year} value={year}>{year}</option>
                  ))}
                </select>
              </div>

              <div>
                <label>Half *</label>
                <select name="valuation_half" value={form.valuation_half} onChange={onChange} required style={{ width: '100%', padding: 8 }}>
                  {HALF_OPTIONS.map((half) => (
                    <option key={half.value} value={half.value}>{half.label}</option>
                  ))}
                </select>
              </div>

              <div>
                <label>Period</label>
                <input value={generatedPeriod} readOnly style={{ width: '100%', padding: 8, background: '#f7f7f7' }} />
              </div>

              <div>
                <label>Status *</label>
                <select name="status" value={form.status} onChange={onChange} required style={{ width: '100%', padding: 8 }}>
                  <option value="Draft">Draft</option>
                  <option value="Published">Published</option>
                </select>
              </div>

              <div>
                <label>Investment Amount *</label>
                <input type="number" step="0.01" min="0" name="investment_amount" value={form.investment_amount} onChange={onChange} required style={{ width: '100%', padding: 8 }} />
              </div>

              <div>
                <label>Profit *</label>
                <input type="number" step="0.01" name="profit" value={form.profit} onChange={onChange} required style={{ width: '100%', padding: 8 }} />
              </div>
            </div>

            <div style={{ marginTop: 12 }}>
              <label>Notes</label>
              <textarea name="notes" value={form.notes} onChange={onChange} rows={3} style={{ width: '100%', padding: 8 }} />
            </div>

            <div style={{ marginTop: 12 }}>
              <button type="submit" disabled={saving} style={{ padding: '8px 14px', marginRight: 8 }}>
                {saving ? 'Saving...' : editingId ? 'Update' : 'Create'}
              </button>
              {editingId && (
                <button type="button" onClick={resetForm} style={{ padding: '8px 14px' }}>
                  Cancel
                </button>
              )}
            </div>
          </form>
        </section>

        <section>
          <h2>Additional Investment Records</h2>
          {loading ? (
            <p>Loading...</p>
          ) : sortedRows.length === 0 ? (
            <p>No records found.</p>
          ) : (
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ background: '#f5f5f5' }}>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Partner</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Label</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Period</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Investment</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Profit</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Status</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {sortedRows.map((row) => (
                  <tr key={row.id}>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{row.partner_name || row.investor?.name || '-'}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{row.card_label || '-'}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{row.valuation_period}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{Number(row.investment_amount).toLocaleString()}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{Number(row.profit).toLocaleString()}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{row.status}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>
                      <button onClick={() => onEdit(row)} style={{ marginRight: 8 }}>Edit</button>
                      <button onClick={() => onDelete(row.id)}>Delete</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>
      </div>
    </Layout>
  );
}
