import { initShell } from '../shell.js';
import { api, ApiError } from '../api.js';
import { DataTable } from '../components/table.js';
import { openModal, closeModal, showFieldErrors } from '../components/modal.js';
import { showToast } from '../components/toast.js';

const session = await initShell('medicines', 'Medicine Inventory');
if (session) init(session);

async function init({ user, content }) {
  const isBhw = user.role === 'bhw';
  const filters = { search: '', category: '', low_stock_only: '' };

  content.innerHTML = `
    <div class="toolbar">
      <div class="filters">
        <input type="search" id="f-search" placeholder="Search medicine…">
        <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" id="f-low-stock"> Low stock only</label>
      </div>
      ${isBhw ? '<button class="btn" id="add-btn">+ Add Medicine</button>' : ''}
    </div>
    <div id="table-container"></div>
  `;

  const tableContainer = content.querySelector('#table-container');
  const table = new DataTable(tableContainer, {
    emptyMessage: 'No medicines found.',
    columns: [
      { key: 'name', label: 'Name' },
      { key: 'category', label: 'Category' },
      { key: 'stock_quantity', label: 'Stock Qty' },
      { key: 'unit', label: 'Unit' },
      { key: 'is_low_stock', label: 'Low Stock', render: (r) => (Number(r.is_low_stock) ? '<span class="badge red">Low</span>' : '<span class="badge green">OK</span>') },
      { key: 'nearest_expiration', label: 'Nearest Expiration', render: (r) => r.nearest_expiration ?? '—' },
      ...(isBhw ? [{ key: 'actions', label: 'Actions', render: (r) => `<button class="btn secondary" data-no-row-click data-edit="${r.id}">Edit</button> <button class="btn" data-no-row-click data-stock="${r.id}">Update Stock</button> <button class="btn danger" data-no-row-click data-delete="${r.id}">Delete</button>` }] : []),
    ],
    fetchPage: (page) => api.get('/medicines', { ...filters, page, per_page: 15 }),
  });
  await table.load(1);

  content.querySelector('#f-search').addEventListener('input', debounce((e) => { filters.search = e.target.value; table.load(1); }, 350));
  content.querySelector('#f-low-stock').addEventListener('change', (e) => { filters.low_stock_only = e.target.checked ? '1' : ''; table.load(1); });

  if (isBhw) {
    content.querySelector('#add-btn').addEventListener('click', () => openMedicineForm({ onSaved: () => table.load(table.page) }));
    tableContainer.addEventListener('click', async (e) => {
      const editId = e.target.dataset.edit;
      const stockId = e.target.dataset.stock;
      const deleteId = e.target.dataset.delete;
      if (editId) {
        const { medicine } = await api.get(`/medicines/${editId}`);
        openMedicineForm({ record: medicine, onSaved: () => table.load(table.page) });
      }
      if (stockId) {
        openStockForm({ medicineId: stockId, onSaved: () => table.load(table.page) });
      }
      if (deleteId) {
        if (!confirm('Deactivate this medicine? Distribution history is preserved.')) return;
        try {
          await api.del(`/medicines/${deleteId}`);
          showToast('Medicine deactivated.', 'success');
          table.load(table.page);
        } catch (err) {
          showToast(err instanceof ApiError ? err.message : 'Failed to deactivate medicine.', 'error');
        }
      }
    });
  }
}

function openMedicineForm({ record, onSaved }) {
  const isEdit = !!record;
  openModal({
    title: isEdit ? 'Edit Medicine' : 'Add Medicine',
    bodyHtml: `
      <div class="field"><label>Name</label><input name="name" style="width:100%" value="${record?.name ?? ''}" required></div>
      <div class="field"><label>Category</label><input name="category" style="width:100%" value="${record?.category ?? ''}" required></div>
      <div class="field"><label>Description</label><textarea name="description" style="width:100%">${record?.description ?? ''}</textarea></div>
      <div class="field"><label>Unit</label><input name="unit" style="width:100%" value="${record?.unit ?? ''}" placeholder="tablet, box, etc." required></div>
      <div class="field"><label>Low Stock Threshold</label><input type="number" min="0" name="low_stock_threshold" style="width:100%" value="${record?.low_stock_threshold ?? 20}"></div>
    `,
    confirmLabel: isEdit ? 'Save Changes' : 'Add Medicine',
    onConfirm: async (form) => {
      const payload = Object.fromEntries(new FormData(form).entries());
      try {
        if (isEdit) {
          await api.put(`/medicines/${record.id}`, payload);
          showToast('Medicine updated.', 'success');
        } else {
          await api.post('/medicines', payload);
          showToast('Medicine added.', 'success');
        }
        closeModal();
        onSaved();
      } catch (err) {
        if (err instanceof ApiError && err.errors) showFieldErrors(form, err.errors);
        else showToast(err instanceof ApiError ? err.message : 'Failed to save medicine.', 'error');
      }
    },
  });
}

function openStockForm({ medicineId, onSaved }) {
  openModal({
    title: 'Update Stock',
    bodyHtml: `
      <div class="field"><label>Quantity Received</label><input type="number" min="1" name="quantity" style="width:100%" required></div>
      <div class="field"><label>Batch Number</label><input name="batch_number" style="width:100%" required></div>
      <div class="field"><label>Date Received</label><input type="date" name="date_received" style="width:100%" required></div>
      <div class="field"><label>Expiration Date</label><input type="date" name="expiration_date" style="width:100%" required></div>
    `,
    confirmLabel: 'Update Stock',
    onConfirm: async (form) => {
      const payload = Object.fromEntries(new FormData(form).entries());
      try {
        const result = await api.post(`/medicines/${medicineId}/stock`, payload);
        if (result.schedule_created) {
          showToast(`Stock updated. Distribution schedule created and ${result.sms_sent} beneficiaries notified.`, 'success', 6000);
        } else {
          showToast('Stock updated.', 'success');
        }
        closeModal();
        onSaved();
      } catch (err) {
        if (err instanceof ApiError && err.errors) showFieldErrors(form, err.errors);
        else showToast(err instanceof ApiError ? err.message : 'Failed to update stock.', 'error');
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
