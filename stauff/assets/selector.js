(() => {
    'use strict';

    const state = {
        rows: [],
        validRows: [],
        clamps: [],
        selectedClamp: null,
        metalFamily: 'Staal',
    };

    const el = id => document.getElementById(id);
    const ui = {
        dataStatus: el('dataStatus'),
        resultCount: el('resultCount'),
        diameterInput: el('diameterInput'),
        suggestions: el('diameterSuggestions'),
        serie: el('serieSelect'),
        serieButtons: [...document.querySelectorAll('[data-serie]')],
        execution: el('uitvoeringSelect'),
        executionButtons: [...document.querySelectorAll('[data-uitvoering]')],
        clampMaterial: el('clampMaterialSelect'),
        clamp: el('clampSelect'),
        materialCode: el('materialCodeSelect'),
        loc1: el('location1Select'),
        loc2: el('location2Value'),
        loc3: el('location3Select'),
        loc4: el('location4Select'),
        loc5: el('location5Select'),
        loc6: el('location6Value'),
        assemblyCode: el('assemblyCode'),
        copyButton: el('copyButton'),
        codeHint: el('codeHint'),
        warningBox: el('warningBox'),
        factGroup: el('factGroup'),
        factDiameter: el('factDiameter'),
        factSerie: el('factSerie'),
        factExecution: el('factExecution'),
        clampFacts: el('clampFacts'),
        shapeImg1: el('shapeImg1'),
        shapeImg2: el('shapeImg2'),
        shapeImg3: el('shapeImg3'),
        shapeImg4: el('shapeImg4'),
        shapeImg5: el('shapeImg5'),
    };

    const norm = value => String(value ?? '').trim();
    const upper = value => norm(value).toUpperCase();
    const unique = arr => [...new Set(arr.filter(v => norm(v) !== ''))];


    function setCompactWidth(element, text, minCh, extraCh, maxCh) {
        if (!element) return;
        const chars = Math.max(minCh, Math.min(maxCh, norm(text).length + extraCh));
        element.style.width = `${chars}ch`;
    }

    function resizeDiameterField() {
        const text = norm(ui.diameterInput.value) || '';
        // Minimaal 8 zichtbare tekens. De extra 28px compenseert padding/border,
        // zodat 8ch niet door de box-sizing kleiner wordt dan bedoeld.
        const chars = Math.max(8, Math.min(14, text.length + 2));
        const holder = ui.diameterInput.closest('.autocomplete');
        if (holder) holder.style.width = `calc(${chars}ch + 28px)`;
    }

    function resizeClampField() {
        // Beugel vult samen met de andere hoofdselecties de resterende rijbreedte.
        ui.clamp.style.width = '100%';
    }

    function resizeClampMaterialField() {
        // Beugelmateriaal vult samen met de andere hoofdselecties de rijbreedte.
        ui.clampMaterial.style.width = '100%';
    }

    function compareDiameter(a, b) {
        const n = v => Number(String(v).replace(',', '.'));
        const na = n(a), nb = n(b);
        if (Number.isFinite(na) && Number.isFinite(nb) && na !== nb) return na - nb;
        return String(a).localeCompare(String(b), 'nl', { numeric: true });
    }

    function displaySerie(v) {
        return v === 'Licht (standaard)' ? 'Licht' : v;
    }


    function syncChoiceButtons(buttons, availableValues, selectedValue, displayFn = v => v) {
        const availableLabels = availableValues.map(v => displayFn(v));
        const selectedLabel = displayFn(selectedValue);
        buttons.forEach(button => {
            const choice = button.dataset.serie || button.dataset.uitvoering || '';
            const enabled = availableLabels.includes(choice);
            button.disabled = !enabled;
            button.classList.toggle('active', enabled && choice === selectedLabel);
            button.setAttribute('aria-checked', enabled && choice === selectedLabel ? 'true' : 'false');
        });
    }

    function actualSerieValue(label) {
        return [...ui.serie.options]
            .map(option => option.value)
            .find(value => value && displaySerie(value) === label) || '';
    }

    function metalFamily(code) {
        const c = upper(code);
        if (/^W(?:1|2|3)(?:$|\/)/.test(c)) return 'Staal';
        if (/^W(?:4|5|55)(?:$|\/)/.test(c)) return 'RVS';
        return 'Overig';
    }

    function firstCodePart(article) {
        const s = norm(article);
        const match = s.match(/^([A-Z+]+)/i);
        return match ? match[1].toUpperCase() : s.split(/[\s-]/)[0].toUpperCase();
    }

    const SHAPE_IMAGE_DIR = 'images/';

    // Welk plaatje (images/<key>.png) hoort bij een gekozen rij. Lasplaat en
    // Dekplaat hebben zelf geen "Enkel / Dubbel"-waarde in de brondata die
    // een dubbele uitvoering onderscheidt (GD daargelaten); of het om een
    // dubbele samenstelling gaat wordt daarom afgeleid van de gekozen beugel
    // zelf. sp1d (lasplaat), 1dpp (beugel) en gd1 (dekplaat) horen zo als
    // vast drietal bij elkaar zodra de beugel dubbel is.
    function isDubbelClamp() {
        return !!state.selectedClamp && norm(state.selectedClamp['Enkel / Dubbel']) === 'Dubbel';
    }

    function shapeImageKey(row) {
        if (!row) return null;
        const onderdeel = row['Onderdeel'];
        const bouwgroep = upper(row['Bouwgroep']);
        const serie = row['Serie'];
        const ed = row['Enkel / Dubbel'];
        const prefix = firstCodePart(row['Artikelcode']);

        switch (onderdeel) {
            case 'Glijmoer':
                return 'gmv';
            case 'Lasplaat':
                if (isDubbelClamp()) return 'sp1d';
                if (prefix === 'SP') return bouwgroep === '1' ? 'sp1' : 'sp1a';
                if (prefix === 'SPAL') return 'sp1a';
                if (prefix === 'SPV') return bouwgroep === '1' ? 'spv1' : 'spv1a';
                return null;
            case 'Beugel':
                if (ed === 'Dubbel') return '1dpp';
                return (bouwgroep === '1' && serie === 'Licht (standaard)') ? '1pp' : '1app';
            case 'Dekplaat':
                if (isDubbelClamp() || prefix === 'GD') return 'gd1';
                return bouwgroep === '1' ? 'dp1' : 'dp1a';
            case 'Borgplaat':
                return prefix === 'SIG' ? 'sig' : null;
            case 'Stapelbout':
                return 'af';
            case 'Inbusbout':
                return 'is';
            case 'Zeskantbout':
                return 'as';
            default:
                // Lasplaat (hoek): geen plaatje beschikbaar.
                return null;
        }
    }

    function setShapeImage(imgEl, row) {
        if (!imgEl) return;
        const slot = imgEl.closest('.image-slot');
        const key = shapeImageKey(row);
        if (!key) {
            imgEl.hidden = true;
            imgEl.removeAttribute('src');
            if (slot) slot.classList.add('is-empty');
            return;
        }
        imgEl.hidden = false;
        imgEl.alt = key;
        imgEl.src = `${SHAPE_IMAGE_DIR}${key}.png`;
        if (slot) slot.classList.remove('is-empty');
    }

    function updateShapeImages() {
        setShapeImage(ui.shapeImg1, selectedRow(ui.loc1));
        setShapeImage(ui.shapeImg2, state.selectedClamp);
        setShapeImage(ui.shapeImg3, selectedRow(ui.loc3));
        setShapeImage(ui.shapeImg4, selectedRow(ui.loc4));
        setShapeImage(ui.shapeImg5, selectedRow(ui.loc5));
    }

    function groupMatches(componentGroup, clampGroup) {
        let component = upper(componentGroup).replace(/\s+/g, '');
        let clamp = upper(clampGroup).replace(/\s+/g, '');
        if (!component || !clamp) return false;
        if (component === clamp) return true;

        // Values such as 1/1A or 1/2 mean that the component is usable for
        // more than one group. A D marker indicates twin/double and is not
        // part of the numeric group comparison here.
        const tokens = component.split('/').map(v => v.replace(/D$/i, ''));
        const normalizedClamp = clamp.replace(/D$/i, '');
        if (tokens.includes(normalizedClamp)) return true;

        // Ranges such as 3-5 (used for GMV) cover all groups in that range.
        for (const token of tokens) {
            const m = token.match(/^(\d+)-(\d+)$/);
            if (m) {
                const g = Number(normalizedClamp.replace(/[^0-9].*$/, ''));
                if (Number.isFinite(g) && g >= Number(m[1]) && g <= Number(m[2])) return true;
            }
        }
        return false;
    }

    function executionMatches(row, clamp) {
        const component = norm(row['Onderdeel']);

        // Lasplaten, dekplaten en bouten zijn niet afhankelijk van de
        // Enkel/Dubbel-uitvoering van de gekozen beugel. Voor deze onderdelen
        // filteren we uitsluitend op bouwgroep, serie en materiaalcode.
        const ignoreExecutionFor = new Set([
            'Lasplaat',
            'Lasplaat (hoek)',
            'Dekplaat',
            'Stapelbout',
            'Inbusbout',
            'Zeskantbout',
        ]);
        if (ignoreExecutionFor.has(component)) return true;

        const part = norm(row['Enkel / Dubbel']);
        return !part || part === norm(clamp['Enkel / Dubbel']);
    }

    function seriesMatches(row, clamp) {
        return norm(row['Serie']) === norm(clamp['Serie']);
    }

    function candidatesForPosition(position, materialCode = '') {
        if (!state.selectedClamp) return [];
        const clamp = state.selectedClamp;
        return state.validRows.filter(row => {
            const pos = Number(position);
            const prefix = firstCodePart(row['Artikelcode']);
            const rowPos = Number(row['Positie']);

            // Harde scheiding tussen lasplaat en dekplaat. Dit voorkomt dat
            // een foutieve CSV-classificatie DPAS/DPAD ooit in locatie 1 kan
            // laten verschijnen. SP/SPAL/SPV/WSP blijven uitsluitend lasplaten.
            if (pos === 1) {
                if (prefix === 'DPAS' || prefix === 'DPAD') return false;
                const isKnownLocation1 = ['SP', 'SPAL', 'SPV', 'WSP', 'GMV'].includes(prefix);
                if (rowPos !== 1 && !isKnownLocation1) return false;
                if (!isKnownLocation1 && !norm(row['Onderdeel']).startsWith('Lasplaat') && norm(row['Onderdeel']) !== 'Glijmoer') return false;
            } else if (pos === 4) {
                if (['SP', 'SPAL', 'SPV', 'WSP', 'GMV'].includes(prefix)) return false;
                if (rowPos !== 4) return false;
            } else if (rowPos !== pos) {
                return false;
            }

            if (row['Onderdeel'] === 'Beugel') return false;
            if (!groupMatches(row['Bouwgroep'], clamp['Bouwgroep'])) return false;
            if (!seriesMatches(row, clamp)) return false;
            if (!executionMatches(row, clamp)) return false;

            // Lasplaten (positie 1) worden op materiaalFAMILIE gefilterd,
            // niet op exact dezelfde W-code als locatie 6. Bijvoorbeeld
            // SPAL 8 bestaat voor staal als W1/W2 en voor RVS als W4/W5.
            if (pos === 1) {
                if (metalFamily(row['Materiaalcode']) !== state.metalFamily) return false;
            } else if (materialCode && upper(row['Materiaalcode']) !== upper(materialCode)) {
                return false;
            }
            return true;
        });
    }

    function setSelectOptions(select, options, placeholder, labelFn = v => v, keepValue = true) {
        const old = keepValue ? select.value : '';
        select.innerHTML = '';
        const ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder;
        select.appendChild(ph);
        options.forEach(option => {
            const opt = document.createElement('option');
            const value = typeof option === 'object' ? norm(option['Artikelcode']) : norm(option);
            opt.value = value;
            opt.textContent = labelFn(option);
            select.appendChild(opt);
        });
        select.disabled = options.length === 0;
        if (old && [...select.options].some(o => o.value === old)) select.value = old;
    }

    function allDiameters() {
        return unique(state.clamps.flatMap(row => [row['Diameter 1'], row['Diameter 2']]))
            .sort(compareDiameter);
    }

    function exactDiameter() {
        const typed = norm(ui.diameterInput.value).replace('.', ',');
        return allDiameters().find(d => norm(d).replace('.', ',') === typed) || '';
    }

    function clampMatchesDiameter(row, diameter) {
        const d = norm(diameter).replace('.', ',');
        return [row['Diameter 1'], row['Diameter 2']]
            .map(v => norm(v).replace('.', ','))
            .includes(d);
    }

    function showDiameterSuggestions() {
        const typed = norm(ui.diameterInput.value).toLowerCase().replace('.', ',');
        const values = allDiameters().filter(d => !typed || norm(d).toLowerCase().replace('.', ',').startsWith(typed)).slice(0, 12);
        ui.suggestions.innerHTML = '';
        if (!values.length || (values.length === 1 && norm(values[0]) === norm(ui.diameterInput.value))) {
            ui.suggestions.hidden = true;
            return;
        }
        values.forEach(value => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = `${value} mm`;
            button.addEventListener('mousedown', event => {
                event.preventDefault();
                ui.diameterInput.value = value;
                resizeDiameterField();
                ui.suggestions.hidden = true;
                rebuildClampFilters(true);
            });
            ui.suggestions.appendChild(button);
        });
        ui.suggestions.hidden = false;
    }

    function filteredClamps(stage = 'all') {
        const diameter = exactDiameter();
        if (!diameter) return [];
        let rows = state.clamps.filter(r => clampMatchesDiameter(r, diameter));
        if (stage === 'diameter') return rows;
        if (ui.serie.value) rows = rows.filter(r => norm(r['Serie']) === ui.serie.value);
        if (stage === 'serie') return rows;
        if (ui.execution.value) rows = rows.filter(r => norm(r['Enkel / Dubbel']) === ui.execution.value);
        if (stage === 'execution') return rows;
        if (ui.clampMaterial.value) rows = rows.filter(r => norm(r['Materiaalcode']) === ui.clampMaterial.value);
        return rows;
    }

    function rebuildClampFilters(resetDownstream = false) {
        const diameter = exactDiameter();
        if (!diameter) {
            resetClampArea('Kies een diameter uit de voorstellen');
            return;
        }

        let rows = filteredClamps('diameter');
        const series = unique(rows.map(r => norm(r['Serie']))).sort();
        setSelectOptions(ui.serie, series, 'Kies serie', displaySerie, !resetDownstream);
        // Standaard: lichte serie, zolang die voor de gekozen diameter bestaat.
        if (!ui.serie.value) {
            const preferred = series.find(v => v === 'Licht (standaard)') || series.find(v => displaySerie(v) === 'Licht');
            if (preferred) ui.serie.value = preferred;
        }
        syncChoiceButtons(ui.serieButtons, series, ui.serie.value, displaySerie);

        rows = filteredClamps('serie');
        const executions = unique(rows.map(r => norm(r['Enkel / Dubbel']))).sort();
        setSelectOptions(ui.execution, executions, 'Kies uitvoering', v => v, !resetDownstream);
        // Standaard: Enkel.
        if (!ui.execution.value && executions.includes('Enkel')) ui.execution.value = 'Enkel';
        syncChoiceButtons(ui.executionButtons, executions, ui.execution.value);

        rows = filteredClamps('execution');
        const materials = unique(rows.map(r => norm(r['Materiaalcode']))).sort();
        setSelectOptions(ui.clampMaterial, materials, 'Kies beugelmateriaal', code => {
            const row = rows.find(r => norm(r['Materiaalcode']) === code);
            return row ? `${code} — ${row['Materiaal']}` : code;
        }, !resetDownstream);
        // Standaard: PP.
        if (!ui.clampMaterial.value && materials.includes('PP')) ui.clampMaterial.value = 'PP';
        resizeClampMaterialField();

        rows = filteredClamps('all');
        setSelectOptions(ui.clamp, rows, 'Kies beugel', row => norm(row['Artikelcode']), !resetDownstream);
        resizeClampField();

        ui.resultCount.textContent = `${rows.length} ${rows.length === 1 ? 'mogelijkheid' : 'mogelijkheden'}`;

        if (ui.clamp.value) selectClamp(ui.clamp.value);
        else {
            state.selectedClamp = null;
            clearAssembly();
        }
    }

    function resetClampArea(message) {
        [ui.serie, ui.execution, ui.clampMaterial, ui.clamp].forEach(select => {
            select.innerHTML = `<option value="">${message}</option>`;
            select.disabled = true;
        });
        syncChoiceButtons(ui.serieButtons, [], '');
        syncChoiceButtons(ui.executionButtons, [], '');
        ui.resultCount.textContent = '0 mogelijkheden';
        state.selectedClamp = null;
        clearAssembly();
    }

    function clampCodePart(clamp) {
        if (!clamp) return '';
        // Locatie 2 gebruikt de beugelcode zelf. De code bevat al bouwgroep,
        // maat en materiaal, bijvoorbeeld 215 PP (licht, 15 mm) of
        // 3015 PP (zwaar, 15 mm).
        return norm(clamp['Artikelcode']);
    }

    function selectClamp(articleCode) {
        const previousArticle = state.selectedClamp ? norm(state.selectedClamp['Artikelcode']) : '';
        state.selectedClamp = state.clamps.find(r => norm(r['Artikelcode']) === norm(articleCode)) || null;
        if (!state.selectedClamp) {
            clearAssembly();
            return;
        }

        // Een nieuwe beugel start altijd met een lege optionele samenstelling.
        if (previousArticle !== norm(state.selectedClamp['Artikelcode'])) {
            [ui.materialCode, ui.loc1, ui.loc3, ui.loc4, ui.loc5].forEach(select => { select.value = ''; });
        }
        const c = state.selectedClamp;
        ui.factGroup.textContent = c['Bouwgroep'] || '—';
        ui.factDiameter.textContent = c['Diameter 2'] ? `${c['Diameter 1']} / ${c['Diameter 2']} mm` : `${c['Diameter 1']} mm`;
        ui.factSerie.textContent = displaySerie(c['Serie']);
        ui.factExecution.textContent = c['Enkel / Dubbel'] || '—';
        ui.clampFacts.classList.remove('is-empty');
        ui.loc2.textContent = clampCodePart(c);
        rebuildMaterialCodes();
    }

    function rebuildMaterialCodes() {
        if (!state.selectedClamp) {
            ui.materialCode.disabled = true;
            return;
        }
        const positions = [1, 3, 4, 5];
        const all = positions.flatMap(pos => candidatesForPosition(pos));
        const codes = unique(all.map(r => norm(r['Materiaalcode'])))
            .filter(code => metalFamily(code) === state.metalFamily)
            .sort((a, b) => a.localeCompare(b, 'nl', { numeric: true }));

        const placeholder = codes.length ? '— Geen materiaalcode gekozen —' : `Geen ${state.metalFamily}-code beschikbaar`;
        setSelectOptions(ui.materialCode, codes, placeholder, code => code, true);

        // Standaard materiaalcode: staal = W3, RVS = W5.
        // Als de gewenste standaardcode voor deze combinatie niet bestaat,
        // blijft de keuze leeg zodat we geen andere W-code gokken.
        if (!ui.materialCode.value) {
            const preferredCode = state.metalFamily === 'RVS' ? 'W5' : 'W3';
            if (codes.some(code => upper(code) === preferredCode)) {
                ui.materialCode.value = codes.find(code => upper(code) === preferredCode) || '';
            }
        }
        rebuildComponents();
    }

    function componentLabel(row) {
        return `${row['Artikelcode']}`;
    }

    function rebuildComponents() {
        const code = ui.materialCode.value;
        ui.loc6.textContent = code || '—';
        const mapping = [
            [1, ui.loc1, 'Geen passende lasplaat/glijmoer'],
            [3, ui.loc3, 'Geen passende borgplaat'],
            [4, ui.loc4, 'Geen passende dekplaat'],
            [5, ui.loc5, 'Geen passende bout'],
        ];
        const warnings = [];
        mapping.forEach(([pos, select, emptyText]) => {
            let candidates = code ? candidatesForPosition(pos, code) : [];

            // Locatie 1: vaste type-prioriteit.
            // Licht  -> SP is de standaardlasplaat; SPV/WSP zijn alternatieven.
            // Zwaar  -> SPAL is de standaardlasplaat.
            // DPAS/DPAD zijn dekplaten en zijn hierboven al hard uitgesloten.
            if (pos === 1) {
                const isHeavy = displaySerie(state.selectedClamp['Serie']) === 'Zwaar';
                const typeRank = row => {
                    const prefix = firstCodePart(row['Artikelcode']);
                    if (isHeavy) {
                        if (prefix === 'SPAL') return 0;
                        if (prefix === 'SP') return 1;
                        if (prefix === 'SPV') return 2;
                        if (prefix === 'WSP') return 3;
                        if (prefix === 'GMV') return 9;
                        return 8;
                    }
                    if (prefix === 'SP') return 0;
                    if (prefix === 'SPV') return 1;
                    if (prefix === 'WSP') return 2;
                    if (prefix === 'SPAL') return 3;
                    if (prefix === 'GMV') return 9;
                    return 8;
                };
                const materialRank = row => {
                    const mc = upper(row['Materiaalcode']);
                    if (mc === upper(code)) return 0;
                    if (state.metalFamily === 'Staal') {
                        if (mc === 'W2') return 1;
                        if (mc === 'W1') return 2;
                        if (mc === 'W3') return 3;
                    } else if (state.metalFamily === 'RVS') {
                        if (mc === 'W5') return 1;
                        if (mc === 'W4') return 2;
                        if (mc === 'W55') return 3;
                    }
                    return 4;
                };
                candidates = [...candidates].sort((a, b) =>
                    typeRank(a) - typeRank(b)
                    || materialRank(a) - materialRank(b)
                    || norm(a['Artikelcode']).localeCompare(norm(b['Artikelcode']), 'nl', { numeric: true })
                );
            }

            const placeholder = code && candidates.length ? '— Geen onderdeel gekozen —' : (code ? emptyText : 'Kies eerst materiaalcode');
            setSelectOptions(select, candidates, placeholder, componentLabel, true);

            // Standaardselecties: locatie 1 Lasplaat en locatie 4 Dekplaat.
            // De lege optie blijft bestaan, dus de gebruiker kan deze daarna
            // bewust weer leeg maken.
            if (code && !select.value && candidates.length && (pos === 1 || pos === 4)) {
                select.value = norm(candidates[0]['Artikelcode']);
            }

            if (code && candidates.length === 0) warnings.push(`Locatie ${pos}: geen passend artikel voor ${code}.`);
        });
        showWarnings(warnings);
        updateAssemblyCode();
    }

    function selectedRow(select) {
        if (!select.value) return null;
        return state.validRows.find(r => norm(r['Artikelcode']) === norm(select.value)) || null;
    }

    function updateAssemblyCode() {
        const clamp = state.selectedClamp;
        const wcode = ui.materialCode.value;
        const r1 = selectedRow(ui.loc1);
        const r3 = selectedRow(ui.loc3);
        const r4 = selectedRow(ui.loc4);
        const r5 = selectedRow(ui.loc5);
        updateShapeImages();
        if (!clamp) {
            ui.assemblyCode.textContent = 'Selecteer eerst een beugel';
            ui.copyButton.disabled = true;
            ui.codeHint.innerHTML = 'Locaties 1, 3, 4, 5 en 6 zijn optioneel. Locatie 2 gebruikt de beugelcode, bijvoorbeeld <strong>215 PP</strong> of <strong>3015 PP</strong>.';
            return;
        }

        // Alleen daadwerkelijk gekozen locaties worden in de code opgenomen.
        // De volgorde blijft altijd locatie 1 → 6.
        const parts = [
            r1 ? firstCodePart(r1['Artikelcode']) : '',
            clampCodePart(clamp),
            r3 ? firstCodePart(r3['Artikelcode']) : '',
            r4 ? firstCodePart(r4['Artikelcode']) : '',
            r5 ? firstCodePart(r5['Artikelcode']) : '',
            wcode || '',
        ].filter(Boolean);

        const code = parts.join('-');
        ui.assemblyCode.textContent = code;
        ui.copyButton.disabled = !code;
        ui.codeHint.textContent = 'Alleen gekozen locaties worden opgenomen; de volgorde blijft locatie 1 → 6.';
    }

    function showWarnings(messages) {
        if (!messages.length) {
            ui.warningBox.hidden = true;
            ui.warningBox.innerHTML = '';
            return;
        }
        ui.warningBox.hidden = false;
        ui.warningBox.innerHTML = `<strong>Let op:</strong><ul>${messages.map(m => `<li>${m}</li>`).join('')}</ul>`;
    }

    function clearAssembly() {
        ui.factGroup.textContent = '—';
        ui.factDiameter.textContent = '—';
        ui.factSerie.textContent = '—';
        ui.factExecution.textContent = '—';
        ui.clampFacts.classList.add('is-empty');
        ui.loc2.textContent = '—';
        ui.loc6.textContent = '—';
        [ui.materialCode, ui.loc1, ui.loc3, ui.loc4, ui.loc5].forEach(select => {
            select.innerHTML = '<option value="">Kies eerst een beugel</option>';
            select.disabled = true;
        });
        showWarnings([]);
        updateAssemblyCode();
    }

    function bindEvents() {
        ui.diameterInput.addEventListener('input', () => {
            resizeDiameterField();
            showDiameterSuggestions();
            rebuildClampFilters(true);
        });
        ui.diameterInput.addEventListener('focus', showDiameterSuggestions);
        ui.diameterInput.addEventListener('blur', () => setTimeout(() => { ui.suggestions.hidden = true; }, 100));
        ui.serieButtons.forEach(button => {
            button.addEventListener('click', () => {
                if (button.disabled) return;
                const value = actualSerieValue(button.dataset.serie);
                if (!value || value === ui.serie.value) return;
                ui.serie.value = value;
                // Een andere serie kan andere uitvoeringen/materialen opleveren.
                ui.execution.value = '';
                ui.clampMaterial.value = '';
                ui.clamp.value = '';
                rebuildClampFilters(false);
            });
        });
        ui.executionButtons.forEach(button => {
            button.addEventListener('click', () => {
                if (button.disabled) return;
                const value = button.dataset.uitvoering || '';
                if (!value || value === ui.execution.value) return;
                ui.execution.value = value;
                ui.clampMaterial.value = '';
                ui.clamp.value = '';
                rebuildClampFilters(false);
            });
        });
        ui.clampMaterial.addEventListener('change', () => { resizeClampMaterialField(); rebuildClampFilters(false); });
        ui.clamp.addEventListener('change', () => { resizeClampField(); selectClamp(ui.clamp.value); });
        ui.materialCode.addEventListener('change', rebuildComponents);
        [ui.loc1, ui.loc3, ui.loc4, ui.loc5].forEach(select => select.addEventListener('change', updateAssemblyCode));

        document.querySelectorAll('[data-metal]').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('[data-metal]').forEach(b => b.classList.remove('active'));
                button.classList.add('active');
                state.metalFamily = button.dataset.metal;
                rebuildMaterialCodes();
            });
        });

        ui.copyButton.addEventListener('click', async () => {
            const code = ui.assemblyCode.textContent;
            if (!code || ui.copyButton.disabled) return;
            try {
                await navigator.clipboard.writeText(code);
                const old = ui.copyButton.textContent;
                ui.copyButton.textContent = 'Gekopieerd';
                setTimeout(() => { ui.copyButton.textContent = old; }, 1200);
            } catch {
                window.prompt('Kopieer de samenstellingscode:', code);
            }
        });
    }

    async function init() {
        resizeDiameterField();
        resizeClampMaterialField();
        resizeClampField();
        bindEvents();
        try {
            const response = await fetch('api/stauff.php', { cache: 'no-store' });
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.error || 'Data kon niet worden geladen.');
            state.rows = payload.rows;
            state.validRows = state.rows.filter(r => upper(r['Status']) === 'OK');
            state.clamps = state.validRows.filter(r => r['Onderdeel'] === 'Beugel' && Number(r['Positie']) === 2 && norm(r['Diameter 1']));
            ui.dataStatus.textContent = `${payload.count} regels geladen`;
            ui.dataStatus.classList.add('ready');
            ui.diameterInput.disabled = false;
        } catch (error) {
            ui.dataStatus.textContent = 'Datafout';
            ui.dataStatus.classList.add('error');
            ui.warningBox.hidden = false;
            ui.warningBox.innerHTML = `<strong>Fout:</strong> ${error.message}`;
        }
    }

    init();
})();
