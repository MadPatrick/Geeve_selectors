#!/bin/sh
# Installeert de inlogcontrole voor de Config-pagina (Debian/Ubuntu, als root uitvoeren).
set -eu
[ "$(id -u)" -eq 0 ] || { echo "Voer dit uit als root: sudo sh $0" >&2; exit 1; }
dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
command -v php >/dev/null || { echo "PHP CLI (php-cli) is niet geinstalleerd." >&2; exit 1; }

install -o root -g root -m 0750 "$dir/geeve-auth" /usr/local/sbin/geeve-auth
# Sudoers-bestand eerst controleren, dan pas activeren.
tmp=$(mktemp)
cp "$dir/geeve-auth.sudoers" "$tmp"
visudo -cf "$tmp" >/dev/null
install -o root -g root -m 0440 "$tmp" /etc/sudoers.d/geeve-auth
rm -f "$tmp"
echo "Klaar. Test met: printf 'jouwgebruiker\nwachtwoord' | sudo -u www-data sudo -n /usr/local/sbin/geeve-auth; echo \$?"
