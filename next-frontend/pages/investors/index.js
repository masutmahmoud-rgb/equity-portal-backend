import Link from 'next/link'
import useSWR from 'swr'
import Layout from '../../components/Layout'

const fetcher = (url) => fetch(url).then(r => r.json())

export default function InvestorsList() {
  const { data, error } = useSWR('/api/investors', fetcher, {
    revalidateOnFocus: false,
    revalidateOnReconnect: false,
  })

  if (error) return <Layout><p>Error loading investors.</p></Layout>
  if (!data) return <Layout><p>Loading...</p></Layout>

  return (
    <Layout>
      <h1>Investors</h1>
      <p><Link href="/investors/create">Create New Investor</Link></p>
      <ul>
        {data.data.map(inv => (
          <li key={inv.id}>
            <Link href={`/investors/${inv.id}`}>{inv.name}</Link>
          </li>
        ))}
      </ul>
    </Layout>
  )
}
