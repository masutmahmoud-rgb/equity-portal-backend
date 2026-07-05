import { useEffect, useMemo, useState } from 'react';
import Layout from '../../components/Layout';

const DEFAULT_FORM = {
  notification_type: 'Capital Raise',
  title: '',
  message: '',
  important_notes: '',
  publish_date: new Date().toISOString().split('T')[0],
  expiry_date: '',
  is_active: true,
};

export default function NotificationsPage() {
  const [records, setRecords] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [editingId, setEditingId] = useState(null);
  const [form, setForm] = useState(DEFAULT_FORM);

  const loadRecords = async () => {
    setLoading(true);
    setError('');
    try {
      const res = await fetch('/api/notifications');
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Failed to load notifications');
      setRecords(data.data || []);
    } catch (e) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadRecords();
  }, []);

  const sortedRecords = useMemo(() => {
    return [...records].sort((a, b) => new Date(b.publish_date) - new Date(a.publish_date));
  }, [records]);

  const onChange = (e) => {
    const { name, value, type, checked } = e.target;
    setForm((prev) => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
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
        ...form,
        expiry_date: form.expiry_date || null,
      };

      const url = editingId ? `/api/notifications/${editingId}` : '/api/notifications';
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
      notification_type: row.notification_type,
      title: row.title,
      message: row.message,
      important_notes: row.important_notes || '',
      publish_date: row.publish_date ? String(row.publish_date).slice(0, 10) : '',
      expiry_date: row.expiry_date ? String(row.expiry_date).slice(0, 10) : '',
      is_active: !!row.is_active,
    });
  };

  const onDelete = async (id) => {
    if (!confirm('Delete this notification?')) return;
    setError('');
    try {
      const res = await fetch(`/api/notifications/${id}`, { method: 'DELETE' });
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
        <h1>Notifications</h1>

        {error && (
          <div style={{ marginBottom: 15, color: '#b00020', background: '#ffe8ea', padding: 10, borderRadius: 6 }}>
            {error}
          </div>
        )}

        <section style={{ marginBottom: 24, padding: 16, border: '1px solid #ddd', borderRadius: 8 }}>
          <h2 style={{ marginTop: 0 }}>{editingId ? 'Edit Notification' : 'Create Notification'}</h2>
          <form onSubmit={onSubmit}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, minmax(200px, 1fr))', gap: 12 }}>
              <div>
                <label>Notification Type</label>
                <input name="notification_type" value={form.notification_type} onChange={onChange} required style={{ width: '100%', padding: 8 }} />
              </div>
              <div>
                <label>Title</label>
                <input name="title" value={form.title} onChange={onChange} required style={{ width: '100%', padding: 8 }} />
              </div>
              <div>
                <label>Publish Date</label>
                <input type="date" name="publish_date" value={form.publish_date} onChange={onChange} required style={{ width: '100%', padding: 8 }} />
              </div>
              <div>
                <label>Expiry Date (optional)</label>
                <input type="date" name="expiry_date" value={form.expiry_date} onChange={onChange} style={{ width: '100%', padding: 8 }} />
              </div>
            </div>

            <div style={{ marginTop: 12 }}>
              <label>Message</label>
              <textarea name="message" value={form.message} onChange={onChange} rows={4} required style={{ width: '100%', padding: 8 }} />
            </div>

            <div style={{ marginTop: 12 }}>
              <label>Important Notes</label>
              <textarea name="important_notes" value={form.important_notes} onChange={onChange} rows={3} style={{ width: '100%', padding: 8 }} />
            </div>

            <div style={{ marginTop: 12 }}>
              <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                <input type="checkbox" name="is_active" checked={form.is_active} onChange={onChange} />
                Is Active
              </label>
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
          <h2>Notification Records</h2>
          {loading ? (
            <p>Loading...</p>
          ) : sortedRecords.length === 0 ? (
            <p>No notifications found.</p>
          ) : (
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr style={{ background: '#f5f5f5' }}>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Type</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Title</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Publish</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Expiry</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Active</th>
                  <th style={{ padding: 8, border: '1px solid #ddd' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {sortedRecords.map((r) => (
                  <tr key={r.id}>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{r.notification_type}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{r.title}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{String(r.publish_date).slice(0, 10)}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{r.expiry_date ? String(r.expiry_date).slice(0, 10) : '-'}</td>
                    <td style={{ padding: 8, border: '1px solid #ddd' }}>{r.is_active ? 'Yes' : 'No'}</td>
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
