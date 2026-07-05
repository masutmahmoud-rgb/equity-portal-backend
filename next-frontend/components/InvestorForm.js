import { useState, useEffect } from 'react'

export default function InvestorForm({ onSubmit, initialValues }) {
  const [values, setValues] = useState({
    name: '',
    email: '',
    phone: '',
    invested_amount: 0,
    status: 'Active',
    notes: '',
  })

  useEffect(() => {
    if (initialValues) setValues(initialValues)
  }, [initialValues])

  const handleChange = (e) => {
    const { name, value } = e.target
    setValues(v => ({ ...v, [name]: value }))
  }

  const submit = (e) => {
    e.preventDefault()
    // basic client-side validation
    if (!values.name) return alert('Name is required')
    if (!values.email) return alert('Email is required')
    if (isNaN(Number(values.invested_amount))) return alert('Invested amount must be numeric')
    onSubmit(values)
  }

  return (
    <form onSubmit={submit}>
      <div>
        <label>Name</label><br/>
        <input name="name" value={values.name} onChange={handleChange} />
      </div>
      <div>
        <label>Email</label><br/>
        <input name="email" value={values.email} onChange={handleChange} />
      </div>
      <div>
        <label>Phone</label><br/>
        <input name="phone" value={values.phone} onChange={handleChange} />
      </div>
      <div>
        <label>Status</label><br/>
        <select name="status" value={values.status} onChange={handleChange}>
          <option>Active</option>
          <option>Inactive</option>
          <option>Pending</option>
        </select>
      </div>
      <div>
        <label>Notes</label><br/>
        <textarea name="notes" value={values.notes} onChange={handleChange} />
      </div>
      <div style={{marginTop:10}}>
        <button type="submit">Save</button>
      </div>
    </form>
  )
}
