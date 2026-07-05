import { useState, useEffect } from 'react';
import useSWR from 'swr';
import Layout from '../components/Layout';

const fetcher = (url) => fetch(url).then((res) => res.json());

const StatCard = ({ title, value, color = '#007bff' }) => (
  <div style={{
    flex: '1 1 calc(25% - 15px)',
    minWidth: '200px',
    padding: '20px',
    backgroundColor: '#f8f9fa',
    borderRadius: '8px',
    border: `3px solid ${color}`,
    marginBottom: '15px',
  }}>
    <div style={{ fontSize: '0.85em', color: '#666', marginBottom: '8px' }}>{title}</div>
    <div style={{ fontSize: '2em', fontWeight: 'bold', color }}>{value}</div>
  </div>
);

export default function Dashboard() {
  const { data, error, isLoading } = useSWR('/api/dashboard', fetcher, {
    refreshInterval: 5000, // Refresh every 5 seconds for live updates
  });

  if (isLoading) return <Layout><div className="container"><p>Loading dashboard...</p></div></Layout>;
  if (error) return <Layout><div className="container"><p style={{ color: 'red' }}>Error loading dashboard</p></div></Layout>;
  if (!data) return null;

  const stats = data.statistics;
  const investments = data.investment_distribution;
  const monthlyFlow = data.monthly_cash_flow;
  const recentActivity = data.recent_activity;
  const pendingActions = data.pending_actions;

  const formatCurrency = (num) => {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(num);
  };

  return (
    <Layout>
      <div className="container">
        <h1>Admin Dashboard</h1>

        {/* Live Statistics */}
        <section style={{ marginBottom: '40px' }}>
          <h2>Live Statistics</h2>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: '15px' }}>
            <StatCard title="Total Companies" value={stats.total_companies} color="#28a745" />
            <StatCard title="Total Partners" value={stats.total_partners} color="#17a2b8" />
            <StatCard title="Active Investments" value={stats.active_investments} color="#ffc107" />
            <StatCard title="Total Invested Capital" value={formatCurrency(stats.total_invested_capital)} color="#007bff" />
          </div>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: '15px' }}>
            <StatCard title="Dividends Paid" value={formatCurrency(stats.dividends_paid)} color="#6f42c1" />
            <StatCard title="Pending Dividends" value={formatCurrency(stats.pending_dividends)} color="#e83e8c" />
            <StatCard title="Total Withdrawals" value={formatCurrency(stats.withdrawals_total)} color="#fd7e14" />
            <StatCard title="Pending Withdrawals" value={formatCurrency(stats.pending_withdrawals)} color="#dc3545" />
          </div>
        </section>

        {/* Investment Distribution */}
        <section style={{ marginBottom: '40px' }}>
          <h2>Investment Distribution by Company</h2>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ backgroundColor: '#f8f9fa', borderBottom: '2px solid #dee2e6' }}>
                <th style={{ padding: '12px', textAlign: 'left' }}>Company</th>
                <th style={{ padding: '12px', textAlign: 'right' }}>Count</th>
                <th style={{ padding: '12px', textAlign: 'right' }}>Total Amount</th>
              </tr>
            </thead>
            <tbody>
              {investments.map((inv, idx) => (
                <tr key={idx} style={{ borderBottom: '1px solid #dee2e6' }}>
                  <td style={{ padding: '12px' }}>{inv.company}</td>
                  <td style={{ padding: '12px', textAlign: 'right' }}>{inv.count}</td>
                  <td style={{ padding: '12px', textAlign: 'right', fontWeight: 'bold' }}>{formatCurrency(inv.total_amount)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>

        {/* Monthly Cash Flow */}
        <section style={{ marginBottom: '40px' }}>
          <h2>Monthly Cash Flow (Last 12 Months)</h2>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ backgroundColor: '#f8f9fa', borderBottom: '2px solid #dee2e6' }}>
                <th style={{ padding: '12px', textAlign: 'left' }}>Month</th>
                <th style={{ padding: '12px', textAlign: 'right' }}>Dividends Paid</th>
                <th style={{ padding: '12px', textAlign: 'right' }}>Withdrawals Paid</th>
                <th style={{ padding: '12px', textAlign: 'right' }}>Total</th>
              </tr>
            </thead>
            <tbody>
              {monthlyFlow.map((month, idx) => (
                <tr key={idx} style={{ borderBottom: '1px solid #dee2e6' }}>
                  <td style={{ padding: '12px' }}>{month.month}</td>
                  <td style={{ padding: '12px', textAlign: 'right', color: '#6f42c1' }}>{formatCurrency(month.dividends)}</td>
                  <td style={{ padding: '12px', textAlign: 'right', color: '#fd7e14' }}>{formatCurrency(month.withdrawals)}</td>
                  <td style={{ padding: '12px', textAlign: 'right', fontWeight: 'bold' }}>
                    {formatCurrency(month.dividends + month.withdrawals)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>

        {/* Recent Activity */}
        <section style={{ marginBottom: '40px' }}>
          <h2>Recent Activity</h2>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ backgroundColor: '#f8f9fa', borderBottom: '2px solid #dee2e6' }}>
                <th style={{ padding: '12px', textAlign: 'left' }}>Date</th>
                <th style={{ padding: '12px', textAlign: 'left' }}>Type</th>
                <th style={{ padding: '12px', textAlign: 'left' }}>Company</th>
                <th style={{ padding: '12px', textAlign: 'left' }}>Partner</th>
                <th style={{ padding: '12px', textAlign: 'right' }}>Amount</th>
                <th style={{ padding: '12px', textAlign: 'left' }}>Status</th>
              </tr>
            </thead>
            <tbody>
              {recentActivity.map((activity, idx) => (
                <tr key={idx} style={{ borderBottom: '1px solid #dee2e6' }}>
                  <td style={{ padding: '12px' }}>{activity.date}</td>
                  <td style={{ padding: '12px' }}>
                    <span style={{
                      backgroundColor: activity.type === 'Dividend' ? '#d4edda' : '#d1ecf1',
                      color: activity.type === 'Dividend' ? '#155724' : '#0c5460',
                      padding: '4px 8px',
                      borderRadius: '4px',
                      fontSize: '0.9em',
                    }}>
                      {activity.type}
                    </span>
                  </td>
                  <td style={{ padding: '12px' }}>{activity.company}</td>
                  <td style={{ padding: '12px' }}>{activity.partner}</td>
                  <td style={{ padding: '12px', textAlign: 'right', fontWeight: 'bold' }}>{formatCurrency(activity.amount)}</td>
                  <td style={{ padding: '12px' }}>
                    <span style={{
                      backgroundColor: activity.status === 'Pending' ? '#fff3cd' : '#d4edda',
                      color: activity.status === 'Pending' ? '#856404' : '#155724',
                      padding: '4px 8px',
                      borderRadius: '4px',
                      fontSize: '0.9em',
                    }}>
                      {activity.status}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>

        {/* Pending Actions */}
        <section>
          <h2>Pending Actions</h2>
          <table style={{ width: '100%', borderCollapse: 'collapse' }}>
            <thead>
              <tr style={{ backgroundColor: '#f8f9fa', borderBottom: '2px solid #dee2e6' }}>
                <th style={{ padding: '12px', textAlign: 'left' }}>Action</th>
                <th style={{ padding: '12px', textAlign: 'left' }}>Company</th>
                <th style={{ padding: '12px', textAlign: 'left' }}>Partner</th>
                <th style={{ padding: '12px', textAlign: 'right' }}>Amount</th>
                <th style={{ padding: '12px', textAlign: 'left' }}>Date</th>
                <th style={{ padding: '12px', textAlign: 'left' }}>Details</th>
              </tr>
            </thead>
            <tbody>
              {pendingActions.map((action, idx) => (
                <tr key={idx} style={{ borderBottom: '1px solid #dee2e6', backgroundColor: action.type === 'Dividend Payment' ? '#fff8f0' : '#f0f8ff' }}>
                  <td style={{ padding: '12px' }}>
                    <strong style={{ color: action.type === 'Dividend Payment' ? '#d9534f' : '#0275d8' }}>
                      {action.type}
                    </strong>
                  </td>
                  <td style={{ padding: '12px' }}>{action.company}</td>
                  <td style={{ padding: '12px' }}>{action.partner}</td>
                  <td style={{ padding: '12px', textAlign: 'right', fontWeight: 'bold' }}>{formatCurrency(action.amount)}</td>
                  <td style={{ padding: '12px' }}>{action.date}</td>
                  <td style={{ padding: '12px', fontSize: '0.9em', color: '#666' }}>
                    {action.bank && <div>Bank: {action.bank}</div>}
                    {action.reference && <div>Ref: {action.reference}</div>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {pendingActions.length === 0 && (
            <p style={{ padding: '20px', textAlign: 'center', color: '#28a745', fontWeight: 'bold' }}>
              ✓ All actions completed!
            </p>
          )}
        </section>
      </div>

      <style jsx>{`
        .container {
          max-width: 1400px;
          margin: 0 auto;
          padding: 20px;
        }
        h1 {
          color: #333;
          margin-bottom: 30px;
          border-bottom: 3px solid #007bff;
          padding-bottom: 10px;
        }
        h2 {
          color: #444;
          margin-top: 30px;
          margin-bottom: 20px;
          font-size: 1.3em;
        }
        table {
          background: white;
          border-radius: 8px;
          overflow: hidden;
          box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        th {
          font-weight: 600;
          color: #333;
        }
        tr:hover {
          background-color: #f5f5f5;
        }
      `}</style>
    </Layout>
  );
}
