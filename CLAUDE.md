# Werkafspraken voor Claude in deze repo

## Nieuwe (sub)app toevoegen - gedeelde layout is de standaard

Elke nieuwe app in deze portal gebruikt **standaard, zonder dit te hoeven vragen**, de gedeelde
layout: `shared/style.css` (vóór de eigen `assets/style.css`), `shared/header.php` voor de
header/brand-panel, en het gedeelde `version.php` (root) voor `APP_VERSION` - nooit een eigen,
losse kopie van tokens/header-markup/versienummer per app.

**Volg hiervoor altijd [`shared/README.md`](shared/README.md)** - de complete stap-voor-stap gids,
checklist en volledige CSS-/`header.php`-referentie. Lees dat document bij het opzetten van een
nieuwe app, ook als er niet expliciet naar gevraagd wordt.

Wat wél per app hoort (zie `shared/README.md`, "Wat hoort in je eigen `assets/style.css`"): eigen
secties/kaarten, stappenindicatoren, statuspillen e.d. - dat blijft bewust ongedeeld, ook al lijkt
het oppervlakkig op wat een andere app heeft.

## Versienummer (alle apps)

Eén gedeeld versienummer in `version.php` (root) voor het hoofdscherm en alle subapps - zie
`shared/README.md`, "Versienummer". Hoog dit **maximaal ~1x per feature/fix-commit** op (niet bij
elke losse kleine wijziging binnen dezelfde taak), tenzij de gebruiker expliciet om een andere
frequentie vraagt.

## Git

Commits gaan direct naar `main` (geen PR), tenzij de gebruiker expliciet om een aparte branch of
PR vraagt. Altijd `git fetch origin main` + vergelijken vóór het committen; bij een conflict
rebasen, nooit force-pushen.
