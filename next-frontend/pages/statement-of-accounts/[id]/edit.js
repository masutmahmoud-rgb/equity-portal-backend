import { useEffect, useState } from 'react';
import { useRouter } from 'next/router';
import useSWR from 'swr';
import Layout from '../../../components/Layout';
import StatementOfAccountForm from '../../../components/StatementOfAccountForm';

const fetcher = (url) => fetch(url).then((res) => res.json());

export default function EditStatementOfAccount() {
  const router = useRouter();
  const { id } = router.query;
  
  const { data: recordData } = useSWR(id ? `/api/statement-of-accounts/${id}` : null, fetcher);
  const { data: companiesData } = useSWR('/api/companies', fetcher);
  const { data: investorsData } = useSWR('/api/investors', fetcher);
  const { data: investmentsData } = useSWR('/api/investments', fetcher);

  const isLoading = !recordData || !companiesData || !investorsData || !investmentsData;

  if (!id) return <Layout><div className="container"><p>Loading...</p></div></Layout>;
  if (isLoading) return <Layout><div className="container"><p>Loading...</p></div></Layout>;

  return (
    <Layout>
      <div className="container">
        <h1>Edit Statement of Account #{id}</h1>
        <StatementOfAccountForm
          companies={companiesData.data}
          investors={investorsData.data}
          investments={investmentsData.data}
          existingRecord={recordData.data}
        />
      </div>
    </Layout>
  );
}
