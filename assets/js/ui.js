/**
 * Vanilla replacement for the bits of Bootstrap's JS this app used: modals,
 * dismissible alerts, and the navbar's mobile collapse toggle. Driven by
 * plain data attributes (data-toggle / data-target / data-dismiss) so the
 * markup barely changed from when Bootstrap's JS handled it.
 */
(function () {
    'use strict';

    let openModal = null;
    let backdrop = null;
    let lastFocused = null;

    function showModal(modal) {
        if (!modal || modal === openModal) {
            return;
        }
        lastFocused = document.activeElement;

        backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';
        document.body.appendChild(backdrop);
        document.body.classList.add('modal-open');

        modal.style.display = 'block';
        // Force a reflow so the .show transition actually runs.
        void modal.offsetWidth;
        modal.classList.add('show');
        backdrop.classList.add('show');
        openModal = modal;

        const focusable = modal.querySelector('input, textarea, select, button:not([data-dismiss])');
        (focusable || modal).focus({ preventScroll: true });
    }

    function hideModal(modal) {
        if (!modal || modal !== openModal) {
            return;
        }
        modal.classList.remove('show');
        if (backdrop) {
            backdrop.classList.remove('show');
        }

        const doneBackdrop = backdrop;
        window.setTimeout(function () {
            modal.style.display = 'none';
            if (doneBackdrop && doneBackdrop.parentNode) {
                doneBackdrop.parentNode.removeChild(doneBackdrop);
            }
        }, 150);

        document.body.classList.remove('modal-open');
        openModal = null;
        backdrop = null;

        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus({ preventScroll: true });
        }
    }

    document.addEventListener('click', function (event) {
        const opener = event.target.closest('[data-toggle="modal"]');
        if (opener) {
            const target = opener.getAttribute('data-target');
            const modal = target ? document.querySelector(target) : null;
            if (modal) {
                event.preventDefault();
                showModal(modal);
            }
            return;
        }

        const dismisser = event.target.closest('[data-dismiss="modal"]');
        if (dismisser) {
            event.preventDefault();
            hideModal(dismisser.closest('.modal'));
            return;
        }

        const dismissAlert = event.target.closest('[data-dismiss="alert"]');
        if (dismissAlert) {
            event.preventDefault();
            const alertEl = dismissAlert.closest('.alert');
            if (alertEl) {
                alertEl.classList.remove('show');
                window.setTimeout(function () {
                    alertEl.remove();
                }, 150);
            }
            return;
        }

        // Clicking the dimmed backdrop area of an open modal closes it.
        if (openModal && event.target === openModal) {
            hideModal(openModal);
            return;
        }

        const collapseToggle = event.target.closest('[data-toggle="collapse"]');
        if (collapseToggle) {
            const target = collapseToggle.getAttribute('data-target');
            const collapseEl = target ? document.querySelector(target) : null;
            if (collapseEl) {
                collapseEl.classList.toggle('show');
            }
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && openModal) {
            hideModal(openModal);
        }
    });
})();
