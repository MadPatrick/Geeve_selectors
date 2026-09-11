(() => {
    'use strict';

    const articles = Array.isArray(window.ARTICLES) ? window.ARTICLES : [];

    const els = {
        draadsoort1: document.getElementById('draadsoort1'),
        draadmaat1: document.getElementById('draadmaat1'),
        connectie1: document.getElementById('connectie1'),
        draadsoort2: document.getElementById('draadsoort2'),
        draadmaat2: document.getElementById('draadmaat2'),
        connectie2: document.getElementById('connectie2'),
        hoek: document.getElementById('hoek'),
    };
    const hoekButtons = [...document.querySelectorAll('.hoek-icon')];
    const selectedHoek = new Set();
    const connectie1Buttons = [...document.querySelectorAll('#connectie1 .connectie-icon')];
    const connectie2Buttons = [...document.querySelectorAll('#connectie2 .connectie-icon')];
    let selectedConnectie1 = '';
    let selectedConnectie2 = '';
    const resetButton = document.getElementById('resetButton');
    const resultCount = document.getElementById('resultCount');
    const resultTableHead = document.getElementById('resultTableHead');
    const resultTableBody = document.getElementById('resultTableBody');
    const resultTableWrap = document.getElementById('resultTableWrap');
    const resultMoreNote = document.getElementById('resultMoreNote');
    const emptyResult = document.getElementById('emptyResult');

    const MAX_ROWS = 300;
    const ALL = '';

    // Zolang er nog geen draadmaat gekozen is (op geen van beide
    // aansluitingen) kan een selectie honderden losse artikelen opleveren -
    // te veel om als platte lijst te tonen. Groepeer dan per productfamilie
    // (bijv. "2244 - Union"); zodra een maat gekozen is, is de lijst vanzelf
    // klein genoeg voor de normale platte weergave per artikel.
    const FLAT_HEAD_HTML = `
        <tr>
            <th class="result-table-image-col">Afbeelding</th>
            <th>Artikelcode</th>
            <th>Kruisverwijzing</th>
            <th>Familie</th>
            <th>Hoek</th>
            <th>Aansluiting 1</th>
            <th>Aansluiting 2</th>
        </tr>`;
    const GROUPED_HEAD_HTML = `
        <tr>
            <th class="result-table-image-col">Afbeelding</th>
            <th>Familie</th>
            <th>Aantal artikelen</th>
            <th>Hoek(en)</th>
            <th>Aansluitingen (voorbeelden)</th>
        </tr>`;

    // --- helpers -----------------------------------------------------

    function sizeSortKey(text) {
        if (!text) return 99999;
        const metric = /^M(\d+)/.exec(text);
        if (metric) return 10000 + parseFloat(metric[1]);
        const mixed = /^(\d+)?\s*(\d+)\/(\d+)/.exec(text);
        if (mixed) {
            const whole = mixed[1] ? parseFloat(mixed[1]) : 0;
            return whole + parseFloat(mixed[2]) / parseFloat(mixed[3]);
        }
        const plain = /^(\d+(\.\d+)?)/.exec(text);
        if (plain) return parseFloat(plain[1]);
        return 99999;
    }

    function uniqueSorted(values, sortFn) {
        return Array.from(new Set(values.filter((v) => v))).sort(sortFn);
    }

    function capitalize(text) {
        return text ? text.charAt(0).toUpperCase() + text.slice(1) : text;
    }

    function fillSelect(select, values, allLabel) {
        const previous = select.value;
        select.innerHTML = '';
        const allOption = document.createElement('option');
        allOption.value = ALL;
        allOption.textContent = allLabel;
        select.appendChild(allOption);
        values.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = capitalize(value);
            select.appendChild(option);
        });
        select.value = values.includes(previous) ? previous : ALL;
    }

    // --- static option lists ------------------------------------------

    const draadsoortValues = uniqueSorted(
        articles.flatMap((a) => [a.draadsoort1, a.draadsoort2]),
        (a, b) => a.localeCompare(b)
    );

    const draadmaatBySoort = new Map();
    draadsoortValues.forEach((soort) => {
        const maten = uniqueSorted(
            articles.flatMap((a) => [
                a.draadsoort1 === soort ? a.draadmaat1 : '',
                a.draadsoort2 === soort ? a.draadmaat2 : '',
            ]),
            (a, b) => sizeSortKey(a) - sizeSortKey(b) || a.localeCompare(b)
        );
        draadmaatBySoort.set(soort, maten);
    });
    const allDraadmaatValues = uniqueSorted(
        articles.flatMap((a) => [a.draadmaat1, a.draadmaat2]),
        (a, b) => sizeSortKey(a) - sizeSortKey(b) || a.localeCompare(b)
    );

    // --- populate dropdowns --------------------------------------------

    function populateDraadmaat(select, soortSelect) {
        const soort = soortSelect.value;
        const values = soort ? (draadmaatBySoort.get(soort) || []) : allDraadmaatValues;
        fillSelect(select, values, 'Alle');
    }

    fillSelect(els.draadsoort1, draadsoortValues, 'Alle');
    fillSelect(els.draadsoort2, draadsoortValues, 'Alle');
    populateDraadmaat(els.draadmaat1, els.draadsoort1);
    populateDraadmaat(els.draadmaat2, els.draadsoort2);

    // --- matching --------------------------------------------------------

    function sideMatches(articleSoort, articleMaat, articleConnectie, selSoort, selMaat, selConnectie) {
        if (selSoort && articleSoort !== selSoort) return false;
        if (selMaat && articleMaat !== selMaat) return false;
        if (selConnectie && articleConnectie !== selConnectie) return false;
        return true;
    }

    function readSelection() {
        return {
            ds1: els.draadsoort1.value,
            dm1: els.draadmaat1.value,
            ct1: selectedConnectie1,
            ds2: els.draadsoort2.value,
            dm2: els.draadmaat2.value,
            ct2: selectedConnectie2,
            hoek: selectedHoek,
        };
    }

    // Aansluiting 1/2 are interchangeable when matching (the user shouldn't have
    // to know which physical end the catalog happened to store as "position 1"),
    // but the result table still needs to show each matched article's data under
    // the SAME column the user filtered it by - so this returns which orientation
    // matched ('direct': article position 1 -> Aansluiting 1, or 'swapped': article
    // position 2 -> Aansluiting 1), not just whether it matched.
    function matchOrientation(article, sel) {
        if (sel.hoek.size > 0 && !sel.hoek.has(article.hoek)) return null;

        const direct = sideMatches(article.draadsoort1, article.draadmaat1, article.connectieType1, sel.ds1, sel.dm1, sel.ct1)
            && sideMatches(article.draadsoort2, article.draadmaat2, article.connectieType2, sel.ds2, sel.dm2, sel.ct2);
        if (direct) return 'direct';

        const swapped = sideMatches(article.draadsoort1, article.draadmaat1, article.connectieType1, sel.ds2, sel.dm2, sel.ct2)
            && sideMatches(article.draadsoort2, article.draadmaat2, article.connectieType2, sel.ds1, sel.dm1, sel.ct1);
        if (swapped) return 'swapped';

        return null;
    }

    function formatSide(soort, maat, connectie) {
        if (!soort && !maat) return '—';
        const parts = [soort, maat].filter(Boolean).join(' ');
        return connectie ? `${parts} (${capitalize(connectie)})` : parts;
    }

    function sideTexts(a, orientation) {
        const side1 = orientation === 'swapped'
            ? formatSide(a.draadsoort2, a.draadmaat2, a.connectieType2)
            : formatSide(a.draadsoort1, a.draadmaat1, a.connectieType1);
        const side2 = orientation === 'swapped'
            ? formatSide(a.draadsoort1, a.draadmaat1, a.connectieType1)
            : formatSide(a.draadsoort2, a.draadmaat2, a.connectieType2);
        return [side1, side2];
    }

    function buildImageCell(familieCode, familieNaam) {
        const imgTd = document.createElement('td');
        imgTd.className = 'result-table-image-col';
        if (familieCode) {
            const img = document.createElement('img');
            img.src = `images/${familieCode}.png`;
            img.alt = familieNaam || familieCode;
            img.loading = 'lazy';
            img.addEventListener('error', () => { imgTd.replaceChildren(); }, { once: true });
            imgTd.appendChild(img);
        }
        return imgTd;
    }

    function addCells(tr, texts) {
        texts.forEach((text) => {
            const td = document.createElement('td');
            td.textContent = text;
            tr.appendChild(td);
        });
    }

    // Nog geen draadmaat gekozen (op geen van beide aansluitingen) -> de
    // selectie kan nog honderden losse artikelen bevatten.
    function isGrouped(sel) {
        return !sel.dm1 && !sel.dm2;
    }

    function buildFamilyGroups(matches) {
        const groups = new Map();
        matches.forEach(({ article: a, orientation }) => {
            const key = a.familieCode || a.familieNaam || '—';
            if (!groups.has(key)) {
                groups.set(key, {
                    familieCode: a.familieCode,
                    familieNaam: a.familieNaam,
                    count: 0,
                    hoeken: new Set(),
                    combos: new Set(),
                });
            }
            const g = groups.get(key);
            g.count += 1;
            if (a.hoek) g.hoeken.add(capitalize(a.hoek));
            const [side1, side2] = sideTexts(a, orientation);
            g.combos.add(`${side1} ↔ ${side2}`);
        });
        return Array.from(groups.values()).sort((x, y) =>
            (x.familieCode || '').localeCompare(y.familieCode || '', undefined, { numeric: true })
        );
    }

    function renderGrouped(matches) {
        resultTableHead.innerHTML = GROUPED_HEAD_HTML;
        resultTableWrap.classList.add('grouped');

        const groups = buildFamilyGroups(matches);
        resultCount.textContent = `${groups.length} ${groups.length === 1 ? 'groep' : 'groepen'} (${matches.length} adapters) — kies ook een draadmaat voor de artikellijst`;

        resultTableBody.innerHTML = '';
        groups.forEach((g) => {
            const tr = document.createElement('tr');
            tr.appendChild(buildImageCell(g.familieCode, g.familieNaam));

            const comboPreview = Array.from(g.combos);
            const COMBO_LIMIT = 3;
            const comboText = comboPreview.length > COMBO_LIMIT
                ? `${comboPreview.slice(0, COMBO_LIMIT).join('; ')}; +${comboPreview.length - COMBO_LIMIT} meer`
                : comboPreview.join('; ');

            addCells(tr, [
                [g.familieCode, g.familieNaam].filter(Boolean).join(' – '),
                String(g.count),
                Array.from(g.hoeken).join(', ') || '—',
                comboText || '—',
            ]);
            resultTableBody.appendChild(tr);
        });

        resultMoreNote.hidden = true;
        const isEmpty = groups.length === 0;
        emptyResult.hidden = !isEmpty;
        resultTableWrap.hidden = isEmpty;
    }

    function renderFlat(matches) {
        resultTableHead.innerHTML = FLAT_HEAD_HTML;
        resultTableWrap.classList.remove('grouped');

        resultCount.textContent = matches.length + (matches.length === 1 ? ' adapter' : ' adapters');

        resultTableBody.innerHTML = '';
        const shown = matches.slice(0, MAX_ROWS);
        shown.forEach(({ article: a, orientation }) => {
            const tr = document.createElement('tr');
            tr.appendChild(buildImageCell(a.familieCode, a.familieNaam));

            const [side1, side2] = sideTexts(a, orientation);
            addCells(tr, [
                a.artnr,
                a.crossRef || '—',
                [a.familieCode, a.familieNaam].filter(Boolean).join(' – '),
                capitalize(a.hoek) || '—',
                side1,
                side2,
            ]);
            resultTableBody.appendChild(tr);
        });

        const isEmpty = matches.length === 0;
        emptyResult.hidden = !isEmpty;
        resultTableWrap.hidden = isEmpty;

        if (!isEmpty && matches.length > MAX_ROWS) {
            resultMoreNote.hidden = false;
            resultMoreNote.textContent = `Eerste ${MAX_ROWS} van ${matches.length} resultaten getoond. Verfijn de selectie voor een volledig overzicht.`;
        } else {
            resultMoreNote.hidden = true;
        }
    }

    function render() {
        const sel = readSelection();
        const matches = [];
        articles.forEach((a) => {
            const orientation = matchOrientation(a, sel);
            if (orientation) matches.push({ article: a, orientation });
        });

        if (isGrouped(sel)) {
            renderGrouped(matches);
        } else {
            renderFlat(matches);
        }
    }

    // --- event wiring --------------------------------------------------

    els.draadsoort1.addEventListener('change', () => {
        populateDraadmaat(els.draadmaat1, els.draadsoort1);
        render();
    });
    els.draadsoort2.addEventListener('change', () => {
        populateDraadmaat(els.draadmaat2, els.draadsoort2);
        render();
    });
    [els.draadmaat1, els.draadmaat2].forEach((el) => {
        el.addEventListener('change', render);
    });

    hoekButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const value = button.dataset.hoek;
            const active = !button.classList.contains('active');
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            if (active) selectedHoek.add(value);
            else selectedHoek.delete(value);
            render();
        });
    });

    // Connectie type: één keuze per aansluiting (klikken op de actieve knop
    // zet 'm weer uit, terug naar "alle").
    function wireConnectieGroup(buttons, setValue) {
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const value = button.dataset.connectie;
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
                render();
            });
        });
    }
    wireConnectieGroup(connectie1Buttons, (value) => { selectedConnectie1 = value; });
    wireConnectieGroup(connectie2Buttons, (value) => { selectedConnectie2 = value; });

    resetButton.addEventListener('click', () => {
        Object.entries(els).forEach(([key, el]) => {
            if (key === 'hoek' || key === 'connectie1' || key === 'connectie2') return;
            el.value = ALL;
        });
        selectedHoek.clear();
        hoekButtons.forEach((button) => {
            button.classList.remove('active');
            button.setAttribute('aria-pressed', 'false');
        });
        selectedConnectie1 = '';
        selectedConnectie2 = '';
        [...connectie1Buttons, ...connectie2Buttons].forEach((button) => {
            button.classList.remove('active');
            button.setAttribute('aria-pressed', 'false');
        });
        populateDraadmaat(els.draadmaat1, els.draadsoort1);
        populateDraadmaat(els.draadmaat2, els.draadsoort2);
        render();
    });

    render();
})();
