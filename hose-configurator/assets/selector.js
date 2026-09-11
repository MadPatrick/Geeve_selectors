(() => {
    'use strict';

    const hoses = Array.isArray(window.HOSES) ? window.HOSES : [];
    const couplings = Array.isArray(window.COUPLINGS) ? window.COUPLINGS : [];
    const hosesByArtnr = new Map(hoses.map((hose) => [hose.artnr, hose]));

    const els = {
        draadsoort1: document.getElementById('draadsoort1'),
        koppeling1: document.getElementById('koppeling1'),
        draadsoort2: document.getElementById('draadsoort2'),
        koppeling2: document.getElementById('koppeling2'),
        slangtype: document.getElementById('slangtype'),
        lengte: document.getElementById('lengte'),
        textsleeve: document.getElementById('textsleeve'),
    };
    const stand1Buttons = [...document.querySelectorAll('#stand1 .stand-icon')];
    const stand2Buttons = [...document.querySelectorAll('#stand2 .stand-icon')];
    let selectedStand1 = '';
    let selectedStand2 = '';

    const resetButton = document.getElementById('resetButton');
    const hoseVisual = document.getElementById('hoseVisual');
    const configSummary = document.getElementById('configSummary');

    const ALL = '';
    const MIN_LENGTE = 50;
    const MAX_LENGTE = 6000;
    const STAND_LABEL = { '0': 'Recht', '45': '45°', '90': 'Haaks' };

    // --- helpers -----------------------------------------------------

    function capitalize(text) {
        return text ? text.charAt(0).toUpperCase() + text.slice(1) : text;
    }

    function uniqueSorted(values) {
        return Array.from(new Set(values.filter((v) => v))).sort((a, b) =>
            a.localeCompare(b, undefined, { numeric: true })
        );
    }

    function fillSelect(select, values, placeholder) {
        const previous = select.value;
        select.innerHTML = '';
        const placeholderOption = document.createElement('option');
        placeholderOption.value = ALL;
        placeholderOption.textContent = placeholder;
        select.appendChild(placeholderOption);
        values.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = capitalize(value);
            select.appendChild(option);
        });
        select.value = values.includes(previous) ? previous : ALL;
    }

    // --- koppeling-data: filteren op draadsoort/stand/maat --------------

    function matchingCouplings(draadsoort, stand) {
        return couplings.filter((c) =>
            (!draadsoort || c.draadsoort === draadsoort) &&
            (!stand || String(c.stand) === stand)
        );
    }

    function maatOptionsFor(draadsoort, stand) {
        if (!draadsoort) return [];
        return uniqueSorted(matchingCouplings(draadsoort, stand).map((c) => c.maat));
    }

    function compatibleHoseMaten(draadsoort, maat, stand) {
        const set = new Set();
        matchingCouplings(draadsoort, stand)
            .filter((c) => c.maat === maat)
            .forEach((c) => { if (c.hoseMaat !== null && c.hoseMaat !== undefined) set.add(c.hoseMaat); });
        return set;
    }

    function resolveArticles(draadsoort, maat, stand, hoseMaat) {
        return matchingCouplings(draadsoort, stand).filter(
            (c) => c.maat === maat && c.hoseMaat === hoseMaat
        );
    }

    // --- draadsoort/stand/koppeling dropdowns --------------------------

    const draadsoortValues = uniqueSorted(couplings.map((c) => c.draadsoort));

    function populateKoppeling(select, draadsoort, stand) {
        fillSelect(select, maatOptionsFor(draadsoort, stand), 'Kies een maat…');
        select.disabled = select.options.length <= 1;
    }

    fillSelect(els.draadsoort1, draadsoortValues, 'Alle');
    fillSelect(els.draadsoort2, draadsoortValues, 'Alle');
    populateKoppeling(els.koppeling1, els.draadsoort1.value, selectedStand1);
    populateKoppeling(els.koppeling2, els.draadsoort2.value, selectedStand2);

    // --- slangtype-lijst filteren op de gekozen koppeling(en) ------------

    function currentSelection() {
        return {
            draadsoort1: els.draadsoort1.value,
            stand1: selectedStand1,
            maat1: els.koppeling1.value,
            draadsoort2: els.draadsoort2.value,
            stand2: selectedStand2,
            maat2: els.koppeling2.value,
        };
    }

    function allowedHoseMaten(sel) {
        const sets = [];
        if (sel.draadsoort1 && sel.maat1) sets.push(compatibleHoseMaten(sel.draadsoort1, sel.maat1, sel.stand1));
        if (sel.draadsoort2 && sel.maat2) sets.push(compatibleHoseMaten(sel.draadsoort2, sel.maat2, sel.stand2));
        if (sets.length === 0) return null;
        return sets.reduce((acc, set) => new Set([...acc].filter((v) => set.has(v))));
    }

    function refreshSlangtypeOptions(sel) {
        const allowed = allowedHoseMaten(sel);
        const filtered = allowed === null
            ? hoses
            : hoses.filter((h) => h.maat !== null && allowed.has(h.maat));

        const previous = els.slangtype.value;
        els.slangtype.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = ALL;
        placeholder.textContent = filtered.length === 0 ? 'Geen slangtype past bij deze koppeling(en)' : 'Kies een artikelnummer…';
        els.slangtype.appendChild(placeholder);
        filtered.forEach((hose) => {
            const option = document.createElement('option');
            option.value = hose.artnr;
            option.textContent = hose.artnr;
            els.slangtype.appendChild(option);
        });
        els.slangtype.value = filtered.some((h) => h.artnr === previous) ? previous : ALL;
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

    function renderVisual(lengte, label1, label2) {
        const barWidth = lengthToBarWidth(lengte || MIN_LENGTE);
        const svgWidth = Math.round(barWidth + 160);
        const height = 190;
        const hoseY = 120;
        const hoseHeight = 34;
        const nutWidth = 34;
        const hoseStartX = 80;
        const hoseEndX = hoseStartX + barWidth;
        const rulerY = 40;
        const lengteText = lengte ? `${lengte} mm` : '— mm';

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

    // --- artikelcode per koppeling opzoeken ------------------------------

    function articleCodeText(draadsoort, maat, stand, hose) {
        if (!draadsoort || !maat) return { short: '—', full: '—' };
        if (!hose || hose.maat === null || hose.maat === undefined) {
            return { short: `${maat} (kies slangtype)`, full: `${draadsoort} ${maat} — kies eerst een slangtype` };
        }
        const matches = resolveArticles(draadsoort, maat, stand, hose.maat);
        if (matches.length === 0) {
            return { short: `${maat} (geen match)`, full: `${draadsoort} ${maat} — geen passend artikel gevonden voor deze slang` };
        }
        if (matches.length === 1) {
            return { short: matches[0].artikelcode, full: `${matches[0].artikelcode} — ${matches[0].omschrijving}` };
        }
        return {
            short: matches.map((m) => m.artikelcode).join(', '),
            full: matches.map((m) => `${m.artikelcode} — ${m.omschrijving}`).join('; '),
        };
    }

    function renderSummary(sel, hose, art1, art2, lengte, textsleeve) {
        const rows = [
            ['Slangtype', hose ? hose.artnr : '—'],
            ['Omschrijving', hose && hose.artnm ? hose.artnm : '—'],
            ['Draadsoort 1', sel.draadsoort1 ? capitalize(sel.draadsoort1) : '—'],
            ['Stand 1', sel.stand1 ? STAND_LABEL[sel.stand1] : '—'],
            ['Artikelcode koppeling 1', art1.full],
            ['Draadsoort 2', sel.draadsoort2 ? capitalize(sel.draadsoort2) : '—'],
            ['Stand 2', sel.stand2 ? STAND_LABEL[sel.stand2] : '—'],
            ['Artikelcode koppeling 2', art2.full],
            ['Lengte', lengte ? `${lengte} mm` : '—'],
            ['Textsleeve', textsleeve ? 'Ja' : 'Nee'],
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
        const sel = currentSelection();
        refreshSlangtypeOptions(sel);

        const hose = hosesByArtnr.get(els.slangtype.value) || null;
        const lengte = parseInt(els.lengte.value, 10) || 0;
        const textsleeve = els.textsleeve.checked;

        const art1 = articleCodeText(sel.draadsoort1, sel.maat1, sel.stand1, hose);
        const art2 = articleCodeText(sel.draadsoort2, sel.maat2, sel.stand2, hose);

        renderVisual(lengte, art1.short, art2.short);
        renderSummary(sel, hose, art1, art2, lengte, textsleeve);
    }

    // --- event wiring --------------------------------------------------

    els.draadsoort1.addEventListener('change', () => {
        populateKoppeling(els.koppeling1, els.draadsoort1.value, selectedStand1);
        render();
    });
    els.draadsoort2.addEventListener('change', () => {
        populateKoppeling(els.koppeling2, els.draadsoort2.value, selectedStand2);
        render();
    });
    [els.koppeling1, els.koppeling2, els.slangtype, els.textsleeve].forEach((el) => {
        el.addEventListener('change', render);
    });
    els.lengte.addEventListener('input', render);

    function wireStandGroup(buttons, select, draadsoortEl, setValue) {
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const value = button.dataset.stand;
                const wasActive = button.classList.contains('active');
                buttons.forEach((b) => {
                    b.classList.remove('active');
                    b.setAttribute('aria-pressed', 'false');
                });
                if (wasActive) {
                    setValue('');
                } else {
                    button.classList.add('active');
                    button.setAttribute('aria-pressed', 'true');
                    setValue(value);
                }
                populateKoppeling(select, draadsoortEl.value, wasActive ? '' : value);
                render();
            });
        });
    }
    wireStandGroup(stand1Buttons, els.koppeling1, els.draadsoort1, (value) => { selectedStand1 = value; });
    wireStandGroup(stand2Buttons, els.koppeling2, els.draadsoort2, (value) => { selectedStand2 = value; });

    resetButton.addEventListener('click', () => {
        els.draadsoort1.value = ALL;
        els.draadsoort2.value = ALL;
        selectedStand1 = '';
        selectedStand2 = '';
        [...stand1Buttons, ...stand2Buttons].forEach((button) => {
            button.classList.remove('active');
            button.setAttribute('aria-pressed', 'false');
        });
        populateKoppeling(els.koppeling1, ALL, '');
        populateKoppeling(els.koppeling2, ALL, '');
        els.lengte.value = '1000';
        els.textsleeve.checked = false;
        render();
    });

    render();
})();
