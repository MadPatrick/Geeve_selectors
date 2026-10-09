(function () {
    var form = document.getElementById('kaForm');
    if (!form) { return; }
    var tbody = form.querySelector('#kaTable tbody');
    var message = document.getElementById('kaMessage');
    var count = document.getElementById('kaCount');
    var lookupUrl = form.getAttribute('data-lookup');
    var timers = new WeakMap();

    var filterItem = document.getElementById('kaFilterItem');
    var filterCode = document.getElementById('kaFilterCode');

    var PAGE_SIZE = 50;
    var page = 1;
    var pager = document.getElementById('kaPager');
    var pageInfo = document.getElementById('kaPageInfo');

    // Filter + paginering: alle regels blijven in het formulier (en dus in de export), alleen de zichtbaarheid wisselt.
    function applyFilter(resetPage) {
        if (resetPage === true) { page = 1; }
        var fi = filterItem.value.trim().toLowerCase();
        var fc = filterCode.value.trim().toLowerCase();
        var matched = [];
        Array.prototype.forEach.call(tbody.rows, function (row) {
            var item = row.querySelector('.ka-item').value.trim().toLowerCase();
            var code = row.querySelector('.ka-code').value.trim().toLowerCase();
            // Lege (net toegevoegde) regels blijven zichtbaar zodat ze invulbaar blijven.
            var blank = item === '' && code === '';
            var ok = blank || ((fi === '' || item.indexOf(fi) !== -1) && (fc === '' || code.indexOf(fc) !== -1));
            row.hidden = true;
            if (ok) { matched.push(row); }
        });
        var pages = Math.max(1, Math.ceil(matched.length / PAGE_SIZE));
        page = Math.min(Math.max(page, 1), pages);
        matched.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE).forEach(function (row) { row.hidden = false; });
        pager.hidden = pages <= 1;
        pageInfo.textContent = 'Pagina ' + page + ' van ' + pages + ' (' + matched.length + ' regels)';
        document.getElementById('kaPrev').disabled = page <= 1;
        document.getElementById('kaNext').disabled = page >= pages;
    }
    document.getElementById('kaPrev').addEventListener('click', function () { page--; applyFilter(); });
    document.getElementById('kaNext').addEventListener('click', function () { page++; applyFilter(); });

    filterItem.addEventListener('input', function () { applyFilter(true); });
    filterCode.addEventListener('input', function () { applyFilter(true); });

    function updateCount() { count.textContent = '(' + tbody.rows.length + ')'; }

    function setState(row, state, description) {
        row.classList.remove('is-found', 'is-missing', 'is-checking');
        if (state) { row.classList.add(state); }
        row.querySelector('.ka-desc').textContent = description || (state === 'is-missing' ? 'Niet gevonden in Exact' : '');
    }

    function lookup(row) {
        var input = row.querySelector('.ka-item');
        var code = input.value.trim();
        if (code === '') { setState(row, '', ''); return; }
        setState(row, 'is-checking', 'Controleren in Exact…');
        fetch(lookupUrl + '?code=' + encodeURIComponent(code))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (input.value.trim() !== code) { return; } // intussen gewijzigd
                if (data.found) {
                    // Gevonden artikelnummer wordt vastgezet (niet meer wijzigbaar).
                    input.value = data.code;
                    var label = document.createElement('span');
                    label.className = 'ka-itemtext';
                    label.textContent = data.code;
                    input.type = 'hidden';
                    input.parentNode.insertBefore(label, input);
                    setState(row, 'is-found', data.description);
                    row.querySelector('.ka-code').focus();
                } else {
                    setState(row, 'is-missing', data.error ? 'Controle mislukt: ' + data.error : 'Niet gevonden in Exact');
                }
            })
            .catch(function () { setState(row, 'is-missing', 'Controle mislukt'); });
    }

    function schedule(row) {
        clearTimeout(timers.get(row));
        timers.set(row, setTimeout(function () { lookup(row); }, 400));
    }

    function addRow() {
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="ka-itemcell"><input type="text" name="artikel[]" class="ka-input ka-item" placeholder="Artikelnummer" autocomplete="off"></td>' +
            '<td class="ka-desc"></td>' +
            '<td><input type="text" name="klantartikel[]" class="ka-input ka-code" autocomplete="off"></td>' +
            '<td><button type="button" class="ka-remove" title="Regel verwijderen" aria-label="Regel verwijderen">&times;</button></td>';
        tbody.insertBefore(tr, tbody.firstChild);
        updateCount();
        applyFilter(true);
        tr.querySelector('.ka-item').focus();
    }

    applyFilter(true);
    document.getElementById('kaAdd').addEventListener('click', addRow);

    tbody.addEventListener('input', function (e) {
        if (e.target.classList.contains('ka-item')) { schedule(e.target.closest('tr')); }
    });
    tbody.addEventListener('click', function (e) {
        if (e.target.classList.contains('ka-remove')) {
            e.target.closest('tr').remove();
            updateCount();
            applyFilter();
        }
    });

    // Alleen nieuwe regels (geen data-orig) en regels met een gewijzigd klantartikelnummer gaan mee in de XML.
    function isChanged(row) {
        var code = row.querySelector('.ka-code').value.trim();
        return !row.hasAttribute('data-orig') || code !== row.getAttribute('data-orig');
    }

    form.addEventListener('submit', function (e) {
        var problems = [];
        var changed = 0;
        Array.prototype.forEach.call(tbody.rows, function (row) {
            var item = row.querySelector('.ka-item').value.trim();
            var code = row.querySelector('.ka-code').value.trim();
            if (item === '' && code === '') { return; }
            if (!isChanged(row)) { return; }
            changed++;
            if (item === '' || code === '') { problems.push('Regel met onvolledige invoer (' + (item || code) + ')'); }
            else if (row.classList.contains('is-missing') || row.classList.contains('is-checking')) { problems.push('Artikel ' + item + ' is niet gevonden/gecontroleerd in Exact'); }
        });
        if (problems.length || changed === 0) {
            e.preventDefault();
            message.textContent = problems.length
                ? 'Export niet mogelijk: ' + problems.slice(0, 5).join('; ') + (problems.length > 5 ? ' (+' + (problems.length - 5) + ' meer)' : '') + '.'
                : 'Er zijn geen gewijzigde of nieuwe regels om te exporteren.';
            message.hidden = false;
            return;
        }
        message.hidden = true;
        // Ongewijzigde regels uitzetten zodat ze niet worden meegestuurd (beide velden, zodat de volgorde klopt).
        var disabled = [];
        Array.prototype.forEach.call(tbody.rows, function (row) {
            if (!isChanged(row)) {
                Array.prototype.forEach.call(row.querySelectorAll('input'), function (i) { i.disabled = true; disabled.push(i); });
            }
        });
        setTimeout(function () { disabled.forEach(function (i) { i.disabled = false; }); }, 0);
    });
})();
