/**
 * Keeps the ticket queue current on a screen nobody is touching: polls the
 * page itself (?poll=1, same filters) every 30 seconds and, if the queue has
 * changed, reloads -- but only once the agent has been idle for 2 minutes,
 * so it never wipes a half-typed search or an open modal. Only runs on an
 * element marked data-queue-fingerprint (the queue list on dashboard.php).
 */
(function () {
    var queue = document.querySelector('[data-queue-fingerprint]');
    if (!queue) {
        return;
    }

    var POLL_MS = 30 * 1000;
    var IDLE_MS = 2 * 60 * 1000;

    var fingerprint = queue.getAttribute('data-queue-fingerprint');
    var lastActivity = Date.now();
    var changed = false;

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function (name) {
        document.addEventListener(name, function () {
            lastActivity = Date.now();
        }, { passive: true, capture: true });
    });

    function reloadIfIdle() {
        if (changed && Date.now() - lastActivity >= IDLE_MS) {
            // Not location.reload(): after a failed "New Ticket" submit this page
            // is a POST result, and reload() would resubmit it.
            window.location.href = window.location.href.split('#')[0];
        }
    }

    function poll() {
        var url = new URL(window.location.href);
        url.searchParams.set('poll', '1');

        fetch(url, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.fingerprint !== fingerprint) {
                    changed = true;
                }
                reloadIfIdle();
            })
            .catch(function () {
                // Offline, or the session expired and login.php answered with HTML -- try again next tick.
            });
    }

    setInterval(poll, POLL_MS);
})();
