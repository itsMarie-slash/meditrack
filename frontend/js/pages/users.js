import { initShell } from '../shell.js';
import { api, ApiError, getCurrentUser } from '../api.js';
import { DataTable } from '../components/table.js';
import { openModal, closeModal, confirmDialog, showFieldErrors } from '../components/modal.js';
import { showToast } from '../components/toast.js';

const session = await initShell('users', 'User Management');
if (session) init(session);

async function init({ content }) {
  const filters = { search: '', role: '' };

  content.innerHTML = `
    <div class="toolbar">
      <div class="filters">
        <input type="search" id="f-search" placeholder="Search username or name…">
        <select id="f-role">
          <option value="">All Roles</option>
          <option value="bhw">BHW</option>
          <option value="midwife">Midwife</option>
          <option value="ipho">IPHO</option>
        </select>
      </div>
      <button class="btn" id="add-btn">+ Add User</button>
    </div>
    <div id="table-container"></div>
  `;

  const tableContainer = content.querySelector('#table-container');
  const table = new DataTable(tableContainer, {
    emptyMessage: 'No users found.',
    columns: [
      { key: 'username', label: 'Username' },
      { key: 'full_name', label: 'Full Name' },
      { key: 'role', label: 'Role', render: (r) => r.role.toUpperCase() },
      { key: 'is_active', label: 'Status', render: (r) => (Number(r.is_active) ? '<span class="badge green">Active</span>' : '<span class="badge gray">Inactive</span>') },
      { key: 'actions', label: 'Actions', render: (r) => `<button class="btn secondary" data-no-row-click data-edit="${r.id}">Edit</button> <button class="btn danger" data-no-row-click data-deactivate="${r.id}">Deactivate</button>` },
    ],
    fetchPage: (page) => api.get('/users', { ...filters, page, per_page: 15 }),
  });
  await table.load(1);

  content.querySelector('#f-search').addEventListener('input', debounce((e) => { filters.search = e.target.value; table.load(1); }, 350));
  content.querySelector('#f-role').addEventListener('change', (e) => { filters.role = e.target.value; table.load(1); });
  content.querySelector('#add-btn').addEventListener('click', () => openUserForm({ onSaved: () => table.load(table.page) }));

  tableContainer.addEventListener('click', async (e) => {
    const editId = e.target.dataset.edit;
    const deactivateId = e.target.dataset.deactivate;
    if (editId) {
      const record = table.lastItems.find((r) => String(r.id) === editId);
      if (record) openUserForm({ record, onSaved: () => table.load(table.page) });
    }
    if (deactivateId) {
      const currentUser = getCurrentUser();
      if (String(currentUser?.id) === deactivateId) {
        showToast('You cannot deactivate your own account.', 'error');
        return;
      }
      const confirmed = await confirmDialog('Deactivate this user account? They will no longer be able to log in.');
      if (!confirmed) return;
      try {
        await api.del(`/users/${deactivateId}`);
        showToast('User deactivated.', 'success');
        table.load(table.page);
      } catch (err) {
        showToast(err instanceof ApiError ? err.message : 'Failed to deactivate user.', 'error');
      }
    }
  });
}

function openUserForm({ record, onSaved }) {
  const isEdit = !!record;
  openModal({
    title: isEdit ? 'Edit User' : 'Add User',
    bodyHtml: `
      ${isEdit ? '' : `
        <div class="field"><label>Username</label><input name="username" style="width:100%" required></div>
        <div class="field"><label>Temporary Password</label><input type="password" name="password" style="width:100%" minlength="8" required></div>
      `}
      <div class="field"><label>Full Name</label><input name="full_name" style="width:100%" value="${record?.full_name ?? ''}" required></div>
      <div class="field"><label>Role</label>
        <select name="role" style="width:100%">
          <option value="bhw" ${record?.role === 'bhw' ? 'selected' : ''}>BHW</option>
          <option value="midwife" ${record?.role === 'midwife' ? 'selected' : ''}>Midwife</option>
          <option value="ipho" ${record?.role === 'ipho' ? 'selected' : ''}>IPHO</option>
        </select>
      </div>
      ${isEdit ? `
        <div class="field"><label>Status</label>
          <select name="is_active" style="width:100%">
            <option value="1" ${Number(record?.is_active) ? 'selected' : ''}>Active</option>
            <option value="0" ${!Number(record?.is_active) ? 'selected' : ''}>Inactive</option>
          </select>
        </div>
      ` : ''}
    `,
    confirmLabel: isEdit ? 'Save Changes' : 'Add User',
    onConfirm: async (form) => {
      const payload = Object.fromEntries(new FormData(form).entries());
      try {
        if (isEdit) {
          payload.is_active = payload.is_active === '1';
          await api.put(`/users/${record.id}`, payload);
          showToast('User updated.', 'success');
        } else {
          await api.post('/users', payload);
          showToast('User created.', 'success');
        }
        closeModal();
        onSaved();
      } catch (err) {
        if (err instanceof ApiError && err.errors) showFieldErrors(form, err.errors);
        else showToast(err instanceof ApiError ? err.message : 'Failed to save user.', 'error');
      }
    },
  });
}

function debounce(fn, ms) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), ms);
  };
}
