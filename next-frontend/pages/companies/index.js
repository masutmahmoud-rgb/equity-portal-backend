import useSWR from 'swr';
import Layout from '../../components/Layout';

const fetcher = async (url) => {
  const res = await fetch(url);
  if (!res.ok) throw new Error('Failed to fetch companies');
  return res.json();
};

export default function CompaniesPage() {
  const { data, error, isLoading } = useSWR('/api/companies', fetcher);

  if (isLoading) {
    return <Layout><div className="container"><p>Loading companies...</p></div></Layout>;
  }

  if (error) {
    return <Layout><div className="container"><p>Failed to load companies.</p></div></Layout>;
  }

  const companies = data?.data || [];

  return (
    <Layout>
      <div className="container">
        <h1>Companies</h1>
        {companies.length === 0 ? (
          <p>No companies found.</p>
        ) : (
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ backgroundColor: '#f5f5f5' }}>
                <th style={{ border: '1px solid #ddd', padding: '8px' }}>ID</th>
                <th style={{ border: '1px solid #ddd', padding: '8px' }}>Name</th>
                <th style={{ border: '1px solid #ddd', padding: '8px' }}>Sector</th>
                <th style={{ border: '1px solid #ddd', padding: '8px' }}>Status</th>
              </tr>
            </thead>
            <tbody>
              {companies.map((company) => (
                <tr key={company.id}>
                  <td style={{ border: '1px solid #ddd', padding: '8px' }}>{company.id}</td>
                  <td style={{ border: '1px solid #ddd', padding: '8px' }}>{company.name || '-'}</td>
                  <td style={{ border: '1px solid #ddd', padding: '8px' }}>{company.sector || '-'}</td>
                  <td style={{ border: '1px solid #ddd', padding: '8px' }}>{company.status || '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </Layout>
  );
}
