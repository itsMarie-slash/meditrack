import { initShell } from '../shell.js';
import { api, ApiError } from '../api.js';
import { DataTable } from '../components/table.js';
import { openModal, closeModal, confirmDialog, showFieldErrors } from '../components/modal.js';
import { showToast } from '../components/toast.js';

const session = await initShell('senior-citizens', 'Senior Citizens');
if (session) init(session);

async function init({ user, content }) {
  const isBhw = user.role === 'bhw';
  let puroks = [];
  let medicines = [];
  try {
    [puroks, medicines] = await Promise.all([
      api.get('/puroks').then((d) => d.items),
      api.get('/medicines', { per_page: 100 }).then((d) => d.items),
    ]);
  } catch {
    // dropdowns degrade gracefully to empty lists if lookups fail
  }

  const filters = { search: '', purok_id: '', medical_condition: '', distribution_status: '' };

  content.innerHTML = `
    <div class="toolbar">
      <div class="filters">
        <input type="search" id="f-search" placeholder="Search name or mobile…">
        <select id="f-purok"><option value="">All Puroks</option>${puroks.map((p) => `<option value="${p.id}">${p.name}</option>`).join('')}</select>
        <select id="f-condition">
          <option value="">All Conditions</option>
          <option value="hypertension">Hypertension</option>
          <option value="diabetes">Diabetes</option>
          <option value="both">Both</option>
        </select>
        <select id="f-status">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="pending">Pending</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      ${isBhw ? '<button class="btn" id="add-btn">+ Add Beneficiary</button>' : ''}
    </div>
    <div id="table-container"></div>
  `;

  const tableContainer = content.querySelector('#table-container');
  const table = new DataTable(tableContainer, {
    emptyMessage: 'No senior citizen records found.',
    columns: [
      { key: 'full_name', label: 'Name' },
      { key: 'purok_name', label: 'Purok' },
      { key: 'medical_condition', label: 'Condition', render: (r) => capitalize(r.medical_condition) },
      { key: 'assigned_medicine_name', label: 'Assigned Medicine', render: (r) => r.assigned_medicine_name ?? '—' },
      { key: 'distribution_status', label: 'Status', render: (r) => statusBadge(r.distribution_status) },
      ...(isBhw ? [{ key: 'actions', label: 'Actions', render: (r) => `<button class="btn secondary" data-no-row-click data-edit="${r.id}">Edit</button> <button class="btn danger" data-no-row-click data-delete="${r.id}">Delete</button>` }] : []),
    ],
    fetchPage: (page) => api.get('/senior-citizens', { ...filters, page, per_page: 15 }),
    onRowClick: (row) => showDetail(row),
  });
  await table.load(1);

  content.querySelector('#f-search').addEventListener('input', debounce((e) => { filters.search = e.target.value; table.load(1); }, 350));
  content.querySelector('#f-purok').addEventListener('change', (e) => { filters.purok_id = e.target.value; table.load(1); });
  content.querySelector('#f-condition').addEventListener('change', (e) => { filters.medical_condition = e.target.value; table.load(1); });
  content.querySelector('#f-status').addEventListener('change', (e) => { filters.distribution_status = e.target.value; table.load(1); });

  if (isBhw) {
    content.querySelector('#add-btn').addEventListener('click', () => openForm({ puroks, medicines, onSaved: () => table.load(table.page) }));
    tableContainer.addEventListener('click', async (e) => {
      const editId = e.target.dataset.edit;
      const deleteId = e.target.dataset.delete;
      if (editId) {
        const { senior_citizen: record } = await api.get(`/senior-citizens/${editId}`);
        openForm({ puroks, medicines, record, onSaved: () => table.load(table.page) });
      }
      if (deleteId) {
        const confirmed = await confirmDialog('This will deactivate the beneficiary record. Distribution history is preserved. Continue?');
        if (!confirmed) return;
        try {
          await api.del(`/senior-citizens/${deleteId}`);
          showToast('Beneficiary deactivated.', 'success');
          table.load(table.page);
        } catch (err) {
          showToast(err instanceof ApiError ? err.message : 'Failed to deactivate record.', 'error');
        }
      }
    });
  }
}

function showDetail(row) {
  openModal({
    title: row.full_name,
    bodyHtml: `
      <p><strong>Purok:</strong> ${row.purok_name}</p>
      <p><strong>Condition:</strong> ${capitalize(row.medical_condition)}</p>
      <p><strong>Assigned Medicine:</strong> ${row.assigned_medicine_name ?? '—'}</p>
      <p><strong>Distribution Status:</strong> ${capitalize(row.distribution_status)}</p>
      <p><strong>Mobile:</strong> ${row.mobile_number ?? ''}</p>
    `,
    confirmLabel: 'Close',
    onConfirm: () => closeModal(),
  });
}

function openForm({ puroks, medicines, record, onSaved }) {
  const isEdit = !!record;
  const purokOptions = puroks.map((p) => `<option value="${p.id}" ${record?.purok_id == p.id ? 'selected' : ''}>${p.name}</option>`).join('');
  const medicineOptions = medicines.map((m) => `<option value="${m.id}" ${record?.assigned_medicine_id == m.id ? 'selected' : ''}>${m.name}</option>`).join('');

  const backdrop = openModal({
    title: isEdit ? 'Edit Beneficiary' : 'Add Beneficiary',
    bodyHtml: `
      <div class="field"><label>Full Name</label><input name="full_name" style="width:100%" value="${record?.full_name ?? ''}" required></div>
      <div class="field"><label>Birthdate</label><input type="date" name="birthdate" style="width:100%" value="${record?.birthdate ?? ''}" required></div>
      <div class="field"><label>Gender</label>
        <select name="gender" style="width:100%">
          <option value="female" ${record?.gender === 'female' ? 'selected' : ''}>Female</option>
          <option value="male" ${record?.gender === 'male' ? 'selected' : ''}>Male</option>
          <option value="other" ${record?.gender === 'other' ? 'selected' : ''}>Other</option>
        </select>
      </div>
      <div class="field"><label>Purok</label><select name="purok_id" style="width:100%">${purokOptions}</select></div>
      <div class="field"><label>Address Detail</label><input name="address_detail" style="width:100%" value="${record?.address_detail ?? ''}"></div>
      <div class="field"><label>Mobile Number</label><input name="mobile_number" style="width:100%" placeholder="09171234567" value="${record?.mobile_number ?? ''}" required></div>
      <div class="field"><label>Guardian Name</label><input name="guardian_name" style="width:100%" value="${record?.guardian_name ?? ''}"></div>
      <div class="field"><label>Guardian Contact</label><input name="guardian_contact" style="width:100%" value="${record?.guardian_contact ?? ''}"></div>
      <div class="field"><label>Medical Condition</label>
        <select name="medical_condition" style="width:100%">
          <option value="hypertension" ${record?.medical_condition === 'hypertension' ? 'selected' : ''}>Hypertension</option>
          <option value="diabetes" ${record?.medical_condition === 'diabetes' ? 'selected' : ''}>Diabetes</option>
          <option value="both" ${record?.medical_condition === 'both' ? 'selected' : ''}>Both</option>
        </select>
      </div>
      <div class="field"><label>Assigned Medicine</label><select name="assigned_medicine_id" style="width:100%"><option value="">None</option>${medicineOptions}</select></div>
      <div class="field"><label>Distribution Status</label>
        <select name="distribution_status" style="width:100%">
          <option value="active" ${record?.distribution_status === 'active' ? 'selected' : ''}>Active</option>
          <option value="pending" ${record?.distribution_status === 'pending' ? 'selected' : ''}>Pending</option>
          <option value="inactive" ${record?.distribution_status === 'inactive' ? 'selected' : ''}>Inactive</option>
        </select>
      </div>
    `,
    confirmLabel: isEdit ? 'Save Changes' : 'Add Beneficiary',
    onConfirm: async (form) => {
      const payload = Object.fromEntries(new FormData(form).entries());
      try {
        if (isEdit) {
          await api.put(`/senior-citizens/${record.id}`, payload);
          showToast('Beneficiary updated.', 'success');
        } else {
          await api.post('/senior-citizens', payload);
          showToast('Beneficiary added.', 'success');
        }
        closeModal();
        onSaved();
      } catch (err) {
        if (err instanceof ApiError && err.errors) {
          showFieldErrors(form, err.errors);
        } else {
          showToast(err instanceof ApiError ? err.message : 'Failed to save record.', 'error');
        }
      }
    },
  });
  return backdrop;
}

function statusBadge(status) {
  const map = { active: 'green', pending: 'orange', inactive: 'gray' };
  return `<span class="badge ${map[status] || 'gray'}">${capitalize(status)}</span>`;
}

function capitalize(s) {
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
}

function debounce(fn, ms) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), ms);
  };
}
