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
    const resetButton = document.getElementById('resetButton');
    const resultCount = document.getElementById('resultCount');
    const resultTableBody = document.getElementById('resultTableBody');
    const resultTableWrap = document.getElementById('resultTableWrap');
    const resultMoreNote = document.getElementById('resultMoreNote');
    const emptyResult = document.getElementById('emptyResult');

    const MAX_ROWS = 300;
    const ALL = '';

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
            option.textContent = value;
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

    const connectieOrder = ['buiten', 'binnen', 'wartelend'];
    const connectieValues = uniqueSorted(
        articles.flatMap((a) => [a.connectieType1, a.connectieType2]),
        (a, b) => connectieOrder.indexOf(a) - connectieOrder.indexOf(b)
    );

    const hoekOrder = ['recht', 'haaks', '45°', 'T-stuk', 'kruis', 'n.v.t.'];
    const hoekValues = uniqueSorted(
        articles.map((a) => a.hoek),
        (a, b) => hoekOrder.indexOf(a) - hoekOrder.indexOf(b)
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
    fillSelect(els.connectie1, connectieValues, 'Alle');
    fillSelect(els.connectie2, connectieValues, 'Alle');
    fillSelect(els.hoek, hoekValues, 'Alle');

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
            ct1: els.connectie1.value,
            ds2: els.draadsoort2.value,
            dm2: els.draadmaat2.value,
            ct2: els.connectie2.value,
            hoek: els.hoek.value,
        };
    }

    // Aansluiting 1/2 are interchangeable when matching (the user shouldn't have
    // to know which physical end the catalog happened to store as "position 1"),
    // but the result table still needs to show each matched article's data under
    // the SAME column the user filtered it by - so this returns which orientation
    // matched ('direct': article position 1 -> Aansluiting 1, or 'swapped': article
    // position 2 -> Aansluiting 1), not just whether it matched.
    function matchOrientation(article, sel) {
        if (sel.hoek && article.hoek !== sel.hoek) return null;

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
        return connectie ? `${parts} (${connectie})` : parts;
    }

    function render() {
        const sel = readSelection();
        const matches = [];
        articles.forEach((a) => {
            const orientation = matchOrientation(a, sel);
            if (orientation) matches.push({ article: a, orientation });
        });

        resultCount.textContent = matches.length + (matches.length === 1 ? ' adapter' : ' adapters');

        resultTableBody.innerHTML = '';
        const shown = matches.slice(0, MAX_ROWS);
        shown.forEach(({ article: a, orientation }) => {
            const tr = document.createElement('tr');

            const imgTd = document.createElement('td');
            imgTd.className = 'result-table-image-col';
            if (a.familieCode) {
                const img = document.createElement('img');
                img.src = `images/${a.familieCode}.png`;
                img.alt = a.familieNaam || a.familieCode;
                img.loading = 'lazy';
                img.addEventListener('error', () => { imgTd.replaceChildren(); }, { once: true });
                imgTd.appendChild(img);
            }
            tr.appendChild(imgTd);

            const side1 = orientation === 'swapped'
                ? formatSide(a.draadsoort2, a.draadmaat2, a.connectieType2)
                : formatSide(a.draadsoort1, a.draadmaat1, a.connectieType1);
            const side2 = orientation === 'swapped'
                ? formatSide(a.draadsoort1, a.draadmaat1, a.connectieType1)
                : formatSide(a.draadsoort2, a.draadmaat2, a.connectieType2);

            const cells = [
                a.artnr,
                a.crossRef || '—',
                [a.familieCode, a.familieNaam].filter(Boolean).join(' – '),
                a.hoek || '—',
                side1,
                side2,
            ];
            cells.forEach((text) => {
                const td = document.createElement('td');
                td.textContent = text;
                tr.appendChild(td);
            });
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

    // --- event wiring --------------------------------------------------

    els.draadsoort1.addEventListener('change', () => {
        populateDraadmaat(els.draadmaat1, els.draadsoort1);
        render();
    });
    els.draadsoort2.addEventListener('change', () => {
        populateDraadmaat(els.draadmaat2, els.draadsoort2);
        render();
    });
    [els.draadmaat1, els.connectie1, els.draadmaat2, els.connectie2, els.hoek].forEach((el) => {
        el.addEventListener('change', render);
    });

    resetButton.addEventListener('click', () => {
        Object.values(els).forEach((el) => { el.value = ALL; });
        populateDraadmaat(els.draadmaat1, els.draadsoort1);
        populateDraadmaat(els.draadmaat2, els.draadsoort2);
        render();
    });

    render();
})();
