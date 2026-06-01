/**
 * LinkDigest All-Links page — client-side table sorting.
 *
 * Makes table headers clickable to sort columns by text or custom data-sortVal attribute.
 *
 * @since 1.0.0
 */
(function() {
    // Make table headers sortable
    document.querySelectorAll('th[data-col]').forEach(function(th) {
        th.addEventListener('click', function(e) {
            e.preventDefault();
            var table = th.closest('table');
            var col   = parseInt(th.dataset.col, 10);
            var asc   = !(th.classList.contains('sorted') && th.classList.contains('asc'));

            table.querySelectorAll('th[data-col]').forEach(function(h) {
                h.classList.remove('sorted', 'asc', 'desc');
                h.classList.add('sortable', 'desc');
            });

            th.classList.remove('sortable');
            th.classList.add('sorted', asc ? 'asc' : 'desc');

            var tbody = table.querySelector('tbody');
            var rows  = Array.from(tbody.querySelectorAll('tr'));

            rows.sort(function(a, b) {
                var cellA = a.cells[col];
                var cellB = b.cells[col];
                var valA  = (cellA.dataset.sortVal !== undefined) ? cellA.dataset.sortVal : cellA.textContent.trim().toLowerCase();
                var valB  = (cellB.dataset.sortVal !== undefined) ? cellB.dataset.sortVal : cellB.textContent.trim().toLowerCase();

                if (valA === '-' && valB !== '-') return 1;
                if (valB === '-' && valA !== '-') return -1;

                if (valA < valB) return asc ? -1 : 1;
                if (valA > valB) return asc ? 1 : -1;
                return 0;
            });

            rows.forEach(function(row) { tbody.appendChild(row); });
        });
    });
})();
