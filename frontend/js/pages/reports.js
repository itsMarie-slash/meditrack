import { initShell } from '../shell.js';
import { api, ApiError } from '../api.js';
import { DataTable } from '../components/table.js';

const session = await initShell('reports', 'Reports');
if (session) init(session);

const API_BASE = window.MEDITRACK_API_BASE || 'http://localhost:8000/api';

async function init({ user, content }) {
  if (user.role === 'ipho') {
    content.innerHTML = `
      <div class="card">
        <p>As IPHO, your report access is limited to demand forecasting.</p>
        <p><a href="forecasting.html">Go to Demand Forecasting →</a></p>
      </div>
    `;
    return;
  }

  const canExport = user.role === 'bhw';
  const filters = { type: 'distribution', date_from: '', date_to: '' };

  content.innerHTML = `
    <div class="toolbar">
      <div class="filters">
        <select id="f-type">
          <option value="distribution">Distribution Report</option>
          <option value="inventory">Inventory Report</option>
        </select>
        <input type="date" id="f-from" title="From date">
        <input type="date" id="f-to" title="To date">
      </div>
      ${canExport ? '<div><button class="btn secondary" id="export-csv">Export CSV</button> <button class="btn" id="export-pdf">Export PDF</button></div>' : ''}
    </div>
    <div id="table-container"></div>
  `;

  const tableContainer = content.querySelector('#table-container');
  let table = buildTable();

  function buildTable() {
    if (filters.type === 'inventory') {
      return new DataTable(tableContainer, {
        emptyMessage: 'No medicines found.',
        columns: [
          { key: 'name', label: 'Name' },
          { key: 'category', label: 'Category' },
          { key: 'stock_quantity', label: 'Stock Qty' },
          { key: 'unit', label: 'Unit' },
          { key: 'is_low_stock', label: 'Low Stock', render: (r) => (Number(r.is_low_stock) ? 'Yes' : 'No') },
          { key: 'nearest_expiration', label: 'Nearest Expiration', render: (r) => r.nearest_expiration ?? '—' },
        ],
        fetchPage: (page) => api.get('/reports/inventory', { page, per_page: 15 }),
      });
    }
    return new DataTable(tableContainer, {
      emptyMessage: 'No distribution records found.',
      columns: [
        { key: 'date_released', label: 'Date', render: (r) => new Date(r.date_released).toLocaleString() },
        { key: 'senior_citizen_name', label: 'Beneficiary' },
        { key: 'medicine_name', label: 'Medicine' },
        { key: 'quantity_issued', label: 'Qty' },
        { key: 'receiver_name', label: 'Receiver' },
        { key: 'bhw_name', label: 'BHW' },
        { key: 'status', label: 'Status' },
      ],
      fetchPage: (page) => api.get('/reports/distribution', { date_from: filters.date_from, date_to: filters.date_to, page, per_page: 15 }),
    });
  }

  await table.load(1);

  content.querySelector('#f-type').addEventListener('change', async (e) => {
    filters.type = e.target.value;
    table = buildTable();
    await table.load(1);
  });
  content.querySelector('#f-from').addEventListener('change', (e) => { filters.date_from = e.target.value; table.load(1); });
  content.querySelector('#f-to').addEventListener('change', (e) => { filters.date_to = e.target.value; table.load(1); });

  if (canExport) {
    content.querySelector('#export-csv').addEventListener('click', () => exportReport(filters, 'csv'));
    content.querySelector('#export-pdf').addEventListener('click', () => exportReport(filters, 'pdf'));
  }
}

function exportReport(filters, format) {
  const params = new URLSearchParams({ format, date_from: filters.date_from || '', date_to: filters.date_to || '' });
  window.open(`${API_BASE}/reports/${filters.type}/export?${params.toString()}`, '_blank');
}
