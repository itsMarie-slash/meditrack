import { initShell } from '../shell.js';
import { api, ApiError } from '../api.js';
import { DataTable } from '../components/table.js';
import { openModal, closeModal, showFieldErrors } from '../components/modal.js';
import { showToast } from '../components/toast.js';

const session = await initShell('distribution-log', 'Distribution Log');
if (session) init(session);

async function init({ user, content }) {
  const isBhw = user.role === 'bhw';
  const filters = { status: '' };

  content.innerHTML = `
    <div class="toolbar">
      <div class="filters">
        <select id="f-status">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      ${isBhw ? '<button class="btn" id="add-btn">+ Record Distribution</button>' : ''}
    </div>
    <div id="table-container"></div>
  `;

  const tableContainer = content.querySelector('#table-container');
  const table = new DataTable(tableContainer, {
    emptyMessage: 'No distribution records found.',
    columns: [
      { key: 'date_released', label: 'Date', render: (r) => new Date(r.date_released).toLocaleString() },
      { key: 'senior_citizen_name', label: 'Beneficiary' },
      { key: 'medicine_name', label: 'Medicine' },
      { key: 'quantity_issued', label: 'Qty Issued' },
      { key: 'receiver_name', label: 'Receiver' },
      { key: 'bhw_name', label: 'BHW in Charge' },
      { key: 'status', label: 'Status', render: (r) => statusBadge(r.status) },
      ...(isBhw ? [{ key: 'actions', label: 'Actions', render: (r) => `<select data-no-row-click data-status-for="${r.id}"><option value="">Change status…</option><option value="pending">Pending</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select>` }] : []),
    ],
    fetchPage: (page) => api.get('/distributions', { ...filters, page, per_page: 15 }),
  });
  await table.load(1);

  content.querySelector('#f-status').addEventListener('change', (e) => { filters.status = e.target.value; table.load(1); });

  if (isBhw) {
    content.querySelector('#add-btn').addEventListener('click', () => openRecordForm(() => table.load(table.page)));
    tableContainer.addEventListener('change', async (e) => {
      const id = e.target.dataset.statusFor;
      if (!id || !e.target.value) return;
      try {
        await api.put(`/distributions/${id}`, { status: e.target.value });
        showToast('Status updated.', 'success');
        table.load(table.page);
      } catch (err) {
        showToast(err instanceof ApiError ? err.message : 'Failed to update status.', 'error');
      }
    });
  }
}

async function openRecordForm(onSaved) {
  const [schedules, beneficiaries] = await Promise.all([
    api.get('/distribution-schedules', { status: 'upcoming', per_page: 100 }).then((d) => d.items),
    api.get('/senior-citizens', { per_page: 200 }).then((d) => d.items),
  ]);

  const scheduleOptions = schedules.map((s) => `<option value="${s.id}" data-medicine="${s.medicine_id}">${s.medicine_name} — ${s.scheduled_date}</option>`).join('');
  const beneficiaryOptions = beneficiaries.map((b) => `<option value="${b.id}">${b.full_name} (${b.purok_name})</option>`).join('');

  const backdrop = openModal({
    title: 'Record Distribution',
    bodyHtml: `
      <div class="field"><label>Distribution Schedule</label><select name="schedule_id" id="rd-schedule" style="width:100%" required><option value="">Select a schedule…</option>${scheduleOptions}</select></div>
      <div class="field"><label>Beneficiary</label><select name="senior_citizen_id" style="width:100%" required><option value="">Select a beneficiary…</option>${beneficiaryOptions}</select></div>
      <div class="field"><label>Medicine Batch (FEFO order)</label><select name="medicine_batch_id" id="rd-batch" style="width:100%" required><option value="">Select a schedule first…</option></select></div>
      <div class="field"><label>Quantity Issued</label><input type="number" min="1" name="quantity_issued" style="width:100%" required></div>
      <div class="field"><label>Receiver Name</label><input name="receiver_name" style="width:100%" placeholder="Beneficiary or authorized representative" required></div>
      <div class="field"><label>Status</label>
        <select name="status" style="width:100%">
          <option value="completed">Completed</option>
          <option value="pending">Pending</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
    `,
    confirmLabel: 'Record Distribution',
    onConfirm: async (form) => {
      const payload = Object.fromEntries(new FormData(form).entries());
      try {
        await api.post('/distributions', payload);
        showToast('Distribution recorded.', 'success');
        closeModal();
        onSaved();
      } catch (err) {
        if (err instanceof ApiError && err.errors) showFieldErrors(form, err.errors);
        else showToast(err instanceof ApiError ? err.message : 'Failed to record distribution.', 'error');
      }
    },
  });

  const scheduleSelect = backdrop.querySelector('#rd-schedule');
  const batchSelect = backdrop.querySelector('#rd-batch');
  scheduleSelect.addEventListener('change', async () => {
    const medicineId = scheduleSelect.selectedOptions[0]?.dataset.medicine;
    if (!medicineId) {
      batchSelect.innerHTML = '<option value="">Select a schedule first…</option>';
      return;
    }
    const { medicine } = await api.get(`/medicines/${medicineId}`);
    const available = (medicine.batches || []).filter((b) => b.quantity_remaining > 0);
    batchSelect.innerHTML = available.length
      ? available.map((b) => `<option value="${b.id}">${b.batch_number} — ${b.quantity_remaining} left, exp. ${b.expiration_date}</option>`).join('')
      : '<option value="">No available stock for this medicine</option>';
  });
}

function statusBadge(status) {
  const map = { pending: 'orange', completed: 'green', cancelled: 'red' };
  return `<span class="badge ${map[status] || 'gray'}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}
