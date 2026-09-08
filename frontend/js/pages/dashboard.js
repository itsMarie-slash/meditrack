import { initShell } from '../shell.js';
import { api, ApiError } from '../api.js';

const session = await initShell('dashboard', 'Dashboard');
if (session) {
  await render(session);
}

async function render({ user, content }) {
  content.innerHTML = '<div class="loading-state">Loading dashboard…</div>';
  try {
    const data = await api.get('/dashboard');
    content.innerHTML = buildHtml(user.role, data);
  } catch (err) {
    const message = err instanceof ApiError ? err.message : 'Failed to load dashboard.';
    content.innerHTML = `<div class="error-state">${message}</div>`;
  }
}

function buildHtml(role, data) {
  if (role === 'ipho') {
    const rows = data.forecast_summary
      .map((f) => `<tr><td>${f.medicine_name}</td><td>${f.forecast_month}</td><td>${f.predicted_quantity}</td></tr>`)
      .join('');
    return `
      <h2>Demand Forecast Summary</h2>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Medicine</th><th>Forecast Month</th><th>Predicted Quantity</th></tr></thead>
          <tbody>${rows || '<tr><td colspan="3">No forecast data yet.</td></tr>'}</tbody>
        </table>
      </div>
      <p><a href="forecasting.html">View full demand forecasting →</a></p>
    `;
  }

  const cards = [
    { label: 'Active Beneficiaries', value: data.active_beneficiaries },
    { label: 'Low-Stock Medicines', value: data.low_stock_medicines },
    { label: 'Upcoming Distributions (7 days)', value: data.upcoming_distributions_this_week },
  ];
  if (role === 'bhw') {
    cards.push({ label: 'Failed SMS Notifications', value: data.pending_sms_failures });
  }

  const statCardsHtml = cards.map((c) => `<div class="stat-card"><div class="value">${c.value}</div><div class="label">${c.label}</div></div>`).join('');

  let activityHtml = '';
  if (role === 'bhw') {
    const rows = (data.recent_activity || [])
      .map((a) => `<tr><td>${new Date(a.created_at).toLocaleString()}</td><td>${a.user_name ?? '—'}</td><td>${a.action}</td><td>${a.entity_type}</td></tr>`)
      .join('');
    activityHtml = `
      <h2>Recent Activity</h2>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th></tr></thead>
          <tbody>${rows || '<tr><td colspan="4">No recent activity.</td></tr>'}</tbody>
        </table>
      </div>
    `;
  }

  return `<div class="stat-grid">${statCardsHtml}</div>${activityHtml}`;
}
