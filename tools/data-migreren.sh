#!/usr/bin/env bash
# Eenmalige overstap naar de centrale datamap data/ (zie shared/paths.php en docs/UITROL-CENTRALE-DATA.md).
#
# Draai dit op de server VOOR de git pull die de CSV's uit git haalt, vanuit de portal-map:
#
#   cd /var/www/html
#   git fetch origin main
#   git show origin/main:tools/data-migreren.sh | bash
#   git pull
#
# Wat het doet:
#  1. kopieert de CSV's van de server (hoses/data, adapters/data, configurator/data) en hun back-ups naar
#     data/<app>/ en data/backups/<app>/ - KOPIEREN, de oude bestanden blijven staan; een bestand dat in data/ al
#     bestaat wordt nooit overschreven;
#  2. controleert dat elke kopie byte-voor-byte gelijk is aan het origineel;
#  3. geeft data/ dezelfde eigenaar als hoses/data (de webserver moet erin kunnen schrijven);
#  4. zet de oude, door git beheerde CSV's terug op de git-versie (eerst skip-worktree eraf), zodat de git pull niet
#     weigert. De serverversie staat dan al veilig in data/.
# Stopt bij de eerste fout; er wordt niets verwijderd.
set -euo pipefail

if [ ! -f version.php ] || [ ! -d .git ] || [ ! -d shared ]; then
    echo "FOUT: draai dit vanuit de portal-map (bijv. /var/www/html)." >&2
    exit 1
fi

# app:bestand - bron is <app>/data/<bestand>, doel data/<app>/<bestand>
FILES=(
    hoses:artikelnummers_staal.csv
    hoses:artikelnummers_rvs.csv
    hoses:artikelnummers_accessoires.csv
    adapters:artikelnummers_adapters.csv
    configurator:artikelnummers.csv
    configurator:slangkoppelingen.csv
)

owner=$(stat -c '%U:%G' hoses/data 2>/dev/null || stat -c '%U:%G' .)
mkdir -p data

for entry in "${FILES[@]}"; do
    app=${entry%%:*}; file=${entry#*:}
    src="$app/data/$file"; dst="data/$app/$file"
    mkdir -p "data/$app"
    if [ ! -f "$src" ]; then
        echo "overgeslagen (bestaat niet): $src"
        continue
    fi
    if [ -e "$dst" ]; then
        echo "overgeslagen (staat al in data/): $dst"
        continue
    fi
    cp -p "$src" "$dst"
    cmp -s "$src" "$dst" || { echo "FOUT: kopie verschilt van origineel: $dst" >&2; exit 1; }
    echo "gekopieerd: $src -> $dst"
done

for app in hoses adapters configurator; do
    if [ -d "$app/data/backups" ]; then
        mkdir -p "data/backups/$app"
        cp -rpn "$app/data/backups/." "data/backups/$app/"
        echo "back-ups gekopieerd: $app/data/backups -> data/backups/$app"
    fi
done

chown -R "$owner" data
chmod -R u+rwX,g+rwX data
echo "eigenaar van data/: $owner"

for entry in "${FILES[@]}"; do
    app=${entry%%:*}; file=${entry#*:}
    src="$app/data/$file"
    if git ls-files --error-unmatch "$src" >/dev/null 2>&1; then
        git update-index --no-skip-worktree --no-assume-unchanged "$src"
        if ! git diff --quiet -- "$src"; then
            [ -f "data/$app/$file" ] && cmp -s "$src" "data/$app/$file" || { echo "FOUT: $src is lokaal gewijzigd maar niet gelijk aan data/$app/$file - niets teruggezet." >&2; exit 1; }
            git checkout -- "$src"
            echo "teruggezet op git-versie (serverversie staat in data/): $src"
        fi
    fi
done

echo
echo "Klaar. Nu: git pull"
