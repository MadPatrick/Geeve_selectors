/* Artikelsticker (zie art-sticker.css): HTML, barcode en passend maken. Gedeeld door stickers/artikel en stickers/order. */
window.ArtSticker = (function () {
    var MODULE_MM = 0.38; // breedte van één streepje in de barcode
    var MAX_BC_MM = 94;   // past binnen de 102 mm met 4 mm marge

    var MAX_BC_70X25_MM = 65; // past binnen de 70 mm met 2,5 mm marge

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function html(omschrijving, artikel, klein) {
        return '<div class="sticker sticker--art' + (klein ? ' sticker--70x25' : '') + '">' +
            '<div class="f desc">' + esc(omschrijving) + '</div>' +
            '<div class="bc"><svg data-art-code="' + esc(artikel) + '"></svg></div>' +
            '<div class="f art">' + esc(artikel) + '</div>' +
            '</div>';
    }

    // Code 128 van het artikelnummer; breedte volgt het aantal streepjes (max. de breedte van de sticker).
    function barcodes(container) {
        Array.prototype.forEach.call(container.querySelectorAll('svg[data-art-code]'), function (svg) {
            var code = svg.getAttribute('data-art-code');
            if (code === '') { return; }
            try {
                JsBarcode(svg, code, { format: 'CODE128', displayValue: false, margin: 0, width: 1, height: 100 });
                var modules = parseFloat(svg.getAttribute('width')) || 0;
                svg.setAttribute('viewBox', '0 0 ' + modules + ' 100');
                svg.setAttribute('preserveAspectRatio', 'none');
                svg.removeAttribute('style');
                svg.removeAttribute('height');
                svg.style.width = Math.min(modules * MODULE_MM, svg.closest('.sticker--70x25') ? MAX_BC_70X25_MM : MAX_BC_MM) + 'mm'; // inline, wint van .sticker .bc svg (100%) bij de orderstickers
            } catch (e) {
                svg.outerHTML = '<span>Geen barcode mogelijk</span>';
            }
        });
    }

    // Tekst die niet past (breedte, of hoogte bij de omschrijving over 2 regels) wordt kleiner gezet.
    function fit(container) {
        Array.prototype.forEach.call(container.querySelectorAll('.sticker--art .f'), function (el) {
            var size = parseFloat(getComputedStyle(el).fontSize);
            var min = size * 0.4;
            while ((el.scrollWidth > el.clientWidth || el.scrollHeight > el.clientHeight) && size > min) {
                size *= 0.95;
                el.style.fontSize = size + 'px';
            }
        });
    }

    return { html: html, barcodes: barcodes, fit: fit };
}());
