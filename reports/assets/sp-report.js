(function () {
    var box = document.getElementById('rpActions');
    var input = document.getElementById('rpNumber');
    var fetchBtn = document.getElementById('rpFetch');
    var downloadBtn = document.getElementById('rpDownload');
    var status = document.getElementById('rpStatus');
    var message = document.getElementById('rpMessage');
    var result = document.getElementById('rpResult');
    var summary = document.getElementById('rpSummary');
    var table = document.getElementById('rpTable');
    var data = null;
    var asked = '';
    var ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function nl(n) { return Number(n).toLocaleString('nl-NL', { minimumFractionDigits: 0, maximumFractionDigits: 4 }); }
    function isEuro(i) { return (data.currency || []).indexOf(data.columns[i]) !== -1; }
    function euro(n) { return '€ ' + Number(n).toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function dmy(iso) { return iso.split('-').reverse().join('-'); }
    function fail(text) { message.textContent = text; message.hidden = false; }

    function render() {
        summary.innerHTML =
            '<span class="msp-pill">' + esc(box.getAttribute('data-label')) + ' <strong>' + esc(asked) + '</strong></span>' +
            (data.kop ? '<span class="msp-pill"><strong>' + esc(data.kop) + '</strong></span>' : '') +
            '<span class="msp-pill"><strong>' + nl(data.rows.length) + '</strong> regels</span>';
        var html = '<thead><tr>' + data.columns.map(function (c) { return '<th>' + esc(c.trim()) + '</th>'; }).join('') + '</tr></thead><tbody>';
        data.rows.forEach(function (r) {
            html += '<tr>' + r.map(function (v, i) {
                if (v === null) { return '<td></td>'; }
                if (isEuro(i) && typeof v === 'number') { return '<td class="num">' + euro(v) + '</td>'; }
                if (data.numeric[i] && typeof v === 'number') { return '<td class="num">' + nl(v) + '</td>'; }
                if (typeof v === 'string' && ISO_DATE.test(v)) { return '<td>' + dmy(v) + '</td>'; }
                return '<td>' + esc(v) + '</td>';
            }).join('') + '</tr>';
        });
        table.innerHTML = html + '</tbody>';
        result.hidden = false;
    }

    function fetchData() {
        message.hidden = true;
        var number = input.value.trim();
        if (!/^\d{1,18}$/.test(number)) { fail('Vul een geldig nummer in (alleen cijfers).'); return; }
        fetchBtn.disabled = true;
        downloadBtn.disabled = true;
        status.textContent = 'Bezig met ophalen…';
        var body = new URLSearchParams();
        body.set('csrf', box.getAttribute('data-csrf'));
        body.set('nummer', number);
        fetch(box.getAttribute('data-endpoint'), { method: 'POST', body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                if (!json.ok) { throw new Error(json.error || 'Ophalen mislukt.'); }
                data = json;
                asked = number;
                render();
                downloadBtn.disabled = data.rows.length === 0;
                status.textContent = data.rows.length === 0
                    ? 'Geen regels gevonden voor ' + number + '.'
                    : 'Opgehaald om ' + new Date().toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' }) + '.';
            })
            .catch(function (err) {
                status.textContent = '';
                result.hidden = true;
                fail(err.message);
            })
            .then(function () { fetchBtn.disabled = false; });
    }

    fetchBtn.addEventListener('click', fetchData);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); fetchData(); } });

    downloadBtn.addEventListener('click', function () {
        if (!data || typeof XLSX === 'undefined') { fail('Excel-bibliotheek niet geladen.'); return; }
        var aoa = [data.columns];
        data.rows.forEach(function (r) { aoa.push(r.map(function (v) { return v === null ? '' : v; })); });
        var ws = XLSX.utils.aoa_to_sheet(aoa);
        // Datumkolommen (ISO-tekst) als echte Excel-datum.
        var range = XLSX.utils.decode_range(ws['!ref']);
        for (var c = range.s.c; c <= range.e.c; c++) {
            for (var r = 1; r <= range.e.r; r++) {
                var addr = XLSX.utils.encode_cell({ r: r, c: c });
                var cell = ws[addr];
                if (cell && cell.t === 'n' && isEuro(c)) { cell.z = '"€" #,##0.00'; }
                if (cell && cell.t === 's' && ISO_DATE.test(cell.v)) {
                    var p = cell.v.split('-');
                    ws[addr] = { t: 'n', v: Math.round(Date.UTC(+p[0], +p[1] - 1, +p[2]) / 86400000) + 25569, z: 'dd-mm-yyyy' };
                }
            }
        }
        ws['!cols'] = data.columns.map(function (name, c) {
            var w = name.length + 2;
            data.rows.slice(0, 200).forEach(function (row) {
                if (row[c] !== null) { w = Math.max(w, Math.min(String(row[c]).length + 2, 50)); }
            });
            return { wch: w };
        });
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, box.getAttribute('data-sheet'));
        XLSX.writeFile(wb, box.getAttribute('data-file') + '_' + asked + '.xlsx');
    });
})();
