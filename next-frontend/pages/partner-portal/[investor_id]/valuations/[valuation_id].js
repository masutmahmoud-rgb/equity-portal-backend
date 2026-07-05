import { useRouter } from 'next/router';
import useSWR from 'swr';
import PartnerPortalLayout from '../../../../components/PartnerPortalLayout';

const fetcher = (url) => fetch(url).then((res) => res.json());

function ValueCard({ label, value }) {
  return (
    <div style={{ flex: '1 1 260px', padding: 16, border: '1px solid #ddd', borderRadius: 8, background: '#fff' }}>
      <div style={{ fontSize: 12, color: '#666', marginBottom: 6 }}>{label}</div>
      <div style={{ fontSize: 22, fontWeight: 700 }}>{value ?? '-'}</div>
    </div>
  );
}

export default function ValuationDetailPage() {
  const router = useRouter();
  const { investor_id, valuation_id } = router.query;

  const { data, error } = useSWR(
    investor_id && valuation_id ? `/api/partner-portal/${investor_id}/valuations/${valuation_id}` : null,
    fetcher,
  );

  if (error) return <PartnerPortalLayout investorId={investor_id || ''} title="Valuation Detail"><p style={{ color: 'red' }}>Error loading valuation.</p></PartnerPortalLayout>;
  if (!data) return <PartnerPortalLayout investorId={investor_id || ''} title="Valuation Detail"><p>Loading...</p></PartnerPortalLayout>;

  const valuation = data.data;

  return (
    <PartnerPortalLayout investorId={investor_id || ''} title="Valuation Detail">
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12, marginBottom: 20 }}>
        <ValueCard label="Company" value={valuation.company?.name} />
        <ValueCard label="Valuation Period" value={valuation.valuation_period} />
        <ValueCard label="Total Invested" value={Number(valuation.total_invested).toLocaleString()} />
        <ValueCard label="Indicative Value" value={Number(valuation.indicative_value).toLocaleString()} />
        <ValueCard label="Current Value" value={Number(valuation.current_value).toLocaleString()} />
        <ValueCard label="Profit" value={Number(valuation.profit).toLocaleString()} />
        <ValueCard label="ROI" value={valuation.roi_percentage === null ? '-' : `${valuation.roi_percentage}%`} />
        <ValueCard label="Valuation Date" value={valuation.valuation_date} />
      </div>

      <section style={{ padding: 16, border: '1px solid #ddd', borderRadius: 8, background: '#fff' }}>
        <h2 style={{ marginTop: 0 }}>Notes</h2>
        <p style={{ marginBottom: 0 }}>{valuation.notes || '-'}</p>
      </section>
    </PartnerPortalLayout>
  );
}