import { useEffect } from 'react';
import { useRouter } from 'next/router';

export default function DividendPage() {
  const router = useRouter();

  useEffect(() => {
    router.replace('/statement-of-accounts/create?type=Dividend');
  }, [router]);

  return <p style={{ padding: 20 }}>Redirecting to dividend form...</p>;
}
