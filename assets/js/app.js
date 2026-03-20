const bindConfirmations = () => {
    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            const message = element.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
};

const applyThemeLabel = (theme) => {
    const toggle = document.querySelector('[data-theme-toggle]');
    if (!toggle) {
        return;
    }

    const icon = toggle.querySelector('.theme-toggle-icon');
    const text = toggle.querySelector('.theme-toggle-text');
    const isDark = theme === 'dark';

    if (icon) {
        icon.textContent = isDark ? '☀️' : '🌙';
    }

    if (text) {
        text.textContent = isDark ? 'Light mode' : 'Dark mode';
    }

    toggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
};

const bindThemeToggle = () => {
    const toggle = document.querySelector('[data-theme-toggle]');
    if (!toggle) {
        return;
    }

    applyThemeLabel(document.documentElement.getAttribute('data-theme') || 'light');

    toggle.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', nextTheme);
        window.localStorage.setItem('portal-theme', nextTheme);
        applyThemeLabel(nextTheme);
    });
};

bindConfirmations();
bindThemeToggle();
