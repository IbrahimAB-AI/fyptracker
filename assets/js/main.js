/**
 * FYPTracker — Global JavaScript
 * assets/js/main.js
 *
 * Responsibilities:
 *  - Theme (dark/light) with localStorage persistence
 *  - Sidebar toggle (mobile)
 *  - Modal helpers
 *  - Alert/flash auto-dismiss
 *  - Confirm guard for destructive actions
 *  - Lucide icon rendering
 *  - File input label sync
 */

'use strict';

/* ══════════════════════════════════════════════════════════
   1. THEME SYSTEM
   Persists in localStorage; respects prefers-color-scheme
   ══════════════════════════════════════════════════════════ */
const Theme = (() => {
  const KEY  = 'fyp-theme';
  const root = document.documentElement;

  // System preference query
  const darkMQ = window.matchMedia('(prefers-color-scheme: dark)');

  function getStored()  { try { return localStorage.getItem(KEY); } catch(e) { return null; } }
  function getSystem()  { return darkMQ.matches ? 'dark' : 'light'; }
  // If user has never toggled: follow system. Otherwise use stored.
  function getCurrent() { return getStored() || getSystem(); }

  function apply(theme) {
    root.setAttribute('data-theme', theme);
    try { localStorage.setItem(KEY, theme); } catch(e) {}
    _updateToggleIcon(theme);
    _updateThemeColorMeta(theme);
  }

  function toggle() {
    apply(getCurrent() === 'dark' ? 'light' : 'dark');
  }

  function init() {
    // Apply on load — stored wins, else system
    apply(getCurrent());

    // skill: color-dark-mode — re-apply when system changes
    // only if user has NOT manually overridden
    darkMQ.addEventListener('change', e => {
      if (!getStored()) {
        apply(e.matches ? 'dark' : 'light');
      }
    });
  }

  function _updateToggleIcon(theme) {
    const sun  = document.getElementById('icon-sun');
    const moon = document.getElementById('icon-moon');
    const btn  = document.getElementById('themeToggle');
    if (sun)  sun.style.display  = theme === 'dark' ? 'none'  : 'block';
    if (moon) moon.style.display = theme === 'dark' ? 'block' : 'none';
    if (btn) {
      btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
      btn.setAttribute('title',      theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    }
  }

  // skill: safe-area-awareness — theme-color meta keeps browser chrome in sync
  function _updateThemeColorMeta(theme) {
    let meta = document.querySelector('meta[name="theme-color"]');
    if (!meta) {
      meta = document.createElement('meta');
      meta.name = 'theme-color';
      document.head.appendChild(meta);
    }
    meta.content = theme === 'dark' ? '#161b22' : '#f8f9fa';
  }

  return { init, toggle, getCurrent };
})();


/* ══════════════════════════════════════════════════════════
   2. SIDEBAR TOGGLE (mobile)
   ══════════════════════════════════════════════════════════ */
const Sidebar = (() => {
  let isOpen = false;

  function open() {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('sidebarOverlay');
    if (!sb || !ov) return;
    sb.classList.add('open');
    ov.classList.add('open');
    document.body.style.overflow = 'hidden';
    isOpen = true;
    // Move focus to sidebar for keyboard nav (skill: focus-on-route-change)
    sb.querySelector('.nav-item')?.focus();
  }

  function close() {
    const sb = document.getElementById('sidebar');
    const ov = document.getElementById('sidebarOverlay');
    if (!sb || !ov) return;
    sb.classList.remove('open');
    ov.classList.remove('open');
    document.body.style.overflow = '';
    isOpen = false;
  }

  function toggle() { isOpen ? close() : open(); }

  return { open, close, toggle };
})();

// Global function refs for inline onclick
function toggleSidebar() { Sidebar.toggle(); }
function closeSidebar()  { Sidebar.close(); }
function toggleTheme()   { Theme.toggle(); }


/* ══════════════════════════════════════════════════════════
   3. MODAL SYSTEM
   skill: modal-escape, escape-routes
   ══════════════════════════════════════════════════════════ */
const Modal = (() => {
  let activeModal = null;

  function open(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
    document.body.style.overflow = 'hidden';
    activeModal = el;
    // Focus first focusable element (skill: keyboard-nav)
    const focusable = el.querySelector('input, select, textarea, button:not(.modal-close), [tabindex]');
    setTimeout(() => focusable?.focus(), 50);
  }

  function close(id) {
    const el = id ? document.getElementById(id) : activeModal;
    if (!el) return;
    el.classList.remove('open');
    document.body.style.overflow = '';
    if (activeModal === el) activeModal = null;
  }

  function closeAll() {
    document.querySelectorAll('.modal-backdrop.open').forEach(el => {
      el.classList.remove('open');
    });
    document.body.style.overflow = '';
    activeModal = null;
  }

  return { open, close, closeAll };
})();

function openModal(id)  { Modal.open(id); }
function closeModal(id) { Modal.close(id); }


/* ══════════════════════════════════════════════════════════
   4. ALERT AUTO-DISMISS
   skill: duration-timing
   ══════════════════════════════════════════════════════════ */
function dismissAlert(el) {
  el.style.transition = 'opacity 250ms ease-out, transform 250ms ease-out';
  el.style.opacity    = '0';
  el.style.transform  = 'translateY(-4px)';
  setTimeout(() => el.remove(), 260);
}


/* ══════════════════════════════════════════════════════════
   5. LUCIDE ICON HELPER
   Renders icons from CDN after page load
   ══════════════════════════════════════════════════════════ */
function renderIcons() {
  if (typeof lucide !== 'undefined') {
    lucide.createIcons();
  }
}


/* ══════════════════════════════════════════════════════════
   6. FILE INPUT SYNC
   Shows filename after file chosen
   ══════════════════════════════════════════════════════════ */
function initFileInputs() {
  document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', () => {
      const label = input.closest('.file-input-wrapper')?.querySelector('.file-input-name');
      if (label) {
        label.textContent = input.files.length
          ? input.files[0].name
          : 'No file selected';
      }
    });
  });
}


/* ══════════════════════════════════════════════════════════
   7. PASSWORD STRENGTH METER
   ══════════════════════════════════════════════════════════ */
function checkPasswordStrength(value, fillId) {
  const fill = document.getElementById(fillId);
  if (!fill) return;

  let score = 0;
  if (value.length >= 8)           score++;
  if (value.length >= 12)          score++;
  if (/[A-Z]/.test(value))         score++;
  if (/[0-9]/.test(value))         score++;
  if (/[^A-Za-z0-9]/.test(value))  score++;

  const levels = [
    { w: '0%',   bg: 'transparent' },
    { w: '25%',  bg: '#ef4444' },
    { w: '50%',  bg: '#f59e0b' },
    { w: '75%',  bg: '#3b82f6' },
    { w: '90%',  bg: '#10b981' },
    { w: '100%', bg: '#059669' },
  ];

  const level = levels[Math.min(score, 5)];
  fill.style.width           = level.w;
  fill.style.background      = level.bg;
  fill.style.transition      = 'width 250ms ease-out, background 250ms ease-out';
}


/* ══════════════════════════════════════════════════════════
   8. DOM-READY BOOTSTRAP
   ══════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {

  /* --- Theme --- */
  Theme.init();

  /* --- Lucide icons --- */
  renderIcons();

  /* --- Theme toggle button --- */
  const themeBtn = document.getElementById('themeToggle');
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      Theme.toggle();
      setTimeout(renderIcons, 10);
    });
  }

  /* --- Sidebar overlay click --- */
  document.getElementById('sidebarOverlay')?.addEventListener('click', Sidebar.close);

  /* --- Alert auto-dismiss after 5s --- */
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el => {
    const delay = parseInt(el.dataset.autoDismiss) || 5000;
    if (delay > 0) setTimeout(() => dismissAlert(el), delay);
  });

  /* --- Alert close buttons --- */
  document.querySelectorAll('.alert-close').forEach(btn => {
    btn.addEventListener('click', () => dismissAlert(btn.closest('.alert')));
  });

  /* --- Backdrop click closes modal --- */
  document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', e => {
      if (e.target === backdrop) Modal.close();
    });
  });

  /* --- Escape key closes modal --- */
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') Modal.closeAll();
  });

  /* --- Confirm guard for destructive actions --- */
  document.addEventListener('click', e => {
    const el  = e.target.closest('[data-confirm]');
    if (!el) return;
    const msg = el.dataset.confirm || 'Are you sure? This cannot be undone.';
    if (!confirm(msg)) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  });

  /* --- File inputs --- */
  initFileInputs();

  /* --- Progress bar widths --- */
  document.querySelectorAll('[data-progress]').forEach(bar => {
    const inner = bar.querySelector('.progress-bar');
    if (inner) {
      requestAnimationFrame(() => {
        inner.style.width = bar.dataset.progress + '%';
      });
    }
  });

  /* ── MOBILE: Bottom sheet swipe-to-dismiss ────────────────────────────
     skill: modal-escape — swipe down to dismiss on mobile
     Uses touch events to track drag distance, closes if user
     drags down >100px or with enough velocity
  ── */
  if ('ontouchstart' in window) {
    document.querySelectorAll('.modal').forEach(modal => {
      let startY    = 0;
      let currentY  = 0;
      let isDragging = false;

      modal.addEventListener('touchstart', e => {
        // Only allow drag from the modal header area
        if (!e.target.closest('.modal-header')) return;
        startY    = e.touches[0].clientY;
        isDragging = true;
        modal.style.transition = 'none';
      }, { passive: true });

      modal.addEventListener('touchmove', e => {
        if (!isDragging) return;
        currentY = e.touches[0].clientY;
        const delta = Math.max(0, currentY - startY);
        // skill: gesture-feedback — real-time visual response
        modal.style.transform = `translateY(${delta}px)`;
      }, { passive: true });

      modal.addEventListener('touchend', () => {
        if (!isDragging) return;
        isDragging = false;
        modal.style.transition = '';
        const delta = currentY - startY;

        if (delta > 100) {
          // Dragged far enough — dismiss
          modal.style.transform = '';
          Modal.closeAll();
        } else {
          // Snap back
          modal.style.transform = '';
        }
      });
    });
  }

  /* ── MOBILE: Inject data-label attributes into table cells ───────────
     skill: content-priority — stacked card tables need label context
     Reads thead th text and adds data-label to each tbody td so
     CSS ::before pseudo-element can display the column name
  ── */
  document.querySelectorAll('.table-wrapper table').forEach(table => {
    const headers = Array.from(table.querySelectorAll('thead th'))
      .map(th => th.textContent.trim());

    table.querySelectorAll('tbody tr').forEach(row => {
      Array.from(row.querySelectorAll('td')).forEach((td, i) => {
        if (headers[i]) td.setAttribute('data-label', headers[i]);
      });
    });
  });

  /* ── MOBILE: Bottom tab bar active state sync ─────────────────────────
     Marks the correct tab as active based on current URL path
  ── */
  const currentPath = window.location.pathname;
  document.querySelectorAll('.tab-item[data-page]').forEach(item => {
    if (currentPath.includes(item.dataset.page)) {
      item.classList.add('active');
    }
  });

  /* ── Touch feedback on cards (skill: press-feedback / scale-feedback) ─ */
  if ('ontouchstart' in window) {
    document.querySelectorAll('.card, .stat-card').forEach(card => {
      card.addEventListener('touchstart', () => {
        card.style.transform = 'scale(0.99)';
        card.style.transition = 'transform 80ms ease-out';
      }, { passive: true });
      card.addEventListener('touchend', () => {
        card.style.transform = '';
      });
    });
  }

});
