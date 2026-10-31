#!/usr/bin/env bash
# Eén update-script voor de Geeve-server: haalt de nieuwste versie uit git en voert de stappen uit die daarna nodig zijn.
#
#   cd /var/www/html
#   bash tools/update.sh              # bijwerken
#   bash tools/update.sh --dry-run    # alleen laten zien wat er zou gebeuren (geen pull, niets geschreven)
#
# Stappen:
#  1. controles: portal-map, branch main, geen lokale wijzigingen in gevolgde bestanden, php aanwezig;
#  2. git fetch + git pull --ff-only (nooit een merge, rebase of force);
#  3. php -l op de gewijzigde PHP-bestanden;
#  4. controle dat data/ bestaat en schrijfbaar is voor de webserver, en melding van het nieuwe versienummer.
# Stopt bij de eerste fout. Er wordt niets verwijderd of geïnstalleerd en niets met sudo gedaan; draai als de
# eigenaar van /var/www/html (zelfde gebruiker als voor git pull).
set -euo pipefail

DRY=0
for arg in "$@"; do
    case "$arg" in
        --dry-run)   DRY=1 ;;
        -h|--help)   sed -n '2,17p' "$0"; exit 0 ;;
        *) echo "FOUT: onbekende optie: $arg" >&2; exit 2 ;;
    esac
done

stap() { echo; echo "== $* =="; }
fout() { echo "FOUT: $*" >&2; exit 1; }

cd "$(dirname "$0")/.."
[ -f version.php ] && [ -e .git ] && [ -d shared ] || fout "dit is niet de portal-map (bijv. /var/www/html)."
command -v git >/dev/null 2>&1 || fout "git niet gevonden."
command -v php >/dev/null 2>&1 || fout "php niet gevonden."

stap "1. Controles"
branch="$(git rev-parse --abbrev-ref HEAD)"
[ "$branch" = "main" ] || fout "branch is '$branch', verwacht 'main'. Niets gedaan."
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    git status --short --untracked-files=no >&2
    fout "er zijn lokale wijzigingen in gevolgde bestanden (zie boven). Los die eerst op; niets gedaan."
fi
echo "Branch main, geen lokale wijzigingen."
oud="$(git rev-parse HEAD)"
oudversie="$(php -r 'echo include "version.php";')"

stap "2. Git"
git fetch origin main
nieuw="$(git rev-parse origin/main)"
if [ "$oud" = "$nieuw" ]; then
    echo "Al up-to-date ($oudversie)."
elif [ "$DRY" = 1 ]; then
    echo "Dry-run: git pull zou deze wijzigingen ophalen:"
    git diff --stat "$oud" "$nieuw" | tail -n 15
else
    git pull --ff-only origin main
fi

if [ "$DRY" = 1 ]; then
    gewijzigd="$(git diff --name-only "$oud" "$nieuw")"
else
    gewijzigd="$(git diff --name-only "$oud" HEAD)"
fi

stap "3. PHP-syntaxcontrole van gewijzigde bestanden"
if [ "$DRY" = 1 ]; then
    echo "Dry-run: overgeslagen."
else
    aantal=0
    while IFS= read -r f; do
        case "$f" in *.php) [ -f "$f" ] || continue; php -l "$f" >/dev/null || fout "syntaxfout in $f"; aantal=$((aantal+1)) ;; esac
    done <<< "$gewijzigd"
    echo "$aantal PHP-bestand(en) gecontroleerd, geen fouten."
fi

stap "4. Data en versie"
[ -d data ] || fout "map data/ ontbreekt (zie docs/UITROL-CENTRALE-DATA.md)."
if [ -w data ]; then echo "data/ is schrijfbaar voor $(id -un)."; else echo "WAARSCHUWING: data/ is niet schrijfbaar voor $(id -un); de webserver mogelijk ook niet." >&2; fi
echo "Versie: $oudversie -> $(php -r 'echo include "version.php";')"
[ "$DRY" = 1 ] && echo "Dry-run: er is niets gewijzigd."
echo; echo "Klaar."
