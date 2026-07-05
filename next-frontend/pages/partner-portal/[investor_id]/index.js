import { useRouter } from 'next/router';
import useSWR from 'swr';
import PartnerPortalLayout from '../../../components/PartnerPortalLayout';

const fetcher = (url) => fetch(url).then((res) => res.json());

function ValueCard({ label, value }) {
  return (
    <div style={{ flex: '1 1 260px', padding: 16, border: '1px solid #ddd', borderRadius: 8, background: '#fff' }}>
      <div style={{ fontSize: 12, color: '#666', marginBottom: 6 }}>{label}</div>
      <div style={{ fontSize: 22, fontWeight: 700 }}>{value ?? '-'}</div>
    </div>
  );
}

export default function PartnerPortalDashboard() {
  const router = useRouter();
  const { investor_id } = router.query;

  const { data, error } = useSWR(
    investor_id ? `/api/partner-portal/${investor_id}/portfolio-summary` : null,
    fetcher,
  );

  if (error) {
    return <PartnerPortalLayout investorId={investor_id || ''} title="Partner Dashboard"><p style={{ color: 'red' }}>Error loading partner dashboard.</p></PartnerPortalLayout>;
  }

  if (!data) {
    return <PartnerPortalLayout investorId={investor_id || ''} title="Partner Dashboard"><p>Loading...</p></PartnerPortalLayout>;
  }

  const payload = data.data;
  const summary = payload?.summary_cards;
  const cards = payload?.investment_cards || [];
  const reportingCurrency = payload?.reporting_currency || 'USD';

  const formatCurrency = (amount, currency) => {
    if (amount === null || amount === undefined) return '-';
    return `${Number(amount).toLocaleString()} ${currency}`;
  };

  return (
    <PartnerPortalLayout investorId={investor_id || ''} title={`Partner Dashboard - ${payload?.investor?.name || ''}`}>
      {!summary ? (
        <p>Loading dashboard summary...</p>
      ) : (
        <>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12, marginBottom: 20 }}>
            <ValueCard label="Partner Account Balance" value={formatCurrency(summary.partner_account_balance, reportingCurrency)} />
            <ValueCard label="Total Investments" value={formatCurrency(summary.total_investments, reportingCurrency)} />
            <ValueCard label="Last Declared Profit" value={formatCurrency(summary.last_declared_profit, reportingCurrency)} />
            <ValueCard label="Portfolio Value" value={formatCurrency(summary.portfolio_value, reportingCurrency)} />
          </div>

          <section>
            <h2 style={{ marginBottom: 12 }}>Investments</h2>
            {cards.length === 0 ? (
              <p>No investment records found.</p>
            ) : (
              <div style={{ display: 'grid', gap: 12 }}>
                {cards.map((card, index) => (
                  <div key={`${card.company?.id || 'company'}-${index}`} style={{ padding: 16, border: '1px solid #ddd', borderRadius: 8, background: '#fff' }}>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12 }}>
                      <ValueCard label="Company" value={card.company?.name || '-'} />
                      <ValueCard label="Currency" value={card.currency || '-'} />
                      <ValueCard label="Total Invested" value={formatCurrency(card.total_invested, card.currency || reportingCurrency)} />
                      <ValueCard label="Indicative Value" value={formatCurrency(card.indicative_value, card.currency || reportingCurrency)} />
                      <ValueCard label="Profit" value={formatCurrency(card.profit, card.currency || reportingCurrency)} />
                      <ValueCard label="ROI (Semi-Annual)" value={card.roi_percentage === null ? '-' : `${card.roi_percentage}%`} />
                      <ValueCard label="Valuation Period" value={card.valuation_period || '-'} />
                    </div>
                  </div>
                ))}
              </div>
            )}
          </section>
        </>
      )}
    </PartnerPortalLayout>
  );
}