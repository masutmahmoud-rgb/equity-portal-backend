import { useEffect, useState } from 'react';
import { useRouter } from 'next/router';
import useSWR from 'swr';
import Layout from '../../../components/Layout';

const fetcher = (url) => fetch(url).then((res) => res.json());

export default function ViewStatementOfAccount() {
  const router = useRouter();
  const { id } = router.query;
  const { data, error } = useSWR(id ? `/api/statement-of-accounts/${id}` : null, fetcher);

  if (!id) return <Layout><div className="container"><p>Loading...</p></div></Layout>;
  if (error) return <Layout><div className="container"><p>Error loading record</p></div></Layout>;
  if (!data) return <Layout><div className="container"><p>Loading...</p></div></Layout>;

  const record = data.data;

  const handleDelete = async () => {
    if (!confirm('Are you sure?')) return;
    try {
      await fetch(`/api/statement-of-accounts/${id}`, { method: 'DELETE' });
      router.push('/statement-of-accounts');
    } catch (err) {
      alert('Error deleting record');
    }
  };

  return (
    <Layout>
      <div className="container">
        <div style={{ marginBottom: '20px' }}>
          <h1>Statement of Account #{record.id}</h1>
          <a href="/statement-of-accounts" style={{ marginRight: '10px' }}>
            ← Back
          </a>
          <a href={`/statement-of-accounts/${id}/edit`} style={{ marginRight: '10px' }}>
            Edit
          </a>
          <button onClick={handleDelete} style={{ color: 'red' }}>
            Delete
          </button>
        </div>

        <div style={{ maxWidth: '600px', backgroundColor: '#f9f9f9', padding: '20px', borderRadius: '5px' }}>
          <p>
            <strong>Company:</strong> {record.company.name}
          </p>
          <p>
            <strong>Investor:</strong> {record.investor.name}
          </p>
          <p>
            <strong>Type:</strong> {record.transaction_type}
          </p>
          <p>
            <strong>Amount:</strong> ${parseFloat(record.amount).toFixed(2)}
          </p>
          <p>
            <strong>Status:</strong> {record.status}
          </p>
          <p>
            <strong>Date:</strong> {new Date(record.transaction_date).toLocaleDateString()}
          </p>
          {record.notes && (
            <p>
              <strong>Notes:</strong> {record.notes}
            </p>
          )}

          {record.attachment_urls && record.attachment_urls.length > 0 && (
            <div>
              <strong>Attachments:</strong>
              <ul>
                {record.attachment_urls.map((url, index) => (
                  <li key={index}>
                    <a href={url} target="_blank" rel="noopener noreferrer">
                      Download Attachment {index + 1}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          )}

          <p style={{ fontSize: '0.9em', color: '#666' }}>
            Created: {new Date(record.created_at).toLocaleString()} | Updated: {new Date(record.updated_at).toLocaleString()}
          </p>
        </div>
      </div>
    </Layout>
  );
}
