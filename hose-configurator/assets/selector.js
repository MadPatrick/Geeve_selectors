(() => {
    'use strict';

    const hoses = Array.isArray(window.HOSES) ? window.HOSES : [];
    const hosesByArtnr = new Map(hoses.map((hose) => [hose.artnr, hose]));

    const els = {
        koppeling1: document.getElementById('koppeling1'),
        slangtype: document.getElementById('slangtype'),
        koppeling2: document.getElementById('koppeling2'),
        lengte: document.getElementById('lengte'),
        textsleeve: document.getElementById('textsleeve'),
    };
    const resetButton = document.getElementById('resetButton');
    const hoseVisual = document.getElementById('hoseVisual');
    const configSummary = document.getElementById('configSummary');

    const ALL = '';
    const MIN_LENGTE = 50;
    const MAX_LENGTE = 6000;

    // --- koppeling-dropdowns vullen n.a.v. gekozen slangtype ----------------

    function fillKoppelingSelect(select, codes) {
        const previous = select.value;
        select.innerHTML = '';

        if (codes.length === 0) {
            const option = document.createElement('option');
            option.value = ALL;
            option.textContent = 'Geen koppelingen bekend';
            select.appendChild(option);
            select.disabled = true;
            return;
        }

        const placeholder = document.createElement('option');
        placeholder.value = ALL;
        placeholder.textContent = 'Kies een koppeling…';
        select.appendChild(placeholder);

        codes.forEach((code) => {
            const option = document.createElement('option');
            option.value = code;
            option.textContent = code;
            select.appendChild(option);
        });

        select.disabled = false;
        select.value = codes.includes(previous) ? previous : ALL;
    }

    function onSlangtypeChange() {
        const hose = hosesByArtnr.get(els.slangtype.value);
        const codes = hose ? hose.couplings.slice().sort((a, b) => a.localeCompare(b)) : [];
        fillKoppelingSelect(els.koppeling1, codes);
        fillKoppelingSelect(els.koppeling2, codes);
        render();
    }

    // --- visuele weergave (liniaal + slanggrafiek) ---------------------------

    function lengthToBarWidth(lengteMm) {
        const clamped = Math.min(Math.max(lengteMm, MIN_LENGTE), MAX_LENGTE);
        const t = (clamped - MIN_LENGTE) / (MAX_LENGTE - MIN_LENGTE);
        return 220 + t * 480; // 220px..700px
    }

    function escapeXml(text) {
        return String(text).replace(/[&<>]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[ch]));
    }

    function renderVisual(sel) {
        const barWidth = lengthToBarWidth(sel.lengte || MIN_LENGTE);
        const totalWidth = barWidth + 160;
        const svgWidth = Math.round(totalWidth);
        const height = 190;
        const hoseY = 120;
        const hoseHeight = 34;
        const nutWidth = 34;
        const hoseStartX = 80;
        const hoseEndX = hoseStartX + barWidth;
        const rulerY = 40;

        const label1 = sel.koppeling1 || '—';
        const label2 = sel.koppeling2 || '—';
        const lengteText = sel.lengte ? `${sel.lengte} mm` : '— mm';

        hoseVisual.innerHTML = `
            <svg viewBox="0 0 ${svgWidth} ${height}" width="100%" height="${height}" role="img" aria-label="Voorbeeld van de samengestelde slang">
                <line x1="${hoseStartX}" y1="${rulerY}" x2="${hoseEndX}" y2="${rulerY}" class="ruler-line"></line>
                <line x1="${hoseStartX}" y1="${rulerY - 10}" x2="${hoseStartX}" y2="${rulerY + 10}" class="ruler-tick"></line>
                <line x1="${hoseEndX}" y1="${rulerY - 10}" x2="${hoseEndX}" y2="${rulerY + 10}" class="ruler-tick"></line>
                <text x="${(hoseStartX + hoseEndX) / 2}" y="${rulerY - 14}" text-anchor="middle" class="ruler-label">${escapeXml(lengteText)}</text>

                <text x="${hoseStartX}" y="${hoseY - hoseHeight / 2 - 10}" text-anchor="middle" class="koppeling-label">${escapeXml(label1)}</text>
                <text x="${hoseEndX}" y="${hoseY - hoseHeight / 2 - 10}" text-anchor="middle" class="koppeling-label">${escapeXml(label2)}</text>

                <rect x="${hoseStartX - nutWidth}" y="${hoseY - hoseHeight / 2 - 4}" width="${nutWidth}" height="${hoseHeight + 8}" rx="4" class="hose-nut"></rect>
                <rect x="${hoseStartX}" y="${hoseY - hoseHeight / 2}" width="${barWidth}" height="${hoseHeight}" class="hose-barrel"></rect>
                <rect x="${hoseEndX}" y="${hoseY - hoseHeight / 2 - 4}" width="${nutWidth}" height="${hoseHeight + 8}" rx="4" class="hose-nut"></rect>

                <text x="${hoseStartX + barWidth * 0.28}" y="${hoseY + 5}" text-anchor="middle" class="hose-brand">GEEVE</text>
                <text x="${hoseStartX + barWidth * 0.72}" y="${hoseY + 5}" text-anchor="middle" class="hose-brand">GEEVE</text>
            </svg>
        `;
    }

    function renderSummary(sel, hose) {
        const rows = [
            ['Slangtype', hose ? (hose.artnm || hose.artnr) : '—'],
            ['Koppeling 1', sel.koppeling1 || '—'],
            ['Koppeling 2', sel.koppeling2 || '—'],
            ['Lengte', sel.lengte ? `${sel.lengte} mm` : '—'],
            ['Textsleeve', sel.textsleeve ? 'Ja' : 'Nee'],
        ];
        configSummary.innerHTML = '';
        rows.forEach(([label, value]) => {
            const dt = document.createElement('dt');
            dt.textContent = label;
            const dd = document.createElement('dd');
            dd.textContent = value;
            configSummary.appendChild(dt);
            configSummary.appendChild(dd);
        });
    }

    function render() {
        const sel = {
            hoseArtnr: els.slangtype.value,
            koppeling1: els.koppeling1.value,
            koppeling2: els.koppeling2.value,
            lengte: parseInt(els.lengte.value, 10) || 0,
            textsleeve: els.textsleeve.checked,
        };
        const hose = hosesByArtnr.get(sel.hoseArtnr) || null;

        renderVisual(sel);
        renderSummary(sel, hose);
    }

    // --- event wiring --------------------------------------------------

    els.slangtype.addEventListener('change', onSlangtypeChange);
    [els.koppeling1, els.koppeling2, els.textsleeve].forEach((el) => {
        el.addEventListener('change', render);
    });
    els.lengte.addEventListener('input', render);

    resetButton.addEventListener('click', () => {
        els.slangtype.value = ALL;
        fillKoppelingSelect(els.koppeling1, []);
        fillKoppelingSelect(els.koppeling2, []);
        els.koppeling1.disabled = true;
        els.koppeling2.disabled = true;
        els.lengte.value = '1000';
        els.textsleeve.checked = false;
        render();
    });

    render();
})();
