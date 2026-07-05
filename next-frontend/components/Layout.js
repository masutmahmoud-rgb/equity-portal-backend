import Link from 'next/link'

export default function Layout({ children }) {
  return (
    <div style={{padding:20,fontFamily:'Arial, sans-serif'}}>
      <nav style={{marginBottom:20}}>
        <Link href="/dashboard">Dashboard</Link> | <Link href="/investors">Home</Link> | <Link href="/investors">Investors</Link> | <Link href="/statement-of-accounts">Statement of Accounts</Link> | <Link href="/portfolio-valuations">Portfolio Valuations</Link> | <Link href="/announcements">Announcements</Link> | <Link href="/financial-data">Financial Data</Link> | <Link href="/notifications">Notifications</Link>
      </nav>
      <main>{children}</main>
    </div>
  )
}
