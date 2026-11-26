(() => {
  const dialog = document.getElementById('user-deletion-dialog');
  if (!dialog) return;
  const form = document.getElementById('user-deletion-form');
  const confirm = document.getElementById('confirm-user-deletion');
  const message = document.getElementById('deletion-preview-message');
  const title = document.getElementById('deletion-preview-title');
  const counts = document.getElementById('deletion-preview-counts');
  let request;
  document.getElementById('cancel-user-deletion').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => request?.abort());
  document.querySelectorAll('[data-delete-user]').forEach(button => button.addEventListener('click', async () => {
    request?.abort(); request = new AbortController(); const current = request;
    form.reset(); form.elements.user_id.value = button.dataset.deleteUser;
    form.elements.revision.value = ''; confirm.disabled = true; counts.replaceChildren(); title.textContent = '';
    message.textContent = 'Cargando los datos asociados…'; dialog.showModal();
    try {
      const response = await fetch('deletions.php?type=users&format=json&id=' + encodeURIComponent(button.dataset.deleteUser), {cache:'no-store', signal:current.signal, headers:{Accept:'application/json'}});
      if (!response.ok) { const error = await response.json().catch(() => ({})); throw new Error(error.error || 'No se puede eliminar esta cuenta. Revisa tus permisos o vuelve a iniciar sesión.'); }
      const preview = await response.json(); if (current.signal.aborted) return;
      title.textContent = preview.title; message.textContent = 'Datos que se eliminarán con copia recuperable:';
      for (const [key, count] of Object.entries(preview.counts)) { const item = document.createElement('li'); item.textContent = (preview.labels[key] || key) + ': ' + count; counts.append(item); }
      form.elements.revision.value = preview.revision; confirm.disabled = false;
    } catch (error) { if (error.name !== 'AbortError') message.textContent = error.message; }
  }));
  form.addEventListener('submit', event => { if (confirm.disabled || !form.elements.revision.value) {event.preventDefault();return;} confirm.disabled = true; confirm.textContent = 'Eliminando…'; });
})();
