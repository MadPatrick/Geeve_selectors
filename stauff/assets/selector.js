(() => {
    'use strict';

    const LOCATION_FILTERS_STORAGE_KEY = 'stauffLocationFilters';

    function loadLocationFilters() {
        try {
            const raw = localStorage.getItem(LOCATION_FILTERS_STORAGE_KEY);
            return raw ? JSON.parse(raw) : {};
        } catch {
            return {};
        }
    }

    function saveLocationFilters(filters) {
        try {
            localStorage.setItem(LOCATION_FILTERS_STORAGE_KEY, JSON.stringify(filters));
        } catch {
            // Geen opslag beschikbaar (bijv. privénavigatie) - filters blijven
            // dan alleen voor deze paginaweergave actief, niet blokkerend.
        }
    }

    const state = {
        selectedClamp: null,
        // Bouwgroep-tag (bijv. "GR10") van de gekozen beugel, gehaald uit de
        // Exact-omschrijving bij het kiezen (selectExactArticle()) - niet
        // uit de CSV. Gebruikt om locatie-filters (zie hieronder) verder te
        // verfijnen op bouwgroep.
        beugelGroup: '',
        // Per locatie (1, 3, 4, 5) een ; -gescheiden lijst artikelnummer-
        // voorvoegsels - ingesteld via de config-cog op die regel (zie
        // bindEvents()). Blijft bewaard in localStorage, dus niet per
        // zoekactie opnieuw invullen. Leeg = de standaard live Exact-
        // voorvoegsels voor die locatie blijven gebruikt (PREFIXES_BY_POSITION).
        locationFilters: loadLocationFilters(),
        editingLocation: null,
        // Laatst opgehaalde verkoopprijzen + vrije voorraad (Exact), per
        // artikelcode - los bijgehouden zodat recomputeTotal() de
        // totaalprijs/totaal beschikbaar opnieuw kan berekenen zodra een
        // aantal wijzigt, zonder opnieuw te moeten fetchen (zie
        // refreshLocationPrices()).
        lastPrices: {},
        lastStock: {},
    };

    const el = id => document.getElementById(id);
    const ui = {
        diameterInput: el('diameterInput'),
        exactLiveResults: el('exactLiveResults'),
        exactLiveStatus: el('exactLiveStatus'),
        exactLiveList: el('exactLiveList'),
        materialFamily: el('materialFamilySwitch'),
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
        shapeImg1: el('shapeImg1'),
        shapeImg2: el('shapeImg2'),
        shapeImg3: el('shapeImg3'),
        shapeImg4: el('shapeImg4'),
        shapeImg5: el('shapeImg5'),
        priceLoc1: el('locationPrice1'),
        priceLoc2: el('locationPrice2'),
        priceLoc3: el('locationPrice3'),
        priceLoc4: el('locationPrice4'),
        priceLoc5: el('locationPrice5'),
        stockLoc1: el('locationStock1'),
        stockLoc2: el('locationStock2'),
        stockLoc3: el('locationStock3'),
        stockLoc4: el('locationStock4'),
        stockLoc5: el('locationStock5'),
        aantalLoc1: el('locationAantal1'),
        aantalLoc2: el('locationAantal2'),
        aantalLoc3: el('locationAantal3'),
        aantalLoc4: el('locationAantal4'),
        aantalLoc5: el('locationAantal5'),
        assemblyTotalPrice: el('assemblyTotalPrice'),
        assemblyTotalPriceRow: el('assemblyTotalPriceRow'),
        assemblyTotalStock: el('assemblyTotalStock'),
        assemblyTotalStockRow: el('assemblyTotalStockRow'),
        locationConfigButtons: [...document.querySelectorAll('.location-number[data-location]')],
        locationAddButtons: [...document.querySelectorAll('.location-add-button')],
        assemblyList: document.querySelector('.assembly-list'),
        locationFilterOverlay: el('locationFilterOverlay'),
        locationFilterTitle: el('locationFilterTitle'),
        locationFilterInput: el('locationFilterInput'),
        locationFilterApply: el('locationFilterApply'),
        locationFilterCancel: el('locationFilterCancel'),
        locationFilterClear: el('locationFilterClear'),
    };

    const norm = value => String(value ?? '').trim();
    const upper = value => norm(value).toUpperCase();
    const unique = arr => [...new Set(arr.filter(v => norm(v) !== ''))];
    const escapeHtml = value => norm(value).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
    // Haalt de bouwgroep-tag (bijv. "GR10", of een range-tag als "GR1-8/1D")
    // uit een Exact-omschrijving - zelfde patroon als extractGroupTag() in
    // api/exact_location_search.php. Komt altijd uit de omschrijving die de
    // live Exact-zoekopdracht al teruggeeft, nooit uit de CSV.
    // \d direct na "GR" is bewust verplicht - anders matcht dit ook gewone
    // woorden die met "gr" beginnen (GROEP, GRIJS, GROOT, ...). "-" en "/"
    // horen bij de class, voor glijmoer-artikelen (SM/GMV) wier omschrijving
    // meerdere bouwgroepen in 1 tag dekt (zie parseGroupTags() hieronder) -
    // bv. "GR1-8/1D" (Glijmoer SM 1) of "GR3-5S" (Glijmoer GMV 3).
    const extractGroupTag = description => {
        const match = norm(description).match(/\bGR\d[A-Za-z0-9/-]*\b/);
        return match ? match[0] : '';
    };

    /**
     * Breidt een (mogelijk range-)bouwgroep-tag uit naar de losse GRx-tags
     * die hij dekt - nodig voor glijmoer-artikelen (SM/GMV), die met 1
     * artikel meerdere bouwgroepen bedienen i.p.v. 1 tag per artikel zoals
     * de rest (zie tagMatches() hieronder). Patronen (bevestigd door de
     * klant aan de hand van 2 echte voorbeelden):
     *   - "GR1-8/1D" -> GR1..GR8 (talrange, hier zonder letter-suffix) +
     *     GR1D (het stuk na de "/" is een eigen, losse tag - "1D" wordt
     *     "GR1D", geen GR-prefix nodig in de omschrijving zelf).
     *   - "GR3-5S" -> GR3S, GR4S, GR5S (talrange MET letter-suffix "S" -
     *     die suffix geldt dan voor elk nummer in de range).
     * Een gewone, niet-range tag ("GR10") levert gewoon zichzelf als enige
     * resultaat op - dit vervangt dus geen bestaand gedrag, het breidt het
     * alleen uit voor de nieuwe range-notatie.
     */
    function parseGroupTags(rawTag) {
        const tag = upper(rawTag).replace(/^GR/, '');
        if (!tag) return [];

        return tag.split('/').flatMap(segment => {
            const rangeMatch = segment.match(/^(\d+)-(\d+)([A-Za-z]*)$/);
            if (!rangeMatch) return [`GR${segment}`];

            const start = Number(rangeMatch[1]);
            const end = Number(rangeMatch[2]);
            const suffix = rangeMatch[3] || '';
            const tags = [];
            for (let i = start; i <= end; i++) {
                tags.push(`GR${i}${suffix}`);
            }
            return tags;
        });
    }


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

    function metalFamily(code) {
        const c = upper(code);
        if (/^W(?:1|2|3)(?:$|\/)/.test(c)) return 'Staal';
        if (/^W(?:4|5|55)(?:$|\/)/.test(c)) return 'RVS';
        return 'Overig';
    }

    // Volledige, altijd-beschikbare set W-materiaalcodes per familie -
    // gebruikt door materialQueryValue() om locaties 1/3/4/5 op de hele
    // gekozen familie (Staal/RVS) te filteren. Niet meer gebruikt voor een
    // eigen locatie-6-select - zie computeLocation6Code() voor hoe code 6
    // tegenwoordig (automatisch) tot stand komt.
    const MATERIAL_CODES = [
        { code: 'W1', label: 'CS' },
        { code: 'W2', label: 'CS Ph' },
        { code: 'W3', label: 'ZN' },
        { code: 'W4', label: 'V2A' },
        { code: 'W5', label: 'V4A' },
        { code: 'W55', label: 'V4A CR' },
    ];

    // Staal/RVS-switch (knoppen staan vast in index.php) - filtert
    // MATERIAL_CODES via metalFamily() (W1/W2/W3 -> Staal, W4/W5/W55 -> RVS)
    // voor materialQueryValue(). De uiteindelijke filterwaarde voor de
    // artikelen blijft de kale W-code - de switch bepaalt alleen welke
    // W-codes ter keuze staan.

    function firstCodePart(article) {
        const s = norm(article);
        const match = s.match(/^([A-Z+]+)/i);
        return match ? match[1].toUpperCase() : s.split(/[\s-]/)[0].toUpperCase();
    }

    // Eén centrale classificatietabel: welk artikelcode-voorvoegsel hoort bij
    // welke vaste locatie (1/3/4/5) en welk Onderdeel-type. Geverifieerd
    // tegen alle 1282 CSV-rijen: elk voorvoegsel wijst 100% betrouwbaar naar
    // precies 1 Onderdeel, geen kruisbesmetting. Vervangt de losse
    // voorvoegsel-arrays die voorheen verspreid in dit bestand stonden.
    // Geen Enkel/Dubbel-informatie hier - dat komt voortaan uit de
    // GRx/GRxD-tag in de Exact-omschrijving (zie tagMatches() hieronder),
    // niet uit een apart veld per Onderdeel-type.
    const PREFIX_RULES = [
        { prefix: 'SP', position: 1, onderdeel: 'Lasplaat' },
        { prefix: 'SPAL', position: 1, onderdeel: 'Lasplaat' },
        { prefix: 'SPV', position: 1, onderdeel: 'Lasplaat' },
        { prefix: 'WSP', position: 1, onderdeel: 'Lasplaat (hoek)' },
        { prefix: 'GMV', position: 1, onderdeel: 'Glijmoer' },
        { prefix: 'SM', position: 1, onderdeel: 'Glijmoer' },
        { prefix: 'DP', position: 4, onderdeel: 'Dekplaat' },
        { prefix: 'DPAL', position: 4, onderdeel: 'Dekplaat' },
        { prefix: 'DPAS', position: 4, onderdeel: 'Dekplaat' },
        { prefix: 'GD', position: 4, onderdeel: 'Dekplaat' },
        { prefix: 'DPAD', position: 4, onderdeel: 'Dekplaat' },
        { prefix: 'SI', position: 3, onderdeel: 'Borgplaat' },
        { prefix: 'SIP', position: 3, onderdeel: 'Borgplaat' },
        { prefix: 'SIG', position: 3, onderdeel: 'Borgplaat' },
        { prefix: 'AF', position: 5, onderdeel: 'Stapelbout' },
        { prefix: 'IS', position: 5, onderdeel: 'Inbusbout' },
        { prefix: 'AS', position: 5, onderdeel: 'Zeskantbout' },
    ];

    // Let op: voorvoegsels overlappen qua SQL LIKE ('DP%' matcht ook
    // 'DPAD...'). De server (exact_location_search.php) gebruikt deze lijst
    // daarom alleen om een RUIMERE kandidatenset op te halen; classificatie
    // van een teruggekomen rij gebeurt altijd hier, client-side, via
    // RULE_BY_PREFIX op de eigen ItemCode - nooit via "welke voorvoegsel-
    // clausule raakte".
    const RULE_BY_PREFIX = new Map(PREFIX_RULES.map(rule => [rule.prefix, rule]));
    const PREFIXES_BY_POSITION = PREFIX_RULES.reduce((acc, rule) => {
        (acc[rule.position] ||= []).push(rule.prefix);
        return acc;
    }, {});

    // Beugel-artikelen (locatie 2) hebben geen letter-voorvoegsel (cijfer-
    // eerst, zie exact_search.php) en staan dus niet in PREFIX_RULES.
    function classify(itemCode) {
        const code = norm(itemCode);
        if (!code) return null;
        if (/^\d/.test(code)) return { position: 2, onderdeel: 'Beugel' };
        return RULE_BY_PREFIX.get(firstCodePart(code)) || null;
    }

    // Per-locatie vlag: pas de GRx/GRxD-tagfilter (zie tagMatches()) toe op
    // live kandidaten. Gecontroleerd voor alle locaties: Borgplaat/Dekplaat/
    // Bout-omschrijvingen in Exact dragen ook een herkenbare GRx/GRxD-tag
    // (zie README.md).
    const GROUP_FILTER_ENABLED_FOR = { 1: true, 2: true, 3: true, 4: true, 5: true };

    // Haalt de W-materiaalcode terug uit een artikelcode-string (bijv. "SPAL
    // 8 M W2" -> "W2"), zodat de locatie-1 materiaal-rangorde (zie
    // rebuildComponents()) kan werken zonder CSV-veld.
    function materialCodeFromItemCode(itemCode) {
        const match = upper(itemCode).match(/\bW\d+(?:\/\d+)?\b/);
        return match ? match[0] : '';
    }

    // Welke waarde als "material"-parameter naar exact_location_search.php
    // gaat. Alle locaties (1/3/4/5) filteren op de hele materiaalFAMILIE
    // (W1/W2/W3 voor Staal, W4/W5/W55 voor RVS, als ;-lijst) - er is geen
    // apart, vooraf gekozen exacte W-code meer die de andere locaties
    // stuurt. De specifieke code volgt uit welk artikel de gebruiker per
    // locatie kiest (zie materialCodeFromItemCode()). Locatie 2 (Beugel)
    // slaat de materiaalcode-check over (eigen kunststof/beugel-schema).
    function materialQueryValue(pos, family) {
        if (!family || Number(pos) === 2) return '';
        return MATERIAL_CODES.filter(m => metalFamily(m.code) === family).map(m => m.code).join(';');
    }

    /**
     * Welke "rol" (uit de Stauff-materiaalcombinatietabel, zie
     * MATERIAL_COMBINATION_RULES hieronder) hoort bij een gekozen
     * artikelcode - gebaseerd op de Onderdeel-classificatie (classify()),
     * niet op de locatie zelf: locatie 1 kan zowel Lasplaat/Lasplaat (hoek)
     * (rol "WeldPlate") als Glijmoer (rol "Glijmoer") zijn, afhankelijk van
     * wat er daadwerkelijk gekozen is.
     */
    function materialRoleForCode(itemCode) {
        const rule = classify(itemCode);
        if (!rule) return null;
        switch (rule.onderdeel) {
            case 'Lasplaat':
            case 'Lasplaat (hoek)':
                return 'WeldPlate';
            case 'Glijmoer':
                return 'Glijmoer';
            case 'Dekplaat':
                return 'CoverPlate';
            case 'Borgplaat':
                return 'SafetyLockingPlate';
            case 'Stapelbout':
            case 'Inbusbout':
            case 'Zeskantbout':
                return 'Bolts';
            default:
                return null;
        }
    }

    /**
     * Code 6 (het "Materiaal"-onderdeel van de samenstellingscode) is geen
     * apart te kiezen artikel meer, maar wordt automatisch afgeleid uit de
     * eigen W-code van de daadwerkelijk gekozen artikelen op locatie 1, 3, 4
     * en 5 - volgens Stauff's officiële materiaalcombinatietabel. Zijn alle
     * aanwezige rollen gelijk (bv. alles W2), dan is dat gewoon de
     * samenstellingscode (geen aparte combinatiecode nodig). Verschillen ze,
     * dan moet de combinatie exact voorkomen in MATERIAL_COMBINATION_RULES -
     * komt geen enkele regel overeen, dan blijft code 6 leeg (geen
     * foutmelding, net als een niet-ingevulde optionele locatie).
     *
     * Volgorde is belangrijk: regels met meer genoemde rollen staan vooraan,
     * zodat bv. W12 (WeldPlate+CoverPlate+Bolts) voorrang krijgt boven het
     * minder specifieke W19 (alleen CoverPlate+Bolts) wanneer ook WeldPlate
     * aanwezig is.
     */
    const MATERIAL_COMBINATION_RULES = [
        { code: 'W12', roles: { WeldPlate: 'W2', CoverPlate: 'W2', Bolts: 'W1' } },
        { code: 'W13', roles: { Glijmoer: 'W3', CoverPlate: 'W2', Bolts: 'W1' } },
        { code: 'W15', roles: { WeldPlate: 'W2', CoverPlate: 'W2', Bolts: 'W3' } },
        { code: 'W16', roles: { Glijmoer: 'W3', CoverPlate: 'W2', Bolts: 'W3' } },
        { code: 'W17', roles: { SafetyLockingPlate: 'W2', Bolts: 'W3' } },
        { code: 'W18', roles: { SafetyLockingPlate: 'W1', Bolts: 'W2' } },
        { code: 'W19', roles: { CoverPlate: 'W2', Bolts: 'W1' } },
        // Speciaal: "Other metal parts" in de tabel noemt geen vaste rollen -
        // elke andere aanwezige rol moet W3 zijn (othersCode), niet alleen de
        // hier expliciet genoemde.
        { code: 'W10', roles: { WeldPlate: 'W2' }, othersCode: 'W3' },
    ];

    /**
     * Haalt de huidige materiaalcode per rol op uit de daadwerkelijk
     * gekozen artikelen op locatie 1, 3, 4 en 5 (ontbrekende/optionele
     * locaties tellen niet mee).
     */
    function currentMaterialByRole() {
        const byRole = {};
        [ui.loc1, ui.loc3, ui.loc4, ui.loc5].forEach(select => {
            const code = norm(select.value);
            if (!code) return;
            const role = materialRoleForCode(code);
            const material = materialCodeFromItemCode(code);
            if (role && material) byRole[role] = material;
        });
        return byRole;
    }

    /** Zie MATERIAL_COMBINATION_RULES hierboven voor de volledige toelichting. */
    function computeLocation6Code(materialByRole) {
        const presentRoles = Object.keys(materialByRole);
        if (presentRoles.length === 0) return '';

        const codes = presentRoles.map(role => materialByRole[role]);
        if (codes.every(code => code === codes[0])) return codes[0];

        for (const rule of MATERIAL_COMBINATION_RULES) {
            const namedRoles = Object.keys(rule.roles);
            const namedMatch = namedRoles.every(role => materialByRole[role] === rule.roles[role]);
            if (!namedMatch) continue;

            if (!rule.othersCode) return rule.code;

            const otherRoles = presentRoles.filter(role => !namedRoles.includes(role));
            if (otherRoles.length > 0 && otherRoles.every(role => materialByRole[role] === rule.othersCode)) {
                return rule.code;
            }
        }

        return '';
    }

    /**
     * Eén mechanisme voor zowel bouwgroep- als enkel/dubbel-matching: een
     * kandidaat-artikel telt alleen mee als zijn eigen GRx/GRxD-tag (uit de
     * Exact-omschrijving, zie extractGroupTag()) de bouwgroep van de gekozen
     * beugel dekt - inclusief de "D". Voor de meeste Onderdeel-types is dat
     * een exacte 1-op-1 match; glijmoer-artikelen (SM/GMV) kunnen met 1
     * artikel meerdere bouwgroepen dekken via een range-tag (bv. "GR1-8/1D")
     * - parseGroupTags() breidt die uit, en de match slaagt zodra de
     * beugel-tag ergens in die uitbreiding voorkomt. Geen apart enkel/
     * dubbel-veld en geen D-strippen: de D is het onderscheidende signaal.
     */
    function tagMatches(candidateTag, clampTag) {
        const b = upper(clampTag);
        if (!b) return false;
        return parseGroupTags(candidateTag).includes(b);
    }

    // Leid de image-map af van de URL van selector.js zelf. Dit blijft correct
    // wanneer de Stauff-app in een submap of via een andere route wordt geopend.
    const selectorScriptUrl = document.currentScript?.src || new URL('assets/selector.js', window.location.href).href;
    const SHAPE_IMAGE_DIR = new URL('../images/', selectorScriptUrl).href;

    // Welk plaatje (images/<key>.png) hoort bij een gekozen rij. Lasplaat,
    // Dekplaat en Beugel gebruiken hiervoor of het om een dubbele
    // samenstelling gaat (zie isDubbelBeugel() hieronder). sp1d (lasplaat),
    // 1dpp (beugel) en gd1 (dekplaat) horen zo als vast drietal bij elkaar
    // zodra de beugel dubbel is.

    /**
     * Dubbele beugel o.b.v. de GRxD-groep-tag uit de Exact-omschrijving
     * (state.beugelGroup, gezet in selectExactArticle() via
     * extractGroupTag()) - geen CSV. Een "D" aan het eind van de groep-tag
     * betekent dubbel/twin, zelfde conventie als tagMatches() hieronder
     * gebruikt voor de volledige GRx/GRxD-tag. Geen groep bekend (geen
     * GR-tag in de omschrijving) -> niet dubbel. Zie isDubbelBeugel()
     * hieronder voor het gecombineerde, uiteindelijk gebruikte signaal.
     */
    function isDubbelFromExactGroup() {
        return /D$/i.test(norm(state.beugelGroup));
    }

    /**
     * Zware serie o.b.v. de GRxS-groep-tag uit de Exact-omschrijving
     * (state.beugelGroup) - een "S" aan het eind van de groep-tag (na het
     * eventueel strippen van de "D" voor dubbel, bijv. "GR10SD") betekent
     * zware serie, zelfde conventie als isDubbelFromExactGroup() hierboven
     * voor de "D". Geen groep bekend -> niet zwaar (lichte serie is de
     * standaard). Gebruikt door materialRank() in renderLiveCandidates()
     * om voor Dekplaat/Bout bij een zware-serie-beugel ook W3 (ZN) als
     * standaard te nemen, i.p.v. de W2 (CS Ph) die voor de lichte serie
     * blijft gelden.
     */
    function isZwareSerieBeugel() {
        return /S$/i.test(norm(state.beugelGroup).replace(/D$/i, ''));
    }

    /**
     * Dubbele beugel o.b.v. de artikelcode zelf: een herhaalde diameter,
     * gescheiden door "/" (bijv. "112/12" of "103,2/03,2"), betekent
     * dubbel; zonder "/" (bijv. "112") is enkel - rechtstreeks op de
     * artikelcode-string zoals Exact die teruggeeft, geen CSV-veld nodig.
     * Klopt 1-op-1 met de CSV ('Enkel / Dubbel') voor alle 128 dubbele
     * beugel-rijen (geen enkele dubbele rij zonder "/", geen enkele "/"-
     * rij die niet dubbel is) - betrouwbaarder dan de GR-tag hieronder,
     * want de artikelcode is altijd beschikbaar (de omschrijving bevat
     * niet altijd een parsbare GR-tag).
     */
    function isDubbelArtikelcode(code) {
        return norm(code).includes('/');
    }

    /**
     * Dubbele beugel, gecombineerd uit de 2 Exact-gebaseerde signalen
     * hierboven (bewust geen CSV): de artikelcode (leidend) en de
     * GRxD-groep-tag uit de omschrijving (vangnet voor het geval de
     * artikelcode een ander formaat heeft).
     */
    function isDubbelBeugel() {
        return isDubbelArtikelcode(state.selectedClamp ? state.selectedClamp['Artikelcode'] : '') || isDubbelFromExactGroup();
    }

    /**
     * Welk plaatje (images/<key>.png) hoort bij een gekozen artikelcode.
     * Werkt volledig op live Exact-gegevens: de Onderdeel-classificatie komt
     * uit classify()/PREFIX_RULES, de bouwgroep uit de eigen GRx/GRxD-tag
     * van het artikel (ownGroupTag, zie selectedGroupTag()) met de tag van
     * de gekozen beugel (state.beugelGroup) als terugval wanneer die eigen
     * tag niet bekend is (bijv. nog niet tag-gefilterde locaties, zie
     * GROUP_FILTER_ENABLED_FOR). Geen Serie/Licht-Zwaar meer - die bestaat
     * niet in Exact en is met opzet vervallen.
     */
    function shapeImageKey(code, ownGroupTag = '') {
        const artikelcode = norm(code);
        if (!artikelcode) return null;
        const prefix = firstCodePart(artikelcode);
        const rule = classify(artikelcode);
        const onderdeel = rule ? rule.onderdeel : null;
        const group = upper(ownGroupTag) || upper(state.beugelGroup);

        // Familie-afbeeldingen voor WSP/CRA.
        if (prefix === 'WSP') return 'wsp';
        if (prefix === 'CRA') return 'cra';

        switch (onderdeel) {
            case 'Glijmoer':
                return 'gmv';
            case 'Lasplaat':
                if (isDubbelBeugel()) return 'sp1d';
                if (prefix === 'SP') {
                    // GR1 -> sp1.png, GR1A/GR2+ (of onbekend) -> sp1a.png.
                    return group === 'GR1' ? 'sp1' : 'sp1a';
                }
                if (prefix === 'SPAL') return 'sp1a';
                if (prefix === 'SPV') return group === 'GR1' ? 'spv1' : 'spv1a';
                return null;
            case 'Beugel':
                if (isDubbelBeugel()) return '1dpp';
                return group === 'GR1' ? '1pp' : '1app';
            case 'Dekplaat':
                if (isDubbelBeugel() || prefix === 'GD') return 'gd1';
                return group === 'GR1' ? 'dp1' : 'dp1a';
            case 'Borgplaat':
                return (prefix === 'SIG' || prefix === 'SIP') ? 'sig' : null;
            case 'Stapelbout':
                return 'af';
            case 'Inbusbout':
                return 'is';
            case 'Zeskantbout':
                return 'as';
            default:
                return null;
        }
    }

    // Leest de GRx/GRxD-tag van de nu geselecteerde optie terug (gezet als
    // data-group bij het opbouwen van de lijst, zie renderLiveCandidates()) -
    // zodat shapeImageKey() de EIGEN tag van het gekozen artikel kan
    // gebruiken i.p.v. altijd terug te vallen op state.beugelGroup.
    function selectedGroupTag(select) {
        const option = select && select.selectedOptions && select.selectedOptions[0];
        return option ? norm(option.dataset.group || '') : '';
    }

    function setShapeImage(imgEl, code, groupTag) {
        if (!imgEl) return;
        const slot = imgEl.closest('.location-image');
        const key = shapeImageKey(code, groupTag);
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
        setShapeImage(ui.shapeImg1, ui.loc1.value, selectedGroupTag(ui.loc1));
        setShapeImage(ui.shapeImg2, state.selectedClamp ? state.selectedClamp['Artikelcode'] : '', state.beugelGroup);
        setShapeImage(ui.shapeImg3, ui.loc3.value, selectedGroupTag(ui.loc3));
        setShapeImage(ui.shapeImg4, ui.loc4.value, selectedGroupTag(ui.loc4));
        setShapeImage(ui.shapeImg5, ui.loc5.value, selectedGroupTag(ui.loc5));
    }

    let exactLiveController = null;
    let exactLiveDebounce = null;

    /**
     * Live, fuzzy zoekopdracht op artikelnummer tegen de Exact-database
     * (api/exact_search.php, groep 67, artikelen die met een cijfer
     * beginnen) - eerste stap van het vervangen van de CSV door live
     * Exact-data (zie de docblock daar). Toont enkel de echte, actuele
     * artikelnummers (geen omschrijving) - de gebruiker kiest er 1 uit,
     * los van de bestaande (nog CSV-gedreven) wizard hieronder.
     */
    function queryExactLive(term) {
        if (exactLiveDebounce) clearTimeout(exactLiveDebounce);

        const typed = norm(term);
        if (!typed) {
            if (exactLiveController) exactLiveController.abort();
            ui.exactLiveResults.hidden = true;
            return;
        }

        exactLiveDebounce = setTimeout(() => {
            if (exactLiveController) exactLiveController.abort();
            exactLiveController = new AbortController();

            ui.exactLiveResults.hidden = false;
            ui.exactLiveStatus.textContent = 'Zoeken…';
            ui.exactLiveStatus.classList.remove('error');
            ui.exactLiveList.innerHTML = '';

            fetch(`api/exact_search.php?q=${encodeURIComponent(typed)}`, {
                cache: 'no-store',
                signal: exactLiveController.signal,
            })
                .then(response => response.json())
                .then(payload => {
                    if (!payload || payload.ok !== true) {
                        throw new Error((payload && payload.error) || 'Onbekende fout.');
                    }
                    ui.exactLiveList.innerHTML = '';
                    if (payload.rows.length === 0) {
                        ui.exactLiveStatus.textContent = 'Geen artikelen gevonden in Exact.';
                        return;
                    }
                    ui.exactLiveStatus.textContent = `${payload.rows.length} artikel${payload.rows.length === 1 ? '' : 'en'}`;
                    payload.rows.forEach(row => {
                        const artikelnummer = norm(row.ItemCode);
                        const omschrijving = norm(row['Item Description']);
                        const item = document.createElement('li');
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.textContent = artikelnummer;
                        button.addEventListener('click', () => selectExactArticle(artikelnummer, omschrijving));
                        item.appendChild(button);
                        ui.exactLiveList.appendChild(item);
                    });
                })
                .catch(error => {
                    if (error && error.name === 'AbortError') return;
                    ui.exactLiveStatus.textContent = `Exact niet bereikbaar: ${error.message}`;
                    ui.exactLiveStatus.classList.add('error');
                });
        }, 300);
    }

    /**
     * Kiest een artikel uit de live Exact-zoekresultaten (zie
     * queryExactLive()): vult het diameterveld met het artikelnummer en
     * activeert de beugel rechtstreeks vanuit dit live resultaat - geen
     * CSV-koppeling meer nodig (zie selectClamp()). Het infopaneel toont
     * daarna het gekozen artikelnummer + de Exact-omschrijving; opnieuw
     * zoeken kan door het diameterveld te overtypen.
     */
    function selectExactArticle(artikelnummer, omschrijving) {
        ui.diameterInput.value = artikelnummer;
        resizeDiameterField();

        state.beugelGroup = extractGroupTag(omschrijving);
        selectClamp(artikelnummer);

        ui.exactLiveStatus.textContent = 'Gekozen artikel';
        ui.exactLiveList.innerHTML = '';
        const item = document.createElement('li');
        const row = document.createElement('div');
        row.className = 'exact-live-chosen';
        const code = document.createElement('strong');
        code.textContent = artikelnummer;
        const description = document.createElement('span');
        description.textContent = omschrijving || '—';
        row.appendChild(code);
        row.appendChild(description);
        item.appendChild(row);
        ui.exactLiveList.appendChild(item);
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
        const code = norm(articleCode);
        // Rechtstreeks uit het live Exact-zoekresultaat opgebouwd - geen
        // CSV-koppeling/lookup meer nodig (zie selectExactArticle()).
        state.selectedClamp = code ? { Artikelcode: code } : null;
        if (!state.selectedClamp) {
            clearAssembly();
            return;
        }

        // Een nieuwe beugel start altijd met een lege optionele samenstelling.
        if (previousArticle !== norm(state.selectedClamp['Artikelcode'])) {
            setMaterialFamilyValue('');
            [ui.loc1, ui.loc3, ui.loc4, ui.loc5].forEach(select => { select.value = ''; });
            resetAantalFields();
        }
        const c = state.selectedClamp;
        ui.loc2.textContent = clampCodePart(c);
        rebuildMaterialCodes();
    }

    // Staal/RVS-switch (2 knoppen, geen <select>) - deze helpers geven het
    // gedrag van een normale select (.value/.disabled) terug zodat de rest
    // van het bestand er op dezelfde manier mee om kan gaan.
    function getMaterialFamily() {
        return ui.materialFamily.dataset.value || '';
    }

    function setMaterialFamilyValue(value) {
        ui.materialFamily.dataset.value = value || '';
        ui.materialFamily.querySelectorAll('.family-switch-option').forEach(btn => {
            const active = btn.dataset.family === value;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function setMaterialFamilyDisabled(disabled) {
        ui.materialFamily.classList.toggle('is-disabled', disabled);
        ui.materialFamily.querySelectorAll('.family-switch-option').forEach(btn => { btn.disabled = disabled; });
        if (disabled) setMaterialFamilyValue('');
    }

    function rebuildMaterialCodes() {
        if (!state.selectedClamp) {
            setMaterialFamilyDisabled(true);
            return;
        }
        // De knoppen zelf staan al vast in index.php (Staal/RVS) - alleen
        // ontgrendelen, een eerder gekozen familie (zelfde beugel opnieuw)
        // blijft gewoon staan.
        setMaterialFamilyDisabled(false);
        rebuildComponents();
    }

    // Ook gebruikt als soort-lijst in addExtraItemRow() - Beugel (2) heeft
    // daar wél een eigen optie nodig (een 2e beugel binnen dezelfde
    // bouwgroep kiezen, zie applyLocationFilterSelect()), maar geen eigen
    // zoekfilter-knop (die bestaat alleen voor 1/3/4/5, zie index.php)
    // - LOCATION_LABELS[2] wordt dus nooit voor de filtermodal-titel
    // opgevraagd.
    const LOCATION_LABELS = {
        1: 'Lasplaat / Glijmoer',
        2: 'Beugel',
        3: 'Borgplaat',
        4: 'Dekplaat',
        5: 'Bout',
    };

    // Standaardaantal per vaste locatie (1-6) - overal 1, behalve Bout
    // (locatie 5, zie resetAantalFields() hieronder: standaard 2, maar 1
    // bij een dubbele beugel - isDubbelBeugel(), met opzet niet de CSV).
    // Gebruikt bij het opnieuw leegmaken van de samenstelling
    // (clearAssembly()) en bij het kiezen van een nieuwe, andere beugel
    // (selectClamp()).
    const DEFAULT_AANTAL = { 1: 1, 2: 1, 3: 1, 4: 1 };

    function resetAantalFields() {
        Object.entries(DEFAULT_AANTAL).forEach(([pos, value]) => {
            const input = ui[`aantalLoc${pos}`];
            if (input) input.value = String(value);
        });
        if (ui.aantalLoc5) ui.aantalLoc5.value = isDubbelBeugel() ? '1' : '2';
    }

    function syncLocationConfigButtons() {
        ui.locationConfigButtons.forEach(button => {
            const pos = button.dataset.location;
            button.classList.toggle('is-active', !!norm(state.locationFilters[pos]));
        });
    }

    function openLocationFilterModal(pos) {
        state.editingLocation = pos;
        ui.locationFilterTitle.textContent = `Zoekfilter voor ${LOCATION_LABELS[pos] || `locatie ${pos}`}`;
        ui.locationFilterInput.value = state.locationFilters[pos] || '';
        ui.locationFilterOverlay.hidden = false;
        ui.locationFilterInput.focus();
    }

    function closeLocationFilterModal() {
        ui.locationFilterOverlay.hidden = true;
        state.editingLocation = null;
    }

    function applyLocationFilter(value) {
        const pos = state.editingLocation;
        if (!pos) return;
        const cleaned = norm(value);
        if (cleaned) {
            state.locationFilters[pos] = cleaned;
        } else {
            delete state.locationFilters[pos];
        }
        saveLocationFilters(state.locationFilters);
        syncLocationConfigButtons();
        closeLocationFilterModal();
        rebuildComponents();
    }

    // Per locatie (niet 1 gedeelde controller) - anders annuleert het
    // zoeken voor locatie 3 per ongeluk een nog lopende zoekopdracht voor
    // locatie 1, die dan voorgoed op "Zoeken…" blijft staan (er komt geen
    // response meer om dat tekstje te vervangen). Zie ook de tijdslimiet
    // hieronder: zonder die blijft een écht trage/hangende Exact-query
    // ook voorgoed op "Zoeken…" staan, zonder foutmelding.
    const locationFilterRequests = {};
    const LOCATION_FILTER_TIMEOUT_MS = 20000;

    // Bijgehouden "geen kandidaten"-waarschuwing per vaste locatie (1/3/4/5) -
    // apart per locatie omdat elke locatie zijn eigen, onafhankelijke async
    // live-zoekopdracht heeft (zie applyLocationFilterSelect()), dus niet in
    // 1 synchrone pas samen te voegen zoals voorheen bij de CSV-filter.
    const emptyWarnings = {};
    function refreshLocationWarnings() {
        showWarnings(Object.values(emptyWarnings).filter(Boolean));
    }

    /**
     * Verwerkt de live Exact-zoekresultaten (ItemCode/Group-tag paren) tot
     * de uiteindelijke kandidatenlijst voor 1 locatie/select: classificeert
     * elke rij (classify()/PREFIX_RULES), filtert op de GRx/GRxD-tag wanneer
     * GROUP_FILTER_ENABLED_FOR dat voor deze locatie toestaat (tagMatches() -
     * dit vervangt zowel de oude bouwgroep- als enkel/dubbel-matching in één
     * keer), past voor locatie 1/4 een vaste rangorde toe (locatie 1 ook op
     * type, beide op materiaalcode-voorkeur binnen de gekozen familie - er
     * is geen apart vooraf gekozen exacte code meer om op te ranken), en
     * selecteert automatisch (locatie 1/4: altijd de eerste; overige
     * locaties: alleen bij precies 1 resultaat). Gedeeld tussen de vaste
     * locaties (rebuildComponents()) en vrij toegevoegde extra-regels
     * (populateExtraArticleSelect()).
     */
    function renderLiveCandidates(pos, select, rawRows, { family = '', previousValue = '', emptyText = '', trackWarning = false } = {}) {
        const numPos = Number(pos);
        let items = rawRows
            .map(row => ({ code: norm(row.ItemCode), group: norm(row.Group) }))
            .filter(item => {
                if (numPos === 2) return true; // Beugel: cijfer-eerst, geen PREFIX_RULES-classificatie.
                const rule = classify(item.code);
                return !!rule && rule.position === numPos;
            });

        if (GROUP_FILTER_ENABLED_FOR[numPos] && state.beugelGroup) {
            items = items.filter(item => tagMatches(item.group, state.beugelGroup));
        }

        if (numPos === 1 || numPos === 4 || numPos === 5) {
            // Locatie 1 (Lasplaat/Glijmoer) en 4 (Dekplaat) hebben elk hun
            // eigen standaard-materiaalvoorkeur: locatie 1 default altijd
            // naar W3 bij Staal / W5 bij RVS. Locatie 4/5 blijven W2 bij
            // Staal voor de LICHTE serie, maar gaan ook naar W3 bij Staal
            // zodra de beugel een zware-serie-tag heeft (GRxS, zie
            // isZwareSerieBeugel()) - de klant gaf aan dat W3 voor
            // Dekplaat/Bout bij de zware serie de 1e keuze moet zijn.
            // Alleen de EERSTE (rank 0) bepaalt de standaardselectie
            // hieronder; de rest is tiebreak-volgorde.
            const materialRank = item => {
                const mc = materialCodeFromItemCode(item.code);
                if (family === 'Staal') {
                    if (numPos === 1 || isZwareSerieBeugel()) {
                        if (mc === 'W3') return 0;
                        if (mc === 'W1') return 1;
                        if (mc === 'W2') return 2;
                    } else {
                        if (mc === 'W2') return 0;
                        if (mc === 'W1') return 1;
                        if (mc === 'W3') return 2;
                    }
                } else if (family === 'RVS') {
                    if (mc === 'W5') return 0;
                    if (mc === 'W4') return 1;
                    if (mc === 'W55') return 2;
                }
                return 3;
            };
            if (numPos === 1) {
                const typeRank = item => {
                    const prefix = firstCodePart(item.code);
                    if (prefix === 'SP') return 0;
                    if (prefix === 'SPV') return 1;
                    if (prefix === 'WSP') return 2;
                    if (prefix === 'SPAL') return 3;
                    if (prefix === 'GMV' || prefix === 'SM') return 9;
                    return 8;
                };
                items = [...items].sort((a, b) =>
                    typeRank(a) - typeRank(b)
                    || materialRank(a) - materialRank(b)
                    || a.code.localeCompare(b.code, 'nl', { numeric: true })
                );
            } else if (numPos === 5) {
                // Standaardtype Bout: Zeskantbout (AS) eerst.
                const typeRank = item => {
                    const prefix = firstCodePart(item.code);
                    if (prefix === 'AS') return 0;
                    if (prefix === 'IS') return 1;
                    if (prefix === 'AF') return 2;
                    return 3;
                };
                items = [...items].sort((a, b) =>
                    typeRank(a) - typeRank(b)
                    || materialRank(a) - materialRank(b)
                    || a.code.localeCompare(b.code, 'nl', { numeric: true })
                );
            } else {
                // Locatie 4 (Dekplaat): zonder eigen typeRank viel de
                // standaardselectie terug op alfabetische volgorde zodra DP
                // niet bestond voor deze bouwgroep - dat liet DPAD/DPAS vóór
                // DPAL komen. DPAL hoort net als DP standaard gekozen te
                // kunnen worden, dus krijgt een vaste plek in de voorkeur.
                const typeRank = item => {
                    const prefix = firstCodePart(item.code);
                    if (prefix === 'DP') return 0;
                    if (prefix === 'DPAL') return 1;
                    if (prefix === 'DPAS') return 2;
                    if (prefix === 'DPAD') return 3;
                    if (prefix === 'GD') return 4;
                    return 8;
                };
                items = [...items].sort((a, b) =>
                    typeRank(a) - typeRank(b)
                    || materialRank(a) - materialRank(b)
                    || a.code.localeCompare(b.code, 'nl', { numeric: true })
                );
            }
        }

        select.innerHTML = '';
        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = items.length
            ? '— Geen onderdeel gekozen —'
            : (emptyText || `Geen artikelen gevonden${family ? ` voor materiaalsoort ${family}` : ''}.`);
        select.appendChild(placeholderOption);
        items.forEach(item => {
            const option = document.createElement('option');
            option.value = item.code;
            // Bewaart de eigen GRx/GRxD-tag op de optie (data-group) - o.a.
            // gebruikt door shapeImageKey()/selectedGroupTag() om de juiste
            // vorm-afbeelding te tonen zonder opnieuw te hoeven zoeken.
            option.dataset.group = item.group;
            option.textContent = item.group ? `${item.code} (${item.group})` : item.code;
            select.appendChild(option);
        });
        select.disabled = false;

        if (items.some(item => item.code === previousValue)) {
            select.value = previousValue;
        } else if ((numPos === 1 || numPos === 4 || numPos === 5) && items.length) {
            // Standaardselecties: locatie 1 Lasplaat, locatie 4 Dekplaat,
            // locatie 5 Bout (voorkeur Zeskantbout/AS, zie typeRank hierboven).
            select.value = items[0].code;
        } else if (numPos !== 3 && items.length === 1) {
            // Locatie 3 (Borgplaat) staat bewust standaard op "geen keuze",
            // ook als er maar 1 passend artikel is - optioneel onderdeel,
            // de gebruiker kiest 'm pas als 'ie 'm echt nodig heeft.
            select.value = items[0].code;
        }

        if (trackWarning) {
            emptyWarnings[numPos] = (family && items.length === 0)
                ? `Locatie ${pos}: geen passend artikel voor materiaalsoort ${family}.`
                : null;
            refreshLocationWarnings();
        }
    }

    /**
     * Live vervanging van de vroegere CSV-candidatesForPosition(): zoekt in
     * Exact op artikelnummer-voorvoegsel (state.locationFilters[pos], of de
     * vaste PREFIXES_BY_POSITION-default wanneer niets handmatig ingesteld
     * is) + optioneel materiaalcode + optioneel de GRx/GRxD-bouwgroepfilter
     * (api/exact_location_search.php) - dit is nu de ENIGE, altijd actieve
     * bron voor locaties 1/3/4/5 en voor Beugel-als-extra-regel (pos 2);
     * het tandwiel/state.locationFilters blijft alleen een optionele
     * power-user override van de standaard-voorvoegsels. Classificatie/
     * sortering/auto-selectie gebeurt client-side in renderLiveCandidates().
     *
     * requestKey is standaard pos, maar extra (vrij toegevoegde)
     * artikelregels voor dezelfde soort (zie addExtraItemRow()) geven
     * een eigen unieke key mee - anders zouden ze elkaars zoekopdracht
     * annuleren via dezelfde locationFilterRequests-sleutel.
     */
    function applyLocationFilterSelect(pos, select, family, requestKey = pos, { emptyText = '', trackWarning = false } = {}) {
        if (locationFilterRequests[requestKey]) {
            clearTimeout(locationFilterRequests[requestKey].timeoutId);
            locationFilterRequests[requestKey].controller.abort();
        }
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), LOCATION_FILTER_TIMEOUT_MS);
        const request = { controller, timeoutId };
        locationFilterRequests[requestKey] = request;

        const previousValue = select.value;
        select.disabled = true;
        select.innerHTML = '<option value="">Zoeken…</option>';

        const prefixes = state.locationFilters[pos]
            || (Number(pos) === 2 ? '__DIGIT__' : (PREFIXES_BY_POSITION[Number(pos)] || []).join(';'));
        const params = new URLSearchParams({ prefixes });
        const materialValue = materialQueryValue(pos, family);
        if (materialValue) params.set('material', materialValue);
        if (GROUP_FILTER_ENABLED_FOR[Number(pos)] && state.beugelGroup) params.set('group', state.beugelGroup);

        fetch(`api/exact_location_search.php?${params.toString()}`, { cache: 'no-store', signal: controller.signal })
            .then(response => response.json())
            .then(payload => {
                clearTimeout(timeoutId);
                if (!payload || payload.ok !== true) {
                    throw new Error((payload && payload.error) || 'Onbekende fout.');
                }
                renderLiveCandidates(pos, select, payload.rows, { family, previousValue, emptyText, trackWarning });
                updateAssemblyCode();
            })
            .catch(error => {
                clearTimeout(timeoutId);
                if (error && error.name === 'AbortError') {
                    // Alleen tonen als dit nog steeds de meest recente
                    // zoekopdracht voor deze locatie is - anders is dit een
                    // bewuste "overruled door een nieuwere aanroep"-abort,
                    // die niet zelf iets hoeft te tonen (de nieuwere aanroep
                    // doet dat al).
                    if (locationFilterRequests[requestKey] === request) {
                        select.innerHTML = '<option value="">Zoeken duurt te lang - probeer het filter opnieuw.</option>';
                        select.disabled = false;
                    }
                    return;
                }
                select.innerHTML = `<option value="">Zoeken mislukt: ${escapeHtml(error.message)}</option>`;
                select.disabled = false;
            });
    }

    function rebuildComponents() {
        // Kan aangeroepen worden zonder gekozen beugel (bijv. via
        // applyLocationFilter(), dat direct rebuildComponents() aanroept
        // ook als de config-cog gebruikt wordt vóórdat er een beugel
        // gekozen is).
        if (!state.selectedClamp) return;

        const family = getMaterialFamily();
        const mapping = [
            [1, ui.loc1, 'Geen passende lasplaat/glijmoer'],
            [3, ui.loc3, 'Geen passende borgplaat'],
            [4, ui.loc4, 'Geen passende dekplaat'],
            [5, ui.loc5, 'Geen passende bout'],
        ];
        mapping.forEach(([pos, select, emptyText]) => {
            if (!family) {
                select.innerHTML = '<option value="">Kies eerst Staal of RVS</option>';
                select.disabled = true;
                emptyWarnings[pos] = null;
                return;
            }
            applyLocationFilterSelect(pos, select, family, pos, { emptyText, trackWarning: true });
        });
        refreshLocationWarnings();
        refreshExtraItems();
        updateAssemblyCode();
    }

    /**
     * Vult de artikel-select van een vrij toegevoegde extra regel (zie
     * addExtraItemRow()) voor de gekozen soort (pos) - zelfde live bron als
     * de vaste locaties (applyLocationFilterSelect()).
     */
    function populateExtraArticleSelect(pos, select, requestKey) {
        const family = getMaterialFamily();
        if (!family) {
            select.innerHTML = '<option value="">Kies eerst Staal of RVS</option>';
            select.disabled = true;
            return;
        }
        applyLocationFilterSelect(pos, select, family, requestKey, { emptyText: `Geen passend artikel voor materiaalsoort ${family}` });
    }

    let extraItemCounter = 0;

    /**
     * Verversen van alle al toegevoegde extra-regels (zie
     * addExtraItemRow()) - aangeroepen vanuit rebuildComponents(), dus bij
     * elke wijziging van beugel/materiaalcode. Regels waarvoor nog geen
     * soort gekozen is, worden overgeslagen (artikel-select blijft
     * disabled tot een soort gekozen is).
     */
    function refreshExtraItems() {
        if (!ui.assemblyList) return;
        ui.assemblyList.querySelectorAll('.extra-item-card').forEach(card => {
            const soortSelect = card.querySelector('.extra-item-soort');
            const artikelSelect = card.querySelector('.extra-item-artikel');
            const pos = soortSelect.value;
            if (!pos) return;
            populateExtraArticleSelect(pos, artikelSelect, `extra-${card.dataset.extraId}`);
        });
    }

    /**
     * Voegt een nieuwe, vrij te configureren artikelregel toe aan de
     * samenstelling - klik op de "+"-knop bij een vaste locatie (1/2/3/4/5,
     * zie bindEvents()) of bij een al eerder toegevoegde extra regel
     * (zie de "+"-knop hieronder in deze functie zelf). De regel komt
     * direct ONDER de knop waarop geklikt is (afterElement), niet
     * onderaan de hele lijst - vandaar de insertAdjacentElement('afterend', ...)
     * i.p.v. ui.assemblyList.appendChild().
     *
     * De gebruiker kiest eerst de soort (dezelfde opties als de vaste
     * locaties, inclusief Beugel - zie LOCATION_LABELS/applyLocationFilterSelect()
     * voor hoe een 2e beugel binnen dezelfde bouwgroep gekozen kan worden),
     * dan het artikel binnen die soort, en het aantal (standaard 1, bij
     * Bout 2).
     */
    function addExtraItemRow(afterElement) {
        const id = ++extraItemCounter;
        const requestKey = `extra-${id}`;

        const card = document.createElement('article');
        card.className = 'location-card extra-item-card';
        card.dataset.extraId = String(id);

        const numberDiv = document.createElement('div');
        numberDiv.className = 'location-number';
        numberDiv.textContent = '+';

        const imageDiv = document.createElement('div');
        imageDiv.className = 'location-image is-empty';
        const imagePlaceholder = document.createElement('span');
        imagePlaceholder.className = 'location-image-placeholder';
        imagePlaceholder.textContent = '—';
        imageDiv.appendChild(imagePlaceholder);

        const content = document.createElement('div');
        content.className = 'location-content extra-item-content';

        const aantalInput = document.createElement('input');
        aantalInput.type = 'number';
        aantalInput.className = 'extra-item-aantal';
        aantalInput.min = '1';
        aantalInput.value = '1';
        aantalInput.setAttribute('aria-label', 'Aantal');

        const soortSelect = document.createElement('select');
        soortSelect.className = 'extra-item-soort';
        const soortPlaceholder = document.createElement('option');
        soortPlaceholder.value = '';
        soortPlaceholder.textContent = 'Kies soort…';
        soortSelect.appendChild(soortPlaceholder);
        Object.entries(LOCATION_LABELS).forEach(([pos, label]) => {
            const option = document.createElement('option');
            option.value = pos;
            option.textContent = label;
            soortSelect.appendChild(option);
        });

        const artikelSelect = document.createElement('select');
        artikelSelect.className = 'extra-item-artikel';
        artikelSelect.disabled = true;
        artikelSelect.innerHTML = '<option value="">Kies eerst een soort</option>';

        const priceBox = document.createElement('div');
        priceBox.className = 'location-price';
        const priceSpan = document.createElement('span');
        priceSpan.className = 'extra-item-price price-box';
        priceSpan.textContent = '—';
        const stockSpan = document.createElement('span');
        stockSpan.className = 'extra-item-stock price-box';
        stockSpan.textContent = '—';
        priceBox.append(priceSpan, stockSpan);

        const addButton = document.createElement('button');
        addButton.type = 'button';
        addButton.className = 'location-add-button extra-item-add';
        addButton.setAttribute('aria-label', 'Extra artikel toevoegen');
        addButton.textContent = '+';

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'extra-item-remove';
        removeButton.setAttribute('aria-label', 'Extra artikel verwijderen');
        removeButton.textContent = '×';

        // Volgorde bepaalt de grid-kolom (zie .extra-item-content in
        // style.css): soort neemt de "titel"-kolomplek van de vaste
        // locaties over, zodat aantal/artikel/prijs/+ daarna verticaal
        // uitlijnen met de vaste rijen erboven/eronder.
        content.append(soortSelect, aantalInput, artikelSelect, priceBox, addButton, removeButton);
        card.append(numberDiv, imageDiv, content);
        if (afterElement) {
            afterElement.insertAdjacentElement('afterend', card);
        } else {
            ui.assemblyList.appendChild(card);
        }

        soortSelect.addEventListener('change', () => {
            const pos = soortSelect.value;
            // Standaard 1, behalve bij Bout (waarde "5"): standaard 2,
            // maar 1 bij een dubbele beugel (zie isDubbelBeugel()/
            // resetAantalFields()). Alleen gezet bij het wisselen van
            // soort, zodat een handmatig aangepast aantal daarna niet
            // weer overschreven wordt.
            aantalInput.value = pos === '5' ? (isDubbelBeugel() ? '1' : '2') : '1';
            if (!pos) {
                delete locationFilterRequests[requestKey];
                artikelSelect.disabled = true;
                artikelSelect.innerHTML = '<option value="">Kies eerst een soort</option>';
                refreshLocationPrices();
                return;
            }
            populateExtraArticleSelect(pos, artikelSelect, requestKey);
            refreshLocationPrices();
        });
        artikelSelect.addEventListener('change', refreshLocationPrices);
        aantalInput.addEventListener('input', recomputeTotal);
        addButton.addEventListener('click', () => addExtraItemRow(card));
        removeButton.addEventListener('click', () => {
            delete locationFilterRequests[requestKey];
            card.remove();
            refreshLocationPrices();
        });
    }

    function updateAssemblyCode() {
        const clamp = state.selectedClamp;
        const wcode = computeLocation6Code(currentMaterialByRole());
        ui.loc6.textContent = wcode || '—';
        updateShapeImages();
        refreshLocationPrices();
        if (!clamp) {
            ui.assemblyCode.textContent = 'Selecteer eerst een beugel';
            ui.copyButton.disabled = true;
            ui.codeHint.innerHTML = 'Locaties 1, 3, 4 en 5 zijn optioneel; locatie 6 wordt automatisch bepaald. Locatie 2 gebruikt de beugelcode, bijvoorbeeld <strong>215 PP</strong> of <strong>3015 PP</strong>.';
            return;
        }

        // Alleen daadwerkelijk gekozen locaties worden in de code opgenomen.
        // De volgorde blijft altijd locatie 1 → 6. select.value werkt voor
        // zowel CSV-gedreven als live Exact-gefilterde locaties (zie
        // applyLocationFilterSelect()) - beide zetten daar gewoon het
        // artikelnummer in.
        const parts = [
            ui.loc1.value ? firstCodePart(ui.loc1.value) : '',
            clampCodePart(clamp),
            ui.loc3.value ? firstCodePart(ui.loc3.value) : '',
            ui.loc4.value ? firstCodePart(ui.loc4.value) : '',
            ui.loc5.value ? firstCodePart(ui.loc5.value) : '',
            wcode || '',
        ].filter(Boolean);

        const code = parts.join('-');
        ui.assemblyCode.textContent = code;
        ui.copyButton.disabled = !code;
        ui.codeHint.textContent = '';
    }

    function formatPrice(value) {
        const n = Number(String(value).replace(',', '.'));
        if (!Number.isFinite(n)) return '';
        return `€ ${n.toFixed(2).replace('.', ',')}`;
    }

    /**
     * Voorraad als kaal getal voor zijn eigen kader (.price-box), geen "op
     * voorraad"-tekst erbij. Een voorraad van 0 is een geldige,
     * betekenisvolle waarde (géén voorraad) en wordt dus wél getoond -
     * alleen een lege/onbekende waarde (geen koppeling, artikel niet
     * gevonden) geeft een liggend streepje, zodat het kader altijd
     * dezelfde hoogte/uitlijning houdt, ook zonder gekozen artikel.
     */
    function formatStock(stock) {
        const stockNum = stock === undefined || stock === null || stock === ''
            ? null
            : Number(String(stock).replace(',', '.'));
        return stockNum !== null && Number.isFinite(stockNum) ? String(Math.trunc(stockNum)) : '—';
    }

    let priceDebounce = null;

    /**
     * Haalt de verkoopprijs + vrije voorraad op (Exact, api/exact_prices.php)
     * van het artikel dat nu bij elke locatie (1-5) gekozen is, en toont die
     * elk in hun eigen kader (.price-box: 1 voor prijs, 1 voor voorraad - zie
     * formatPrice()/formatStock()). Wordt aangeroepen vanuit
     * updateAssemblyCode() - dus bij elke wijziging van een locatieselectie/
     * beugel/materiaalcode. Locatie 6 heeft geen eigen prijs/voorraad (geen
     * apart te kiezen artikel meer, zie computeLocation6Code()).
     */
    function refreshLocationPrices() {
        if (priceDebounce) clearTimeout(priceDebounce);

        const entries = [
            [ui.priceLoc1, ui.stockLoc1, norm(ui.loc1.value)],
            [ui.priceLoc2, ui.stockLoc2, state.selectedClamp ? norm(state.selectedClamp['Artikelcode']) : ''],
            [ui.priceLoc3, ui.stockLoc3, norm(ui.loc3.value)],
            [ui.priceLoc4, ui.stockLoc4, norm(ui.loc4.value)],
            [ui.priceLoc5, ui.stockLoc5, norm(ui.loc5.value)],
        ];
        // Extra, vrij toegevoegde regels (zie addExtraItemRow()) tellen op
        // dezelfde manier mee in de prijs-batch-lookup - niet vermenigvuldigd
        // met het aantal, consistent met de vaste locaties 1-6.
        const extraEntries = ui.assemblyList
            ? [...ui.assemblyList.querySelectorAll('.extra-item-card')].map(card => [
                card.querySelector('.extra-item-price'),
                card.querySelector('.extra-item-stock'),
                norm(card.querySelector('.extra-item-artikel').value),
            ])
            : [];
        entries.push(...extraEntries);
        // "—" i.p.v. leeg: elk kader houdt zo altijd dezelfde hoogte, ook
        // zonder gekozen artikel (zie .price-box in style.css) - anders stond
        // elke rij net iets anders uitgelijnd.
        entries.forEach(([priceEl, stockEl]) => {
            if (priceEl) priceEl.textContent = '—';
            if (stockEl) stockEl.textContent = '—';
        });

        const codes = unique(entries.map(([, , code]) => code));
        if (codes.length === 0) {
            recomputeTotal();
            return;
        }

        priceDebounce = setTimeout(() => {
            fetch(`api/exact_prices.php?codes=${encodeURIComponent(codes.join(';'))}`, { cache: 'no-store' })
                .then(response => response.json())
                .then(payload => {
                    if (!payload || payload.ok !== true) return;
                    entries.forEach(([priceEl, stockEl, code]) => {
                        if (!code) return;
                        if (priceEl) priceEl.textContent = formatPrice(payload.prices[code]) || '—';
                        if (stockEl) stockEl.textContent = formatStock((payload.stock || {})[code]);
                    });
                    Object.entries(payload.prices || {}).forEach(([code, value]) => {
                        const n = Number(String(value).replace(',', '.'));
                        if (Number.isFinite(n)) state.lastPrices[code] = n;
                    });
                    Object.entries(payload.stock || {}).forEach(([code, value]) => {
                        const n = Number(String(value).replace(',', '.'));
                        if (Number.isFinite(n)) state.lastStock[code] = n;
                    });
                    recomputeTotal();
                })
                .catch(() => {});
        }, 200);
    }

    function aantalValue(input) {
        const n = parseInt(input && input.value, 10);
        return Number.isFinite(n) && n > 0 ? n : 1;
    }

    /**
     * Berekent 2 totalen voor de samenstelling, over de vaste locaties 1-5
     * (locatie 6 heeft geen eigen artikel/prijs/voorraad meer, zie
     * computeLocation6Code()) én elke extra, vrij toegevoegde regel:
     * - Totaalprijs: som van (verkoopprijs x aantal).
     * - Totaal beschikbaar: de bottleneck - het laagste van
     *   floor(voorraad / aantal) over alle onderdelen met een bekende
     *   voorraad, dus hoeveel complete samenstellingen er NU gemaakt
     *   kunnen worden gegeven de huidige voorraad van het krapste
     *   onderdeel. Onderdelen zonder bekende voorraad tellen niet mee
     *   (blokkeren de berekening niet).
     * Gebruikt de laatst opgehaalde prijzen/voorraad (state.lastPrices/
     * state.lastStock, zie refreshLocationPrices()) i.p.v. zelf opnieuw te
     * fetchen, zodat wijzigen van een aantal direct (zonder
     * netwerkvertraging) nieuwe totalen toont.
     */
    function recomputeTotal() {
        if (!ui.assemblyTotalPrice) return;

        const entries = [
            [norm(ui.loc1.value), aantalValue(ui.aantalLoc1)],
            [state.selectedClamp ? norm(state.selectedClamp['Artikelcode']) : '', aantalValue(ui.aantalLoc2)],
            [norm(ui.loc3.value), aantalValue(ui.aantalLoc3)],
            [norm(ui.loc4.value), aantalValue(ui.aantalLoc4)],
            [norm(ui.loc5.value), aantalValue(ui.aantalLoc5)],
        ];
        if (ui.assemblyList) {
            ui.assemblyList.querySelectorAll('.extra-item-card').forEach(card => {
                entries.push([
                    norm(card.querySelector('.extra-item-artikel').value),
                    aantalValue(card.querySelector('.extra-item-aantal')),
                ]);
            });
        }

        let total = 0;
        let hasPrice = false;
        let available = Infinity;
        let hasStock = false;
        entries.forEach(([code, aantal]) => {
            if (!code) return;
            const price = state.lastPrices[code];
            if (typeof price === 'number' && Number.isFinite(price)) {
                total += price * aantal;
                hasPrice = true;
            }
            const stock = state.lastStock[code];
            if (typeof stock === 'number' && Number.isFinite(stock)) {
                available = Math.min(available, Math.floor(stock / aantal));
                hasStock = true;
            }
        });

        ui.assemblyTotalPrice.textContent = hasPrice ? formatPrice(total) : '';
        ui.assemblyTotalPriceRow.classList.toggle('is-empty', !hasPrice);
        ui.assemblyTotalStock.textContent = hasStock ? String(Math.max(0, available)) : '';
        ui.assemblyTotalStockRow.classList.toggle('is-empty', !hasStock);
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
        ui.loc2.textContent = '—';
        setMaterialFamilyDisabled(true);
        [ui.loc1, ui.loc3, ui.loc4, ui.loc5].forEach(select => {
            select.innerHTML = '<option value="">Kies eerst een beugel</option>';
            select.disabled = true;
        });
        resetAantalFields();
        showWarnings([]);
        updateAssemblyCode();
    }

    function bindEvents() {
        ui.diameterInput.addEventListener('input', () => {
            resizeDiameterField();
            queryExactLive(ui.diameterInput.value);
        });
        ui.materialFamily.querySelectorAll('.family-switch-option').forEach(button => {
            button.addEventListener('click', () => {
                if (button.disabled) return;
                setMaterialFamilyValue(button.dataset.family);
                rebuildComponents();
            });
        });
        [ui.loc1, ui.loc3, ui.loc4, ui.loc5].forEach(select => select.addEventListener('change', updateAssemblyCode));
        // Aantal wijzigen hoeft geen nieuwe prijs op te halen - alleen de
        // totaalprijs opnieuw berekenen met de al bekende prijzen.
        [ui.aantalLoc1, ui.aantalLoc2, ui.aantalLoc3, ui.aantalLoc4, ui.aantalLoc5]
            .forEach(input => input.addEventListener('input', recomputeTotal));

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

        ui.locationConfigButtons.forEach(button => {
            button.addEventListener('click', () => openLocationFilterModal(button.dataset.location));
        });
        ui.locationAddButtons.forEach(button => {
            button.addEventListener('click', () => addExtraItemRow(button.closest('.location-card')));
        });
        ui.locationFilterApply.addEventListener('click', () => applyLocationFilter(ui.locationFilterInput.value));
        ui.locationFilterClear.addEventListener('click', () => applyLocationFilter(''));
        ui.locationFilterCancel.addEventListener('click', closeLocationFilterModal);
        ui.locationFilterOverlay.addEventListener('click', event => {
            if (event.target === ui.locationFilterOverlay) closeLocationFilterModal();
        });
        ui.locationFilterInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') applyLocationFilter(ui.locationFilterInput.value);
            if (event.key === 'Escape') closeLocationFilterModal();
        });
    }

    function init() {
        resizeDiameterField();
        bindEvents();
        syncLocationConfigButtons();
        // Geen CSV meer te laden - alles komt live uit Exact (zie
        // queryExactLive()/applyLocationFilterSelect()), dus het diameterveld
        // kan direct actief worden.
        ui.diameterInput.disabled = false;
    }

    init();
})();
