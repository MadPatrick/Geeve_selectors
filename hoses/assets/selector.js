(() => {
    'use strict';

    const articles = Array.isArray(window.ARTICLES) ? window.ARTICLES : [];
    const searchInput = document.getElementById('search');
    const werkdrukInput = document.getElementById('searchWerkdruk');
    const maatInput = document.getElementById('searchMaat');
    const suggestions = document.getElementById('suggestions');
    const result = document.getElementById('result');
    const twoPieceSection = document.getElementById('twoPieceSection');
    const twoPieceGrid = document.getElementById('twoPieceGrid');
    const onePieceSection = document.getElementById('onePieceSection');
    const onePieceGrid = document.getElementById('onePieceGrid');
    const onePieceCount = document.getElementById('onePieceCount');
    const emptyResult = document.getElementById('emptyResult');
    const accessorySection = document.getElementById('accessorySection');
    const accessoryGrid = document.getElementById('accessoryGrid');
    const pdfButton = document.getElementById('pdfButton');
    const pdfMenu = document.getElementById('pdfMenu');
    const printSheet = document.getElementById('printSheet');

    if (!searchInput || !suggestions || !result) {
        return;
    }

    const baseTitle = document.title;
    let currentMatches = [];
    let selectedIndex = -1;

    const normalize = (value) => String(value ?? '').trim().toLowerCase();

    function formatMillimetres(value) {
        const text = String(value ?? '').trim();
        if (!text) {
            return '';
        }
        return /mm$/i.test(text) ? text : `${text} mm`;
    }

    // Print-table measurements: no "mm" suffix, always exactly 1 decimal
    // (including for 0), using the comma decimal convention.
    function formatMeasurement(value) {
        const text = String(value ?? '').trim();
        if (!text) {
            return '';
        }
        const numeric = Number(text.replace(',', '.'));
        if (!Number.isFinite(numeric)) {
            return text;
        }
        return numeric.toFixed(1).replace('.', ',');
    }

    function articleMaat(artnr) {
        const text = String(artnr ?? '');
        const dashIndex = text.indexOf('-');
        // The size is the number right after the first dash (e.g. "03" in
        // "2440D-03V32", where "V32" is a separate code, not the size).
        // Article numbers without a dash (e.g. "AIR06MM") use the first
        // digit group in the whole string instead.
        const searchText = dashIndex === -1 ? text : text.slice(dashIndex + 1);
        const match = searchText.match(/(\d+)/);
        return match ? match[1] : '';
    }

    const werkdrukDatalist = document.getElementById('werkdrukOptions');

    function updateWerkdrukOptions() {
        if (!werkdrukDatalist || !maatInput) {
            return;
        }

        const maatQuery = normalize(maatInput.value);
        werkdrukDatalist.innerHTML = '';

        if (!maatQuery) {
            return;
        }

        const values = new Set();
        articles.forEach((article) => {
            if (article.werkdruk && articleMaat(article.artnr).includes(maatQuery)) {
                values.add(article.werkdruk);
            }
        });

        Array.from(values)
            .sort((a, b) => parseFloat(a.replace(',', '.')) - parseFloat(b.replace(',', '.')))
            .forEach((value) => {
                const option = document.createElement('option');
                option.value = value;
                werkdrukDatalist.appendChild(option);
            });
    }

    function closeSuggestions() {
        suggestions.innerHTML = '';
        suggestions.hidden = true;
        selectedIndex = -1;
    }

    function searchArticles() {
        const query = normalize(searchInput.value);
        const werkdrukQuery = normalize(werkdrukInput ? werkdrukInput.value : '');
        const maatQuery = normalize(maatInput ? maatInput.value : '');
        selectedIndex = -1;

        if (query) {
            currentMatches = articles
                .filter((article) => (
                    normalize(article.artnr).includes(query) ||
                    normalize(article.artnm).includes(query) ||
                    normalize(article.supplier).includes(query) ||
                    normalize(article.vendor).includes(query)
                ))
                .slice(0, 40);
        } else if (werkdrukQuery || maatQuery) {
            currentMatches = articles
                .filter((article) => (
                    (!werkdrukQuery || normalize(article.werkdruk).includes(werkdrukQuery)) &&
                    (!maatQuery || articleMaat(article.artnr).includes(maatQuery))
                ))
                .slice(0, 40);
        } else {
            currentMatches = [];
            closeSuggestions();
            result.hidden = true;
            return;
        }

        renderSuggestions();
    }

    function renderSuggestions() {
        suggestions.innerHTML = '';

        if (currentMatches.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'no-results';
            empty.textContent = 'Geen artikelen gevonden';
            suggestions.appendChild(empty);
            suggestions.hidden = false;
            return;
        }

        currentMatches.forEach((article, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'suggestion-item';
            button.dataset.index = String(index);
            button.setAttribute('role', 'option');

            const top = document.createElement('span');
            top.className = 'suggestion-top';

            const number = document.createElement('strong');
            number.className = 'suggestion-number';
            number.textContent = article.artnr || '';

            const name = document.createElement('span');
            name.className = 'suggestion-name';
            name.textContent = article.artnm || '';

            top.append(number, name);

            const meta = document.createElement('span');
            meta.className = 'suggestion-meta';
            meta.textContent = article.supplier
                ? `Leveranciersartikel: ${article.supplier}`
                : 'Leveranciersartikel: -';

            button.append(top, meta);
            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
                selectArticle(article);
            });

            suggestions.appendChild(button);
        });

        suggestions.hidden = false;
    }

    function formatSeries(value) {
        const text = String(value ?? '').trim();
        return text ? `${text}-serie` : '–';
    }

    function createFact(label, value, primary = false) {
        const text = String(value ?? '').trim() || '-';

        const fact = document.createElement('div');
        fact.className = `fitting-fact${primary ? ' primary' : ''}`;

        const labelEl = document.createElement('span');
        labelEl.textContent = label;

        const valueEl = document.createElement('strong');
        valueEl.textContent = text;
        if (text === '-') {
            valueEl.classList.add('is-empty');
        }

        fact.append(labelEl, valueEl);
        return fact;
    }

    function createFactsRow(facts, badge) {
        const row = document.createElement('div');
        row.className = 'fitting-facts';
        if (badge) {
            row.appendChild(badge);
        }
        facts.forEach((fact) => row.appendChild(fact));
        return row;
    }

    function createAccessoryFact(label, value, imageKey) {
        const text = String(value ?? '').trim() || '-';
        const item = document.createElement('div');
        item.className = 'accessory-fact';

        const textWrap = document.createElement('div');
        textWrap.className = 'accessory-fact-text';

        const labelEl = document.createElement('span');
        labelEl.textContent = label;

        const valueEl = document.createElement('strong');
        valueEl.textContent = text;
        if (text === '-') {
            valueEl.classList.add('is-empty');
        }

        textWrap.append(labelEl, valueEl);
        item.appendChild(textWrap);

        if (imageKey) {
            const imgWrap = document.createElement('div');
            imgWrap.className = 'accessory-fact-image';
            const img = document.createElement('img');
            img.src = `images/${imageKey}.png`;
            img.alt = label;
            img.loading = 'lazy';
            img.addEventListener('error', () => { imgWrap.remove(); }, { once: true });
            imgWrap.appendChild(img);
            item.appendChild(imgWrap);
            item.classList.add('has-image');
        }

        return item;
    }

    function renderAccessories(article) {
        if (!accessorySection || !accessoryGrid) {
            return false;
        }

        accessoryGrid.innerHTML = '';
        const accessories = article && article.accessories && typeof article.accessories === 'object'
            ? article.accessories
            : {};

        const fields = [
            ['Buitenmaat slang', formatMillimetres(accessories.outside), 'buitenmaat'],
            ['PolyGuard', accessories.polyGuard, null],
            ['ParKoil', accessories.parKoil, 'parkoil'],
            ['Spring Guard', accessories.springGuard, 'springguard'],
            ['Firesleeve', accessories.firesleeve, 'firesleeve'],
            ['SpiralGuard', accessories.spiralGuard, 'spiralguard'],
            ['Texsleeve', accessories.texsleeve, 'texsleeve'],
            ['Huls Texsleeve (Staal)', accessories.hulsTexStaal, '19001'],
            ['Huls Texsleeve (RVS)', accessories.hulsTexRvs, '19001'],
        ];

        const hasAccessoryData = fields.some(([, value]) => String(value ?? '').trim() !== '');
        if (!hasAccessoryData) {
            accessorySection.hidden = true;
            return false;
        }

        fields.forEach(([label, value, imageKey]) => accessoryGrid.appendChild(createAccessoryFact(label, value, imageKey)));
        accessorySection.hidden = false;
        return true;
    }

    function materialBadge(material) {
        const badge = document.createElement('span');
        badge.className = `material-badge ${material.toLowerCase()}`;
        badge.textContent = material;
        return badge;
    }

    function seriesBadge(value) {
        const badge = document.createElement('span');
        badge.className = 'series-badge';
        badge.textContent = formatSeries(value);
        return badge;
    }

    function createComboCard(variant) {
        const card = document.createElement('article');
        card.className = 'fitting-card combo-card';

        card.appendChild(createFactsRow([
            createFact('Huls', variant.huls, true),
            createFact('Persmaat', formatMillimetres(variant.persmaat)),
            createFact('Schilmaat intern', formatMillimetres(variant.schilIntern)),
            createFact('Schilmaat extern', formatMillimetres(variant.schilExtern)),
        ], seriesBadge(variant.pilaar)));

        return card;
    }

    function createCouplingCard(variant) {
        const card = document.createElement('article');
        card.className = 'fitting-card coupling-card';

        card.appendChild(createFactsRow([
            createFact('Persmaat', formatMillimetres(variant.persmaat)),
            createFact('Insteekdiepte', formatMillimetres(variant.insteekdiepte)),
            createFact('Schilmaat intern', formatMillimetres(variant.schilIntern)),
            createFact('Schilmaat extern', formatMillimetres(variant.schilExtern)),
        ], seriesBadge(variant.koppeling)));

        return card;
    }

    function addMaterialBlock(material, variants) {
        if (!Array.isArray(variants) || variants.length === 0) {
            return;
        }

        const block = document.createElement('section');
        block.className = 'material-block';

        const heading = document.createElement('div');
        heading.className = 'material-block-heading';
        heading.appendChild(materialBadge(material));

        const grid = document.createElement('div');
        grid.className = 'variant-grid';
        variants.forEach((variant) => grid.appendChild(createComboCard(variant)));

        block.append(heading, grid);
        twoPieceGrid.appendChild(block);
    }

    function renderTwoPiece(article) {
        twoPieceGrid.innerHTML = '';
        addMaterialBlock('STAAL', article.comboStaal);
        addMaterialBlock('RVS', article.comboRvs);
        twoPieceSection.hidden = twoPieceGrid.children.length === 0;
        return !twoPieceSection.hidden;
    }

    function addCouplingMaterialBlock(material, variants) {
        if (!Array.isArray(variants) || variants.length === 0) {
            return 0;
        }

        const block = document.createElement('section');
        block.className = 'material-block';

        const heading = document.createElement('div');
        heading.className = 'material-block-heading';
        heading.appendChild(materialBadge(material));

        const grid = document.createElement('div');
        grid.className = 'variant-grid';
        variants.forEach((variant) => grid.appendChild(createCouplingCard(variant)));

        block.append(heading, grid);
        onePieceGrid.appendChild(block);
        return variants.length;
    }

    function renderOnePiece(article) {
        onePieceGrid.innerHTML = '';

        const countStaal = addCouplingMaterialBlock('STAAL', article.koppelingStaal);
        const countRvs = addCouplingMaterialBlock('RVS', article.koppelingRvs);
        const total = countStaal + countRvs;

        onePieceCount.textContent = total === 1
            ? '1 mogelijkheid'
            : `${total} mogelijkheden`;
        onePieceSection.hidden = total === 0;
        return total > 0;
    }

    function fieldValue(value) {
        const text = String(value ?? '').trim();
        return text === '' ? '-' : text;
    }

    function hoseTypeInfo(artnr) {
        const text = String(artnr ?? '').trim();

        // Parker multi-spiral hoses (SR25/SR29/SR35/SR45/SRI42) share one
        // combined "SR/SRI" group regardless of the number, with ST/TC/SN
        // suffixes merged the same way as the digit-led series below.
        const srMatch = /^(SRI?)(\d+)([A-Za-z]*)/i.exec(text);
        if (srMatch) {
            const family = srMatch[1].toUpperCase();
            const digits = srMatch[2];
            const suffix = srMatch[3] || '';
            const isMerged = /^(ST|TC|SN)$/i.test(suffix);

            return {
                type: 'SR/SRI',
                rawPrefix: family + digits + suffix.toUpperCase(),
                displayArtnr: isMerged ? family + digits + text.slice(srMatch[0].length) : text,
            };
        }

        // PLK*TC/PLK*ST/PLK*SN article numbers carry a per-row number right
        // after "PLK" that has nothing to do with the hose type (it doesn't
        // vary by size in a meaningful way) - group them all under one "PLK"
        // type regardless of that number, same idea as SR/SRI above.
        const plkMatch = /^(PLK)\d+(ST|TC|SN)?/i.exec(text);
        if (plkMatch) {
            const suffix = plkMatch[2] || '';
            return {
                type: 'PLK',
                rawPrefix: plkMatch[0].toUpperCase(),
                displayArtnr: text,
            };
        }

        // Everything else: split the part before the first dash into an
        // optional leading letter prefix, an optional digit run and a
        // trailing letter suffix - this covers digit-led series
        // (0492-.., 0492ST-.., 301SN-..), letter+digit series (R42-..,
        // H29TC-..) and pure-letter series with no digits at all (BPK-..,
        // PDH-..). An ST/TC/SN suffix right before the dash is merged into
        // the bare series (same convention throughout this app); everything
        // else (a different letter prefix, or a non-ST/TC/SN suffix like
        // PU/RH/LT) stays its own type.
        const dashIndex = text.indexOf('-');
        const prefix = dashIndex === -1 ? text : text.slice(0, dashIndex);
        const rest = dashIndex === -1 ? '' : text.slice(dashIndex);
        const match = /^([A-Za-z]*)(\d*)([A-Za-z]*)$/.exec(prefix);
        if (!match || (!match[1] && !match[2])) {
            return { type: text || '-', rawPrefix: text || '-', displayArtnr: text || '-' };
        }

        const letterPrefix = match[1] || '';
        const digits = match[2] || '';
        const suffix = match[3] || '';
        const isMerged = /^(ST|TC|SN)$/i.test(suffix);
        const core = letterPrefix.toUpperCase() + digits;

        return {
            type: isMerged ? core : core + suffix,
            rawPrefix: core + suffix,
            displayArtnr: isMerged ? core + rest : text,
        };
    }

    function groupArticlesByType(materialKey) {
        const groups = new Map();
        articles.forEach((article) => {
            const variants = Array.isArray(article[materialKey]) ? article[materialKey] : [];
            if (variants.length === 0) {
                return;
            }

            const { type, rawPrefix, displayArtnr } = hoseTypeInfo(article.artnr);
            if (!groups.has(type)) {
                groups.set(type, { rawPrefixes: new Set(), entries: [] });
            }
            const group = groups.get(type);
            group.rawPrefixes.add(rawPrefix);
            group.entries.push({ article, variants, displayArtnr });
        });
        return groups;
    }

    function buildTableHead(headerRows) {
        const thead = document.createElement('thead');
        headerRows.forEach((cells) => {
            const tr = document.createElement('tr');
            cells.forEach(({ text, colSpan, rowSpan }) => {
                const th = document.createElement('th');
                th.textContent = text;
                if (colSpan) {
                    th.colSpan = colSpan;
                }
                if (rowSpan) {
                    th.rowSpan = rowSpan;
                }
                tr.appendChild(th);
            });
            thead.appendChild(tr);
        });
        return thead;
    }

    function buildTypeRow(rawPrefixes, columnCount) {
        const tr = document.createElement('tr');
        tr.className = 'print-type-row';
        const td = document.createElement('td');
        td.colSpan = columnCount;
        td.textContent = `Type ${Array.from(rawPrefixes).join('/')}`;
        tr.appendChild(td);
        return tr;
    }

    function buildDataRow(cells) {
        const tr = document.createElement('tr');
        cells.forEach((cell) => {
            const td = document.createElement('td');
            td.textContent = cell;
            tr.appendChild(td);
        });
        return tr;
    }

    // Rows are duplicates when every column except the description matches -
    // that happens when an ST/TC/base variant was merged into the same type
    // and shares identical fitting specs for that dash size.
    function dedupeKey(cells) {
        return cells.filter((_, index) => index !== 1).join('');
    }

    function appendUniqueRows(tbody, rows) {
        const seen = new Set();
        rows.forEach((cells) => {
            const key = dedupeKey(cells);
            if (seen.has(key)) {
                return;
            }
            seen.add(key);
            tbody.appendChild(buildDataRow(cells));
        });
    }

    function buildCouplingTable(groups) {
        const headers = ['Artikelnummer', 'Omschrijving', 'Koppeling', 'Persmaat', 'Insteekdiepte', 'Schilmaat intern', 'Schilmaat extern'];
        const table = document.createElement('table');
        table.className = 'print-table';
        table.appendChild(buildTableHead([headers.map((text) => ({ text }))]));

        const tbody = document.createElement('tbody');
        groups.forEach(({ rawPrefixes, entries }) => {
            const rows = [];
            entries.forEach(({ article, variants, displayArtnr }) => {
                variants.forEach((variant) => {
                    rows.push([
                        fieldValue(displayArtnr),
                        fieldValue(article.artnm),
                        fieldValue(variant.koppeling),
                        fieldValue(formatMeasurement(variant.persmaat)),
                        fieldValue(formatMeasurement(variant.insteekdiepte)),
                        fieldValue(formatMeasurement(variant.schilIntern)),
                        fieldValue(formatMeasurement(variant.schilExtern)),
                    ]);
                });
            });

            tbody.appendChild(buildTypeRow(rawPrefixes, headers.length));
            appendUniqueRows(tbody, rows);
        });
        table.appendChild(tbody);
        return table;
    }

    function buildComboTable(groups) {
        const fieldLabels = ['Huls', 'Pilaar', 'Persmaat', 'Schilmaat intern', 'Schilmaat extern'];
        const table = document.createElement('table');
        table.className = 'print-table';
        table.appendChild(buildTableHead([
            [
                { text: 'Artikelnummer', rowSpan: 2 },
                { text: 'Omschrijving', rowSpan: 2 },
                { text: 'Huls 1', colSpan: fieldLabels.length },
                { text: 'Huls 2', colSpan: fieldLabels.length },
            ],
            [...fieldLabels, ...fieldLabels].map((text) => ({ text })),
        ]));

        const columnCount = 2 + fieldLabels.length * 2;
        const tbody = document.createElement('tbody');
        groups.forEach(({ rawPrefixes, entries }) => {
            const rows = [];
            entries.forEach(({ article, variants, displayArtnr }) => {
                const v1 = variants.find((variant) => variant.number === 1) || {};
                const v2 = variants.find((variant) => variant.number === 2) || {};
                rows.push([
                    fieldValue(displayArtnr),
                    fieldValue(article.artnm),
                    fieldValue(v1.huls),
                    fieldValue(v1.pilaar),
                    fieldValue(formatMeasurement(v1.persmaat)),
                    fieldValue(formatMeasurement(v1.schilIntern)),
                    fieldValue(formatMeasurement(v1.schilExtern)),
                    fieldValue(v2.huls),
                    fieldValue(v2.pilaar),
                    fieldValue(formatMeasurement(v2.persmaat)),
                    fieldValue(formatMeasurement(v2.schilIntern)),
                    fieldValue(formatMeasurement(v2.schilExtern)),
                ]);
            });

            tbody.appendChild(buildTypeRow(rawPrefixes, columnCount));
            appendUniqueRows(tbody, rows);
        });
        table.appendChild(tbody);
        return table;
    }

    function groupAccessoryArticlesByType() {
        const groups = new Map();
        articles.forEach((article) => {
            const accessories = article && typeof article.accessories === 'object' && article.accessories !== null
                ? article.accessories
                : {};
            const values = [
                formatMillimetres(accessories.outside),
                accessories.polyGuard,
                accessories.parKoil,
                accessories.springGuard,
                accessories.firesleeve,
                accessories.spiralGuard,
                accessories.texsleeve,
                accessories.hulsTexStaal,
                accessories.hulsTexRvs,
            ];
            const hasData = values.some((value) => String(value ?? '').trim() !== '');
            if (!hasData) {
                return;
            }

            const { type, rawPrefix, displayArtnr } = hoseTypeInfo(article.artnr);
            if (!groups.has(type)) {
                groups.set(type, { rawPrefixes: new Set(), entries: [] });
            }
            const group = groups.get(type);
            group.rawPrefixes.add(rawPrefix);
            group.entries.push({ article, displayArtnr, values });
        });
        return groups;
    }

    function buildAccessoryTable(groups) {
        const headers = ['Artikelnummer', 'Omschrijving', 'Buitenmaat slang', 'PolyGuard', 'ParKoil', 'Spring Guard', 'Firesleeve', 'SpiralGuard', 'Texsleeve', 'Huls Texsleeve (Staal)', 'Huls Texsleeve (RVS)'];
        const table = document.createElement('table');
        table.className = 'print-table';
        table.appendChild(buildTableHead([headers.map((text) => ({ text }))]));

        const tbody = document.createElement('tbody');
        groups.forEach(({ rawPrefixes, entries }) => {
            const rows = entries.map(({ article, displayArtnr, values }) => [
                fieldValue(displayArtnr),
                fieldValue(article.artnm),
                ...values.map(fieldValue),
            ]);

            tbody.appendChild(buildTypeRow(rawPrefixes, headers.length));
            appendUniqueRows(tbody, rows);
        });
        table.appendChild(tbody);
        return table;
    }

    // `chapterLabel`, when given, is shown bold in the top-right corner of
    // the page header so every printed page makes clear which chapter
    // (Accessoires / 1-delig / 2-delig / Staal / RVS) it belongs to.
    function buildPrintHeader(chapterLabel) {
        const header = document.createElement('div');
        header.className = 'print-header';
        const brand = document.createElement('div');
        brand.className = 'print-brand';
        brand.innerHTML = '<strong>GEEVE</strong><strong class="hydraulics">HYDRAULICS</strong>';
        header.appendChild(brand);

        if (chapterLabel) {
            const label = document.createElement('div');
            label.className = 'print-header-chapter';
            label.textContent = chapterLabel;
            header.appendChild(label);
        }

        return header;
    }

    // A full divider page with the chapter name shown large - always
    // immediately followed by that chapter's own content page(s).
    function buildDividerPage(groupName) {
        const divider = document.createElement('div');
        divider.className = 'print-divider';
        divider.appendChild(buildPrintHeader(groupName));

        const title = document.createElement('div');
        title.className = 'print-divider-title';
        title.textContent = groupName;
        divider.appendChild(title);

        return divider;
    }

    // A divider page followed by its content section (table), both tagged
    // with the same chapter label in the running header.
    function buildChapterPages(chapterLabel, titleText, table) {
        const fragment = document.createDocumentFragment();
        fragment.appendChild(buildDividerPage(chapterLabel));

        const section = document.createElement('div');
        section.className = 'print-section';
        section.appendChild(buildPrintHeader(chapterLabel));
        if (titleText) {
            const heading = document.createElement('h3');
            heading.textContent = titleText;
            section.appendChild(heading);
        }
        section.appendChild(table);
        fragment.appendChild(section);

        return fragment;
    }

    function addMaterialChapter(material, couplingGroups, comboGroups) {
        if (couplingGroups.size === 0 && comboGroups.size === 0) {
            return false;
        }

        printSheet.appendChild(buildDividerPage(material));

        if (couplingGroups.size > 0) {
            printSheet.appendChild(buildChapterPages('1-delig', '1-delige koppelingen', buildCouplingTable(couplingGroups)));
        }

        if (comboGroups.size > 0) {
            printSheet.appendChild(buildChapterPages('2-delig', '2-delige koppelingen', buildComboTable(comboGroups)));
        }

        return true;
    }

    // scope: 'all' (default), 'accessoires', 'staal' or 'rvs' - limits the
    // printed catalogue to just that chapter for the matching download
    // option.
    function renderCatalogPrintSheet(scope) {
        if (!printSheet) {
            return;
        }
        const effectiveScope = scope || 'all';

        printSheet.innerHTML = '';

        let hasAnyChapter = false;

        if (effectiveScope === 'all' || effectiveScope === 'accessoires') {
            const accessoryGroups = groupAccessoryArticlesByType();
            if (accessoryGroups.size > 0) {
                printSheet.appendChild(buildChapterPages('Accessoires', null, buildAccessoryTable(accessoryGroups)));
                hasAnyChapter = true;
            }
        }

        if (effectiveScope === 'all' || effectiveScope === 'staal') {
            if (addMaterialChapter('STAAL', groupArticlesByType('koppelingStaal'), groupArticlesByType('comboStaal'))) {
                hasAnyChapter = true;
            }
        }

        if (effectiveScope === 'all' || effectiveScope === 'rvs') {
            if (addMaterialChapter('RVS', groupArticlesByType('koppelingRvs'), groupArticlesByType('comboRvs'))) {
                hasAnyChapter = true;
            }
        }

        if (!hasAnyChapter) {
            const empty = document.createElement('p');
            empty.className = 'print-empty';
            empty.textContent = 'Er zijn geen artikelen beschikbaar.';
            printSheet.appendChild(empty);
        }

        const footer = document.createElement('div');
        footer.className = 'print-footer';
        footer.textContent = 'Geeve Hydraulics — know how in hydraulics';
        printSheet.appendChild(footer);
    }

    function selectArticle(article) {
        searchInput.value = article.artnr || '';
        if (werkdrukInput) {
            werkdrukInput.value = '';
        }
        if (maatInput) {
            maatInput.value = '';
        }
        updateWerkdrukOptions();
        closeSuggestions();

        document.getElementById('resultArtnr').textContent = article.artnr || '-';
        document.getElementById('resultArtnm').textContent = article.artnm || '-';
        document.getElementById('resultVendor').textContent = article.vendor || '-';
        document.getElementById('resultSupplier').textContent = article.supplier || '-';
        document.getElementById('resultWerkdruk').textContent = article.werkdruk
            ? `${article.werkdruk} bar`
            : '-';

        renderAccessories(article);
        const hasOnePiece = renderOnePiece(article);
        const hasTwoPiece = renderTwoPiece(article);

        emptyResult.hidden = hasTwoPiece || hasOnePiece;
        result.hidden = false;
        result.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function updateKeyboardSelection(items) {
        items.forEach((item) => item.classList.remove('selected'));
        if (selectedIndex >= 0 && items[selectedIndex]) {
            items[selectedIndex].classList.add('selected');
            items[selectedIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    searchInput.addEventListener('input', searchArticles);
    searchInput.addEventListener('focus', () => {
        if (searchInput.value.trim()) {
            searchArticles();
        }
    });

    if (werkdrukInput) {
        werkdrukInput.addEventListener('input', searchArticles);
        werkdrukInput.addEventListener('focus', () => {
            if (werkdrukInput.value.trim() || (maatInput && maatInput.value.trim())) {
                searchArticles();
            }
        });
    }

    if (maatInput) {
        maatInput.addEventListener('input', () => {
            updateWerkdrukOptions();
            searchArticles();
        });
        maatInput.addEventListener('focus', () => {
            if (maatInput.value.trim() || (werkdrukInput && werkdrukInput.value.trim())) {
                searchArticles();
            }
        });
    }

    function handleSearchKeydown(event) {
        const items = suggestions.querySelectorAll('.suggestion-item');

        if (event.key === 'ArrowDown' && items.length > 0) {
            event.preventDefault();
            selectedIndex = (selectedIndex + 1) % items.length;
            updateKeyboardSelection(items);
            return;
        }

        if (event.key === 'ArrowUp' && items.length > 0) {
            event.preventDefault();
            selectedIndex = selectedIndex <= 0 ? items.length - 1 : selectedIndex - 1;
            updateKeyboardSelection(items);
            return;
        }

        if (event.key === 'Enter') {
            if (selectedIndex >= 0 && currentMatches[selectedIndex]) {
                event.preventDefault();
                selectArticle(currentMatches[selectedIndex]);
            } else if (currentMatches.length === 1) {
                event.preventDefault();
                selectArticle(currentMatches[0]);
            }
            return;
        }

        if (event.key === 'Escape') {
            closeSuggestions();
        }
    }

    searchInput.addEventListener('keydown', handleSearchKeydown);
    if (maatInput) {
        maatInput.addEventListener('keydown', handleSearchKeydown);
    }
    // Werkdruk has its own native datalist pulldown, which also uses the
    // arrow keys - attaching the custom suggestion-list navigation here
    // would intercept those keys and break the browser's own dropdown.

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.search-row')) {
            closeSuggestions();
        }
    });

    const scopeTitles = {
        all: 'Geeve Hydraulics - Slangen fitting catalogus',
        accessoires: 'Geeve Hydraulics - Accessoires',
        staal: 'Geeve Hydraulics - Staal',
        rvs: 'Geeve Hydraulics - RVS',
    };

    function closePdfMenu() {
        if (!pdfMenu || !pdfButton) {
            return;
        }
        pdfMenu.hidden = true;
        pdfButton.setAttribute('aria-expanded', 'false');
    }

    function openPdfMenu() {
        if (!pdfMenu || !pdfButton) {
            return;
        }
        pdfMenu.hidden = false;
        pdfButton.setAttribute('aria-expanded', 'true');
    }

    function printCatalog(scope) {
        document.title = scopeTitles[scope] || scopeTitles.all;
        renderCatalogPrintSheet(scope);
        window.print();
    }

    if (pdfButton && pdfMenu) {
        pdfButton.addEventListener('click', (event) => {
            event.stopPropagation();
            if (pdfMenu.hidden) {
                openPdfMenu();
            } else {
                closePdfMenu();
            }
        });

        pdfMenu.querySelectorAll('.pdf-menu-item').forEach((item) => {
            item.addEventListener('click', () => {
                closePdfMenu();
                printCatalog(item.dataset.scope || 'all');
            });
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.pdf-dropdown')) {
                closePdfMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePdfMenu();
            }
        });
    }

    window.addEventListener('afterprint', () => {
        document.title = baseTitle;
    });
})();
