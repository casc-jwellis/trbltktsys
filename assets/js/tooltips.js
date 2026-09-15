/**
 * Lightweight tooltip for any [data-tooltip] element. Content comes from
 * the element's `title` attribute (moved into a data attribute at init so
 * the browser's own native tooltip doesn't also show up), rendered as HTML
 * since the app only ever builds trusted, pre-escaped markup for these.
 */
(function () {
    'use strict';

    let tipEl = null;

    function showTip(trigger) {
        const html = trigger.dataset.tooltipContent;
        if (!html) {
            return;
        }

        tipEl = document.createElement('div');
        tipEl.className = 'tooltip-custom';
        tipEl.innerHTML = html;
        document.body.appendChild(tipEl);

        const rect = trigger.getBoundingClientRect();
        const tipRect = tipEl.getBoundingClientRect();
        let left = rect.left + rect.width / 2 - tipRect.width / 2 + window.scrollX;
        left = Math.max(8, Math.min(left, window.scrollX + document.documentElement.clientWidth - tipRect.width - 8));
        const top = rect.bottom + window.scrollY + 8;

        tipEl.style.left = left + 'px';
        tipEl.style.top = top + 'px';

        void tipEl.offsetWidth;
        tipEl.classList.add('show');
    }

    function hideTip() {
        if (tipEl) {
            tipEl.remove();
            tipEl = null;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-tooltip]').forEach(function (el) {
            const title = el.getAttribute('title');
            if (title) {
                el.dataset.tooltipContent = title;
                el.removeAttribute('title');
            }
            el.addEventListener('mouseenter', function () { showTip(el); });
            el.addEventListener('mouseleave', hideTip);
            el.addEventListener('focus', function () { showTip(el); });
            el.addEventListener('blur', hideTip);
        });
    });
})();
