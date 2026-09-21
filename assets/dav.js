/**
 * Kleine Helfer für den Assistenten "Gerät verbinden": Werte kopieren und Rückfrage vor dem Widerrufen.
 * Ereignis-Delegation am Dokument, damit es auch nach einem PJAX-Seitenwechsel funktioniert.
 */
document.addEventListener('click', async (event) => {
  const copy = event.target.closest('[data-dav-copy]');
  if (copy) {
    const source = document.querySelector(copy.dataset.davCopy);
    if (!source) return;
    try {
      await navigator.clipboard.writeText(source.textContent.trim());
    } catch {
      // Ohne Zwischenablage-Rechte (etwa ohne HTTPS) den Text markieren, damit Strg+C genügt.
      const range = document.createRange();
      range.selectNodeContents(source);
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(range);
      return;
    }
    const original = copy.innerHTML;
    copy.textContent = copy.dataset.davCopied;
    setTimeout(() => { copy.innerHTML = original; }, 1600);
    return;
  }

  const confirmButton = event.target.closest('[data-dav-confirm]');
  if (confirmButton && !window.confirm(confirmButton.dataset.davConfirm)) {
    event.preventDefault();
  }
});
