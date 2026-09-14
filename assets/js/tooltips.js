/** Bootstrap tooltips must be initialized explicitly — this wires up every [data-bs-toggle="tooltip"] on the page. */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});
