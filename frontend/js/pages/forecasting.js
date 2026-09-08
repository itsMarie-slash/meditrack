import { initShell } from '../shell.js';
import { api, ApiError } from '../api.js';

const session = await initShell('forecasting', 'Demand Forecasting');
if (session) init(session);

let chart = null;

async function init({ user, content }) {
  const canExport = user.role !== 'midwife';

  content.innerHTML = `
    <div class="toolbar">
      <div class="filters">
        <label style="margin:0;">Historical window:
          <select id="months-back">
            <option value="1">Past 1 month</option>
            <option value="2" selected>Past 2 months</option>
            <option value="3">Past 3 months</option>
            <option value="6">Past 6 months</option>
          </select>
        </label>
      </div>
      ${canExport ? '<button class="btn" id="export-btn">Export PDF</button>' : ''}
    </div>
    <div class="card" style="margin-bottom:20px;"><canvas id="forecast-chart" height="90"></canvas></div>
    <div id="table-container"></div>
  `;

  const monthsSelect = content.querySelector('#months-back');
  monthsSelect.addEventListener('change', () => load(content, monthsSelect.value));

  if (canExport) {
    content.querySelector('#export-btn').addEventListener('click', () => {
      window.open(`${window.MEDITRACK_API_BASE || 'http://localhost:8000/api'}/forecasts/export?months_back=${monthsSelect.value}`, '_blank');
    });
  }

  await load(content, monthsSelect.value);
}

async function load(content, monthsBack) {
  const tableContainer = content.querySelector('#table-container');
  tableContainer.innerHTML = '<div class="loading-state">Loading forecasts…</div>';
  try {
    const { forecasts } = await api.get('/forecasts', { months_back: monthsBack });
    renderChart(forecasts);
    renderTable(tableContainer, forecasts, monthsBack);
  } catch (err) {
    tableContainer.innerHTML = `<div class="error-state">${err instanceof ApiError ? err.message : 'Failed to load forecasts.'}</div>`;
  }
}

function renderChart(forecasts) {
  const ctx = document.getElementById('forecast-chart');
  const data = {
    labels: forecasts.map((f) => f.medicine_name),
    datasets: [{
      label: 'Predicted Quantity (next month)',
      data: forecasts.map((f) => f.predicted_quantity),
      backgroundColor: '#0f766e',
    }],
  };
  if (chart) chart.destroy();
  chart = new Chart(ctx, { type: 'bar', data, options: { responsive: true, plugins: { legend: { display: false } } } });
}

function renderTable(container, forecasts, monthsBack) {
  if (forecasts.length === 0) {
    container.innerHTML = '<div class="empty-state">No forecast data available yet.</div>';
    return;
  }
  const rows = forecasts
    .map((f) => `<tr><td>${f.medicine_name}</td><td>${f.forecast_month}</td><td>${f.predicted_quantity}</td><td>Based on the past ${monthsBack} completed month(s)</td></tr>`)
    .join('');
  container.innerHTML = `
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Medicine</th><th>Forecast Month</th><th>Predicted Quantity</th><th>Reliability Note</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
    </div>
  `;
}
