/*
 * Gedeelde voortgangsbalk (GeeveProgress) voor knoppen die een export, download, import of "Naar Exact" starten.
 * Geladen door shared/head.php; CSS staat in shared/style.css (.geeve-progress), kleuren in tokens.css (--progress-*).
 * Bewust ES5 (geen pijlfuncties, geen Promise-vereiste) zodat het overal werkt waar de rest van de portal werkt.
 *
 *   // Werk in de browser (Excel/XML maken): balk eerst laten verschijnen, dan het werk doen.
 *   GeeveProgress.run('Excel maken…', function (p) { ...; p.set(3, 10); ... }, { button: btn });
 *   // Werk dat een belofte (Promise/thenable) teruggeeft, bijv. fetch: balk loopt tot de belofte klaar is.
 *   GeeveProgress.run('Naar Exact schrijven…', function (p) { return fetch(...); }, { button: btn });
 *   // Knop met een bestaande klik-functie: GeeveProgress.onClick(btn, 'Excel maken…', functie);
 *   // Download via een formulier (POST): balk loopt tot de server het bestand begint te sturen (zie shared/progress.php).
 *   GeeveProgress.download(form, 'XML maken…');
 *
 * Een knop in {button: btn} blijft zelf ongemoeid (zijn eigen disabled-logica blijft werken); een tweede klik tijdens de
 * balk wordt genegeerd.
 *
 * Heeft de aanroep een knop ({button: btn}, onClick, start(label, btn), download), dan staat de voortgang IN de knop
 * (vulling + percentage of "Bezig…"; de tekst staat als tooltip); zonder knop is het een zwevende balk onderaan.
 *
 * Handle p: set(done, total[, label]) = bepaald, indeterminate([label]), label(text), finish([text]), fail(text).
 * Weet je het aantal stappen niet, doe dan niets: de balk is dan een lopende (onbepaalde) balk.
 */
(function (w, d) {
    'use strict';

    var host = null;

    function getHost() {
        if (!host || !host.parentNode) {
            host = d.createElement('div');
            host.className = 'geeve-progress-host';
            d.body.appendChild(host);
        }
        return host;
    }

    function el(tag, cls, text) {
        var e = d.createElement(tag);
        e.className = cls;
        if (text) { e.appendChild(d.createTextNode(text)); }
        return e;
    }

    function setText(node, text) {
        if (node.firstChild) { node.firstChild.nodeValue = text; } else { node.appendChild(d.createTextNode(text)); }
    }

    // Met een knop: de voortgang staat in de knop zelf (vulling + "Bezig…"/percentage); zonder knop een zwevende balk onderaan.
    function Handle(label, btn) {
        var self = this;
        this.closed = false;
        this.timer = null;
        this.btn = btn || null;
        if (this.btn) {
            this.box = el('span', 'geeve-btn-progress is-indeterminate');
            this.box.setAttribute('role', 'progressbar');
            this.box.setAttribute('aria-valuemin', '0');
            this.box.setAttribute('aria-valuemax', '100');
            this.fill = el('span', 'geeve-btn-progress-fill');
            this.pct = el('span', 'geeve-btn-progress-text', 'Bezig…');
            this.text = null;
            this.box.appendChild(this.fill);
            this.box.appendChild(this.pct);
            this.oldMin = this.btn.style.minWidth;
            this.btn.style.minWidth = this.btn.offsetWidth + 'px';   // de knop verandert niet van breedte
            this.btn.className += ' geeve-busy';
            this.btn.appendChild(this.box);
            this.btn.title = label || '';
            return;
        }
        this.box = el('div', 'geeve-progress is-indeterminate');
        this.box.setAttribute('role', 'progressbar');
        this.box.setAttribute('aria-valuemin', '0');
        this.box.setAttribute('aria-valuemax', '100');
        this.box.setAttribute('aria-live', 'polite');
        var top = el('div', 'geeve-progress-top');
        this.text = el('span', 'geeve-progress-label', label || '');
        this.pct = el('span', 'geeve-progress-pct');
        top.appendChild(this.text);
        top.appendChild(this.pct);
        var track = el('div', 'geeve-progress-track');
        this.fill = el('div', 'geeve-progress-fill');
        track.appendChild(this.fill);
        this.box.appendChild(top);
        this.box.appendChild(track);
        this.box.onclick = function () { self.close(); };
        getHost().appendChild(this.box);
    }

    Handle.prototype.label = function (text) {
        if (this.btn) { this.btn.title = text; if (!/%$/.test(this.pct.firstChild.nodeValue)) { setText(this.pct, text); } return; }
        setText(this.text, text);
    };

    Handle.prototype.indeterminate = function (text) {
        this.box.className = (this.btn ? 'geeve-btn-progress' : 'geeve-progress') + ' is-indeterminate';
        this.fill.style.width = '';
        this.box.removeAttribute('aria-valuenow');
        setText(this.pct, this.btn ? 'Bezig…' : '');
        if (text && !this.btn) { this.label(text); }
    };

    Handle.prototype.set = function (done, total, text) {
        var pct = total > 0 ? Math.max(0, Math.min(100, Math.round(done * 100 / total))) : 0;
        this.box.className = this.btn ? 'geeve-btn-progress' : 'geeve-progress';
        this.fill.style.width = pct + '%';
        this.box.setAttribute('aria-valuenow', String(pct));
        setText(this.pct, pct + '%');
        if (text) { if (this.btn) { this.btn.title = text; } else { this.label(text); } }
    };

    Handle.prototype.close = function () {
        if (this.closed) { return; }
        this.closed = true;
        clearTimeout(this.timer);
        if (this.box.parentNode) { this.box.parentNode.removeChild(this.box); }
        if (this.btn) {
            this.btn.className = this.btn.className.replace(/\s*\bgeeve-busy\b/, '');
            this.btn.style.minWidth = this.oldMin;
            this.btn.removeAttribute('title');
        }
    };

    Handle.prototype.finish = function (text) {
        this.set(1, 1, text || 'Klaar');
        var self = this;
        this.timer = setTimeout(function () { self.close(); }, this.btn ? 700 : 1500);
    };

    Handle.prototype.fail = function (text) {
        this.box.className = (this.btn ? 'geeve-btn-progress' : 'geeve-progress') + ' is-error';
        this.fill.style.width = '100%';
        if (this.btn) { setText(this.pct, 'Mislukt'); this.btn.title = text || 'Mislukt'; } else { this.label(text || 'Mislukt'); }
        var self = this;
        this.timer = setTimeout(function () { self.close(); }, this.btn ? 3000 : 6000);
    };

    var MIN_VISIBLE = 400;   // ms: ook een snelle export laat de balk even zien
    var PAINT_DELAY = 40;    // ms: de browser moet de balk tekenen vóór zwaar synchroon werk (XLSX.writeFile) begint

    var api = {
        start: function (label, btn) { return new Handle(label, btn); },

        run: function (label, work, opts) {
            opts = opts || {};
            var btn = opts.button || null;
            if (btn && btn.geeveBusy) { return null; }   // dubbelklik: er loopt al een balk voor deze knop
            var p = new Handle(label, btn);
            var t0 = new Date().getTime();
            if (btn) { btn.geeveBusy = true; btn.setAttribute('aria-busy', 'true'); }

            function end(fn) {
                var wait = Math.max(0, MIN_VISIBLE - (new Date().getTime() - t0));
                setTimeout(function () {
                    if (btn) { btn.geeveBusy = false; btn.removeAttribute('aria-busy'); }
                    fn();
                }, wait);
            }

            setTimeout(function () {
                var result;
                try {
                    result = work(p);
                } catch (e) {
                    end(function () { p.fail(opts.errorText || 'Mislukt: ' + (e && e.message ? e.message : e)); });
                    return;
                }
                if (result && typeof result.then === 'function') {
                    result.then(function () {
                        end(function () { p.finish(opts.doneText); });
                    }, function (e) {
                        end(function () { p.fail(opts.errorText || 'Mislukt: ' + (e && e.message ? e.message : e)); });
                    });
                } else {
                    end(function () { p.finish(opts.doneText); });
                }
            }, PAINT_DELAY);
            return p;
        },

        // Kortste route voor een knop: btn.addEventListener('click', f) wordt GeeveProgress.onClick(btn, 'Excel maken…', f).
        // f(event, p) mag p gebruiken, een belofte teruggeven (balk loopt tot die klaar is) of false (niets gedaan, balk weg).
        onClick: function (btn, label, handler, opts) {
            var o = opts || {};
            o.button = btn;
            btn.addEventListener('click', function (e) {
                var p = api.run(label, function (h) {
                    var r = handler.call(btn, e, h);
                    if (r === false) { h.close(); }
                    return r;
                }, o);
                return p;
            });
        },

        // Download via een formulier (POST dat een bestand teruggeeft): de pagina laadt niet opnieuw, dus de server
        // zet een cookie (geeveProgressDownloadDone() in shared/progress.php) zodra het bestand begint; daarop wacht de balk.
        download: function (form, label) {
            var token = String(new Date().getTime()) + Math.floor(Math.random() * 1000);
            var input = form.querySelector('input[name="geeve_dl"]');
            if (!input) {
                input = d.createElement('input');
                input.type = 'hidden';
                input.name = 'geeve_dl';
                form.appendChild(input);
            }
            input.value = token;
            var p = new Handle(label || 'Bestand maken…', form.querySelector('button[type="submit"]'));
            var tries = 0;
            var poll = setInterval(function () {
                tries++;
                var seen = (' ' + d.cookie.replace(/;\s*/g, ' ') + ' ').indexOf(' geeve_dl=' + token + ' ') !== -1;
                if (seen || tries > 400) {   // 400 x 300 ms = 2 minuten, daarna sluiten we de balk zonder bevestiging
                    clearInterval(poll);
                    d.cookie = 'geeve_dl=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
                    p.finish(seen ? 'Download gestart' : 'Klaar');
                }
            }, 300);
            return p;
        }
    };

    w.GeeveProgress = api;
}(window, document));
