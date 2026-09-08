let activeBackdrop = null;

export function closeModal() {
  if (activeBackdrop) {
    activeBackdrop.remove();
    activeBackdrop = null;
  }
}

/**
 * @param {{title:string, bodyHtml:string, confirmLabel?:string, onConfirm:(form:HTMLFormElement)=>Promise<void>|void, cancelLabel?:string}} opts
 */
export function openModal({ title, bodyHtml, confirmLabel = 'Save', cancelLabel = 'Cancel', onConfirm }) {
  closeModal();

  const backdrop = document.createElement('div');
  backdrop.className = 'modal-backdrop';
  backdrop.innerHTML = `
    <div class="modal" role="dialog" aria-modal="true" aria-label="${title}">
      <h2>${title}</h2>
      <form id="modal-form">${bodyHtml}</form>
      <div class="actions">
        <button type="button" class="btn secondary" data-action="cancel">${cancelLabel}</button>
        <button type="submit" form="modal-form" class="btn" data-action="confirm">${confirmLabel}</button>
      </div>
    </div>
  `;
  document.body.appendChild(backdrop);
  activeBackdrop = backdrop;

  backdrop.addEventListener('click', (e) => {
    if (e.target === backdrop) closeModal();
  });
  backdrop.querySelector('[data-action="cancel"]').addEventListener('click', closeModal);

  const form = backdrop.querySelector('#modal-form');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const confirmBtn = backdrop.querySelector('[data-action="confirm"]');
    confirmBtn.disabled = true;
    try {
      await onConfirm(form);
    } finally {
      if (activeBackdrop === backdrop) confirmBtn.disabled = false;
    }
  });

  return backdrop;
}

export function showFieldErrors(form, errors) {
  form.querySelectorAll('.field-error').forEach((el) => el.remove());
  Object.entries(errors || {}).forEach(([field, message]) => {
    const input = form.querySelector(`[name="${field}"]`);
    if (!input) return;
    const el = document.createElement('div');
    el.className = 'field-error';
    el.textContent = message;
    input.closest('.field')?.appendChild(el);
  });
}

/** @returns {Promise<boolean>} */
export function confirmDialog(message) {
  return new Promise((resolve) => {
    openModal({
      title: 'Please confirm',
      bodyHtml: `<p>${message}</p>`,
      confirmLabel: 'Confirm',
      onConfirm: () => {
        resolve(true);
        closeModal();
      },
    });
    const backdrop = document.querySelector('.modal-backdrop');
    backdrop.querySelector('[data-action="cancel"]').addEventListener('click', () => resolve(false));
  });
}
