/* Theme toggle (local)
   Controls html[data-theme] with localStorage key: ipcow-theme */

(function () {
    const STORAGE_KEY = 'ipcow-theme';

    const getTheme = () => {
        try {
            return localStorage.getItem(STORAGE_KEY);
        } catch {
            return null;
        }
    };

    const setTheme = (theme) => {
        const html = document.documentElement;
        if (theme) {
            html.setAttribute('data-theme', theme);
        } else {
            html.removeAttribute('data-theme');
        }

        try {
            if (theme) {
                localStorage.setItem(STORAGE_KEY, theme);
            } else {
                localStorage.removeItem(STORAGE_KEY);
            }
        } catch {
            // ignore
        }

        const toggle = document.getElementById('themeToggle');
        if (toggle) {
            toggle.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
            toggle.setAttribute('aria-label', 'Toggle theme');
        }
    };

    const resolveInitialTheme = () => {
        const saved = getTheme();
        if (saved === 'dark' || saved === 'light') return saved;
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) return 'dark';
        return 'light';
    };

    const init = () => {
        const toggle = document.getElementById('themeToggle');
        if (!toggle) return;

        // Ensure theme is set (in case head inline script isn't present).
        const current = document.documentElement.getAttribute('data-theme') || resolveInitialTheme();
        setTheme(current);

        toggle.addEventListener('click', () => {
            const theme = document.documentElement.getAttribute('data-theme') || 'light';
            setTheme(theme === 'dark' ? 'light' : 'dark');
        });

        toggle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle.click();
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
