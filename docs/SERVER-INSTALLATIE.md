# Een nieuwe Geeve-server opzetten

Handleiding voor het inrichten van een nieuwe (of herbouwde) webserver voor de Geeve-portal. Het meeste is te doen met
`tools/server-installeren.sh`; dit document zegt wat dat script doet, wat je er zelf bij moet doen en hoe je het controleert.
Gebaseerd op de huidige server (Debian 13, Apache, PHP 8, hostnaam PCNL23-0010). Het script is **niet getest op een verse
server**: draai het eerst met `--dry-run` en lees de uitvoer.

## Overzicht

| Onderdeel | Doet | Door |
|---|---|---|
| Pakketten, Apache, mappen, rechten | stap 1 t/m 5 | script |
| Deploy key voor GitHub | maken door script, toevoegen in GitHub | script + jij |
| Portal ophalen | `--clone` | script |
| Inlogbestanden in `/etc/geeve` | wachtwoorden invullen | jij |
| SQL Server-driver voor PHP | `--met-sql-driver` | script (optioneel) |
| Brother QL-labelprinters | `--met-brother-ql` | script (optioneel) |
| Netwerk (printserver, SQL Server, GitHub) | firewall/DNS | jij of netwerkbeheer |
| Slangkaart.Web (.NET) en de Apache-doorverwijzing `/slangkaarten/` | | Hans, zie onderaan |

## Stappen

### 1. Besturingssysteem

Debian 13 met Apache en PHP 8 (`php -v`). Zet de tijdzone goed (`timedatectl set-timezone Europe/Amsterdam`): datums in
de topbalk-animaties en de bestandsnamen van back-ups volgen de servertijd. De server draait de portal in `/var/www/html`.

### 2. Script draaien (als root)

```bash
bash tools/server-installeren.sh --dry-run     # eerst kijken (op een verse server: het bestand eerst kopiëren)
bash tools/server-installeren.sh               # pakketten, Apache, deploy key
```

Het script maakt een deploy key (`/root/.ssh/geeve_selectors_deploy`) en toont de publieke sleutel. Voeg die toe in
GitHub: repo `MadPatrick/Geeve_selectors` > Settings > Deploy keys > Add deploy key, **zonder** write access. Daarna:

```bash
bash tools/server-installeren.sh --clone       # haalt de portal op in /var/www/html
```

Voeg `--met-sql-driver` en/of `--met-brother-ql` toe als je die nodig hebt. Het script is herhaalbaar; wat er al is wordt
overgeslagen. Het verwijdert niets (de standaard `index.html` van Apache wordt hernoemd) en schrijft geen wachtwoorden.

### 3. Wat het script installeert

| Pakket | Waarvoor |
|---|---|
| `apache2`, `libapache2-mod-php`, `php-cli` | webserver en PHP 8; de CLI voor de scripts in `tools/` |
| `git`, `curl`, `openssl`, `ca-certificates` | ophalen en bijwerken; PHP gebruikt de openssl-extensie voor de versleutelde instellingen |
| `smbclient` (bevat `rpcclient`) | printerlijst en opdrachten naar de printserver `SVNL-VWP-PS01` |
| `chromium-headless-shell` (of `chromium`) | HTML wordt PDF voor het printen |
| `ghostscript` | PDF wordt PCL XL voor de A4-printers |
| `poppler-utils` | `pdftoppm`, bitmap voor de labelprinters |
| `fonts-liberation` | zonder dit valt de print terug op het bredere DejaVu en wordt rechts tekst afgesneden |

**Niet installeren:** `php-mbstring`. De code gebruikt geen `mb_*`; de huidige server heeft het ook niet.

Optioneel: `msodbcsql18` (Microsoft, repo `packages.microsoft.com`) met de PECL-extensies `sqlsrv` en `pdo_sqlsrv`, nodig voor
alle tegels met een database (Slangkaarten Printen, Stickers, Stauff, rapporten). Controle: `php -m | grep sqlsrv`.
Optioneel: `/opt/brother_ql` (Python-omgeving met `brother_ql`), alleen voor de Brother QL-labelprinters (Naamstickers).

### 4. Apache-instellingen

- `a2enmod headers`: de `.htaccess` in de root zet `Cache-Control: no-cache` op css/js/afbeeldingen, zodat de browser na een update
  de nieuwe versie laadt. Zonder `mod_headers` blijft oude CSS staan.
- `/etc/apache2/conf-available/geeve-htaccess.conf` met `AllowOverride All` voor `/var/www/html`, geactiveerd met
  `a2enconf geeve-htaccess`. **Dit is essentieel:** zonder worden alle `.htaccess`-bestanden genegeerd en zijn
  `.settings.key` en `data/` te downloaden (dit is op 19-10-2026 gebeurd).
- Een eventuele testkopie (`/var/www/test`, poort 8080) is niet nodig voor productie.

### 5. Mappen en rechten

| Pad | Eigenaar | Waarom |
|---|---|---|
| `/var/www/html` | root (de gebruiker die `git pull` doet) | de git-checkout |
| `data/` en `data/<app>/` | `www-data` | uploads, back-ups, printerlijst-cache; **niet in git**, `data/.htaccess` sluit de map af |
| `.settings.key`, `.settings.enc` | `www-data`, 600 | versleutelde instellingen uit Config; niet in git, dotfiles zijn geblokkeerd door `.htaccess` |
| `/etc/geeve/` | root:www-data, 750 | inlogbestanden buiten de webmap |

De CSV's (`data/hoses`, `data/adapters`, `data/configurator`) staan niet in git. Op een nieuwe server: zet ze terug uit een
back-up van de oude server (`data/backups/<app>/`) of via de uploadknop in de app. Zonder deze bestanden zijn de Selectors leeg.

### 6. Inlogbestanden in `/etc/geeve` (zelf invullen, nooit in git)

| Bestand | Inhoud | Gebruikt voor |
|---|---|---|
| `smbprint.cred` | `username = ...`, `password = ...`, `domain = ...` (formaat van `smbclient -A`); `chown root:www-data`, `chmod 640` | inloggen op printserver `SVNL-VWP-PS01` (account van de Exact-koppeling) |
| `portal.json` | `{"EXACT_DB_HOST": "...", "EXACT_DB_PORT": "...", "EXACT_DB_NAME": "...", "EXACT_DB_USER": "...", "EXACT_DB_PASSWORD": "...", "EXACT_DB_TRUST_SERVER_CERT": "...", "DB_USER": "...", "DB_PASSWORD": "..."}` | database-inlog; zonder dit bestand kun je ze in Config invullen (versleuteld in `.settings.enc`) |
| `config.cred` | één regel: `password_hash()` van de code voor de Config-pagina | toegang tot Config; ontbreekt het, dan geldt de toegangscode van de beveiligde tegels |

Het script maakt de map aan maar niet deze bestanden. Op de huidige server is de SQL-login `GeeveSlangkaart` (db_datareader op
`Exactrapportage`; server `GEEVE-SQL-2019`, poort 1433). Haal de wachtwoorden uit de wachtwoordkluis, niet uit een oude chat.

### 7. Netwerk

De server moet bij: `SVNL-VWP-PS01` (poort 445, SMB), `GEEVE-SQL-2019` (poort 1433) en GitHub. **Poort 22 naar github.com
is op het Geeve-netwerk geblokkeerd**; het script zet daarom een SSH-alias `github-geeve` op `ssh.github.com:443`. De
remote van de checkout is `git@github-geeve:MadPatrick/Geeve_selectors.git`.

### 8. Controleren

1. `https://<server>/` toont het hoofdscherm met het versienummer rechtsboven.
2. `/.settings.key` en `/data/` geven **403** (anders staat `AllowOverride` fout).
3. `php -m | grep -i sqlsrv` (als je database-tegels wilt) en `smbclient -A /etc/geeve/smbprint.cred -L //SVNL-VWP-PS01` toont de printers.
4. Config opent met de code en slaat instellingen op (`.settings.enc` verandert).
5. Slangkaarten Printen: een order opzoeken en op een printer printen.
6. `bash tools/update.sh --dry-run` meldt "Al up-to-date".

### 9. Bijwerken daarna

`bash tools/update.sh` (zie README, hoofdstuk 3). Het werkt alleen als de checkout schoon is en de deploy key werkt.

## Slangkaart.Web (niet in dit script)

`/slangkaarten/` wordt door Apache doorgestuurd naar de aparte .NET-app Slangkaart.Web (repo Rubix-BNL/slangkaart-web,
systemd-service `slangkaart`, config `/etc/slangkaart/appsettings.Production.json`). Installeren en uitrollen is van Hans,
vanuit `master` (zie de README van die repo en `deploy-debian.sh`). Zonder die app werkt de rest van de portal wel; alleen
de tegel Slangkaarten en "Naar Exact" in Stauff doen dan niets.

## Terugdraaien

Het script verandert niets aan bestaande data. Apache-configuratie terugdraaien: `a2disconf geeve-htaccess`. De deploy key
intrekken: verwijder hem in GitHub > Deploy keys.
