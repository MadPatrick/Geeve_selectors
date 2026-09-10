# Geeve Hydraulics — Selectors

Startpagina met tegels naar de twee Geeve-selectors:

- **`/hoses`** — Slangen fitting Selector (kopie van `madpatrick/Geeve_hose`)
- **`/adapters`** — Adapters Selector (kopie van `madpatrick/Geeve_adapters`)

## Gebruik

Zet de hele map op een PHP-webserver (PHP 8+, geen database nodig) en open `index.php`. Elke
subapp is zelfstandig en gebruikt alleen relatieve paden (`assets/...`, `images/...`, `data/...`),
dus werkt zonder aanpassingen op elke diepte.

## Structuur

```
index.php              Startpagina met 2 tegels
assets/style.css        Styling van alleen de startpagina
images/                 Logo's voor de startpagina (Geeve + Rubix)
hoses/                  Volledige Slangen fitting Selector-app (eigen assets/data/docs/etc.)
adapters/               Volledige Adapters Selector-app (eigen assets/data/docs/etc.)
```

## Bijwerken van een subapp

`/hoses` en `/adapters` zijn 1-op-1 kopieën van hun eigen bronrepo op het moment van aanmaken.
Wijzigingen in `madpatrick/Geeve_hose` of `madpatrick/Geeve_adapters` komen hier dus niet
automatisch door — kopieer de bijgewerkte bestanden opnieuw naar de betreffende submap wanneer
een van beide apps los is bijgewerkt.
