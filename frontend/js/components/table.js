import { ApiError } from '../api.js';

/**
 * Renders a paginated table with loading/empty/error states.
 * @param {HTMLElement} container
 * @param {{columns: {key:string,label:string,render?:(row:object)=>string}[],
 *          fetchPage: (page:number)=>Promise<{items:object[],total:number,per_page:number,page:number}>,
 *          emptyMessage?: string}} config
 */
export class DataTable {
  constructor(container, config) {
    this.container = container;
    this.config = config;
    this.page = 1;
  }

  async load(page = this.page) {
    this.page = page;
    this.container.innerHTML = '<div class="loading-state">Loading…</div>';
    try {
      const result = await this.config.fetchPage(page);
      this.render(result);
    } catch (err) {
      const message = err instanceof ApiError ? err.message : 'Failed to load data.';
      this.container.innerHTML = `
        <div class="error-state">
          <div>${message}</div>
          <button class="btn secondary" id="retry-btn">Retry</button>
        </div>`;
      this.container.querySelector('#retry-btn').addEventListener('click', () => this.load(page));
    }
  }

  render(result) {
    const { items, total, per_page: perPage } = result;
    if (!items || items.length === 0) {
      this.container.innerHTML = `<div class="empty-state">${this.config.emptyMessage || 'No records found.'}</div>`;
      return;
    }

    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const headerCells = this.config.columns.map((c) => `<th>${c.label}</th>`).join('');
    const bodyRows = items
      .map((row) => {
        const cells = this.config.columns
          .map((c) => `<td>${c.render ? c.render(row) : (row[c.key] ?? '')}</td>`)
          .join('');
        return `<tr data-id="${row.id ?? ''}">${cells}</tr>`;
      })
      .join('');

    this.container.innerHTML = `
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr>${headerCells}</tr></thead>
          <tbody>${bodyRows}</tbody>
        </table>
      </div>
      <div class="pagination">
        <span class="info">Page ${this.page} of ${totalPages} (${total} total)</span>
        <button id="prev-page" ${this.page <= 1 ? 'disabled' : ''}>Previous</button>
        <button id="next-page" ${this.page >= totalPages ? 'disabled' : ''}>Next</button>
      </div>
    `;

    this.container.querySelector('#prev-page')?.addEventListener('click', () => this.load(this.page - 1));
    this.container.querySelector('#next-page')?.addEventListener('click', () => this.load(this.page + 1));

    if (this.config.onRowClick) {
      this.container.querySelectorAll('tbody tr').forEach((tr) => {
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', (e) => {
          if (e.target.closest('[data-no-row-click]')) return;
          const row = items.find((r) => String(r.id) === tr.dataset.id);
          this.config.onRowClick(row);
        });
      });
    }
  }
}
