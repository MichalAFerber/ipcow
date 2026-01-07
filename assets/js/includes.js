/**
 * Dynamic includes for header and footer
 * Loads HTML fragments into designated containers
 */

function loadIncludes() {
    // Load header
    fetch('../../includes/header.html')
        .then(response => response.text())
        .then(html => {
            const headerContainer = document.getElementById('header-container');
            if (headerContainer) {
                headerContainer.innerHTML = html;
            }
        })
        .catch(error => console.error('Error loading header:', error));

    // Load footer
    fetch('includes/footer.html')
        .then(response => response.text())
        .then(html => {
            const footerContainer = document.getElementById('footer-container');
            if (footerContainer) {
                footerContainer.innerHTML = html;
                // Initialize theme toggle after footer is loaded
                initializeThemeToggle();
            }
        })
        .catch(error => console.error('Error loading footer:', error));
}

// Initialize theme toggle
function initializeThemeToggle() {
    const toggle = document.getElementById('themeToggle');
    if (!toggle) return;

    const html = document.documentElement;

    function setTheme(theme) {
        html.setAttribute('data-theme', theme);
        localStorage.setItem('ipcow-theme', theme);
    }

    const savedTheme = localStorage.getItem('ipcow-theme');
    if (savedTheme) {
        setTheme(savedTheme);
    } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        setTheme('dark');
    }

    toggle.addEventListener('click', () => {
        const current = html.getAttribute('data-theme') || 'light';
        setTheme(current === 'dark' ? 'light' : 'dark');
    });
}

// Load includes when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadIncludes);
} else {
    loadIncludes();
}
