import { useState, useEffect } from 'react';
import { useRouter } from 'next/router';
import Layout from '../../components/Layout';
import DividendForm from '../../components/DividendForm';
import WithdrawalForm from '../../components/WithdrawalForm';

export default function CreateStatementOfAccount() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [companies, setCompanies] = useState([]);
  const [partners, setPartners] = useState([]);
  const [transactionType, setTransactionType] = useState('Dividend');

  useEffect(() => {
    const fetchData = async () => {
      try {
        const [companiesRes, partnersRes] = await Promise.all([
          fetch('/api/companies'),
          fetch('/api/investors'),
        ]);

        setCompanies(companiesRes.ok ? (await companiesRes.json()).data : []);
        setPartners(partnersRes.ok ? (await partnersRes.json()).data : []);
      } catch (err) {
        alert('Error loading reference data');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, []);

  if (loading) return <Layout><div className="container"><p>Loading...</p></div></Layout>;

  return (
    <Layout>
      <div className="container">
        <h1>Register Transaction</h1>
        
        <div style={{ marginBottom: '20px', paddingBottom: '15px', borderBottom: '1px solid #ddd' }}>
          <label style={{ fontWeight: 'bold', marginRight: '20px' }}>Transaction Type:</label>
          <button
            onClick={() => setTransactionType('Dividend')}
            style={{
              padding: '8px 16px',
              marginRight: '10px',
              backgroundColor: transactionType === 'Dividend' ? '#007bff' : '#f0f0f0',
              color: transactionType === 'Dividend' ? 'white' : '#333',
              border: `2px solid ${transactionType === 'Dividend' ? '#007bff' : '#ddd'}`,
              borderRadius: '4px',
              cursor: 'pointer',
              fontWeight: transactionType === 'Dividend' ? 'bold' : 'normal',
            }}
          >
            Dividend
          </button>
          <button
            onClick={() => setTransactionType('Withdrawal')}
            style={{
              padding: '8px 16px',
              marginRight: '10px',
              backgroundColor: transactionType === 'Withdrawal' ? '#007bff' : '#f0f0f0',
              color: transactionType === 'Withdrawal' ? 'white' : '#333',
              border: `2px solid ${transactionType === 'Withdrawal' ? '#007bff' : '#ddd'}`,
              borderRadius: '4px',
              cursor: 'pointer',
              fontWeight: transactionType === 'Withdrawal' ? 'bold' : 'normal',
            }}
          >
            Withdrawal
          </button>
          <button
            onClick={() => setTransactionType('Addition')}
            style={{
              padding: '8px 16px',
              backgroundColor: transactionType === 'Addition' ? '#007bff' : '#f0f0f0',
              color: transactionType === 'Addition' ? 'white' : '#333',
              border: `2px solid ${transactionType === 'Addition' ? '#007bff' : '#ddd'}`,
              borderRadius: '4px',
              cursor: 'pointer',
              fontWeight: transactionType === 'Addition' ? 'bold' : 'normal',
            }}
          >
            Addition
          </button>
        </div>

        {transactionType === 'Dividend' ? (
          <DividendForm companies={companies} partners={partners} />
        ) : transactionType === 'Withdrawal' ? (
          <WithdrawalForm companies={companies} partners={partners} />
        ) : (
          <WithdrawalForm companies={companies} partners={partners} mode="Addition" />
        )}
      </div>
    </Layout>
  );
}
