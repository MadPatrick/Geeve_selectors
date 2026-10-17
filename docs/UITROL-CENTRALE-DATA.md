# Uitrol: centrale data en korte adressen

Eenmalige stappen op de server (`/var/www/html`) voor de eerste `git pull` na de merge van de
branch `1_centrale-data-en-css`. Daarna is het weer gewoon `git pull`.

**Waarom:** de CSV's gaan uit git en verhuizen naar `data/`. Op de server zijn die CSV's lokaal
bewerkt (en staan ze deels op skip-worktree), dus een gewone `git pull` weigert. De
serverversies moeten eerst veilig naar `data/`.

## 1. Apache controleren (alleen lezen)

De korte adressen en "geen mappenlijsten" staan in de root-`.htaccess`. Mag Apache die regels niet
uitvoeren, dan geeft **de hele portal** een foutmelding 500. Controleer daarom eerst:

```bash
apache2ctl -M | grep rewrite          # verwacht: rewrite_module (shared)
grep -rn -A4 '<Directory /var/www' /etc/apache2/apache2.conf /etc/apache2/sites-enabled/
```

Bij `<Directory /var/www/html>` (of `/var/www/`) moet `AllowOverride All` staan, of minstens
`AllowOverride AuthConfig FileInfo Options`.

- `rewrite_module` ontbreekt: `a2enmod rewrite && systemctl reload apache2`.
- `AllowOverride` staat op `None` of mist `FileInfo`/`Options`: aanpassen in de Apache-config en
  `systemctl reload apache2`. Dat is een wijziging aan de server: alleen met akkoord van Patrick.

## 2. Data veiligstellen

```bash
cd /var/www/html
git fetch origin main
git show origin/main:tools/data-migreren.sh | bash
```

Het script kopieert (verplaatst niet) de CSV's van `hoses/data`, `adapters/data` en
`configurator/data` met hun back-ups naar `data/<app>/` en `data/backups/<app>/`, controleert
elke kopie byte voor byte, geeft `data/` dezelfde eigenaar als `hoses/data`, en zet daarna de oude
CSV's terug op de git-versie. Een bestand dat al in `data/` staat, wordt nooit overschreven. Bij
een fout stopt het, zonder iets te verwijderen.

## 3. Bijwerken

```bash
git pull
```

De pull verwijdert de oude CSV's uit `hoses/data/` e.d. (de back-upmappen blijven staan) en zet
`data/.htaccess` neer, die de datamap afsluit voor de browser.

## 4. Controleren

| Controle | Verwacht |
|---|---|
| `ls data/hoses data/adapters data/configurator` | De CSV's staan er |
| Slangen fitting Selector, Adapters Selector openen | Zelfde aantal artikelen als voorheen |
| In de Slangen fitting Selector iets wijzigen en opslaan | Nieuwe back-up in `data/backups/hoses/` |
| `/data/hoses/artikelnummers_staal.csv` in de browser | Forbidden (403) |
| `/reports/msp/index.php` in de browser | Wordt `/msp/` |
| `/hoses/images/` in de browser | Forbidden (403), geen bestandenlijst |
| Slangkaart printen: een order printen | Kaart en picklijst zoals voorheen, krimpmaten gevuld |

## Terugdraaien

- **Korte adressen geven problemen:** haal in de root-`.htaccess` het blok `<IfModule mod_rewrite.c>`
  en de regel `Options -Indexes` weg. De apps werken dan via hun oude adressen.
- **Alles terug:** `git revert` van de merge en `git pull`. De CSV's staan dan weer in git op hun
  oude plek, met de inhoud van vóór de overstap. De bewerkingen van daarna staan in `data/`:
  kopieer die terug naar `hoses/data/` e.d.
