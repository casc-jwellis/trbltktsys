/**
 * Click-to-sort table headers. Rows carrying data-admin-group="1" always
 * sort above the rest, regardless of which column or direction is active —
 * only the ordering within each of those two groups changes.
 */
(function () {
    function cellText(row, index) {
        var cell = row.children[index];
        return cell ? cell.textContent.trim().toLowerCase() : '';
    }

    function sortTable(table, columnIndex, direction) {
        var tbody = table.tBodies[0];
        if (!tbody) {
            return;
        }

        var rows = Array.prototype.slice.call(tbody.rows);
        var admins = rows.filter(function (row) { return row.dataset.adminGroup === '1'; });
        var others = rows.filter(function (row) { return row.dataset.adminGroup !== '1'; });

        [admins, others].forEach(function (group) {
            group.sort(function (a, b) {
                var textA = cellText(a, columnIndex);
                var textB = cellText(b, columnIndex);
                if (textA === textB) {
                    return 0;
                }
                var result = textA < textB ? -1 : 1;
                return direction === 'asc' ? result : -result;
            });
        });

        admins.concat(others).forEach(function (row) { tbody.appendChild(row); });
    }

    document.querySelectorAll('table.sortable-table').forEach(function (table) {
        var headerRow = table.tHead && table.tHead.rows[0];
        if (!headerRow) {
            return;
        }

        Array.prototype.forEach.call(headerRow.cells, function (th, columnIndex) {
            if (!('sort' in th.dataset)) {
                return;
            }

            th.classList.add('sortable-header');
            th.setAttribute('role', 'button');
            th.setAttribute('tabindex', '0');

            var indicator = document.createElement('span');
            indicator.className = 'sort-indicator';
            th.appendChild(indicator);

            function activate() {
                var direction = th.dataset.sortDirection === 'asc' ? 'desc' : 'asc';

                Array.prototype.forEach.call(headerRow.cells, function (otherTh) {
                    if (otherTh === th) {
                        return;
                    }
                    delete otherTh.dataset.sortDirection;
                    var otherIndicator = otherTh.querySelector('.sort-indicator');
                    if (otherIndicator) {
                        otherIndicator.textContent = '';
                    }
                });

                th.dataset.sortDirection = direction;
                indicator.textContent = direction === 'asc' ? '▲' : '▼';

                sortTable(table, columnIndex, direction);
            }

            th.addEventListener('click', activate);
            th.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    activate();
                }
            });
        });
    });
})();
