(function () {
  'use strict';

  const modal = document.getElementById('teamModal');
  if (!modal) return;

  const overlay = modal.querySelector('.team-modal__overlay');
  const closeBtn = modal.querySelector('.team-modal__close');
  const imgEl = modal.querySelector('.team-modal__image');
  const nameEl = document.getElementById('teamModalTitle');
  const roleEl = document.getElementById('teamModalRole');
  const bioEl = document.getElementById('teamModalBio');
  const cards = document.querySelectorAll('.team-card');

  let lastFocused = null;

  function openModal(memberId) {
    const member = TEAM_MEMBERS[memberId];
    if (!member) return;

    nameEl.textContent = member.name;
    roleEl.textContent = member.role;
    bioEl.textContent = member.bio;
    imgEl.src = member.image;
    imgEl.alt = member.name;

    lastFocused = document.activeElement;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('team-modal-open');

    requestAnimationFrame(() => closeBtn.focus());
  }

  function closeModal() {
    if (!modal.classList.contains('is-open')) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('team-modal-open');

    if (lastFocused && typeof lastFocused.focus === 'function') {
      lastFocused.focus();
    }
  }

  cards.forEach(card => {
    card.addEventListener('click', () => {
      const memberId = card.dataset.memberId;
      openModal(memberId);
    });
    card.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        const memberId = card.dataset.memberId;
        openModal(memberId);
      }
    });
  });

  closeBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', closeModal);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) {
      closeModal();
    }

    if (e.key === 'Tab' && modal.classList.contains('is-open')) {
      const focusable = modal.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });
})();