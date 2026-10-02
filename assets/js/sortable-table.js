/**
 * Click-to-sort table headers. Rows carrying data-admin-group="1" always
 * sort above the rest, regardless of which column or direction is active —
 * only the ordering within each of those two groups changes.
 *
 * Columns sort by their cell text unless a cell supplies data-sort-value
 * (e.g. a priority's rank, or a timestamp). A header with data-sort="number"
 * compares those values numerically. A header with data-sort-direction
 * ("asc"/"desc") shows that arrow up front, for a table the server already
 * delivered in that order. Rows that tie keep their original page order.
 */
(function () {
    function cellValue(row, index, numeric) {
        var cell = row.children[index];
        if (!cell) {
            return numeric ? 0 : '';
        }
        var raw = 'sortValue' in cell.dataset
            ? String(cell.dataset.sortValue)
            : cell.textContent.trim().toLowerCase();
        return numeric ? (parseFloat(raw) || 0) : raw;
    }

    function sortTable(table, columnIndex, direction, numeric) {
        var tbody = table.tBodies[0];
        if (!tbody) {
            return;
        }

        var rows = Array.prototype.slice.call(tbody.rows);
        var admins = rows.filter(function (row) { return row.dataset.adminGroup === '1'; });
        var others = rows.filter(function (row) { return row.dataset.adminGroup !== '1'; });

        [admins, others].forEach(function (group) {
            group.sort(function (a, b) {
                var textA = cellValue(a, columnIndex, numeric);
                var textB = cellValue(b, columnIndex, numeric);
                if (textA === textB) {
                    return Number(a.dataset.originalIndex) - Number(b.dataset.originalIndex);
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

        Array.prototype.forEach.call(table.tBodies[0] ? table.tBodies[0].rows : [], function (row, position) {
            row.dataset.originalIndex = position;
        });

        Array.prototype.forEach.call(headerRow.cells, function (th, columnIndex) {
            if (!('sort' in th.dataset)) {
                return;
            }
            var numeric = th.dataset.sort === 'number';

            th.classList.add('sortable-header');
            th.setAttribute('role', 'button');
            th.setAttribute('tabindex', '0');

            var indicator = document.createElement('span');
            indicator.className = 'sort-indicator';
            th.appendChild(indicator);
            if (th.dataset.sortDirection) {
                indicator.textContent = th.dataset.sortDirection === 'asc' ? '▲' : '▼';
            }

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

                sortTable(table, columnIndex, direction, numeric);
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
