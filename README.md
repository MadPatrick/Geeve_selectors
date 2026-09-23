# Geeve Hydraulics — Selectors

Startpagina met tegels naar de Geeve-selectors:

- **`/hoses`** — Slangen fitting Selector (kopie van `madpatrick/Geeve_hose`)
- **`/adapters`** — Adapters Selector (kopie van `madpatrick/Geeve_adapters`)
- **`/stauff`** — Stauff Selector / beugelconfigurator (kopie van `madpatrick/Geeve_stauff`, layout
  omgezet naar dezelfde brand-panel/paneel-stijl als `/hoses` en `/adapters`)
- **`/hose-configurator`** — Slang configurator, zelfde brand-panel/paneel-stijl

## Gebruik

Zet de hele map op een PHP-webserver (PHP 8+, geen database nodig) en open `index.php`. Elke
subapp gebruikt verder alleen eigen relatieve paden (`assets/...`, `data/...`, eigen `images/...`
voor productfoto's), met drie uitzonderingen die uitgaan van de vaste nesting één niveau onder de
root: de "terug naar hoofdmenu"-knop (`../index.php`), het Geeve/Rubix-merklogo
(`../images/geeve.jpg` en `../images/rubix.jpg`, zie hieronder) en het gedeelde versienummer
(`../version.php`, zie hieronder). Een subapp-map los deployen buiten `Geeve_selectors` werkt dus
niet identiek zonder die bestanden zelf mee te kopiëren — gebruik daarvoor de eigen bronrepo
(`madpatrick/Geeve_hose`, `madpatrick/Geeve_adapters`, `madpatrick/Geeve_stauff`), die elk nog wel
hun eigen lokale `images/geeve.jpg`/`rubix.jpg` hebben; het versienummer valt in dat geval terug op
een hardcoded waarde in de pagina zelf (zie "Eén gedeeld versienummer" hieronder), dus dat breekt
niet.

## Structuur

```
index.php              Startpagina met tegels
version.php             Eén gedeeld versienummer voor hoofdscherm + alle subapps, zie hieronder
assets/style.css        Styling van alleen de startpagina
images/                 Gedeeld Geeve/Rubix-merklogo (geeve.jpg, rubix.jpg) - door alle
                        subapps gebruikt via ../images/..., één plek om bij te werken
docs/                   Bron-PDF's (catalogi, persmaatlijsten) - alleen referentiemateriaal,
                        wordt niet door de apps zelf ingelezen of gelinkt
  hoses/                Algemene hoses-referentiecatalogi (voorheen hoses/docs/*.pdf)
  perslijst/            Persmaatbladen per koppeling (voorheen hoses/perslijst/*.pdf)
  adapters/             Adapters-referentiecatalogi (voorheen adapters/docs/*.pdf)
hoses/                  Volledige Slangen fitting Selector-app (eigen assets/data/etc.)
adapters/               Volledige Adapters Selector-app (eigen assets/data + eigen
                        images/ met alleen de productfoto's per adapterfamilie)
stauff/                 Volledige Stauff Selector-app (eigen assets/data/api/etc.)
hose-configurator/      Volledige Slang configurator-app (eigen assets/data/etc.)
```

## Eén gedeeld versienummer

Het hoofdscherm en alle subapps (`hoses`, `adapters`, `hose-configurator`, `stauff`) tonen/gebruiken
sinds kort hetzelfde versienummer, uit `version.php` op rootniveau (`return '0.3.0';`). Elke pagina
laadt dit via `is_file(__DIR__ . '/version.php') ? (string) require __DIR__ . '/version.php' : '...'`
(root-pagina's) resp. `__DIR__ . '/../version.php'` (subapp-pagina's) i.p.v. een eigen losse
`APP_VERSION`-constante te declareren. Een versie-ophoging hoeft dus nog maar op één plek: pas het
`return '...'` in `version.php` aan. Ontbreekt `version.php` (bijv. bij een los buiten deze
portal-map gedeployde subapp-kopie), dan valt elke pagina terug op de hardcoded waarde na de `:` in
diezelfde regel - die fallback wordt niet automatisch bijgewerkt en kan dus afwijken; dat is alleen
relevant voor een standalone-deploy van een subapp, niet voor deze portal zelf.

## Bijwerken van een subapp

`/hoses`, `/adapters` en `/stauff` zijn kopieën van hun eigen bronrepo op het moment van aanmaken
(`/stauff` is qua opmaak omgezet naar de gedeelde brand-panel/paneel-stijl; de selectielogica in
`assets/selector.js` en `api/stauff.php` is ongewijzigd). Wijzigingen in `madpatrick/Geeve_hose`,
`madpatrick/Geeve_adapters` of `madpatrick/Geeve_stauff` komen hier dus niet automatisch door —
kopieer de bijgewerkte bestanden opnieuw naar de betreffende submap wanneer een van de apps los is
bijgewerkt. Kopieer daarbij **niet** `images/geeve.jpg`/`images/rubix.jpg` uit de bronrepo terug
in de submap — die verwijzing loopt hier bewust via het gedeelde `../images/` op rootniveau.
