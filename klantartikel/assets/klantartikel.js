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

    function applyFilter() {
        var fi = filterItem.value.trim().toLowerCase();
        var fc = filterCode.value.trim().toLowerCase();
        Array.prototype.forEach.call(tbody.rows, function (row) {
            var item = row.querySelector('.ka-item').value.trim().toLowerCase();
            var code = row.querySelector('.ka-code').value.trim().toLowerCase();
            // Lege (net toegevoegde) regels blijven zichtbaar zodat ze invulbaar blijven.
            var blank = item === '' && code === '';
            row.hidden = !blank && ((fi !== '' && item.indexOf(fi) === -1) || (fc !== '' && code.indexOf(fc) === -1));
        });
    }
    filterItem.addEventListener('input', applyFilter);
    filterCode.addEventListener('input', applyFilter);

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
        applyFilter();
        tr.querySelector('.ka-item').focus();
    }

    document.getElementById('kaAdd').addEventListener('click', addRow);

    tbody.addEventListener('input', function (e) {
        if (e.target.classList.contains('ka-item')) { schedule(e.target.closest('tr')); }
    });
    tbody.addEventListener('click', function (e) {
        if (e.target.classList.contains('ka-remove')) {
            e.target.closest('tr').remove();
            updateCount();
        }
    });

    form.addEventListener('submit', function (e) {
        var problems = [];
        Array.prototype.forEach.call(tbody.rows, function (row) {
            var item = row.querySelector('.ka-item').value.trim();
            var code = row.querySelector('.ka-code').value.trim();
            if (item === '' && code === '') { return; }
            if (item === '' || code === '') { problems.push('Regel met onvolledige invoer (' + (item || code) + ')'); }
            else if (row.classList.contains('is-missing') || row.classList.contains('is-checking')) { problems.push('Artikel ' + item + ' is niet gevonden/gecontroleerd in Exact'); }
        });
        if (problems.length) {
            e.preventDefault();
            message.textContent = 'Export niet mogelijk: ' + problems.slice(0, 5).join('; ') + (problems.length > 5 ? ' (+' + (problems.length - 5) + ' meer)' : '') + '.';
            message.hidden = false;
        } else {
            message.hidden = true;
        }
    });
})();
