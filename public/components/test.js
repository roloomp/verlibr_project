function openModal(tab) {
  var overlay = document.getElementById('overlay');
  if (!overlay) { console.error('overlay not found'); return; }
  overlay.classList.add('open');
  switchTab(tab);
}

function closeModal() {
  var overlay = document.getElementById('overlay');
  if (overlay) overlay.classList.remove('open');
}

function switchTab(tab) {
  var panels = document.querySelectorAll('.form-panel');
  panels.forEach(function(p) { p.classList.remove('active'); });
  var target = document.getElementById('panel-' + tab);
  if (!target) { console.error('panel not found: panel-' + tab); return; }
  target.classList.add('active');
}

function handleOverlayClick(e) {
  if (e.target === document.getElementById('overlay')) closeModal();
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeModal();
});
