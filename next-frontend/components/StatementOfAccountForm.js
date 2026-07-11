import { useState } from 'react';
import { useRouter } from 'next/router';

export default function StatementOfAccountForm({ companies = [], investors = [], investments = [], existingRecord = null }) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState({});
  const [files, setFiles] = useState([]);
  const [formData, setFormData] = useState(existingRecord || {
    company_id: '',
    investment_id: '',
    investor_id: '',
    transaction_type: 'Dividend',
    entry_direction: 'Credit',
    amount: '',
    status: 'Pending',
    transaction_date: new Date().toISOString().split('T')[0],
    description: '',
    notes: '',
  });

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData({ ...formData, [name]: value });
  };

  const handleFileChange = (e) => {
    setFiles(Array.from(e.target.files || []));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setErrors({});

    // Client-side validation for required fields
    const clientErrors = {};
    if (!formData.company_id) clientErrors.company_id = ['Company is required'];
    if (!formData.investment_id) clientErrors.investment_id = ['Investment is required'];
    if (!formData.investor_id) clientErrors.investor_id = ['Investor is required'];
    if (!formData.amount) clientErrors.amount = ['Amount is required'];
    if (!formData.transaction_date) clientErrors.transaction_date = ['Transaction date is required'];

    if (Object.keys(clientErrors).length > 0) {
      setErrors(clientErrors);
      setLoading(false);
      return;
    }

    try {
      const payload = new FormData();
      Object.keys(formData).forEach((key) => {
        if (formData[key] !== '' && formData[key] !== null) {
          payload.append(key, formData[key]);
        }
      });

      // Only add attachments if it's a Withdrawal and files are provided
      if (formData.transaction_type === 'Withdrawal' && files.length > 0) {
        files.forEach((file, index) => {
          payload.append(`attachments[${index}]`, file);
        });
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

  // Get investments for selected company
  const availableInvestments = investments.filter(
    (inv) => !formData.company_id || inv.company_id == formData.company_id
  );

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
        <label style={{ fontWeight: 'bold' }}>Investment *</label>
        {!formData.company_id ? (
          <input
            type="text"
            disabled
            placeholder="Select a company first"
            style={{ width: '100%', padding: '8px', backgroundColor: '#f5f5f5', borderRadius: '4px', border: '1px solid #ccc' }}
          />
        ) : (
          <>
            <select
              name="investment_id"
              value={formData.investment_id}
              onChange={handleInputChange}
              required
              style={{
                width: '100%',
                padding: '8px',
                borderColor: errors.investment_id ? 'red' : '#ccc',
                border: '1px solid',
                borderRadius: '4px',
              }}
            >
              <option value="">Select Investment</option>
              {availableInvestments.map((inv) => (
                <option key={inv.id} value={inv.id}>
                  {inv.investor.name} - ${parseFloat(inv.amount).toFixed(2)}
                </option>
              ))}
            </select>
            {availableInvestments.length === 0 && (
              <p style={{ color: '#ff6600', fontSize: '0.9em', marginTop: '5px' }}>
                ⚠️ No investments found for this company. Please select a different company or create an investment first.
              </p>
            )}
          </>
        )}
        {errors.investment_id && <p style={{ color: 'red', fontSize: '0.9em' }}>{errors.investment_id[0]}</p>}
      </div>

      <div style={{ marginBottom: '15px' }}>
        <label style={{ fontWeight: 'bold' }}>Investor *</label>
        <select
          name="investor_id"
          value={formData.investor_id}
          onChange={handleInputChange}
          required
          style={{ width: '100%', padding: '8px', borderColor: errors.investor_id ? 'red' : '#ccc', border: '1px solid', borderRadius: '4px' }}
        >
          <option value="">Select Investor</option>
          {investors.map((inv) => (
            <option key={inv.id} value={inv.id}>
              {inv.name}
            </option>
          ))}
        </select>
        {errors.investor_id && <p style={{ color: 'red', fontSize: '0.9em' }}>{errors.investor_id[0]}</p>}
      </div>

      <div style={{ marginBottom: '15px' }}>
        <label style={{ fontWeight: 'bold' }}>Transaction Type *</label>
        <select
          name="transaction_type"
          value={formData.transaction_type}
          onChange={handleInputChange}
          required
          style={{ width: '100%', padding: '8px', borderColor: errors.transaction_type ? 'red' : '#ccc', border: '1px solid', borderRadius: '4px' }}
        >
          <option value="Dividend">Dividend</option>
          <option value="Withdrawal">Withdrawal</option>
          <option value="Deposit">Deposit</option>
        </select>
        {errors.transaction_type && <p style={{ color: 'red', fontSize: '0.9em' }}>{errors.transaction_type[0]}</p>}
      </div>

      {formData.transaction_type === 'Deposit' && (
        <div style={{ marginBottom: '15px' }}>
          <label style={{ fontWeight: 'bold' }}>Direction *</label>
          <select
            name="entry_direction"
            value={formData.entry_direction}
            onChange={handleInputChange}
            required
            style={{ width: '100%', padding: '8px', borderColor: errors.entry_direction ? 'red' : '#ccc', border: '1px solid', borderRadius: '4px' }}
          >
            <option value="Credit">Credit</option>
            <option value="Debit">Debit</option>
          </select>
          {errors.entry_direction && <p style={{ color: 'red', fontSize: '0.9em' }}>{errors.entry_direction[0]}</p>}
        </div>
      )}

      <div style={{ marginBottom: '15px' }}>
        <label style={{ fontWeight: 'bold' }}>Amount *</label>
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
        <label>Description</label>
        <textarea
          name="description"
          value={formData.description}
          onChange={handleInputChange}
          style={{ width: '100%', padding: '8px', minHeight: '80px', border: '1px solid #ccc', borderRadius: '4px' }}
        />
        {errors.description && <p style={{ color: 'red', fontSize: '0.9em' }}>{errors.description[0]}</p>}
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

      {formData.transaction_type === 'Withdrawal' && (
        <div style={{ marginBottom: '15px' }}>
          <label>Attachments (for Withdrawals only)</label>
          <input
            type="file"
            multiple
            onChange={handleFileChange}
            style={{ width: '100%', padding: '8px', border: '1px solid #ccc', borderRadius: '4px' }}
          />
          <p style={{ fontSize: '0.9em', color: '#666' }}>
            Max 10MB per file. {files.length} file(s) selected.
          </p>
          {errors.attachments && <p style={{ color: 'red', fontSize: '0.9em' }}>{errors.attachments[0]}</p>}
          {errors['attachments.0'] && <p style={{ color: 'red', fontSize: '0.9em' }}>File upload failed. Contact support if this persists.</p>}
        </div>
      )}

      <div style={{ marginBottom: '15px', paddingTop: '10px' }}>
        <button 
          type="submit" 
          disabled={loading || !formData.company_id || !formData.investment_id || !formData.investor_id}
          style={{ 
            padding: '10px 20px', 
            cursor: loading ? 'not-allowed' : 'pointer',
            backgroundColor: loading ? '#ccc' : '#007bff',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            marginRight: '10px',
            opacity: loading || !formData.company_id || !formData.investment_id || !formData.investor_id ? 0.6 : 1
          }}
        >
          {loading ? 'Saving...' : existingRecord ? 'Update' : 'Create'}
        </button>
        <a href="/statement-of-accounts" style={{ marginLeft: '10px', color: '#007bff', textDecoration: 'none' }}>
          Cancel
        </a>
      </div>
    </form>
  );
}
