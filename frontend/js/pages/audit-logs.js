import { initShell } from '../shell.js';
import { api } from '../api.js';
import { DataTable } from '../components/table.js';

const session = await initShell('audit-logs', 'Audit Logs');
if (session) init(session);

async function init({ content }) {
  const filters = { action: '', date_from: '', date_to: '' };

  content.innerHTML = `
    <div class="toolbar">
      <div class="filters">
        <select id="f-action">
          <option value="">All Actions</option>
          <option value="login_success">Login Success</option>
          <option value="login_failed">Login Failed</option>
          <option value="create">Create</option>
          <option value="update">Update</option>
          <option value="delete">Delete</option>
          <option value="stock_update">Stock Update</option>
        </select>
        <input type="date" id="f-from" title="From date">
        <input type="date" id="f-to" title="To date">
      </div>
    </div>
    <div id="table-container"></div>
  `;

  const table = new DataTable(content.querySelector('#table-container'), {
    emptyMessage: 'No audit log entries found.',
    columns: [
      { key: 'created_at', label: 'Timestamp', render: (r) => new Date(r.created_at).toLocaleString() },
      { key: 'user_name', label: 'User', render: (r) => r.user_name ?? '—' },
      { key: 'action', label: 'Action' },
      { key: 'entity_type', label: 'Entity' },
      { key: 'details', label: 'Details', render: (r) => `<code style="font-size:12px;">${escapeHtml(r.details ?? '')}</code>` },
    ],
    fetchPage: (page) => api.get('/audit-logs', { ...filters, page, per_page: 20 }),
  });
  await table.load(1);

  content.querySelector('#f-action').addEventListener('change', (e) => { filters.action = e.target.value; table.load(1); });
  content.querySelector('#f-from').addEventListener('change', (e) => { filters.date_from = e.target.value; table.load(1); });
  content.querySelector('#f-to').addEventListener('change', (e) => { filters.date_to = e.target.value; table.load(1); });
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}
