#!/usr/bin/env bash
# Richt een NIEUWE Geeve-server in (Debian 13 "trixie"): pakketten, Apache, PHP, mappen, systemd-service voor de
# Slangkaart-app en de lege configuratiebestanden. Handleiding en achtergrond: docs/SERVER-OPZETTEN.md.
#
#   sudo bash tools/install-server.sh --dry-run   # alleen tonen wat er zou gebeuren
#   sudo bash tools/install-server.sh             # uitvoeren (opnieuw draaien is veilig)
#   sudo bash tools/install-server.sh --clone     # ook de portal ophalen (eerst de deploy key uit stap 7 in GitHub zetten)
#
# Wat dit script NIET doet (handmatig, zie de handleiding):
#   - wachtwoorden of tokens invullen (portal.json, smbprint.cred, config.cred, appsettings.Production.json);
#   - de CSV's in data/ terugzetten (staan niet in git);
#   - de Slangkaart-app (.NET) uitrollen in /var/www/html/slangkaarten (komt niet uit git; dat doet Hans);
#   - DNS, firewall of de deploy key toevoegen in GitHub (de sleutel zelf maakt het script wel, stap 7).
# Het script overschrijft geen bestaande configuratie: een bestaand bestand wordt overgeslagen en gemeld.
set -euo pipefail

DRY=0
CLONE=0
for arg in "$@"; do
    case "$arg" in
        --dry-run)  DRY=1 ;;
        --clone)    CLONE=1 ;;
        -h|--help)  sed -n '2,15p' "$0"; exit 0 ;;
        *) echo "FOUT: onbekende optie: $arg" >&2; exit 2 ;;
    esac
done

PORTAL=/var/www/html
APP_USER=slangkaart
APP_PORT=5288
REPO=git@github-geeve:MadPatrick/Geeve_selectors.git

stap() { echo; echo "== $* =="; }
doe()  { if [ "$DRY" = 1 ]; then echo "  [dry-run] $*"; else eval "$@"; fi; }
fout() { echo "FOUT: $*" >&2; exit 1; }

[ "$(id -u)" = 0 ] || [ "$DRY" = 1 ] || fout "draai dit als root (sudo bash tools/install-server.sh)."
. /etc/os-release
[ "${ID:-}" = debian ] && [ "${VERSION_ID:-}" = 13 ] || echo "WAARSCHUWING: getest op Debian 13; dit is ${PRETTY_NAME:-onbekend}." >&2

stap "1. Basispakketten (Apache, PHP 8.4, git, printen, PDF/labels)"
# smbclient (bevat rpcclient): printen naar de printserver; ghostscript: PDF -> PCL XL voor A4-printers;
# poppler-utils: pdftoppm voor labels; chromium: HTML -> PDF; fonts-liberation: zonder dit valt de print terug op het
# bredere DejaVu en wordt rechts tekst afgesneden; python3-venv: brother_ql voor Brother-labelprinters;
# php8.4-dev/php-pear/build-essential/unixodbc-dev: sqlsrv bouwen. Geen php-mbstring: de code gebruikt geen mb_*.
doe "apt-get update"
doe "DEBIAN_FRONTEND=noninteractive apt-get install -y apache2 libapache2-mod-php8.4 php8.4 php8.4-cli php8.4-xml php8.4-dev php-pear \
 build-essential unixodbc-dev git curl openssl ca-certificates gnupg smbclient ghostscript poppler-utils fonts-liberation chromium python3 python3-venv cups-client"

stap "2. Microsoft ODBC-driver 18 + PHP sqlsrv/pdo_sqlsrv (verbinding met Exact/SQL Server)"
if dpkg -s msodbcsql18 >/dev/null 2>&1; then
    echo "msodbcsql18 staat er al."
else
    doe "curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg"
    doe "echo 'deb [arch=amd64,arm64,armhf signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/13/prod trixie main' > /etc/apt/sources.list.d/microsoft-prod.list"
    doe "apt-get update"
    doe "ACCEPT_EULA=Y DEBIAN_FRONTEND=noninteractive apt-get install -y msodbcsql18"
fi
if php -m 2>/dev/null | grep -qx pdo_sqlsrv; then
    echo "sqlsrv/pdo_sqlsrv staan er al."
else
    doe "pecl install sqlsrv pdo_sqlsrv"
    doe "printf 'extension=sqlsrv.so\n' > /etc/php/8.4/mods-available/sqlsrv.ini"
    doe "printf 'extension=pdo_sqlsrv.so\n' > /etc/php/8.4/mods-available/pdo_sqlsrv.ini"
    doe "phpenmod sqlsrv pdo_sqlsrv"
fi

stap "3. .NET (voor de Slangkaart-app; ASP.NET Core runtime 10)"
if dpkg -s aspnetcore-runtime-10.0 >/dev/null 2>&1; then
    echo "aspnetcore-runtime-10.0 staat er al."
else
    # Debian 13 levert .NET 10 niet altijd in de eigen repo; de Microsoft-repo uit stap 2 heeft het wel.
    doe "DEBIAN_FRONTEND=noninteractive apt-get install -y aspnetcore-runtime-10.0"
fi

stap "4. Brother-labelprinter: brother_ql in /opt/brother_ql"
if [ -x /opt/brother_ql/bin/brother_ql_create ]; then
    echo "/opt/brother_ql staat er al."
else
    doe "python3 -m venv /opt/brother_ql"
    doe "/opt/brother_ql/bin/pip install brother_ql_next"
fi

stap "5. Apache: modules en portal-configuratie"
doe "a2dismod -q mpm_event mpm_worker || true"
doe "a2enmod -q mpm_prefork php8.4 rewrite headers deflate proxy proxy_http proxy_wstunnel"
conf=/etc/apache2/conf-available/geeve-htaccess.conf
if [ -e "$conf" ]; then echo "$conf bestaat al, overgeslagen."; else
    doe "cat > $conf <<'EOF'
# Geeve portal: .htaccess-bestanden toestaan, en de plek van de database-instellingen
# (buiten de webmap, alleen leesbaar voor root en www-data) doorgeven aan PHP.
<Directory $PORTAL>
    AllowOverride All
    SetEnv GEEVE_INSTELLINGEN /etc/geeve/portal.json
</Directory>
EOF"
fi
conf=/etc/apache2/conf-available/slangkaart-app.conf
if [ -e "$conf" ]; then echo "$conf bestaat al, overgeslagen."; else
    doe "cat > $conf <<'EOF'
# /slangkaarten gaat naar de .NET-app op 127.0.0.1:$APP_PORT (de bestanden in $PORTAL/slangkaarten worden niet door Apache geserveerd).
RedirectMatch 301 ^/slangkaarten\$ /slangkaarten/
<Location \"/slangkaarten/\">
    ProxyPreserveHost On
    ProxyPass \"http://127.0.0.1:$APP_PORT/slangkaarten/\" upgrade=websocket
    ProxyPassReverse \"http://127.0.0.1:$APP_PORT/slangkaarten/\"
</Location>
EOF"
fi
doe "a2enconf -q geeve-htaccess slangkaart-app"

stap "6. PHP-instellingen (php.ini van Apache)"
echo "Standaardwaarden (memory_limit 128M, upload 2M, display_errors Off) volstaan; zet de tijdzone vast:"
doe "printf '[Date]\ndate.timezone = Europe/Amsterdam\n' > /etc/php/8.4/apache2/conf.d/90-geeve.ini"
doe "printf '[Date]\ndate.timezone = Europe/Amsterdam\n' > /etc/php/8.4/cli/conf.d/90-geeve.ini"

stap "7. GitHub-toegang: read-only deploy key (SSH via poort 443)"
# Poort 22 naar github.com is op het Geeve-netwerk geblokkeerd, dus SSH via ssh.github.com:443.
KEY=/root/.ssh/geeve_selectors_deploy
doe "install -d -m 700 /root/.ssh"
if [ -f "$KEY" ]; then echo "Sleutel bestaat al: $KEY"; else
    doe "ssh-keygen -q -t ed25519 -N '' -C 'geeve-server-selectors-deploy' -f $KEY"
fi
if grep -q 'Host github-geeve' /root/.ssh/config 2>/dev/null; then echo "Host github-geeve staat al in /root/.ssh/config."; else
    doe "cat >> /root/.ssh/config <<'EOF'

Host github-geeve
    HostName ssh.github.com
    Port 443
    User git
    IdentityFile $KEY
    IdentitiesOnly yes
EOF"
    doe "chmod 600 /root/.ssh/config"
fi
if [ -f "$KEY.pub" ]; then
    echo "Publieke sleutel: voeg toe in GitHub > Geeve_selectors > Settings > Deploy keys, ZONDER write access:"
    cat "$KEY.pub"
fi

stap "8. Portal-code ($PORTAL)"
if [ -d "$PORTAL/.git" ]; then
    echo "$PORTAL is al een git-kopie."
elif [ "$CLONE" = 1 ]; then
    if [ -f "$PORTAL/index.html" ]; then doe "mv $PORTAL/index.html $PORTAL/index.html.apache-default"; fi
    if [ "$DRY" = 1 ]; then echo "  [dry-run] git clone $REPO $PORTAL"; else
        [ -z "$(ls -A "$PORTAL" 2>/dev/null)" ] || fout "$PORTAL is niet leeg; ruim dat eerst zelf op."
        GIT_SSH_COMMAND="ssh -o StrictHostKeyChecking=accept-new" git clone "$REPO" "$PORTAL" \
            || fout "clonen mislukt: staat de deploy key uit stap 7 in GitHub?"
    fi
else
    echo "Nog geen portal. Zet de sleutel uit stap 7 in GitHub en draai opnieuw met --clone (of: git clone $REPO $PORTAL)."
fi
if [ -d "$PORTAL" ]; then
    doe "chown -R www-data:www-data $PORTAL"
    for d in data data/adapters data/configurator data/hoses data/printers data/backups data/backups/adapters data/backups/configurator data/backups/hoses tmp; do
        doe "install -d -o www-data -g www-data -m 0775 $PORTAL/$d"
    done
    doe "git config --global --add safe.directory $PORTAL"
fi

stap "9. Configuratie buiten de webmap: /etc/geeve (root:www-data, 0750/0640)"
doe "install -d -o root -g www-data -m 0750 /etc/geeve"
if [ -e /etc/geeve/portal.json ]; then echo "/etc/geeve/portal.json bestaat al, overgeslagen."; else
    doe "install -o root -g www-data -m 0640 /dev/null /etc/geeve/portal.json"
    doe "printf '{\n  \"EXACT_DB_USER\": \"\",\n  \"EXACT_DB_PASSWORD\": \"\",\n  \"EXACT_DB_TRUST_SERVER_CERT\": \"yes\",\n  \"DB_USER\": \"\",\n  \"DB_PASSWORD\": \"\"\n}\n' > /etc/geeve/portal.json"
    echo "  -> VUL IN: /etc/geeve/portal.json (SQL-inlog)."
fi
if [ -e /etc/geeve/smbprint.cred ]; then echo "/etc/geeve/smbprint.cred bestaat al, overgeslagen."; else
    doe "install -o root -g www-data -m 0640 /dev/null /etc/geeve/smbprint.cred"
    doe "printf 'username = \npassword = \ndomain = \n' > /etc/geeve/smbprint.cred"
    echo "  -> VUL IN: /etc/geeve/smbprint.cred (account dat op de printserver SVNL-VWP-PS01 mag printen)."
fi
if [ -e /etc/geeve/config.cred ]; then echo "/etc/geeve/config.cred bestaat al, overgeslagen."; else
    echo "  -> /etc/geeve/config.cred (toegangscode voor Config): één regel met password_hash() van de code, zie shared/access.php;"
    echo "     ontbreekt het bestand, dan geldt de toegangscode van de beveiligde tegels."
fi

stap "10. Slangkaart-app (.NET): gebruiker, mappen en systemd-service"
if id "$APP_USER" >/dev/null 2>&1; then echo "gebruiker $APP_USER bestaat al."; else
    doe "useradd --system --home-dir /var/lib/slangkaart --shell /usr/sbin/nologin $APP_USER"
fi
doe "install -d -o $APP_USER -g $APP_USER -m 0750 /var/lib/slangkaart /var/lib/slangkaart/output"
doe "install -d -o root -g $APP_USER -m 0750 /etc/slangkaart"
if [ -e /etc/slangkaart/appsettings.Production.json ]; then echo "appsettings.Production.json bestaat al, overgeslagen."; else
    doe "printf '{\n  \"BasisPad\": \"/slangkaarten\",\n  \"ConnectionStrings\": {\n    \"Exact\": \"\",\n    \"OudeSlangkaarten\": \"\",\n    \"Slangkaarten\": \"Data Source=/var/lib/slangkaart/slangkaart.db\"\n  },\n  \"Uitvoer\": { \"Aan\": false, \"Map\": \"/var/lib/slangkaart/output\" },\n  \"PortalUrl\": \"/\"\n}\n' > /etc/slangkaart/appsettings.Production.json"
    doe "chown root:$APP_USER /etc/slangkaart/appsettings.Production.json; chmod 0640 /etc/slangkaart/appsettings.Production.json"
    echo "  -> VUL IN: /etc/slangkaart/appsettings.Production.json (connection strings, EntityService)."
fi
unit=/etc/systemd/system/slangkaart.service
if [ -e "$unit" ]; then echo "$unit bestaat al, overgeslagen."; else
    doe "cat > $unit <<'EOF'
[Unit]
Description=Slangkaart-app (Geeve)
After=network-online.target
Wants=network-online.target

[Service]
User=$APP_USER
Group=$APP_USER
WorkingDirectory=$PORTAL/slangkaarten
ExecStart=/usr/bin/dotnet $PORTAL/slangkaarten/Slangkaart.Web.dll --urls http://127.0.0.1:$APP_PORT
Environment=ASPNETCORE_ENVIRONMENT=Production
Environment=DOTNET_CLI_TELEMETRY_OPTOUT=1
Environment=SLANGKAART_INSTELLINGEN=/etc/slangkaart/appsettings.Production.json
Restart=on-failure
RestartSec=5
NoNewPrivileges=true
ProtectSystem=strict
ReadWritePaths=/var/lib/slangkaart
ProtectHome=true
PrivateTmp=true
PrivateDevices=true
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true
RestrictSUIDSGID=true
LockPersonality=true

[Install]
WantedBy=multi-user.target
EOF"
    doe "systemctl daemon-reload"
fi
if [ -f "$PORTAL/slangkaarten/Slangkaart.Web.dll" ]; then
    doe "systemctl enable --now slangkaart.service"
else
    echo "Slangkaart.Web.dll ontbreekt in $PORTAL/slangkaarten: Hans rolt de gepubliceerde app uit; daarna: systemctl enable --now slangkaart"
fi

stap "11. Apache controleren en herstarten"
doe "apache2ctl configtest"
doe "systemctl enable apache2"
doe "systemctl restart apache2"

stap "12. Controle"
if [ "$DRY" = 1 ]; then echo "Dry-run: niets gewijzigd."; else
    php -v | head -1
    php -m | grep -E '^(sqlsrv|pdo_sqlsrv|xml|xsl|fileinfo|sockets|openssl|sodium|zlib|iconv)$' | tr '\n' ' '; echo
    php -m | grep -qi '^mbstring$' && echo "Let op: mbstring staat aan; de code gebruikt geen mb_* en dat is ook niet nodig."
    for b in smbclient rpcclient gs pdftoppm chromium; do command -v "$b" >/dev/null 2>&1 && echo "ok: $b" || echo "ONTBREEKT: $b"; done
    if [ -d "$PORTAL/.git" ]; then
        for u in /.settings.key /data/; do
            echo "http://localhost$u -> $(curl -s -o /dev/null -w '%{http_code}' "http://localhost$u" || echo '?') (moet 403 zijn)"
        done
    fi
fi
echo; echo "Klaar. Volgende stappen: docs/SERVER-OPZETTEN.md (instellingen invullen, data terugzetten, app uitrollen, testen)."
