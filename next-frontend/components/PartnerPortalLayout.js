import Link from 'next/link';

export default function PartnerPortalLayout({ investorId, title, children }) {
  return (
    <div style={{ padding: 20, fontFamily: 'Arial, sans-serif' }}>
      <nav style={{ marginBottom: 20 }}>
        <Link href={`/partner-portal/${investorId}`}>Dashboard</Link> | <Link href={`/partner-portal/${investorId}/valuation-history`}>Valuation History</Link> | <Link href={`/partner-portal/${investorId}/notifications`}>Notifications</Link>
      </nav>
      <h1 style={{ marginBottom: 20 }}>{title}</h1>
      <main>{children}</main>
    </div>
  );
}