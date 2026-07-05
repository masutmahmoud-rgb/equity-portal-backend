import { useEffect, useMemo, useState } from 'react';
import Layout from '../../components/Layout';

const HALF_OPTIONS = [
  { value: 'H1', label: 'First Half (H1)' },
  { value: 'H2', label: 'Second Half (H2)' },
];

const YEAR_OPTIONS = Array.from({ length: 16 }, (_, index) => 2020 + index);

const DEFAULT_FORM = {
  partner_id: '',
  company_id: '',
  valuation_year: new Date().getFullYear(),
  valuation_half: 'H1',
  indicative_value: '',
  profit: '',
  valuation_date: new Date().toISOString().split('T')[0],
  notes: '',
  status: 'Draft',
};

export default function PortfolioValuationsPage() {
  const [valuations, setValuations] = useState([]);
  const [companies, setCompanies] = useState([]);
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
      const [valuationsRes, companiesRes, partnersRes] = await Promise.all([
        fetch('/api/portfolio-valuations'),
        fetch('/api/companies'),
        fetch('/api/investors'),
      ]);

      const valuationsJson = await valuationsRes.json();
      const companiesJson = await companiesRes.json();
      const partnersJson = await partnersRes.json();

      if (!valuationsRes.ok) throw new Error(valuationsJson.message || 'Failed to load portfolio valuations');

      setValuations(valuationsJson.data || []);
      setCompanies(companiesRes.ok ? (companiesJson.data || []) : []);
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

  const sortedValuations = useMemo(() => {
    return [...valuations].sort((a, b) => new Date(b.valuation_date || b.created_at) - new Date(a.valuation_date || a.created_at));
  }, [valuations]);

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
        partner_id: Number(form.partner_id),
        company_id: Number(form.company_id),
        valuation_year: Number(form.valuation_year),
        valuation_half: form.valuation_half,
        indicative_value: Number(form.indicative_value),
        profit: Number(form.profit),
        valuation_date: form.valuation_date,
        notes: form.notes || null,
        status: form.status,
      };

      const url = editingId ? `/api/portfolio-valuations/${editingId}` : '/api/portfolio-valuations';
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
    } catch (e2) {
      setError(e2.message);
    } finally {
      setSaving(false);
    }
  };

  const onEdit = (row) => {
    const parsedPeriod = String(row.valuation_period || '').match(/^(\d{4})-(H1|H2)$/);
    setEditingId(row.id);
    setForm({
      partner_id: row.partner_id || row.investor_id || row.partner?.id || row.investor?.id || '',
      company_id: row.company?.id || '',
      valuation_year: row.valuation_year || (parsedPeriod ? Number(parsedPeriod[1]) : new Date().getFullYear()),
      valuation_half: row.valuation_half || (parsedPeriod ? parsedPeriod[2] : 'H1'),
      indicative_value: row.indicative_value || '',
      profit: row.profit || '',
      valuation_date: row.valuation_date ? String(row.valuation_date).slice(0, 10) : '',
      notes: row.notes || '',
      status: row.status || 'Draft',
    });
  };

  const onDelete = async (id) => {
    if (!confirm('Delete this valuation?')) return;
    setError('');

    try {
      const res = await fetch(`/api/portfolio-valuations/${id}`, { method: 'DELETE' });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Delete failed');
      await loadData();
      if (editingId === id) resetForm();
    } catch (e) {
      setError(e.message);
    }
  };

  return (
    <Layout>
      <div className="container">
        <h1>Portfolio Valuations</h1>

        {error && (
          <div style={{ marginBottom: 15, color: '#b00020', background: '#ffe8ea', padding: 10, borderRadius: 6 }}>
            {error}
          </div>
        )}

        <section style={{ marginBottom: 24, padding: 16, border: '1px solid #ddd', borderRadius: 8 }}>
          <h2 style={{ marginTop: 0 }}>{editingId ? 'Edit Valuation' : 'Add New Valuation'}</h2>
          <form onSubmit={onSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(220px, 1fr))', gap: 12 }}>
              <div>
                <label>Partner *</label>
                <select name="partner_id" value={form.partner_id} onChange={onChange} required style={{ width: '100%', padding: 8 }}>
                  <option value="">Select a partner...</option>
                  {partners.map((partner) => (
                    <option key={partner.id} value={partner.id}>
                      {partner.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label>Company *</label>
                <select name="company_id" value={form.company_id} onChange={onChange} required style={{ width: '100%', padding: 8 }}>
                  <option value="">Select a company...</option>
                  {companies.map((company) => (
                    <option key={company.id} value={company.id}>
                      {company.name}
                    </option>
                  ))}
                </select>
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
                <label>Valuation Period</label>
                <input value={generatedPeriod} readOnly style={{ width: '100%', padding: 8, background: '#f7f7f7' }} />
              </div>

              <div>
                <label>Valuation Date *</label>
                <input type="date" name="valuation_date" value={form.valuation_date} onChange={onChange} required style={{ width: '100%', padding: 8 }} />
              </div>

              <div>
                <label>Indicative Value *</label>
                <input type="number" step="0.01" min="0" name="indicative_value" value={form.indicative_value} onChange={onChange} required style={{ width: '100%', padding: 8 }} />
              </div>

              <div>
                <label>Profit *</label>
                <input type="number" step="0.01" min="0" name="profit" value={form.profit} onChange={onChange} required style={{ width: '100%', padding: 8 }} />
              </div>

              <div>
                <label>Status *</label>
                <select name="status" value={form.status} onChange={onChange} required style={{ width: '100%', padding: 8 }}>
                  <option value="Draft">Draft</option>
                  <option value="Published">Published</option>
                </select>
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
          <h2>Valuation Records</h2>
          {loading ? (
            <p>Loading...</p>
          ) : sortedValuations.length === 0 ? (
            <p>No valuations found.</p>
          ) : (
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ background: '#f5f5f5' }}>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Partner</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Company</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Period</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Indicative Value</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Profit</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>ROI</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Date</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Status</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {sortedValuations.map((valuation) => (
                  <tr key={valuation.id}>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{valuation.partner_name || valuation.partner?.name || valuation.investor?.name || '-'}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{valuation.company?.name || '-'}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{valuation.valuation_period}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{Number(valuation.indicative_value).toLocaleString()}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{Number(valuation.profit).toLocaleString()}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{valuation.roi_percentage ?? '-'}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{String(valuation.valuation_date).slice(0, 10)}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{valuation.status}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>
                      <button onClick={() => onEdit(valuation)} style={{ marginRight: 8 }}>Edit</button>
                      <button onClick={() => onDelete(valuation.id)}>Delete</button>
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