/**
 * Progressive enhancement for multi-select "assignment picker" fields: a
 * plain checkbox list (the real form data, always present and functional
 * without JS) gets replaced with a searchable tag input. Each
 * `.assignment-picker` finds its own `.assignment-option` checkboxes, so the
 * same script drives any number of pickers on a page (e.g. Users + Groups).
 */
(function () {
    'use strict';

    function initPicker(picker) {
        const optionsContainer = picker.querySelector('.assignment-options');
        const pillsContainer = picker.querySelector('.assignment-pills');
        const searchInput = picker.querySelector('.assignment-search');
        const dropdown = picker.querySelector('.assignment-dropdown');

        if (!optionsContainer || !pillsContainer || !searchInput || !dropdown) {
            return;
        }

        const options = Array.from(optionsContainer.querySelectorAll('.assignment-option')).map(function (row) {
            const checkbox = row.querySelector('input[type="checkbox"]');
            const label = row.querySelector('label');
            return { checkbox: checkbox, text: label ? label.textContent.trim() : '' };
        }).filter(function (o) { return o.checkbox; });

        if (!options.length) {
            return;
        }

        // Real checkboxes stay in the DOM (they're what the form submits) —
        // just hide the plain list now that the picker UI is taking over.
        optionsContainer.classList.add('d-none');
        dropdown.classList.add('d-none');

        function renderPills() {
            pillsContainer.innerHTML = '';
            const selected = options.filter(function (o) { return o.checkbox.checked; });

            if (!selected.length) {
                const empty = document.createElement('span');
                empty.className = 'text-body-secondary small';
                empty.textContent = 'None selected';
                pillsContainer.appendChild(empty);
                return;
            }

            selected.forEach(function (o) {
                const pill = document.createElement('span');
                pill.className = 'assignment-pill badge text-bg-secondary d-inline-flex align-items-center gap-1';

                const text = document.createElement('span');
                text.textContent = o.text;
                pill.appendChild(text);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn-close btn-close-white';
                remove.setAttribute('aria-label', 'Remove ' + o.text);
                remove.addEventListener('click', function () {
                    o.checkbox.checked = false;
                    renderPills();
                    renderDropdown();
                });
                pill.appendChild(remove);

                pillsContainer.appendChild(pill);
            });
        }

        function renderDropdown() {
            const query = searchInput.value.trim().toLowerCase();
            const matches = options.filter(function (o) {
                return !o.checkbox.checked && o.text.toLowerCase().includes(query);
            });

            dropdown.innerHTML = '';

            if (!matches.length) {
                dropdown.classList.add('d-none');
                return;
            }

            matches.forEach(function (o) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action py-1 px-2 small';
                item.textContent = o.text;
                item.addEventListener('click', function () {
                    o.checkbox.checked = true;
                    searchInput.value = '';
                    renderPills();
                    renderDropdown();
                    searchInput.focus();
                });
                dropdown.appendChild(item);
            });

            dropdown.classList.remove('d-none');
        }

        searchInput.addEventListener('input', renderDropdown);
        searchInput.addEventListener('focus', renderDropdown);

        document.addEventListener('click', function (event) {
            if (!picker.contains(event.target)) {
                dropdown.classList.add('d-none');
            }
        });

        // Esc closes the dropdown without closing an enclosing modal.
        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !dropdown.classList.contains('d-none')) {
                dropdown.classList.add('d-none');
                event.stopPropagation();
            }
        });

        renderPills();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.assignment-picker').forEach(initPicker);
    });
})();
