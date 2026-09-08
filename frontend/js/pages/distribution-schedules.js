import { initShell } from '../shell.js';
import { api, ApiError } from '../api.js';
import { DataTable } from '../components/table.js';
import { openModal, closeModal, showFieldErrors } from '../components/modal.js';
import { showToast } from '../components/toast.js';

const session = await initShell('distribution-schedules', 'Distribution Schedules');
if (session) init(session);

async function init({ user, content }) {
  const isBhw = user.role === 'bhw';
  const filters = { status: '' };

  content.innerHTML = `
    <div class="toolbar">
      <div class="filters">
        <select id="f-status">
          <option value="">All Statuses</option>
          <option value="upcoming">Upcoming</option>
          <option value="ongoing">Ongoing</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
    </div>
    <div id="table-container"></div>
  `;

  const tableContainer = content.querySelector('#table-container');
  const table = new DataTable(tableContainer, {
    emptyMessage: 'No distribution schedules found.',
    columns: [
      { key: 'medicine_name', label: 'Medicine' },
      { key: 'scheduled_date', label: 'Date' },
      { key: 'time_slot', label: 'Time Slot' },
      { key: 'venue', label: 'Venue' },
      { key: 'status', label: 'Status', render: (r) => statusBadge(r.status) },
      { key: 'auto_generated', label: 'Source', render: (r) => (Number(r.auto_generated) ? '<span class="badge gray">Auto-generated</span>' : 'Manual') },
      ...(isBhw ? [{ key: 'actions', label: 'Actions', render: (r) => `<button class="btn secondary" data-no-row-click data-edit="${r.id}">Edit</button>` }] : []),
    ],
    fetchPage: (page) => api.get('/distribution-schedules', { ...filters, page, per_page: 15 }),
  });
  await table.load(1);

  content.querySelector('#f-status').addEventListener('change', (e) => { filters.status = e.target.value; table.load(1); });

  if (isBhw) {
    tableContainer.addEventListener('click', (e) => {
      const editId = e.target.dataset.edit;
      if (!editId) return;
      const record = table.lastItems.find((r) => String(r.id) === editId);
      if (record) openEditForm(record, () => table.load(table.page));
    });
  }
}

function openEditForm(record, onSaved) {
  openModal({
    title: `Edit Schedule — ${record.medicine_name}`,
    bodyHtml: `
      <div class="field"><label>Scheduled Date</label><input type="date" name="scheduled_date" style="width:100%" value="${record.scheduled_date}" required></div>
      <div class="field"><label>Time Slot</label><input name="time_slot" style="width:100%" value="${record.time_slot}" required></div>
      <div class="field"><label>Venue</label><input name="venue" style="width:100%" value="${record.venue}" required></div>
      <div class="field"><label>Status</label>
        <select name="status" style="width:100%">
          <option value="upcoming" ${record.status === 'upcoming' ? 'selected' : ''}>Upcoming</option>
          <option value="ongoing" ${record.status === 'ongoing' ? 'selected' : ''}>Ongoing</option>
          <option value="completed" ${record.status === 'completed' ? 'selected' : ''}>Completed</option>
          <option value="cancelled" ${record.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
        </select>
      </div>
    `,
    confirmLabel: 'Save Changes',
    onConfirm: async (form) => {
      const payload = Object.fromEntries(new FormData(form).entries());
      try {
        await api.put(`/distribution-schedules/${record.id}`, payload);
        showToast('Schedule updated.', 'success');
        closeModal();
        onSaved();
      } catch (err) {
        if (err instanceof ApiError && err.errors) showFieldErrors(form, err.errors);
        else showToast(err instanceof ApiError ? err.message : 'Failed to update schedule.', 'error');
      }
    },
  });
}

function statusBadge(status) {
  const map = { upcoming: 'orange', ongoing: 'gray', completed: 'green', cancelled: 'red' };
  return `<span class="badge ${map[status] || 'gray'}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
}
