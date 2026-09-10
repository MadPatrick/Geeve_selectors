# Geeve Hydraulics — Selectors

Startpagina met tegels naar de Geeve-selectors:

- **`/hoses`** — Slangen fitting Selector (kopie van `madpatrick/Geeve_hose`)
- **`/adapters`** — Adapters Selector (kopie van `madpatrick/Geeve_adapters`)
- **`/stauff`** — Stauff Selector / beugelconfigurator (kopie van `madpatrick/Geeve_stauff`, layout
  omgezet naar dezelfde brand-panel/paneel-stijl als `/hoses` en `/adapters`)

## Gebruik

Zet de hele map op een PHP-webserver (PHP 8+, geen database nodig) en open `index.php`. Elke
subapp is zelfstandig en gebruikt alleen relatieve paden (`assets/...`, `images/...`, `data/...`),
dus werkt zonder aanpassingen op elke diepte.

## Structuur

```
index.php              Startpagina met tegels
assets/style.css        Styling van alleen de startpagina
images/                 Logo's voor de startpagina (Geeve + Rubix)
hoses/                  Volledige Slangen fitting Selector-app (eigen assets/data/docs/etc.)
adapters/               Volledige Adapters Selector-app (eigen assets/data/docs/etc.)
stauff/                 Volledige Stauff Selector-app (eigen assets/data/api/etc.)
```

## Bijwerken van een subapp

`/hoses`, `/adapters` en `/stauff` zijn kopieën van hun eigen bronrepo op het moment van aanmaken
(`/stauff` is qua opmaak omgezet naar de gedeelde brand-panel/paneel-stijl; de selectielogica in
`assets/selector.js` en `api/stauff.php` is ongewijzigd). Wijzigingen in `madpatrick/Geeve_hose`,
`madpatrick/Geeve_adapters` of `madpatrick/Geeve_stauff` komen hier dus niet automatisch door —
kopieer de bijgewerkte bestanden opnieuw naar de betreffende submap wanneer een van de apps los is
bijgewerkt.
