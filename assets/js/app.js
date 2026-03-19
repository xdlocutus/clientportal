const THEME_KEY = 'business-portal-theme';

const getPreferredTheme = () => {
    const storedTheme = localStorage.getItem(THEME_KEY);
    if (storedTheme === 'light' || storedTheme === 'dark') {
        return storedTheme;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

const applyTheme = (theme) => {
    document.documentElement.setAttribute('data-bs-theme', theme);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const icon = button.querySelector('.theme-toggle-icon');
        const label = button.querySelector('.theme-toggle-label');
        const nextTheme = theme === 'dark' ? 'light' : 'dark';

        if (icon) {
            icon.textContent = theme === 'dark' ? '☀️' : '🌙';
        }

        if (label) {
            label.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
        }

        button.setAttribute('data-next-theme', nextTheme);
    });
};

applyTheme(getPreferredTheme());

document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const nextTheme = button.getAttribute('data-next-theme') || 'dark';
        localStorage.setItem(THEME_KEY, nextTheme);
        applyTheme(nextTheme);
    });
});

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (!localStorage.getItem(THEME_KEY)) {
        applyTheme(getPreferredTheme());
    }
});

document.querySelectorAll('[data-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => {
        const message = element.getAttribute('data-confirm') || 'Are you sure?';
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});
