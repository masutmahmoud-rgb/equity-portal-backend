const segmentMap = {
  dashboard: 'dashboard',
  companies: 'companies',
  investors: 'investors',
  dividend: 'dividend',
  dividends: 'dividends',
  'statement-of-accounts': 'statement-of-accounts',
  statement: 'statement-of-accounts',
  'portfolio-valuations': 'portfolio-valuations',
  valuations: 'portfolio-valuations',
  announcements: 'announcements',
  'financial-data': 'financial-data',
  financial: 'financial-data',
  notifications: 'notifications',
};

export async function getServerSideProps(context) {
  const slug = Array.isArray(context.params?.slug) ? context.params.slug : [];
  const [head = 'dashboard', ...rest] = slug;

  const targetHead = segmentMap[head] || 'dashboard';
  const suffix = rest.length > 0 ? `/${rest.join('/')}` : '';
  const query = context.resolvedUrl.includes('?')
    ? context.resolvedUrl.slice(context.resolvedUrl.indexOf('?'))
    : '';

  return {
    redirect: {
      destination: `/${targetHead}${suffix}${query}`,
      permanent: true,
    },
  };
}

export default function AdminRouteFallback() {
  return null;
}
