(() => {
  const dialog = document.getElementById('project-lightbox');
  const image = document.getElementById('project-lightbox-image');
  const caption = document.getElementById('project-lightbox-caption');
  if (!dialog || !image || !caption) return;

  const items = Array.from(document.querySelectorAll('.project-gallery-item'));
  const closeButton = dialog.querySelector('.project-lightbox-close');

  items.forEach((item) => {
    item.addEventListener('click', () => {
      const source = item.querySelector('img');
      if (!source) return;
      image.src = source.currentSrc || source.src;
      image.alt = source.alt;
      caption.textContent = item.querySelector('span')?.textContent?.trim() || source.alt;
      dialog.showModal();
    });
  });

  closeButton?.addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog) dialog.close();
  });
  dialog.addEventListener('close', () => {
    image.removeAttribute('src');
    image.alt = '';
    caption.textContent = '';
  });
})();
