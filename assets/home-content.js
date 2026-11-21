// Local editorial preview only: images are uploaded by the normal authenticated form.
document.querySelectorAll('[data-home-editor]').forEach(form => {
  const picker = form.querySelector('[data-home-image]');
  let preview = form.querySelector('[data-home-preview]');
  if (!preview && picker) {
    const wrapper = document.createElement('div');
    wrapper.className = 'home-card-preview';
    preview = document.createElement('img');
    preview.alt = 'Vista previa de la imagen seleccionada';
    preview.hidden = true;
    wrapper.append(preview);
    picker.closest('label').after(wrapper);
  }
  let objectUrl;
  picker?.addEventListener('change', () => {
    const file = picker.files?.[0];
    picker.setCustomValidity('');
    if (!file) return;
    if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
      picker.setCustomValidity('Elige una imagen JPG, PNG o WebP de hasta 5 MB.');
      picker.reportValidity();
      return;
    }
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = URL.createObjectURL(file);
    preview.src = objectUrl;
    preview.hidden = false;
  });
  for (const field of ['title','description']) {
    const input = form.querySelector(`[data-home-${field}]`);
    const target = form.querySelector(`[data-home-preview-${field}]`);
    input?.addEventListener('input', () => {if (target) target.textContent = input.value;});
  }
  window.addEventListener('pagehide', () => {if (objectUrl) URL.revokeObjectURL(objectUrl);});
});
