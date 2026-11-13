// Knop "Download XML" naast "Download Excel": volgt de Excel-knop (aan zodra er gegevens zijn) en downloadt hetzelfde nummer als XML.
(function () {
    var box = document.getElementById('rpActions');
    var excel = document.getElementById('rpDownload');
    var xml = document.getElementById('rpXml');
    var fetchBtn = document.getElementById('rpFetch');
    var input = document.getElementById('rpNumber');
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

    xml.addEventListener('click', function () {
        if (!asked) { onthoud(); }
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = box.getAttribute('data-xml');
        [['csrf', box.getAttribute('data-csrf')], ['nummer', asked]].forEach(function (p) {
            var f = document.createElement('input');
            f.type = 'hidden';
            f.name = p[0];
            f.value = p[1];
            form.appendChild(f);
        });
        document.body.appendChild(form);
        GeeveProgress.download(form, 'XML maken…');
        form.submit();
        setTimeout(function () { if (form.parentNode) { form.parentNode.removeChild(form); } }, 2000);
    });
})();
