# How the Adapters Selector matches a fitting on two ports at once

A field-by-field account of `geeve_selectors/adapters`: the CSV it reads, the facet filters it builds from that data, and — the part worth copying carefully — how it treats a fitting's two ports as interchangeable when matching.

Source: `adapters/index.php`, `adapters/inc/csv-paths.php`, `adapters/assets/selector.js`.

## 1. How the pieces fit together

Same skeleton as the hose selector — no database, `index.php` reads a CSV on every load and prints it into the page as `window.ARTICLES` — but the search itself works completely differently. There's no text box: the user picks facets (thread type, thread size, connection type, shape) from dropdowns and icon buttons, and every change re-filters the full in-memory array. There's also only one source file, so there's no merge step at all.

```
01 Read    -> 1 CSV, parsed by header name
02 Emit    -> window.ARTICLES, sorted by artnr
03 Derive  -> Facet option lists built from the data itself, in JS
04 Match   -> Every facet change re-filters the array, port-order-agnostic
```

## 2. The CSV file

One file: `artikelnummers_adapters.csv`. One row is one specific adapter variant — a fixed combination of both ports' thread type, thread size and connection type, plus its shape. Same low-level format as the hose files: `;`-delimited, UTF-8 with BOM, columns looked up by name.

Location: `data/artikelnummers_adapters.csv`, falling back to `artikelnummers_adapters.csv` in the app root.

| Column | Meaning |
|---|---|
| `artnr` | Article number |
| `cross_ref` | Manufacturer's own cross-reference code |
| `familie_code` | Family/series code — also the key used to find that family's illustration (`images/{familie_code}.png`) |
| `familie_naam` | Family name, e.g. "Union", "Male stud elbow 90°" |
| `omschrijving` | Free-text description (not used for filtering — display / print only) |
| `hoek` | Shape: `recht`, `haaks`, `45°`, `T-stuk`, `kruis`, or `n.v.t.` for single-port items |
| `draadsoort_1` | Port 1 thread standard: `BSPP`, `BSPT`, `NPT/NPTF`, `NPSM`, `JIC`, `JIS`, `UN/UNF`, `Metric` |
| `draadmaat_1` | Port 1 thread size, e.g. `3/8-19`, `M 16×1.5` |
| `connectie_type_1` | Port 1 connection type: `buiten` (male) / `binnen` (female) / `wartelend` (swivel) |
| `draadsoort_2` / `draadmaat_2` / `connectie_type_2` | Same three fields for port 2 — blank for a single-port item (plug, cap, blanking nut, …) |
| `tube_od_mm` / `tube_od_inch` | Tube outer diameter, where relevant (not used for filtering) |
| `bron_pagina` | Source catalogue page (reference only) |

## 3. Reading a CSV row

Identical cleaning rules to the hose selector — both apps share the same small PHP helpers:

1. Column lookup is case- and whitespace-insensitive, with the header row's BOM stripped before comparison.
2. Every cell value has NBSP and a stray Excel export artefact (`_x000D_`) replaced with a space, runs of whitespace collapsed, then trimmed.
3. A row is skipped if `artnr` is empty after cleaning, or if the row has a different number of fields than the header (no crash, just silently dropped).

## 4. The article record

One row in, one flat object out — no nesting, no per-material split like the hose selector has:

```js
{
  artnr: "2244-04-06",
  crossRef: "6-4HMK4S",
  familieCode: "2244",
  familieNaam: "Union",
  omschrijving: "BSPP 60° Cone end",
  hoek: "recht",

  draadsoort1: "BSPP",   draadmaat1: "3/8-19",   connectieType1: "buiten",
  draadsoort2: "BSPP",   draadmaat2: "1/4-19",   connectieType2: "buiten",

  tubeOdMm: "",
  tubeOdInch: ""
}
```

## 5. Building the filter facets

Unlike the hose selector's free-text search, every filter option here is a closed list **derived from the loaded data itself** — there's no hardcoded list of thread standards or sizes anywhere in the code.

### Draadsoort (thread standard)

The dropdown for both Aansluiting 1 and Aansluiting 2 is filled from the *same* list: every distinct non-empty value across both `draadsoort1` and `draadsoort2` of every article, alphabetically sorted. Both ports offer identical options because — see §7 — which one is "port 1" is not physically meaningful.

### Draadmaat (thread size) — cascades from draadsoort

This dropdown's options depend on the draadsoort chosen for that same port. A lookup map is built once at load time:

```js
draadmaatBySoort.set(soort, uniqueSorted(
  articles.flatMap(a => [
    a.draadsoort1 === soort ? a.draadmaat1 : '',
    a.draadsoort2 === soort ? a.draadmaat2 : '',
  ]),
  bySizeThenAlphabetically
));
```

Picking "Alle" for draadsoort falls back to the full union of every draadmaat value across the whole dataset. Changing draadsoort always repopulates draadmaat and resets it back to "Alle" (unless the previous value happens to still be valid for the new draadsoort).

### Size ordering

Sizes are sorted by actual magnitude, not alphabetically — alphabetical order would put `1/2-14` before `1/4-19`, which is wrong. One function handles all three notations found in the data:

```js
function sizeSortKey(text) {
  if (!text) return 99999;
  const metric = /^M(\d+)/.exec(text);
  if (metric) return 10000 + parseFloat(metric[1]);       // "M16..." -> 10016
  const mixed = /^(\d+)?\s*(\d+)\/(\d+)/.exec(text);
  if (mixed) {                                              // "1 1/4-11" -> 1.25
    const whole = mixed[1] ? parseFloat(mixed[1]) : 0;
    return whole + parseFloat(mixed[2]) / parseFloat(mixed[3]);
  }
  const plain = /^(\d+(\.\d+)?)/.exec(text);
  if (plain) return parseFloat(plain[1]);
  return 99999;
}
```

Metric sizes are pushed into a `10000+` range so they always sort after every inch-based size, regardless of the numeric diameter.

### Connectie type & hoek — fixed icon sets, not derived

These two facets are the exception: their options are hardcoded buttons in the HTML (three connection-type icons per port, five hoek icons), not built from the CSV. A value the data contains but that has no matching button simply can't be filtered on.

## 6. The match conditions

Every facet is optional and every chosen facet is a hard AND condition — there's no text relevance, no partial/substring matching anywhere in this selector (contrast with the hose selector, which is substring-only). A per-side check is reused for both ports:

```js
function sideMatches(articleSoort, articleMaat, articleConnectie, selSoort, selMaat, selConnectie) {
  if (selSoort && articleSoort !== selSoort) return false;
  if (selMaat && articleMaat !== selMaat) return false;
  if (selConnectie && articleConnectie !== selConnectie) return false;
  return true;
}
```

1. **Draadsoort** and **draadmaat** are exact matches (equality, not substring) against the dropdown's selected value — empty selection ("Alle") always passes.
2. **Connectie type** is exact match against the single active icon per port — no icon active means unfiltered.
3. **Hoek** is evaluated once per article, not per side: `selectedHoek` is a *set* (multiple shapes can be active at once) and the article passes if its `hoek` is a member of that set, or if the set is empty.

## 7. Port-swap orientation — the one rule worth getting right

A physical adapter has no inherent "port 1" — it's the CSV's own storage order, not a property of the fitting. If a user sets Aansluiting 1 to BSPP and Aansluiting 2 to JIC, an article stored in the CSV as `draadsoort1=JIC, draadsoort2=BSPP` is exactly the same physical part and must still match. The selector handles this by trying **both** orientations and accepting either:

```js
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
```

The return value isn't just a boolean — it records *which* orientation matched, because the result table still needs to show each article's data under the column the user actually filtered by. For example, if the user selects Aansluiting 1 = BSPP 3/8-19 buiten and Aansluiting 2 = JIC 7/16-20 buiten, but the CSV stores that same article with `draadsoort1` = JIC and `draadsoort2` = BSPP, the match returns `orientation = 'swapped'`. The render step then displays the CSV's `draadsoort2` data under the "Aansluiting 1" column and `draadsoort1` under "Aansluiting 2" — so the table always agrees with what the user asked for, regardless of which side the source data happened to store it on.

> **Getting this wrong silently drops real matches.** A naive port-1-to-port-1, port-2-to-port-2 comparison will miss every article whose CSV storage order doesn't happen to agree with the order the user picked facets in — which, for a symmetric two-port catalogue, is roughly half of them.

## 8. Rendering a match

Filtering re-runs on every single facet change (no separate "search" step or button) and rebuilds the whole result table from scratch:

1. Matches are capped at the first **300** rows; if more exist, a note below the table says how many were found and asks the user to narrow the selection — the table itself never grows unbounded.
2. Each row's image is looked up directly as `images/{familieCode}.png` — a flat, direct filename convention, no matching heuristics involved. A 404 removes the broken `<img>` instead of showing a placeholder.
3. Side text is formatted as `"{draadsoort} {draadmaat} ({Connectietype})"`, e.g. `BSPP 3/8-19 (Buiten)`; a side with neither soort nor maat renders as an em dash.
4. Display labels (draadsoort, connectie type, hoek) are capitalised for display only — the underlying match and the CSV values stay lowercase/as-stored.

## 9. Conventions worth copying exactly

1. All facet comparisons are **exact equality**, not substring — the opposite convention from the hose selector's text search. Don't reuse a `.includes()` helper here.
2. **Try both orientations, always.** Any reimplementation that compares port 1 to port 1 and port 2 to port 2 only will under-match on a large, silent fraction of the catalogue.
3. Hoek is a **set**, evaluated once per article (not per side); every other facet is a single value evaluated per side.
4. Draadmaat options are **scoped to the selected draadsoort** — don't offer a flat, unscoped size list, or the dropdown will suggest sizes that don't exist for that thread standard.
5. Sort sizes by **parsed magnitude** (fraction and metric-aware), never alphabetically.
6. The image convention is a flat, direct `familie_code.png` lookup — no fuzzy or cross-reference-based image matching is involved in the live selector (a separate, one-off backfill process was used to source new artwork, but the selector itself only ever does a direct filename lookup).
7. Same low-level CSV conventions as the hose selector: `;` delimiter, UTF-8 BOM, header-name column lookup, candidate-path fallback (`data/` first, then app root), a missing file becomes a banner error rather than a crash.
