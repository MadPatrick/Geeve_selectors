// Knop "Download XML" naast "Download Excel": volgt de Excel-knop (aan zodra er gegevens zijn) en downloadt hetzelfde nummer als XML.
// Het bestand wordt met fetch opgehaald: bij een fout blijft de pagina (en de opgehaalde gegevens) staan en komt de melding in beeld.
(function () {
    var box = document.getElementById('rpActions');
    var excel = document.getElementById('rpDownload');
    var xml = document.getElementById('rpXml');
    var fetchBtn = document.getElementById('rpFetch');
    var input = document.getElementById('rpNumber');
    var message = document.getElementById('rpMessage');
    if (!box || !excel || !xml || !fetchBtn || !input) { return; }
    var asked = '';   // nummer waarvoor de gegevens zijn opgehaald

    function volg() { xml.disabled = excel.disabled; }
    if (typeof MutationObserver !== 'undefined') {
        new MutationObserver(volg).observe(excel, { attributes: true, attributeFilter: ['disabled'] });
    }
    volg();

    function onthoud() { asked = input.value.replace(/\s+/g, ''); }
    fetchBtn.addEventListener('click', onthoud);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { onthoud(); } });

    function melding(tekst) {
        if (!message) { return; }
        message.textContent = tekst;
        message.hidden = !tekst;
    }

    function bestandsnaam(res) {
        var m = /filename="?([^";]+)"?/i.exec(res.headers.get('Content-Disposition') || '');
        return m ? m[1] : 'Order_' + asked + '.xml';
    }

    xml.addEventListener('click', function () {
        if (!asked) { onthoud(); }
        var body = new FormData();
        body.append('csrf', box.getAttribute('data-csrf'));
        body.append('nummer', asked);
        melding('');
        GeeveProgress.run('XML maken…', function () {
            return fetch(box.getAttribute('data-xml'), { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (res) {
                    if (!res.ok) {
                        return res.text().then(function (t) { throw new Error(t || 'XML maken mislukt.'); });
                    }
                    var naam = bestandsnaam(res);
                    return res.blob().then(function (blob) {
                        if (window.navigator && window.navigator.msSaveOrOpenBlob) {
                            window.navigator.msSaveOrOpenBlob(blob, naam);
                            return;
                        }
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = naam;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        setTimeout(function () { window.URL.revokeObjectURL(url); }, 4000);
                    });
                })
                .catch(function (e) { melding(e.message || String(e)); throw e; });
        }, { button: xml, doneText: 'Klaar', errorText: 'XML maken mislukt' });
    });
})();
