import Link from 'next/link';
import { useRouter } from 'next/router';
import useSWR from 'swr';
import PartnerPortalLayout from '../../../components/PartnerPortalLayout';

const fetcher = (url) => fetch(url).then((res) => res.json());

export default function ValuationHistoryPage() {
  const router = useRouter();
  const { investor_id } = router.query;

  const { data, error } = useSWR(
    investor_id ? `/api/partner-portal/${investor_id}/valuation-history` : null,
    fetcher,
  );

  if (error) return <PartnerPortalLayout investorId={investor_id || ''} title="Valuation History"><p style={{ color: 'red' }}>Error loading valuation history.</p></PartnerPortalLayout>;
  if (!data) return <PartnerPortalLayout investorId={investor_id || ''} title="Valuation History"><p>Loading...</p></PartnerPortalLayout>;

  const rows = data.data || [];

  return (
    <PartnerPortalLayout investorId={investor_id || ''} title="Valuation History">
      {rows.length === 0 ? (
        <p>No published valuations found.</p>
      ) : (
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr style={{ background: '#f5f5f5' }}>
              <th style={{ padding: 8, border: '1px solid #ddd' }}>Company</th>
              <th style={{ padding: 8, border: '1px solid #ddd' }}>Period</th>
              <th style={{ padding: 8, border: '1px solid #ddd' }}>Total Invested</th>
              <th style={{ padding: 8, border: '1px solid #ddd' }}>Indicative Value</th>
              <th style={{ padding: 8, border: '1px solid #ddd' }}>Profit</th>
              <th style={{ padding: 8, border: '1px solid #ddd' }}>ROI</th>
              <th style={{ padding: 8, border: '1px solid #ddd' }}>Date</th>
              <th style={{ padding: 8, border: '1px solid #ddd' }}>Action</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id}>
                <td style={{ padding: 8, border: '1px solid #ddd' }}>{row.company?.name || '-'}</td>
                <td style={{ padding: 8, border: '1px solid #ddd' }}>{row.valuation_period}</td>
                <td style={{ padding: 8, border: '1px solid #ddd' }}>{Number(row.total_invested).toLocaleString()}</td>
                <td style={{ padding: 8, border: '1px solid #ddd' }}>{Number(row.indicative_value).toLocaleString()}</td>
                <td style={{ padding: 8, border: '1px solid #ddd' }}>{Number(row.profit).toLocaleString()}</td>
                <td style={{ padding: 8, border: '1px solid #ddd' }}>{row.roi_percentage === null ? '-' : `${row.roi_percentage}%`}</td>
                <td style={{ padding: 8, border: '1px solid #ddd' }}>{row.valuation_date}</td>
                <td style={{ padding: 8, border: '1px solid #ddd' }}>
                  <Link href={`/partner-portal/${investor_id}/valuations/${row.id}`}>Open</Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </PartnerPortalLayout>
  );
}