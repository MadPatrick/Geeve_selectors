# How the Hose & Fitting Selector matches a hose to its couplings

A field-by-field account of `geeve_selectors/hoses`: which CSV columns feed it, how the three source files are merged into one article, and the exact rules the UI uses to search, filter and render a match — written so the same behaviour can be reproduced against the same CSVs in a different codebase.

Source: `hoses/index.php`, `hoses/inc/csv-paths.php`, `hoses/assets/selector.js`.

## 1. How the pieces fit together

There is no database and no build step. On every page load, `index.php` reads the CSV files straight off disk, turns them into one JSON array, and prints that array into the page as `window.ARTICLES`. From that point on the entire selector — search, filtering, rendering — is client-side JavaScript working against that one in-memory array.

Re-implementing it elsewhere means reproducing two things faithfully: the **merge** (turning 3 CSVs into 1 record per hose) and the **match conditions** (how a typed query narrows that array down).

```
01 Read   -> 3 CSVs parsed by header name, comma-delimited
02 Merge  -> staal + rvs joined on artnr; accessoires joined in
03 Emit   -> window.ARTICLES, one JSON array, sorted by artnr
04 Match  -> JS filters the in-memory array on every keystroke
```

## 2. The three CSV files

All three share the same low-level format: `,`-delimited (RFC4180, quoted where a field contains a comma or a literal `"`), UTF-8 with a BOM, one header row. Columns are looked up **by name**, not position — column order in the file doesn't matter, and a missing column just resolves to an empty string rather than erroring.

> **Was `;`-delimited until September 2026.** GitHub's web preview always assumes a `.csv` file is comma-delimited, regardless of the file's actual delimiter — for a `;`-delimited file, any field containing a literal comma or `"` character reliably produced a false-positive "should have N columns" or "illegal quoting" warning in GitHub's UI (the PHP loader parsed the file correctly either way; this only ever affected GitHub's own preview rendering, not the app). Converting to genuinely comma-delimited, properly RFC4180-quoted CSV eliminates that false positive permanently, matching the same fix already applied earlier to the adapters CSV.

| File | Contents |
|---|---|
| `artikelnummers_staal.csv` | Steel hose articles: identity + working pressure + up to 2 two-piece and 3 one-piece coupling variants |
| `artikelnummers_rvs.csv` | Same exact schema as the steel file, for stainless-steel hose articles |
| `artikelnummers_accessoires.csv` | One row per hose artnr: outer diameter plus the article numbers of matching sleeves/guards |

### artikelnummers_staal.csv / artikelnummers_rvs.csv

| Column | Meaning |
|---|---|
| `artnr` | Hose article number — the join key across all three files |
| `artnm` | Description |
| `Leverancier` | Vendor |
| `Artikelnr leverancier` | Vendor's own article number |
| `Werkdruk (bar)` | Working pressure |
| `2delig_1 - Huls` / `Pilaar` / `Persmaat (mm)` / `Schilmaat intern (mm)` / `Schilmaat extern (mm)` | Two-piece coupling, variant 1 (sleeve + stem) |
| `2delig_2 - …` *(same 5 fields)* | Two-piece coupling, variant 2 |
| `1delig_1` / `Persmaat (mm)` / `Insteekdiepte (mm)` / `Schilmaat intern (mm)` / `Schilmaat extern (mm)` | One-piece coupling, variant 1 |
| `1delig_2 - …`, `1delig_3 - …` *(same 4 fields each)* | One-piece coupling, variants 2 and 3 |

A variant only counts as present if its defining field is non-empty: a two-piece variant needs `Huls` or `Pilaar`; a one-piece variant needs its bare `1delig_N` coupling-name field. Empty variant slots are simply skipped, not stored as blanks.

### artikelnummers_accessoires.csv

| Column | Meaning |
|---|---|
| `artnr` | Hose article number — join key back to staal/rvs |
| `Buitenmaat slang (mm)` | Hose outer diameter |
| `RVS Omvlechting` | Matching stainless braid article |
| `PolyGuard` / `SpiralGuard` | Matching guard articles (shown merged as one field) |
| `ParKoil` / `Spring Guard` / `Firesleeve` / `Texsleeve` | Other matching accessory articles |
| `Huls tex staal` / `Huls tex RVS` | Texsleeve sleeve articles, steel and stainless |

## 3. Reading a CSV row

Every value goes through the same two-step clean before it's used anywhere — in a filter, in a lookup key, or on screen.

1. **Column lookup is case- and whitespace-insensitive.** Header names are lower-cased and BOM-stripped before comparison, so `ARTNR`, `artnr` and a header with a stray leading byte-order-mark all resolve to the same column.
2. **Every cell value is cleaned** before use: non-breaking spaces (`\xC2\xA0`) and a stray Excel export artefact (`_x000D_`) are replaced with a plain space, runs of whitespace are collapsed to one space, and the result is trimmed. A row is skipped entirely if its `artnr` cell is empty after this cleaning.

> **Row shape mismatch → skip, not error.** If a data row has a different number of fields than the header row (a stray delimiter, a missing cell), that single row is silently dropped rather than crashing the whole load. One bad row never takes down the rest of the catalogue.

## 4. Merging staal + rvs + accessoires

Every article is keyed by `"@" + artnr.trim().toLowerCase()` — that's the join key across all three files, and it's why matching is naturally case-insensitive: `462TC-08` and `462tc-08` land in the same bucket.

1. Walk `artikelnummers_staal.csv` first, then `artikelnummers_rvs.csv`. The first file to mention a given artnr creates the merged record; the second only fills in whichever of `artnm`/`Leverancier`/`Artikelnr leverancier`/`Werkdruk` the first one left blank.
2. Each file contributes its own coupling lists into separate slots on the merged record — steel rows never overwrite RVS coupling data and vice versa, even when the same artnr appears in both files (a hose available in both materials keeps both sets of couplings).
3. `accessoires.csv` is joined in by the same key, independent of material — whichever of staal/rvs is processed, if the artnr has an accessoires row it's attached once.
4. The final array is sorted by artnr using a natural, case-insensitive comparator (so `0492-8` sorts before `0492-12`, not after).

## 5. The article record

This is the exact shape of one entry in `window.ARTICLES` — the only data structure the front end ever touches.

```js
{
  artnr: "462TC-08",
  artnm: "SUPER TOUGH COVER ...",
  vendor: "Parker",
  supplier: "462TC-08",
  werkdruk: "225",

  comboStaal: [ { number: 1, huls, pilaar, persmaat, schilIntern, schilExtern }, ... ],   // up to 2
  koppelingStaal: [ { number: 1, koppeling, persmaat, insteekdiepte, schilIntern, schilExtern }, ... ], // up to 3
  comboRvs: [ ... ],       // same shape as comboStaal
  koppelingRvs: [ ... ],   // same shape as koppelingStaal

  accessories: {
    outside, rvsOmvlechting, polyGuard, parKoil,
    springGuard, firesleeve, spiralGuard, texsleeve,
    hulsTexStaal, hulsTexRvs
  }
}
```

Every field is always present (empty string / empty array when the CSV had nothing) — the UI never has to check for a missing key, only for an empty value.

## 6. Search conditions

There is exactly one search function, and it branches on which of the three input fields are non-empty. All comparisons run through the same normaliser: `String(value).trim().toLowerCase()`, then a plain substring test (`.includes()`) — no fuzzy matching, no tokenising, no ranking. Results are capped at the first 40 matches.

**Branch A — free-text query is non-empty.** Wins over the maat/werkdruk fields entirely (they're ignored while a text query is present). An article matches if the query is a substring of **any** of these four fields:

| Field | Source |
|---|---|
| `article.artnr` | Article number |
| `article.artnm` | Description |
| `article.supplier` | Vendor's own article number |
| `article.vendor` | Vendor name |

**Branch B — text query empty, maat and/or werkdruk given.** Both conditions must hold (logical AND); either one is skipped if its field is empty:

| Condition | Rule |
|---|---|
| Werkdruk | `normalize(article.werkdruk).includes(werkdrukQuery)` |
| Maat | `articleMaat(article.artnr).includes(maatQuery)` — see §7 |

**Branch C — everything empty.** No query at all → suggestions list is closed and the result panel is hidden. There is no "show everything" state.

> **Selecting a result** is a separate step from matching: clicking (or Enter-selecting) a suggestion doesn't re-run any condition — it just takes that exact article object and renders it. The match conditions only decide what appears in the dropdown.

## 7. How "maat" is derived from an artnr

There is no dedicated "size" column in the source CSVs — the maat is parsed out of the article number itself, on the fly, every time it's needed (in the maat filter, and to populate the werkdruk dropdown's suggested values). One function, used everywhere:

```js
function articleMaat(artnr) {
  const text = String(artnr ?? '');
  const dashIndex = text.indexOf('-');
  // the size is the digit run right after the FIRST dash - e.g. "03" in
  // "2440D-03V32", where "V32" is a separate code, not the size.
  const searchText = dashIndex === -1 ? text : text.slice(dashIndex + 1);
  const match = searchText.match(/(\d+)/);
  return match ? match[1] : '';
}
```

1. If the artnr has a dash, everything *before* the first dash is discarded — only text from the first dash onward is searched.
2. Within that remainder, the maat is the *first run of digits* found — not the whole remainder, and not the last digit run.
3. If there's no dash at all (e.g. `AIR06MM`), the same "first digit run" rule is applied to the entire artnr instead.
4. The maat filter itself is a substring match on this extracted string, not an exact match — typing `1` matches maat `10`, `12`, `16`, etc.

> **Why "first" and not "last" dash-group matters:** an artnr like `2440D-03V32` has a second digit run (`32`) later in the string that is a coupling/variant code, not the hose size — taking the first digit run after the first dash is what correctly isolates `03`.

## 8. Rendering a match

Once an article is selected, three independent blocks render from the same object — each one hides itself if it has nothing to show:

| Block | Source fields | Hidden when… |
|---|---|---|
| One-piece couplings | `koppelingStaal` + `koppelingRvs`, grouped by material | both arrays are empty |
| Two-piece couplings | `comboStaal` + `comboRvs`, grouped by material | both arrays are empty |
| Accessories | all 9 `accessories.*` fields | every one of the 9 fields is empty |

If *both* coupling blocks are empty, an explicit "no couplings catalogued yet" message is shown instead of a blank panel. Millimetre fields get a trailing `mm` appended for display only (never stored that way) unless the value already ends in "mm".

## 9. Grouping hoses by type (print catalogue only)

This logic doesn't affect search or selection — it only feeds the printable PDF catalogue, which groups every hose article by a derived "type" so variants of the same hose print together. Worth knowing about because it encodes real naming conventions in the article numbers:

1. **SR/SRI family** (Parker multi-spiral hoses): `SR25`, `SR29`, `SRI42`, etc. all collapse into one `SR/SRI` group regardless of the number.
2. **PLK family**: the digits right after `PLK` don't identify a meaningful sub-type, so `PLK1…`, `PLK2…` etc. all collapse into one `PLK` group.
3. **Everything else:** the text before the first dash is split into `[letters][digits][letters]`. A trailing `ST`/`TC`/`SN` suffix is merged into the bare series (so `0492ST` and `0492` print as one type); any other suffix (`PU`, `RH`, `LT`, …) stays its own distinct type.

## 10. Conventions worth copying exactly

1. Delimiter is `,`, not `;` — every one of the three files, no exceptions. Quote a field (RFC4180, `"..."` with `""` for a literal embedded quote) whenever it contains a comma, a `"`, or a newline; nothing else needs quoting.
2. Files are read with a UTF-8 BOM; the loader strips a leading BOM off the *header row* specifically before comparing column names.
3. Column matching is by **name**, case-insensitively, never by position — reordering columns in the CSV changes nothing.
4. The join/lookup key is always `"@" + artnr.trim().toLowerCase()` — reuse this exact prefix-and-case convention so hand-typed queries and CSV values compare the same way.
5. All three files tolerate **candidate paths**: each is looked for first in a `data/` subfolder, then in the app root, and the first one found wins — useful if the new program wants to keep the same "drop a replacement CSV in place" workflow.
6. A missing or unreadable file never crashes the app — it's recorded as a load error shown in a banner, and that file's data is simply absent from the merge.
7. Text matching is **substring**, not prefix or exact, everywhere in the selector — artnr, description, vendor, supplier, werkdruk and maat all use `.includes()`.
