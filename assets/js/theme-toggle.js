(function () {
    "use strict";

    var toggle = document.getElementById('themeToggle');
    if (!toggle) {
        return;
    }

    function updateLabel() {
        var isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        var label = isDark ? 'Switch to light theme' : 'Switch to dark theme';
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('title', label);
    }

    // Persists the choice for a logged-in agent server-side (so it follows
    // them to another browser/device) as a best-effort background call --
    // the localStorage write below already covers this browser/device
    // immediately, and it's all an anonymous visitor has anyway.
    function saveToServer(theme) {
        try {
            fetch('set-theme.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'theme=' + encodeURIComponent(theme) + '&csrf_token=' + encodeURIComponent(toggle.dataset.csrf || ''),
                credentials: 'same-origin'
            });
        } catch (e) {
            // Fetch unsupported/blocked -- localStorage still covers this browser.
        }
    }

    updateLabel();

    toggle.addEventListener('click', function () {
        var next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        updateLabel();

        try {
            localStorage.setItem('theme', next);
        } catch (e) {
            // Private browsing / blocked storage -- the toggle still works for
            // this page load, it just won't be remembered on the next one.
        }

        saveToServer(next);
    });
})();
