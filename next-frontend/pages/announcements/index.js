import { useEffect, useMemo, useState } from 'react';
import Layout from '../../components/Layout';

const DEFAULT_FORM = {
  title: '',
  message: '',
  category: '',
  audience_type: 'All',
  company_id: '',
  investor_id: '',
  publish_date: new Date().toISOString().split('T')[0],
  expiry_date: '',
  status: 'Draft',
};

export default function AnnouncementsPage() {
  const [records, setRecords] = useState([]);
  const [companies, setCompanies] = useState([]);
  const [partners, setPartners] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [editingId, setEditingId] = useState(null);
  const [file, setFile] = useState(null);
  const [form, setForm] = useState(DEFAULT_FORM);

  const loadData = async () => {
    setLoading(true);
    setError('');

    try {
      const [announcementsRes, companiesRes, partnersRes] = await Promise.all([
        fetch('/api/announcements'),
        fetch('/api/companies'),
        fetch('/api/investors'),
      ]);

      const announcementsJson = await announcementsRes.json();
      const companiesJson = await companiesRes.json();
      const partnersJson = await partnersRes.json();

      if (!announcementsRes.ok) throw new Error(announcementsJson.message || 'Failed to load announcements');

      setRecords(announcementsJson.data || []);
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

  const sortedRecords = useMemo(() => {
    return [...records].sort((a, b) => new Date(b.publish_date) - new Date(a.publish_date));
  }, [records]);

  const onChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  const resetForm = () => {
    setEditingId(null);
    setFile(null);
    setForm(DEFAULT_FORM);
  };

  const onSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');

    try {
      const payload = new FormData();
      payload.append('title', form.title);
      payload.append('message', form.message);
      payload.append('category', form.category);
      payload.append('audience_type', form.audience_type);
      payload.append('publish_date', form.publish_date);
      payload.append('expiry_date', form.expiry_date || '');
      payload.append('status', form.status);

      if (form.audience_type === 'Company' && form.company_id) {
        payload.append('company_id', form.company_id);
      }

      if (form.audience_type === 'Partner' && form.investor_id) {
        payload.append('investor_id', form.investor_id);
      }

      if (file) {
        payload.append('attachment', file);
      }

      const url = editingId ? `/api/announcements/${editingId}` : '/api/announcements';
      const method = editingId ? 'POST' : 'POST';
      if (editingId) payload.append('_method', 'PUT');

      const res = await fetch(url, {
        method,
        body: payload,
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
    setEditingId(row.id);
    setFile(null);
    setForm({
      title: row.title || '',
      message: row.message || '',
      category: row.category || '',
      audience_type: row.audience_type || 'All',
      company_id: row.company_id || '',
      investor_id: row.investor_id || '',
      publish_date: row.publish_date ? String(row.publish_date).slice(0, 10) : '',
      expiry_date: row.expiry_date ? String(row.expiry_date).slice(0, 10) : '',
      status: row.status || 'Draft',
    });
  };

  const onDelete = async (id) => {
    if (!confirm('Delete this announcement?')) return;
    setError('');

    try {
      const res = await fetch(`/api/announcements/${id}`, { method: 'DELETE' });
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
        <h1>Announcements</h1>

        {error && (
          <div style={{ marginBottom: 15, color: '#b00020', background: '#ffe8ea', padding: 10, borderRadius: 6 }}>
            {error}
          </div>
        )}

        <section style={{ marginBottom: 24, padding: 16, border: '1px solid #ddd', borderRadius: 8 }}>
          <h2 style={{ marginTop: 0 }}>{editingId ? 'Edit Announcement' : 'Create Announcement'}</h2>
          <form onSubmit={onSubmit} encType="multipart/form-data">
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(220px, 1fr))', gap: 12 }}>
              <div>
                <label>Title *</label>
                <input name="title" value={form.title} onChange={onChange} required style={{ width: '100%', padding: 8 }} />
              </div>

              <div>
                <label>Category *</label>
                <input name="category" value={form.category} onChange={onChange} required style={{ width: '100%', padding: 8 }} />
              </div>

              <div>
                <label>Audience Type *</label>
                <select name="audience_type" value={form.audience_type} onChange={onChange} required style={{ width: '100%', padding: 8 }}>
                  <option value="All">All</option>
                  <option value="Company">Company</option>
                  <option value="Partner">Partner</option>
                </select>
              </div>

              <div>
                <label>Status *</label>
                <select name="status" value={form.status} onChange={onChange} required style={{ width: '100%', padding: 8 }}>
                  <option value="Draft">Draft</option>
                  <option value="Published">Published</option>
                </select>
              </div>

              <div>
                <label>Publish Date *</label>
                <input type="date" name="publish_date" value={form.publish_date} onChange={onChange} required style={{ width: '100%', padding: 8 }} />
              </div>

              <div>
                <label>Expiry Date</label>
                <input type="date" name="expiry_date" value={form.expiry_date} onChange={onChange} style={{ width: '100%', padding: 8 }} />
              </div>
            </div>

            {form.audience_type === 'Company' && (
              <div style={{ marginTop: 12 }}>
                <label>Company *</label>
                <select name="company_id" value={form.company_id} onChange={onChange} required style={{ width: '100%', padding: 8 }}>
                  <option value="">Select a company...</option>
                  {companies.map((company) => (
                    <option key={company.id} value={company.id}>{company.name}</option>
                  ))}
                </select>
              </div>
            )}

            {form.audience_type === 'Partner' && (
              <div style={{ marginTop: 12 }}>
                <label>Partner *</label>
                <select name="investor_id" value={form.investor_id} onChange={onChange} required style={{ width: '100%', padding: 8 }}>
                  <option value="">Select a partner...</option>
                  {partners.map((partner) => (
                    <option key={partner.id} value={partner.id}>{partner.name}</option>
                  ))}
                </select>
              </div>
            )}

            <div style={{ marginTop: 12 }}>
              <label>Message *</label>
              <textarea name="message" value={form.message} onChange={onChange} rows={4} required style={{ width: '100%', padding: 8 }} />
            </div>

            <div style={{ marginTop: 12 }}>
              <label>Attachment</label>
              <input type="file" onChange={(e) => setFile(e.target.files?.[0] || null)} style={{ width: '100%', padding: 8 }} />
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
          <h2>Announcement Records</h2>
          {loading ? (
            <p>Loading...</p>
          ) : sortedRecords.length === 0 ? (
            <p>No announcements found.</p>
          ) : (
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ background: '#f5f5f5' }}>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Title</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Category</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Audience</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Publish</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Expiry</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Status</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {sortedRecords.map((record) => (
                  <tr key={record.id}>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{record.title}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{record.category}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{record.audience_type}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{String(record.publish_date).slice(0, 10)}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{record.expiry_date ? String(record.expiry_date).slice(0, 10) : '-'}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{record.status}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>
                      <button onClick={() => onEdit(record)} style={{ marginRight: 8 }}>Edit</button>
                      <button onClick={() => onDelete(record.id)}>Delete</button>
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