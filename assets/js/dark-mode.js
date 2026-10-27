(() => {
  const STORAGE_KEY = 'pawadopt_theme';

  function getInitialTheme() {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved === 'dark' || saved === 'light') return saved;
    } catch (error) {}
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function applyTheme(theme) {
    const dark = theme === 'dark';
    document.documentElement.classList.toggle('dark-mode', dark);
    if (document.body) {
      document.body.classList.toggle('dark-mode', dark);
      document.body.setAttribute('data-theme', theme);
    }
    try { localStorage.setItem(STORAGE_KEY, theme); } catch (error) {}

    const button = document.getElementById('darkModeToggle');
    if (button) {
      button.dataset.theme = theme;
      button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
      button.title = dark ? 'Switch to light mode' : 'Switch to dark mode';
      const icon = button.querySelector('.dm-icon');
      const label = button.querySelector('.dm-label');
      if (icon) icon.textContent = dark ? '☀️' : '🌙';
      if (label) label.textContent = dark ? 'Light mode' : 'Dark mode';
    }
  }

  function ensureButton() {
    if (!document.body || document.getElementById('darkModeToggle')) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.id = 'darkModeToggle';
    button.className = 'darkmode-toggle';
    button.innerHTML = '<span class="dm-icon" aria-hidden="true">🌙</span><span class="dm-label">Dark mode</span>';
    button.addEventListener('click', () => {
      const next = document.body.classList.contains('dark-mode') ? 'light' : 'dark';
      applyTheme(next);
    });
    document.body.appendChild(button);
  }

  const initialTheme = getInitialTheme();
  document.documentElement.classList.toggle('dark-mode', initialTheme === 'dark');

  document.addEventListener('DOMContentLoaded', () => {
    ensureButton();
    applyTheme(initialTheme);
  });
})();
