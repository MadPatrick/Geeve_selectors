(function () {
    var box = document.getElementById('slActions');
    var input = document.getElementById('slNumber');
    var fetchBtn = document.getElementById('slFetch');
    var status = document.getElementById('slStatus');
    var message = document.getElementById('slMessage');
    var result = document.getElementById('slResult');
    var tbody = document.querySelector('#slTable tbody');
    var all = document.getElementById('slAll');
    var sheet = document.getElementById('slSheet');
    var printSheet = document.getElementById('slPrintSheet');
    var printBtn = document.getElementById('slPrint');
    var printInfo = document.getElementById('slPrintInfo');
    var fields = document.querySelectorAll('#slFields [data-field]');
    var withDate = document.getElementById('slWithDate'); // met of zonder datum op het label (geldt voor alle labels)
    var FIELDS = ['slangnummer', 'referentie', 'lengte', 'datum', 'klantnaam', 'aantal'];
    var rows = [];
    var current = -1;  // aangeklikte regel; -1 = handmatig ingevuld (zonder order)
    var manual = blank();

    function blank() {
        var d = new Date();
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        return { slangnummer: '', referentie: '', lengte: '', datum: pad(d.getDate()) + '-' + pad(d.getMonth() + 1) + '-' + d.getFullYear(), klantnaam: '', aantal: '1', artnr_kl: '' };
    }
    // Het datumveld is een kalenderkeuze (waarde jjjj-mm-dd); op het label en in de tabel staat dd-mm-jjjj.
    function naarIso(d) {
        var m = /^(\d{1,2})-(\d{1,2})-(\d{4})$/.exec(String(d).trim());
        return m ? m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2) : '';
    }
    function vanIso(v) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v);
        return m ? m[3] + '-' + m[2] + '-' + m[1] : '';
    }
    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fail(text) { message.textContent = text; message.hidden = false; }
    function count(r) {
        var n = parseInt(r.aantal, 10);
        return isNaN(n) || n < 1 ? 1 : Math.min(n, 99);
    }
    // Lengte zoals in de oude app: "1830 MM" (alleen een getal krijgt " MM" erachter).
    function lengte(v) { v = String(v).trim(); return /^\d+([.,]\d+)?$/.test(v) ? v + ' MM' : v; }

    function label(r) {
        return '<div class="slanglabel"><div class="area">' +
            '<div class="f nr">' + esc(r.slangnummer) + '</div>' +
            '<div class="f ref">' + esc(r.referentie) + '</div>' +
            '<div class="f len">' + esc(lengte(r.lengte)) + '</div>' +
            (withDate.checked ? '<div class="f dat">' + esc(r.datum) + '</div>' : '') +
            '<div class="f kl">' + esc(r.klantnaam) + '</div>' +
            '</div></div>';
    }

    // Tekst die niet past wordt kleiner gezet (in stapjes), zodat niets wordt afgeknipt.
    function fit(container) {
        Array.prototype.forEach.call(container.querySelectorAll('.f'), function (el) {
            var size = parseFloat(getComputedStyle(el).fontSize);
            var min = size * 0.4;
            while ((el.scrollWidth > el.clientWidth || el.scrollHeight > el.clientHeight) && size > min) {
                size *= 0.95;
                el.style.fontSize = size + 'px';
            }
        });
    }

    function active() { return current >= 0 ? rows[current] : manual; }

    function checked() {
        return rows.filter(function (r, i) {
            var cb = tbody.querySelector('input[data-i="' + i + '"]');
            return cb && cb.checked;
        });
    }
    function toPrint() {
        var list = checked();
        return list.length > 0 ? list : [active()];
    }

    function preview() {
        sheet.innerHTML = label(active());
        fit(sheet);
        var list = toPrint();
        var n = list.reduce(function (s, r) { return s + count(r); }, 0);
        printInfo.textContent = n + (n === 1 ? ' label' : ' labels');
        var c = checked().length;
        all.checked = rows.length > 0 && c === rows.length;
        all.indeterminate = c > 0 && c < rows.length;
    }

    function fill() {
        var r = active();
        Array.prototype.forEach.call(fields, function (el) {
            var v = r[el.getAttribute('data-field')];
            el.value = el.type === 'date' ? naarIso(v) : v;
        });
        preview();
    }

    function rowHtml(r, i) {
        return '<td><input type="checkbox" data-i="' + i + '" checked aria-label="' + esc(r.slangnummer) + '"></td>' +
            '<td>' + esc(r.slangnummer) + '</td><td>' + esc(r.referentie) + '</td><td>' + esc(r.datum) + '</td>' +
            '<td class="ka-desc">' + esc(r.klantnaam) + '</td><td class="num">' + esc(r.aantal) + '</td>' +
            '<td>' + esc(lengte(r.lengte)) + '</td><td>' + esc(r.artnr_kl) + '</td>';
    }

    function select(i) {
        current = i;
        Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (tr, n) { tr.classList.toggle('is-current', n === i); });
        fill();
    }

    // Aangepaste velden gelden voor de aangeklikte regel (of het handmatige label); de tabel loopt mee.
    Array.prototype.forEach.call(fields, function (el) {
        el.addEventListener('input', function () {
            var r = active();
            r[el.getAttribute('data-field')] = el.type === 'date' ? vanIso(el.value) : el.value;
            if (current >= 0) {
                var cb = tbody.querySelector('input[data-i="' + current + '"]');
                var on = cb && cb.checked;
                var tr = tbody.querySelectorAll('tr')[current];
                tr.innerHTML = rowHtml(r, current);
                tr.querySelector('input').checked = on;
            }
            preview();
        });
    });

    function load() {
        var nummer = input.value.trim();
        message.hidden = true;
        if (nummer === '') { fail('Vul een ordernummer in.'); input.focus(); return; }
        fetchBtn.disabled = true;
        status.textContent = 'Bezig met ophalen…';
        var body = new FormData();
        body.append('csrf', box.getAttribute('data-csrf'));
        body.append('nummer', nummer);
        fetch(box.getAttribute('data-endpoint'), { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json.ok) { throw new Error(json.error || 'Onbekende fout.'); }
                rows = json.rows || [];
                tbody.innerHTML = rows.map(function (r, i) { return '<tr data-row="' + i + '">' + rowHtml(r, i) + '</tr>'; }).join('');
                result.hidden = rows.length === 0;
                if (rows.length === 0) { fail('Geen slangen gevonden voor order ' + nummer + '.'); select(-1); return; }
                select(0);
            })
            .catch(function (e) { fail(e.message || String(e)); })
            .then(function () { fetchBtn.disabled = false; status.textContent = ''; });
    }

    fetchBtn.addEventListener('click', load);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { load(); } });
    tbody.addEventListener('click', function (e) {
        var tr = e.target.closest('tr[data-row]');
        if (!tr || e.target.matches('input[type="checkbox"]')) { return; }
        select(parseInt(tr.getAttribute('data-row'), 10));
    });
    tbody.addEventListener('change', preview);
    withDate.addEventListener('change', function () {
        document.querySelector('[data-field="datum"]').disabled = !withDate.checked;
        preview();
    });
    all.addEventListener('change', function () {
        Array.prototype.forEach.call(tbody.querySelectorAll('input[type="checkbox"]'), function (cb) { cb.checked = all.checked; });
        preview();
    });

    printBtn.addEventListener('click', function () {
        var html = '';
        toPrint().forEach(function (r) { for (var n = 0; n < count(r); n++) { html += label(r); } });
        printSheet.innerHTML = html;
        printSheet.style.display = 'block'; // even zichtbaar voor fit(), daarna weer alleen bij het printen
        fit(printSheet);
        printSheet.style.display = '';
        window.print();
    });

    fill();
}());
