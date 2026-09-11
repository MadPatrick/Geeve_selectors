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
    const type1Buttons = [...document.querySelectorAll('#type1 .stand-icon')];
    const type2Buttons = [...document.querySelectorAll('#type2 .stand-icon')];
    const DEFAULT_STAND = '0'; // "Recht" staat standaard aan
    let selectedStand1 = DEFAULT_STAND;
    let selectedStand2 = DEFAULT_STAND;
    let selectedType1 = '';
    let selectedType2 = '';

    function activateStandButton(buttons, value) {
        buttons.forEach((button) => {
            const isMatch = button.dataset.stand === value;
            button.classList.toggle('active', isMatch);
            button.setAttribute('aria-pressed', isMatch ? 'true' : 'false');
        });
    }

    const resetButton = document.getElementById('resetButton');
    const hoseVisual = document.getElementById('hoseVisual');
    const configSummary = document.getElementById('configSummary');

    const ALL = '';
    const MIN_LENGTE = 50;
    const MAX_LENGTE = 6000;
    const STAND_LABEL = { '0': 'Recht', '45': '45°', '90': 'Haaks' };
    const TYPE_LABEL = { wartel: 'Wartel', buiten: 'Buiten', standpijp: 'Standpijp', banjo: 'Banjo', flange: 'Flens' };

    // --- helpers -----------------------------------------------------

    function capitalize(text) {
        return text ? text.charAt(0).toUpperCase() + text.slice(1) : text;
    }

    function uniqueSorted(values) {
        return Array.from(new Set(values.filter((v) => v))).sort((a, b) =>
            a.localeCompare(b, undefined, { numeric: true })
        );
    }

    // Maten komen als breuk voor in twee notaties: "1-1/16" (met streepje,
    // bijv. bij JIC) en zonder streepje, waarbij een verkorte gemengde
    // breuk zoals "1 1/2 inch" als "11/2" geschreven is (bijv. bij BSP/
    // Metrisch). Een gewone tekst/numerieke sort ziet "11/2" als het getal
    // 11, niet als 1,5 - daardoor belandden 11/2, 11/4, 13/4 en 21/2 achter
    // 3, 5/8 enz. in plaats van op hun echte grootte. Reken elke maat om
    // naar een decimale waarde en sorteer daarop.
    function maatValue(text) {
        let m = text.match(/^(\d+)-(\d+)\/(\d+)$/);
        if (m) return parseInt(m[1], 10) + parseInt(m[2], 10) / parseInt(m[3], 10);

        m = text.match(/^(\d+)\/(\d+)$/);
        if (m) {
            const numStr = m[1];
            const denom = parseInt(m[2], 10);
            const num = parseInt(numStr, 10);
            if (num < denom) return num / denom; // echte breuk zoals "9/16", "11/16"
            // "11/2" style: laatste cijfer is de teller, de rest het hele getal
            const lastDigit = parseInt(numStr.slice(-1), 10);
            const whole = numStr.length > 1 ? parseInt(numStr.slice(0, -1), 10) : 0;
            return whole + lastDigit / denom;
        }

        m = text.match(/(\d+(?:[.,]\d+)?)/);
        return m ? parseFloat(m[1].replace(',', '.')) : Number.POSITIVE_INFINITY;
    }

    function maatSort(values) {
        return Array.from(new Set(values.filter((v) => v))).sort((a, b) => {
            const diff = maatValue(a) - maatValue(b);
            return diff !== 0 ? diff : a.localeCompare(b, undefined, { numeric: true });
        });
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

    // --- koppeling-data: filteren op draadsoort/stand/type/maat/slangmaat ---
    // `allowedHoseMaten` (Set|null) is de kruisfilter met de andere
    // aansluiting: de laatste cijfers van diens koppeling-artikelcode
    // (dezelfde dash-maat als het eigen slangartikelnummer) moeten
    // overeenkomen. null = geen kruisfilter (die kant heeft nog geen
    // volledige keuze gemaakt).

    function matchingCouplings(draadsoort, stand, type, allowedHoseMaten) {
        return couplings.filter((c) =>
            (!draadsoort || c.draadsoort === draadsoort) &&
            (!stand || String(c.stand) === stand) &&
            (!type || c.type === type) &&
            (!allowedHoseMaten || allowedHoseMaten.has(c.hoseMaat))
        );
    }

    function maatOptionsFor(draadsoort, stand, type, allowedHoseMaten) {
        if (!draadsoort) return [];
        return maatSort(matchingCouplings(draadsoort, stand, type, allowedHoseMaten).map((c) => c.maat));
    }

    function compatibleHoseMaten(draadsoort, maat, stand, type) {
        const set = new Set();
        matchingCouplings(draadsoort, stand, type, null)
            .filter((c) => c.maat === maat)
            .forEach((c) => { if (c.hoseMaat !== null && c.hoseMaat !== undefined) set.add(c.hoseMaat); });
        return set;
    }

    function resolveArticles(draadsoort, maat, stand, type, hoseMaat) {
        return matchingCouplings(draadsoort, stand, type, null).filter(
            (c) => c.maat === maat && c.hoseMaat === hoseMaat
        );
    }

    // --- type -> draadsoort -> koppeling(maat) -> stand, in die volgorde ---
    // Type is het 1e filter: het bepaalt welke draadsoorten er nog zijn,
    // draadsoort bepaalt welke maten er nog zijn, stand filtert daarbinnen.
    // Zodra de andere aansluiting al een volledige koppeling (draadsoort +
    // maat) heeft, filtert diens slangmaat ook mee.

    function draadsoortOptionsFor(type, allowedHoseMaten) {
        return uniqueSorted(
            couplings
                .filter((c) => (!type || c.type === type) && (!allowedHoseMaten || allowedHoseMaten.has(c.hoseMaat)))
                .map((c) => c.draadsoort)
        );
    }

    function populateDraadsoort(select, type, allowedHoseMaten) {
        fillSelect(select, draadsoortOptionsFor(type, allowedHoseMaten), 'Alle');
    }

    function populateKoppeling(select, draadsoort, stand, type, allowedHoseMaten) {
        fillSelect(select, maatOptionsFor(draadsoort, stand, type, allowedHoseMaten), 'Kies een maat…');
        select.disabled = select.options.length <= 1;
    }

    // Wat aansluiting 1 momenteel vastlegt aan slangmaten (voor het filteren
    // van aansluiting 2), en omgekeerd - alleen als die kant al een volledige
    // draadsoort+maat heeft, anders geen kruisfilter (null).
    function sideHoseMaten(draadsoort, maat, stand, type) {
        return draadsoort && maat ? compatibleHoseMaten(draadsoort, maat, stand, type) : null;
    }

    // Centrale herberekening: leest de HUIDIGE waarden (vóór het
    // herbouwen van de dropdowns), berekent per kant welke slangmaten dat
    // oplegt aan de andere kant, en herbouwt dan draadsoort/koppeling voor
    // beide kanten plus de slangtype-lijst in één keer.
    function refreshFilters() {
        const side1Set = sideHoseMaten(els.draadsoort1.value, els.koppeling1.value, selectedStand1, selectedType1);
        const side2Set = sideHoseMaten(els.draadsoort2.value, els.koppeling2.value, selectedStand2, selectedType2);

        populateDraadsoort(els.draadsoort1, selectedType1, side2Set);
        populateKoppeling(els.koppeling1, els.draadsoort1.value, selectedStand1, selectedType1, side2Set);

        populateDraadsoort(els.draadsoort2, selectedType2, side1Set);
        populateKoppeling(els.koppeling2, els.draadsoort2.value, selectedStand2, selectedType2, side1Set);

        refreshSlangtypeOptions();
    }

    // --- slangtype-lijst filteren op de gekozen koppeling(en) ------------

    function currentSelection() {
        return {
            draadsoort1: els.draadsoort1.value,
            stand1: selectedStand1,
            type1: selectedType1,
            maat1: els.koppeling1.value,
            draadsoort2: els.draadsoort2.value,
            stand2: selectedStand2,
            type2: selectedType2,
            maat2: els.koppeling2.value,
        };
    }

    function allowedHoseMatenForHose(sel) {
        const sets = [];
        if (sel.draadsoort1 && sel.maat1) sets.push(compatibleHoseMaten(sel.draadsoort1, sel.maat1, sel.stand1, sel.type1));
        if (sel.draadsoort2 && sel.maat2) sets.push(compatibleHoseMaten(sel.draadsoort2, sel.maat2, sel.stand2, sel.type2));
        if (sets.length === 0) return null;
        return sets.reduce((acc, set) => new Set([...acc].filter((v) => set.has(v))));
    }

    function refreshSlangtypeOptions() {
        const allowed = allowedHoseMatenForHose(currentSelection());
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

    // Tekent de koppeling aan één uiteinde: bij stand 0 (recht) de moer in
    // het verlengde van de slang (zoals voorheen); bij 45/90 draait de moer
    // om het punt waar hij de slang raakt, zodat hij schuin resp. haaks
    // omhoog wijst - een scharnier-effect via SVG rotate() op de moer zelf,
    // met een dun verbindingsstukje en een label dat rechtop boven de
    // (verplaatste) moer blijft staan i.p.v. mee te draaien.
    function endFittingSvg(hingeX, hoseY, hoseHeight, nutWidth, dir, stand, label) {
        // dir: -1 = linker uiteinde (rust-stand wijst naar links), +1 =
        // rechter uiteinde (rust-stand wijst naar rechts). Extra rotatie
        // (deltaDeg) t.o.v. die ruststand, richting "omhoog": voor links met
        // de klok mee (+stand), voor rechts tegen de klok in (-stand).
        const standDeg = parseInt(stand || '0', 10);
        const deltaDeg = dir === -1 ? standDeg : -standDeg;
        const rad = (deltaDeg * Math.PI) / 180;

        const nutX = dir === -1 ? hingeX - nutWidth : hingeX;
        const nutY = hoseY - hoseHeight / 2 - 4;
        const nutH = hoseHeight + 8;

        // Middelpunt van de moer, ongeroteerd, t.o.v. het scharnierpunt.
        const localCx = dir === -1 ? -nutWidth / 2 : nutWidth / 2;
        const centerX = hingeX + localCx * Math.cos(rad);
        const centerY = hoseY + localCx * Math.sin(rad);
        const labelY = centerY - (nutH / 2 + 10);

        return `
            <line x1="${hingeX}" y1="${hoseY}" x2="${centerX}" y2="${centerY}" class="hose-stub"></line>
            <g transform="rotate(${deltaDeg} ${hingeX} ${hoseY})">
                <rect x="${nutX}" y="${nutY}" width="${nutWidth}" height="${nutH}" rx="4" class="hose-nut"></rect>
            </g>
            <text x="${centerX}" y="${labelY}" text-anchor="middle" class="koppeling-label">${escapeXml(label)}</text>
        `;
    }

    function renderVisual(lengte, label1, label2, stand1, stand2) {
        const barWidth = lengthToBarWidth(lengte || MIN_LENGTE);
        const svgWidth = Math.round(barWidth + 160);
        const height = 230;
        const hoseY = 150;
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

                <rect x="${hoseStartX}" y="${hoseY - hoseHeight / 2}" width="${barWidth}" height="${hoseHeight}" class="hose-barrel"></rect>
                ${endFittingSvg(hoseStartX, hoseY, hoseHeight, nutWidth, -1, stand1, label1)}
                ${endFittingSvg(hoseEndX, hoseY, hoseHeight, nutWidth, 1, stand2, label2)}

                <text x="${hoseStartX + barWidth * 0.28}" y="${hoseY + 5}" text-anchor="middle" class="hose-brand">GEEVE</text>
                <text x="${hoseStartX + barWidth * 0.72}" y="${hoseY + 5}" text-anchor="middle" class="hose-brand">GEEVE</text>
            </svg>
        `;
    }

    // --- artikelnummer per koppeling opzoeken ----------------------------

    function articleCodeText(draadsoort, maat, stand, type, hose) {
        if (!draadsoort || !maat) return { short: '—', full: '—' };
        if (!hose || hose.maat === null || hose.maat === undefined) {
            return { short: `${maat} (kies slangtype)`, full: `${draadsoort} ${maat} — kies eerst een slangtype` };
        }
        const matches = resolveArticles(draadsoort, maat, stand, type, hose.maat);
        if (matches.length === 0) {
            return { short: `${maat} (geen match)`, full: `${draadsoort} ${maat} — geen passend artikel gevonden voor deze slang` };
        }
        if (matches.length === 1) {
            return { short: matches[0].artikelnummer, full: `${matches[0].artikelnummer} — ${matches[0].omschrijving}` };
        }
        return {
            short: matches.map((m) => m.artikelnummer).join(', '),
            full: matches.map((m) => `${m.artikelnummer} — ${m.omschrijving}`).join('; '),
        };
    }

    // Een slang heeft twee uiteinden - 2delig_1 (huls1) hoort bij koppeling 1,
    // 2delig_2 (huls2) bij koppeling 2.
    function hulsText(huls) {
        if (!huls) return '—';
        return huls.persmaat ? `${huls.code} (persmaat ${huls.persmaat} mm)` : huls.code;
    }

    // De huls hangt vooral af van de perssdiameter van de slang zelf, niet
    // per se van welk uiteinde - veel rijen in de brondata hebben daarom maar
    // één van de twee 2delig-hulskolommen ingevuld, ook als de slang aan
    // weerskanten dezelfde koppeling heeft. Is er voor deze kant geen eigen
    // huls bekend, val dan terug op de andere kant i.p.v. "—" te tonen
    // terwijl de slang wel degelijk een huls heeft.
    function hulsForSide(hose, own, other) {
        if (!hose) return null;
        return hose[own] || hose[other];
    }

    function renderSummary(sel, hose, art1, art2, lengte, textsleeve) {
        const rows = [
            ['Slangtype', hose ? hose.artnr : '—'],
            ['Omschrijving', hose && hose.artnm ? hose.artnm : '—'],
            ['Draadsoort 1', sel.draadsoort1 ? capitalize(sel.draadsoort1) : '—'],
            ['Stand 1', sel.stand1 ? STAND_LABEL[sel.stand1] : '—'],
            ['Type 1', sel.type1 ? TYPE_LABEL[sel.type1] : '—'],
            ['Artikelnummer koppeling 1', art1.full],
            ['Huls koppeling 1', hulsText(hulsForSide(hose, 'huls1', 'huls2'))],
            ['Draadsoort 2', sel.draadsoort2 ? capitalize(sel.draadsoort2) : '—'],
            ['Stand 2', sel.stand2 ? STAND_LABEL[sel.stand2] : '—'],
            ['Type 2', sel.type2 ? TYPE_LABEL[sel.type2] : '—'],
            ['Artikelnummer koppeling 2', art2.full],
            ['Huls koppeling 2', hulsText(hulsForSide(hose, 'huls2', 'huls1'))],
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

        const hose = hosesByArtnr.get(els.slangtype.value) || null;
        const lengte = parseInt(els.lengte.value, 10) || 0;
        const textsleeve = els.textsleeve.checked;

        const art1 = articleCodeText(sel.draadsoort1, sel.maat1, sel.stand1, sel.type1, hose);
        const art2 = articleCodeText(sel.draadsoort2, sel.maat2, sel.stand2, sel.type2, hose);

        renderVisual(lengte, art1.short, art2.short, sel.stand1, sel.stand2);
        renderSummary(sel, hose, art1, art2, lengte, textsleeve);
    }

    // --- event wiring --------------------------------------------------

    [els.draadsoort1, els.koppeling1, els.draadsoort2, els.koppeling2].forEach((el) => {
        el.addEventListener('change', () => {
            refreshFilters();
            render();
        });
    });
    [els.slangtype, els.textsleeve].forEach((el) => {
        el.addEventListener('change', render);
    });
    els.lengte.addEventListener('input', render);

    // Stand/type-knoppen: één keuze per groep (klikken op de actieve knop
    // zet 'm weer uit, terug naar "alle").
    function wireIconGroup(buttons, setValue) {
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const value = button.dataset.stand || button.dataset.type;
                const wasActive = button.classList.contains('active');
                buttons.forEach((b) => {
                    b.classList.remove('active');
                    b.setAttribute('aria-pressed', 'false');
                });
                const newValue = wasActive ? '' : value;
                if (!wasActive) {
                    button.classList.add('active');
                    button.setAttribute('aria-pressed', 'true');
                }
                setValue(newValue);
                refreshFilters();
                render();
            });
        });
    }
    wireIconGroup(stand1Buttons, (value) => { selectedStand1 = value; });
    wireIconGroup(stand2Buttons, (value) => { selectedStand2 = value; });
    wireIconGroup(type1Buttons, (value) => { selectedType1 = value; });
    wireIconGroup(type2Buttons, (value) => { selectedType2 = value; });

    resetButton.addEventListener('click', () => {
        selectedStand1 = DEFAULT_STAND;
        selectedStand2 = DEFAULT_STAND;
        selectedType1 = '';
        selectedType2 = '';
        [...type1Buttons, ...type2Buttons].forEach((button) => {
            button.classList.remove('active');
            button.setAttribute('aria-pressed', 'false');
        });
        activateStandButton(stand1Buttons, selectedStand1);
        activateStandButton(stand2Buttons, selectedStand2);
        els.draadsoort1.value = ALL;
        els.draadsoort2.value = ALL;
        els.koppeling1.value = ALL;
        els.koppeling2.value = ALL;
        refreshFilters();
        els.lengte.value = '1000';
        els.textsleeve.checked = false;
        render();
    });

    refreshFilters();
    render();
})();
