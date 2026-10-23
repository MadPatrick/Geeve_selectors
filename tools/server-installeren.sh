#!/usr/bin/env bash
# Installatiescript voor een nieuwe Geeve-server (Debian 13, Apache + PHP). Volledige uitleg: docs/SERVER-INSTALLATIE.md.
#
#   bash server-installeren.sh --dry-run            # alleen laten zien wat er zou gebeuren
#   bash server-installeren.sh                      # pakketten, Apache, mappen, rechten
#   bash server-installeren.sh --clone              # ook de portal ophalen (deploy key nodig, zie stap 2 in de handleiding)
#   bash server-installeren.sh --met-brother-ql     # ook /opt/brother_ql (Python-omgeving, download van PyPI)
#   bash server-installeren.sh --met-sql-driver     # ook Microsoft ODBC Driver 18 + PECL sqlsrv/pdo_sqlsrv (download van Microsoft)
#
# Draai als root. Het script is herhaalbaar: wat er al is, wordt overgeslagen. Het verwijdert niets, hernoemt alleen de
# standaard-index.html van Apache, en schrijft geen wachtwoorden: die zet je zelf in /etc/geeve (zie de handleiding).
set -euo pipefail

PORTAL=/var/www/html
REPO=git@github-geeve:MadPatrick/Geeve_selectors.git
WEBUSER=www-data
DRY=0; CLONE=0; BROTHER=0; SQLDRV=0
for arg in "$@"; do
    case "$arg" in
        --dry-run)        DRY=1 ;;
        --clone)          CLONE=1 ;;
        --met-brother-ql) BROTHER=1 ;;
        --met-sql-driver) SQLDRV=1 ;;
        -h|--help)        sed -n '2,12p' "$0"; exit 0 ;;
        *) echo "FOUT: onbekende optie: $arg" >&2; exit 2 ;;
    esac
done

stap() { echo; echo "== $* =="; }
fout() { echo "FOUT: $*" >&2; exit 1; }
run()  { if [ "$DRY" = 1 ]; then echo "[dry-run] $*"; else "$@"; fi; }
# schrijf stdin naar een bestand, tenzij dry-run
schrijf() { if [ "$DRY" = 1 ]; then echo "[dry-run] schrijf $1"; cat >/dev/null; else cat >"$1"; fi; }

[ "$(id -u)" = 0 ] || fout "draai dit als root."
[ -r /etc/os-release ] && . /etc/os-release
[ "${ID:-}" = "debian" ] || fout "dit script is voor Debian (gevonden: ${ID:-onbekend})."
[ "${VERSION_ID:-0}" -ge 12 ] 2>/dev/null || echo "WAARSCHUWING: getest/bedoeld voor Debian 13; gevonden: ${VERSION_ID:-?}." >&2

stap "1. Pakketten (apt)"
# php-cli: voor tools/*.php; smbclient bevat ook rpcclient; poppler-utils = pdftoppm; fonts-liberation: zonder deze
# valt de print terug op het bredere DejaVu en wordt rechts tekst afgesneden. GEEN php-mbstring: de code gebruikt geen mb_*.
PAKKETTEN="apache2 libapache2-mod-php php-cli git curl openssl ca-certificates smbclient ghostscript poppler-utils fonts-liberation"
run apt-get update
run env DEBIAN_FRONTEND=noninteractive apt-get install -y $PAKKETTEN
# headless Chromium: op Debian 13 bestaat chromium-headless-shell; anders het volledige chromium
if [ "$DRY" = 1 ]; then
    echo "[dry-run] apt-get install -y chromium-headless-shell (of chromium als dat pakket ontbreekt)"
elif ! [ -x /usr/bin/chromium-headless-shell ] && ! [ -x /usr/bin/chromium ]; then
    DEBIAN_FRONTEND=noninteractive apt-get install -y chromium-headless-shell \
        || DEBIAN_FRONTEND=noninteractive apt-get install -y chromium \
        || fout "geen Chromium-pakket gevonden."
fi

stap "2. Apache"
run a2enmod headers          # root-.htaccess: Cache-Control no-cache op css/js/afbeeldingen
if [ ! -f /etc/apache2/conf-available/geeve-htaccess.conf ]; then
    schrijf /etc/apache2/conf-available/geeve-htaccess.conf <<'CONF'
# Zonder AllowOverride All worden de .htaccess-bestanden genegeerd en zijn .settings.key en data/ te downloaden.
<Directory /var/www/html>
    AllowOverride All
</Directory>
CONF
fi
run a2enconf geeve-htaccess
run systemctl enable apache2
run systemctl reload apache2 || true

stap "3. Deploy key voor GitHub (alleen lezen)"
SSHDIR=/root/.ssh
KEY=$SSHDIR/geeve_selectors_deploy
if [ -f "$KEY" ]; then
    echo "Sleutel bestaat al: $KEY"
else
    run install -d -m 700 "$SSHDIR"
    run ssh-keygen -q -t ed25519 -N "" -C "geeve-server-selectors-deploy" -f "$KEY"
fi
if ! grep -q "Host github-geeve" "$SSHDIR/config" 2>/dev/null; then
    # Poort 22 naar github.com is op het Geeve-netwerk geblokkeerd: SSH via poort 443.
    if [ "$DRY" = 1 ]; then
        echo "[dry-run] voeg blok 'Host github-geeve' toe aan $SSHDIR/config"
    else
        cat >>"$SSHDIR/config" <<CONF

Host github-geeve
    HostName ssh.github.com
    Port 443
    User git
    IdentityFile $KEY
    IdentitiesOnly yes
CONF
        chmod 600 "$SSHDIR/config"
    fi
fi
[ -f "$KEY.pub" ] && { echo "Publieke sleutel (toevoegen als Deploy key, zonder write access, in GitHub > Geeve_selectors > Settings > Deploy keys):"; cat "$KEY.pub"; }

stap "4. Portal ophalen"
if [ -d "$PORTAL/.git" ]; then
    echo "$PORTAL is al een git-checkout."
elif [ "$CLONE" = 1 ]; then
    if [ -f "$PORTAL/index.html" ]; then run mv "$PORTAL/index.html" "$PORTAL/index.html.apache-default"; fi
    if [ "$DRY" = 1 ]; then echo "[dry-run] git clone $REPO $PORTAL"; else
        [ -z "$(ls -A "$PORTAL" 2>/dev/null)" ] || fout "$PORTAL is niet leeg; ruim dat eerst zelf op."
        GIT_SSH_COMMAND="ssh -o StrictHostKeyChecking=accept-new" git clone "$REPO" "$PORTAL" \
            || fout "clonen mislukt: is de deploy key in GitHub toegevoegd (stap 3)?"
    fi
else
    echo "Overgeslagen (geen --clone). Voeg eerst de publieke sleutel uit stap 3 toe in GitHub en draai opnieuw met --clone."
fi

stap "5. Mappen, rechten en instellingenbestanden"
run install -d -m 750 -o root -g "$WEBUSER" /etc/geeve
if [ -d "$PORTAL/.git" ] || [ "$DRY" = 1 ]; then
    for d in adapters hoses configurator printers backups; do run install -d -o "$WEBUSER" -g "$WEBUSER" "$PORTAL/data/$d"; done
    run chown "$WEBUSER:$WEBUSER" "$PORTAL/data"
    # Config schrijft hier de versleutelde instellingen naartoe (staan in .gitignore). De sleutel moet 32 bytes in base64
    # zijn: een leeg bestand werkt niet.
    if [ ! -e "$PORTAL/.settings.key" ]; then
        if [ "$DRY" = 1 ]; then echo "[dry-run] maak $PORTAL/.settings.key (willekeurige sleutel)"; else
            openssl rand -base64 32 | tr -d '\n' >"$PORTAL/.settings.key"
        fi
        run chown "$WEBUSER:$WEBUSER" "$PORTAL/.settings.key"; run chmod 600 "$PORTAL/.settings.key"
    fi
    if [ ! -e "$PORTAL/.settings.enc" ]; then
        run touch "$PORTAL/.settings.enc"; run chown "$WEBUSER:$WEBUSER" "$PORTAL/.settings.enc"; run chmod 600 "$PORTAL/.settings.enc"
    fi
else
    echo "Overgeslagen: portal nog niet opgehaald."
fi

stap "6. Brother QL (optioneel)"
if [ "$BROTHER" = 1 ]; then
    if [ -x /opt/brother_ql/bin/brother_ql_create ]; then echo "Staat er al."; else
        run apt-get install -y python3-venv python3-pip
        run python3 -m venv /opt/brother_ql
        run /opt/brother_ql/bin/pip install brother_ql
    fi
else
    echo "Overgeslagen (--met-brother-ql om dit te doen). Alleen nodig voor de Brother QL-labelprinters (Naamstickers)."
fi

stap "7. SQL Server-driver voor PHP (optioneel)"
if [ "$SQLDRV" = 1 ]; then
    if php -m 2>/dev/null | grep -qi pdo_sqlsrv; then echo "pdo_sqlsrv staat er al."; else
        run apt-get install -y build-essential php-dev php-pear unixodbc-dev gnupg
        run curl -sSL -o /tmp/packages-microsoft-prod.deb "https://packages.microsoft.com/config/debian/${VERSION_ID}/packages-microsoft-prod.deb"
        run dpkg -i /tmp/packages-microsoft-prod.deb
        run apt-get update
        run env ACCEPT_EULA=Y apt-get install -y msodbcsql18
        run pecl install sqlsrv pdo_sqlsrv
        PHPV=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
        for e in sqlsrv pdo_sqlsrv; do echo "extension=$e.so" | schrijf "/etc/php/$PHPV/mods-available/$e.ini"; run phpenmod "$e"; done
        run systemctl reload apache2
    fi
else
    echo "Overgeslagen (--met-sql-driver om dit te doen). Nodig voor alle tegels met een database (zie de handleiding)."
fi

stap "8. Controle"
if [ "$DRY" = 1 ]; then echo "Dry-run: geen controle."; else
    echo "PHP: $(php -r 'echo PHP_VERSION;')  openssl: $(php -r 'echo extension_loaded("openssl")?"ja":"NEE";')  pdo_sqlsrv: $(php -m | grep -qi pdo_sqlsrv && echo ja || echo nee)"
    for b in smbclient rpcclient gs pdftoppm; do command -v $b >/dev/null && echo "$b: ja" || echo "$b: NEE"; done
    ls /usr/bin/chromium-headless-shell /usr/bin/chromium 2>/dev/null | head -1 | sed 's/^/chromium: /' || true
    if [ -d "$PORTAL/.git" ]; then
        for u in /.settings.key /data/; do
            code=$(curl -s -o /dev/null -w '%{http_code}' "http://localhost$u" || true)
            echo "http://localhost$u -> $code (moet 403 zijn)"
        done
    fi
fi

echo
echo "Klaar. Nog zelf te doen (zie docs/SERVER-INSTALLATIE.md): /etc/geeve/smbprint.cred, /etc/geeve/portal.json,"
echo "/etc/geeve/config.cred, netwerktoegang naar de printserver en SQL-server, Slangkaart.Web (Hans)."
