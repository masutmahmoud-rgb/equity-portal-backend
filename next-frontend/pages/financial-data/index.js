import { useEffect, useMemo, useState } from 'react';
import Layout from '../../components/Layout';

const TYPES = [
  { value: 'profit', label: 'Profit' },
  { value: 'indicative_value', label: 'Indicative Value' },
];

const HALF_YEARS = ['H1', 'H2'];

export default function FinancialDataPage() {
  const [activeType, setActiveType] = useState('profit');
  const [records, setRecords] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [editingId, setEditingId] = useState(null);

  const [form, setForm] = useState({
    year: new Date().getFullYear(),
    half_year: 'H1',
    amount: '',
    currency: 'USD',
    notes: '',
  });

  const amountLabel = activeType === 'profit' ? 'Profit Amount' : 'Indicative Value';

  const loadRecords = async () => {
    setLoading(true);
    setError('');
    try {
      const res = await fetch(`/api/financial-data?type=${activeType}`);
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Failed to load financial data');
      setRecords(data.data || []);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    setEditingId(null);
    setForm({
      year: new Date().getFullYear(),
      half_year: 'H1',
      amount: '',
      currency: 'USD',
      notes: '',
    });
    loadRecords();
  }, [activeType]);

  const sortedRecords = useMemo(() => {
    return [...records].sort((a, b) => {
      if (a.year !== b.year) return b.year - a.year;
      return a.half_year === 'H2' ? -1 : 1;
    });
  }, [records]);

  const onChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  const resetForm = () => {
    setEditingId(null);
    setForm({
      year: new Date().getFullYear(),
      half_year: 'H1',
      amount: '',
      currency: 'USD',
      notes: '',
    });
  };

  const onSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');

    try {
      const payload = {
        type: activeType,
        year: Number(form.year),
        half_year: form.half_year,
        amount: Number(form.amount),
        currency: form.currency,
        notes: form.notes || null,
      };

      const url = editingId ? `/api/financial-data/${editingId}` : '/api/financial-data';
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

      await loadRecords();
      resetForm();
    } catch (e2) {
      setError(e2.message);
    } finally {
      setSaving(false);
    }
  };

  const onEdit = (row) => {
    setEditingId(row.id);
    setForm({
      year: row.year,
      half_year: row.half_year,
      amount: row.amount,
      currency: row.currency,
      notes: row.notes || '',
    });
  };

  const onDelete = async (id) => {
    if (!confirm('Delete this record?')) return;
    setError('');
    try {
      const res = await fetch(`/api/financial-data/${id}`, { method: 'DELETE' });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Delete failed');
      await loadRecords();
      if (editingId === id) resetForm();
    } catch (e) {
      setError(e.message);
    }
  };

  return (
    <Layout>
      <div className="container">
        <h1>Financial Data</h1>

        <div style={{ marginBottom: 20 }}>
          {TYPES.map((tab) => (
            <button
              key={tab.value}
              onClick={() => setActiveType(tab.value)}
              style={{
                marginRight: 10,
                padding: '8px 14px',
                borderRadius: 6,
                border: '1px solid #ccc',
                background: activeType === tab.value ? '#007bff' : '#fff',
                color: activeType === tab.value ? '#fff' : '#222',
                cursor: 'pointer',
              }}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {error && (
          <div style={{ marginBottom: 15, color: '#b00020', background: '#ffe8ea', padding: 10, borderRadius: 6 }}>
            {error}
          </div>
        )}

        <section style={{ marginBottom: 24, padding: 16, border: '1px solid #ddd', borderRadius: 8 }}>
          <h2 style={{ marginTop: 0 }}>{editingId ? 'Edit Record' : 'Add New Record'}</h2>
          <form onSubmit={onSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, minmax(140px, 1fr))', gap: 12 }}>
              <div>
                <label>Year</label>
                <input type="number" name="year" value={form.year} onChange={onChange} required style={{ width: '100%', padding: 8 }} />
              </div>
              <div>
                <label>Half Year</label>
                <select name="half_year" value={form.half_year} onChange={onChange} required style={{ width: '100%', padding: 8 }}>
                  {HALF_YEARS.map((h) => <option key={h} value={h}>{h}</option>)}
                </select>
              </div>
              <div>
                <label>{amountLabel}</label>
                <input type="number" step="0.01" min="0" name="amount" value={form.amount} onChange={onChange} required style={{ width: '100%', padding: 8 }} />
              </div>
              <div>
                <label>Currency</label>
                <input type="text" name="currency" value={form.currency} onChange={onChange} required style={{ width: '100%', padding: 8 }} />
              </div>
            </div>

            <div style={{ marginTop: 12 }}>
              <label>Notes (optional)</label>
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
          <h2>{TYPES.find((t) => t.value === activeType)?.label} Records</h2>
          {loading ? (
            <p>Loading...</p>
          ) : sortedRecords.length === 0 ? (
            <p>No records found.</p>
          ) : (
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ background: '#f5f5f5' }}>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Year</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Half</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Amount</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Currency</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Notes</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {sortedRecords.map((r) => (
                  <tr key={r.id}>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{r.year}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{r.half_year}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{Number(r.amount).toLocaleString()}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{r.currency}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{r.notes || '-'}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>
                      <button onClick={() => onEdit(r)} style={{ marginRight: 8 }}>Edit</button>
                      <button onClick={() => onDelete(r.id)}>Delete</button>
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
