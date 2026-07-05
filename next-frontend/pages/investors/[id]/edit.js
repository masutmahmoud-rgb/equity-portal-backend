import { useState, useEffect } from 'react'
import { useRouter } from 'next/router'
import useSWR from 'swr'
import Layout from '../../../components/Layout'
import InvestorForm from '../../../components/InvestorForm'

const fetcher = (url) => fetch(url).then(r => r.json())

export default function EditInvestor() {
  const router = useRouter()
  const { id } = router.query
  const { data, error } = useSWR(id ? `/api/investors/${id}` : null, fetcher, {
    revalidateOnFocus: false,
    revalidateOnReconnect: false,
  })
  const [errorMsg, setErrorMsg] = useState(null)

  if (error) return <Layout><p>Error loading investor.</p></Layout>
  if (!data) return <Layout><p>Loading...</p></Layout>

  const inv = data.data

  const handleSubmit = async (values) => {
    setErrorMsg(null)
    const res = await fetch(`/api/investors/${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(values),
    })

    if (res.ok) {
      router.push(`/investors/${id}`)
    } else {
      const err = await res.json().catch(() => null)
      if (err && err.errors) {
        const first = Object.values(err.errors)[0]
        setErrorMsg(Array.isArray(first) ? first[0] : String(first))
      } else if (err && err.message) {
        setErrorMsg(err.message)
      } else {
        setErrorMsg('Validation failed')
      }
    }
  }

  return (
    <Layout>
      <h1>Edit Investor</h1>
      {errorMsg && <p style={{color:'red'}}>{errorMsg}</p>}
      <InvestorForm onSubmit={handleSubmit} initialValues={inv} />
    </Layout>
  )
}
