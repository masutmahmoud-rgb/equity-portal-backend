import { useState } from 'react'
import { useRouter } from 'next/router'
import Layout from '../../components/Layout'
import InvestorForm from '../../components/InvestorForm'

export default function CreateInvestor() {
  const router = useRouter()
  const [error, setError] = useState(null)
  const [success, setSuccess] = useState(null)

  const handleSubmit = async (values) => {
    setError(null)
    const res = await fetch('/api/investors', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(values),
    })

    if (res.ok) {
      const payload = await res.json()
      setSuccess('Investor created successfully.')
      router.push(`/investors/${payload.data.id}`)
    } else {
      const err = await res.json().catch(() => null)
      if (err && err.errors) {
        // show first validation message
        const first = Object.values(err.errors)[0]
        setError(Array.isArray(first) ? first[0] : String(first))
      } else if (err && err.message) {
        setError(err.message)
      } else {
        setError('Validation failed')
      }
    }
  }

  return (
    <Layout>
      <h1>Create Investor</h1>
      {error && <p style={{color:'red'}}>{error}</p>}
      {success && <p style={{color:'green'}}>{success}</p>}
      <InvestorForm onSubmit={handleSubmit} />
    </Layout>
  )
}
