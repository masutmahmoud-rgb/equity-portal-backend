import Link from 'next/link';
import { useRouter } from 'next/router';
import useSWR from 'swr';
import PartnerPortalLayout from '../../../components/PartnerPortalLayout';

const fetcher = (url) => fetch(url).then((res) => res.json());

export default function PartnerNotificationsPage() {
  const router = useRouter();
  const { investor_id } = router.query;

  const { data, error } = useSWR(
    investor_id ? `/api/partner-portal/${investor_id}/notifications` : null,
    fetcher,
  );

  if (error) return <PartnerPortalLayout investorId={investor_id || ''} title="Notification Center"><p style={{ color: 'red' }}>Error loading notifications.</p></PartnerPortalLayout>;
  if (!data) return <PartnerPortalLayout investorId={investor_id || ''} title="Notification Center"><p>Loading...</p></PartnerPortalLayout>;

  const records = data.data || [];

  return (
    <PartnerPortalLayout investorId={investor_id || ''} title="Notification Center">
      {records.length === 0 ? (
        <p>No notifications available.</p>
      ) : (
        <div style={{ display: 'grid', gap: 12 }}>
          {records.map((record) => (
            <div key={record.id} style={{ padding: 16, border: '1px solid #ddd', borderRadius: 8, background: '#fff' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, marginBottom: 8 }}>
                <strong>{record.title}</strong>
                <span>{record.publish_date}</span>
              </div>
              <p style={{ marginTop: 0 }}>{record.message}</p>
              {record.valuation_id ? (
                <Link href={record.link || `/partner-portal/${investor_id}/valuations/${record.valuation_id}`}>Open valuation</Link>
              ) : (
                <span style={{ color: '#666' }}>General notification</span>
              )}
            </div>
          ))}
        </div>
      )}
    </PartnerPortalLayout>
  );
}