import Link from 'next/link'
import { useRouter } from 'next/router'
import useSWR from 'swr'
import Layout from '../../../components/Layout'

const fetcher = (url) => fetch(url).then(r => r.json())

export default function ViewInvestor() {
  const router = useRouter()
  const { id } = router.query
  const { data, error } = useSWR(id ? `/api/investors/${id}` : null, fetcher, {
    revalidateOnFocus: false,
    revalidateOnReconnect: false,
  })

  if (error) return <Layout><p>Error loading investor.</p></Layout>
  if (!data) return <Layout><p>Loading...</p></Layout>

  const inv = data.data

  return (
    <Layout>
      <h1>{inv.name}</h1>
      <p><strong>Email:</strong> {inv.email}</p>
      <p><strong>Phone:</strong> {inv.phone}</p>
      <p><strong>Invested Amount:</strong> {inv.invested_amount}</p>
      <p><strong>Status:</strong> {inv.status}</p>
      <p><strong>Notes:</strong> {inv.notes}</p>
      <p>
        <Link href={`/investors/${inv.id}/edit`}>Edit</Link> |
        <a href="#" onClick={async (e) => {
          e.preventDefault()
          if (!confirm('Delete this investor?')) return
          const res = await fetch(`/api/investors/${inv.id}`, { method: 'DELETE' })
          if (res.ok) router.push('/investors')
          else {
            const payload = await res.json().catch(() => ({}))
            alert(payload.message || 'Delete failed')
          }
        }}> Delete</a>
      </p>
      <p><Link href="/investors">Back to list</Link></p>
    </Layout>
  )
}
