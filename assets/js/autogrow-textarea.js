/**
 * Grows any textarea marked data-autogrow to fit what's typed or pasted into
 * it, so long text never needs an inner scrollbar. The textarea's rows
 * attribute stays the minimum height.
 */
(function () {
    function fit(textarea) {
        textarea.style.height = 'auto';
        var borders = textarea.offsetHeight - textarea.clientHeight;
        textarea.style.height = (textarea.scrollHeight + borders) + 'px';
    }

    document.querySelectorAll('textarea[data-autogrow]').forEach(function (textarea) {
        textarea.style.overflowY = 'hidden';
        textarea.addEventListener('input', function () { fit(textarea); });
        fit(textarea);
    });

    // A textarea inside a closed modal has no layout, so size it once it's visible.
    document.addEventListener('shown.bs.modal', function (event) {
        event.target.querySelectorAll('textarea[data-autogrow]').forEach(fit);
    });
})();
