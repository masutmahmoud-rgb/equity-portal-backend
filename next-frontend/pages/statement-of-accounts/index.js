import { useEffect, useState } from 'react';
import useSWR from 'swr';
import Layout from '../../components/Layout';

const fetcher = (url) => fetch(url).then((res) => res.json());

export default function StatementOfAccountIndex() {
  const { data, error } = useSWR('/api/statement-of-accounts', fetcher, { refreshInterval: 2000 });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (data || error) setLoading(false);
  }, [data, error]);

  if (loading) return <Layout><div className="container"><p>Loading...</p></div></Layout>;
  if (error) return <Layout><div className="container"><p>Error loading statement of accounts</p></div></Layout>;

  const accounts = data?.data || [];

  const handleDelete = async (id) => {
    if (!confirm('Are you sure?')) return;
    try {
      await fetch(`/api/statement-of-accounts/${id}`, { method: 'DELETE' });
      // SWR will refetch automatically
    } catch (err) {
      alert('Error deleting record');
    }
  };

  return (
    <Layout>
      <div className="container">
        <div style={{ marginBottom: '20px' }}>
          <h1>Statement of Accounts</h1>
          <a href="/statement-of-accounts/create" className="btn btn-primary">
            Create New Record
          </a>
        </div>

        {accounts.length === 0 ? (
          <p>No statement of account records found.</p>
        ) : (
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ backgroundColor: '#f5f5f5' }}>
                <th style={{ border: '1px solid #ddd', padding: '8px' }}>ID</th>
                <th style={{ border: '1px solid #ddd', padding: '8px' }}>Company</th>
                <th style={{ border: '1px solid #ddd', padding: '8px' }}>Investor</th>
                <th style={{ border: '1px solid #ddd', padding: '8px' }}>Type</th>
                <th style={{ border: '1px solid #ddd', padding: '8px' }}>Amount</th>
                <th style={{ border: '1px solid #ddd', padding: '8px' }}>Status</th>
                <th style={{ border: '1px solid #ddd', padding: '8px' }}>Date</th>
                <th style={{ border: '1px solid #ddd', padding: '8px' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {accounts.map((account) => (
                <tr key={account.id}>
                  <td style={{ border: '1px solid #ddd', padding: '8px' }}>{account.id}</td>
                  <td style={{ border: '1px solid #ddd', padding: '8px' }}>{account.company.name}</td>
                  <td style={{ border: '1px solid #ddd', padding: '8px' }}>{account.investor.name}</td>
                  <td style={{ border: '1px solid #ddd', padding: '8px' }}>{account.transaction_type}</td>
                  <td style={{ border: '1px solid #ddd', padding: '8px' }}>${parseFloat(account.amount).toFixed(2)}</td>
                  <td style={{ border: '1px solid #ddd', padding: '8px' }}>{account.status}</td>
                  <td style={{ border: '1px solid #ddd', padding: '8px' }}>
                    {new Date(account.transaction_date).toLocaleDateString()}
                  </td>
                  <td style={{ border: '1px solid #ddd', padding: '8px' }}>
                    <a href={`/statement-of-accounts/${account.id}`} className="btn btn-sm btn-info" style={{ marginRight: '5px' }}>
                      View
                    </a>
                    <a href={`/statement-of-accounts/${account.id}/edit`} className="btn btn-sm btn-warning" style={{ marginRight: '5px' }}>
                      Edit
                    </a>
                    <button onClick={() => handleDelete(account.id)} className="btn btn-sm btn-danger">
                      Delete
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </Layout>
  );
}
