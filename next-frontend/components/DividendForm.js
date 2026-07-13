import { useState } from 'react';
import { useRouter } from 'next/router';

export default function DividendForm({ companies = [], partners = [], existingRecord = null }) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState({});
  const [formData, setFormData] = useState({
    company_id: '',
    investor_id: '',
    amount: '',
    currency: 'EGP',
    exchange_rate: '1',
    status: 'Pending',
    transaction_date: new Date().toISOString().split('T')[0],
    notes: '',
    ...(existingRecord || {}),
  });

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData({ ...formData, [name]: value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setErrors({});

    // Client-side validation for required fields
    const clientErrors = {};
    if (!formData.company_id) clientErrors.company_id = ['Company is required'];
    if (!formData.investor_id) clientErrors.investor_id = ['Partner is required'];
    if (!formData.amount) clientErrors.amount = ['Amount is required'];
    if (!formData.currency) clientErrors.currency = ['Currency is required'];
    if (!formData.exchange_rate || Number(formData.exchange_rate) <= 0) clientErrors.exchange_rate = ['Exchange rate must be greater than zero'];
    if (!formData.transaction_date) clientErrors.transaction_date = ['Transaction date is required'];

    if (Object.keys(clientErrors).length > 0) {
      setErrors(clientErrors);
      setLoading(false);
      return;
    }

    try {
      const payload = new FormData();
      payload.append('company_id', formData.company_id);
      payload.append('investor_id', formData.investor_id);
      payload.append('amount', formData.amount);
      payload.append('currency', formData.currency);
      payload.append('exchange_rate', formData.exchange_rate);
      payload.append('status', formData.status);
      payload.append('transaction_date', formData.transaction_date);
      payload.append('transaction_type', 'Dividend');
      if (formData.notes) {
        payload.append('notes', formData.notes);
      }

      const method = 'POST';
      const url = existingRecord
        ? `/api/statement-of-accounts/${existingRecord.id}`
        : '/api/statement-of-accounts';

      if (existingRecord) {
        payload.append('_method', 'PUT');
      }

      const response = await fetch(url, {
        method,
        body: payload,
      });

      if (!response.ok) {
        const data = await response.json();
        setErrors(data.errors || { general: 'An error occurred' });
        return;
      }

      router.push('/statement-of-accounts');
    } catch (err) {
      setErrors({ general: err.message });
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} style={{ maxWidth: '600px' }}>
      {errors.general && <div style={{ color: 'red', marginBottom: '10px', padding: '10px', backgroundColor: '#ffe0e0', borderRadius: '4px' }}>{errors.general}</div>}

      <div style={{ marginBottom: '15px' }}>
        <label style={{ fontWeight: 'bold' }}>Company *</label>
        <select
          name="company_id"
          value={formData.company_id}
          onChange={handleInputChange}
          required
          style={{ width: '100%', padding: '8px', borderColor: errors.company_id ? 'red' : '#ccc', border: '1px solid', borderRadius: '4px' }}
        >
          <option value="">Select Company</option>
          {companies.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
        {errors.company_id && <p style={{ color: 'red', fontSize: '0.9em' }}>{errors.company_id[0]}</p>}
      </div>

      <div style={{ marginBottom: '15px' }}>
        <label style={{ fontWeight: 'bold' }}>Partner *</label>
        <select
          name="investor_id"
          value={formData.investor_id}
          onChange={handleInputChange}
          required
          style={{ width: '100%', padding: '8px', borderColor: errors.investor_id ? 'red' : '#ccc', border: '1px solid', borderRadius: '4px' }}
        >
          <option value="">Select Partner</option>
          {partners.map((p) => (
            <option key={p.id} value={p.id}>
              {p.name}
            </option>
          ))}
        </select>
        {errors.investor_id && <p style={{ color: 'red', fontSize: '0.9em' }}>{errors.investor_id[0]}</p>}
      </div>

      <div style={{ marginBottom: '15px' }}>
        <label style={{ fontWeight: 'bold' }}>Amount (Original Currency) *</label>
        <input
          type="number"
          name="amount"
          step="0.01"
          min="0"
          value={formData.amount}
          onChange={handleInputChange}
          required
          style={{ width: '100%', padding: '8px', borderColor: errors.amount ? 'red' : '#ccc', border: '1px solid', borderRadius: '4px' }}
        />
        {errors.amount && <p style={{ color: 'red', fontSize: '0.9em' }}>{errors.amount[0]}</p>}
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: '15px' }}>
        <div>
          <label style={{ fontWeight: 'bold' }}>Currency *</label>
          <input
            type="text"
            name="currency"
            value={formData.currency}
            onChange={handleInputChange}
            maxLength={3}
            required
            style={{ width: '100%', padding: '8px', textTransform: 'uppercase', borderColor: errors.currency ? 'red' : '#ccc', border: '1px solid', borderRadius: '4px' }}
          />
          {errors.currency && <p style={{ color: 'red', fontSize: '0.9em' }}>{errors.currency[0]}</p>}
        </div>
        <div>
          <label style={{ fontWeight: 'bold' }}>Exchange Rate to EGP *</label>
          <input
            type="number"
            name="exchange_rate"
            step="0.000001"
            min="0.000001"
            value={formData.exchange_rate}
            onChange={handleInputChange}
            required
            style={{ width: '100%', padding: '8px', borderColor: errors.exchange_rate ? 'red' : '#ccc', border: '1px solid', borderRadius: '4px' }}
          />
          {errors.exchange_rate && <p style={{ color: 'red', fontSize: '0.9em' }}>{errors.exchange_rate[0]}</p>}
        </div>
      </div>

      <p style={{ marginTop: '-6px', marginBottom: '15px', color: '#555', fontSize: '0.9em' }}>
        Statement amount is stored in EGP as Amount × Exchange Rate.
      </p>

      <div style={{ marginBottom: '15px' }}>
        <label style={{ fontWeight: 'bold' }}>Status *</label>
        <select
          name="status"
          value={formData.status}
          onChange={handleInputChange}
          required
          style={{ width: '100%', padding: '8px', borderColor: errors.status ? 'red' : '#ccc', border: '1px solid', borderRadius: '4px' }}
        >
          <option value="Pending">Pending</option>
          <option value="Paid">Paid</option>
        </select>
        {errors.status && <p style={{ color: 'red', fontSize: '0.9em' }}>{errors.status[0]}</p>}
      </div>

      <div style={{ marginBottom: '15px' }}>
        <label style={{ fontWeight: 'bold' }}>Transaction Date *</label>
        <input
          type="date"
          name="transaction_date"
          value={formData.transaction_date}
          onChange={handleInputChange}
          required
          style={{ width: '100%', padding: '8px', borderColor: errors.transaction_date ? 'red' : '#ccc', border: '1px solid', borderRadius: '4px' }}
        />
        {errors.transaction_date && <p style={{ color: 'red', fontSize: '0.9em' }}>{errors.transaction_date[0]}</p>}
      </div>

      <div style={{ marginBottom: '15px' }}>
        <label>Notes</label>
        <textarea
          name="notes"
          value={formData.notes}
          onChange={handleInputChange}
          style={{ width: '100%', padding: '8px', minHeight: '80px', border: '1px solid #ccc', borderRadius: '4px' }}
        />
        {errors.notes && <p style={{ color: 'red', fontSize: '0.9em' }}>{errors.notes[0]}</p>}
      </div>

      <div style={{ marginBottom: '15px', paddingTop: '10px' }}>
        <button 
          type="submit" 
          disabled={loading || !formData.company_id || !formData.investor_id || !formData.amount || !formData.currency || !formData.exchange_rate}
          style={{ 
            padding: '10px 20px', 
            cursor: loading ? 'not-allowed' : 'pointer',
            backgroundColor: (loading || !formData.company_id || !formData.investor_id || !formData.amount || !formData.currency || !formData.exchange_rate) ? '#ccc' : '#007bff',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            marginRight: '10px',
          }}
        >
          {loading ? 'Creating...' : 'Create Dividend'}
        </button>
        <a 
          href="/statement-of-accounts" 
          style={{ 
            padding: '10px 20px', 
            textDecoration: 'none',
            color: '#007bff',
            cursor: 'pointer',
          }}
        >
          Cancel
        </a>
      </div>
    </form>
  );
}
