#!/usr/bin/env python3
"""Werkt draadsoort en connectietype in artikelnummers_lage_druk.csv bij met de specificaties op www.geeve.com.

Per artikel (https://www.geeve.com/nl/<artikelnummer>) staan "Draadmaat" en "Draadmaat II" als bv.
"1/2 BSP Binnendraad" of "11/2 BSPT Buitendraad (KEG)". Op de site is binnendraad altijd BSP (cilindrisch, in onze
data BSPP) en buitendraad altijd BSPT (conisch).

 - Staat de draad op de site en komt de maat overeen met onze rij: draadsoort en connectie volgen de site.
 - Komt de maat niet overeen (site wijkt af van het artikelnummer) of staat de draad niet op de site: alleen de
   regel "binnen = BSPP" wordt toegepast; maat en connectie blijven. Deze gevallen worden gemeld.
 - Lasproducten zonder draad in onze data maar mét draad op de site: draad 1 wordt ingevuld.
 - NPT/NPTF-artikelen (3000LBS-/150LBS-) staan niet op de site en blijven ongewijzigd.

Gebruik: python3 geeve-com-bijwerken.py [--schrijf]   (zonder --schrijf alleen een rapport)
"""
import csv, html, re, sys, urllib.error, urllib.request, concurrent.futures as cf, os

PAD = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'artikelnummers_lage_druk.csv')


def slug(artnr):
    return re.sub(r'[^a-z0-9]+', '-', artnr.lower()).strip('-')


def haal_op(artnr):
    url = 'https://www.geeve.com/nl/' + slug(artnr)
    for _ in range(3):
        try:
            tekst = urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'}), timeout=30).read().decode('utf8')
            break
        except urllib.error.HTTPError as e:
            if e.code == 404:
                return artnr, None
        except Exception:
            pass
    else:
        return artnr, None
    j = tekst.find('description-list')
    if j < 0:
        return artnr, {}
    deel = tekst[j:tekst.find('</dl>', j)]
    d = {}
    for m in re.finditer(r'dt-content">(.*?)</div></dt>\s*<dd><div class="dd-content">(.*?)</div></dd>', deel, re.S):
        d[html.unescape(m.group(1)).strip()] = html.unescape(re.sub(r'<[^>]+>', '', m.group(2))).strip()
    return artnr, d


def lees_draad(tekst):
    m = re.match(r'^(.*?)\s*(BSPT|BSP)\s+(Binnendraad|Buitendraad)', tekst or '')
    if not m:
        return None
    return ('BSPP' if m.group(2) == 'BSP' else 'BSPT'), m.group(1).strip(), 'binnen' if m.group(3) == 'Binnendraad' else 'buiten'


def maat_basis(maat):
    """'1 1/2-11' -> '1 1/2'; site '11/2' -> '1 1/2'."""
    maat = re.sub(r'-\d+$', '', maat.strip())
    m = re.fullmatch(r'(\d)(\d/\d)', maat)
    return f'{m.group(1)} {m.group(2)}' if m else maat


def main():
    schrijf = '--schrijf' in sys.argv
    with open(PAD, encoding='utf-8-sig', newline='') as f:
        lezer = csv.DictReader(f)
        kop = lezer.fieldnames
        rijen = list(lezer)
    spoed = {}
    for r in rijen:
        for i in '12':
            m = re.fullmatch(r'(.+)-(\d+)', r[f'draadmaat_{i}'])
            if m and r[f'draadsoort_{i}'] in ('BSPT', 'BSPP'):
                spoed[m.group(1)] = m.group(2)
    with cf.ThreadPoolExecutor(4) as ex:
        site = dict(ex.map(haal_op, [r['artnr'] for r in rijen]))

    wijzigingen = []
    afwijkend = []
    for r in rijen:
        s = site[r['artnr']] or {}
        for i, sleutel in (('1', 'Draadmaat'), ('2', 'Draadmaat II')):
            oud = (r[f'draadsoort_{i}'], r[f'draadmaat_{i}'], r[f'connectie_type_{i}'])
            draad = lees_draad(s.get(sleutel))
            if draad and oud[1] and maat_basis(oud[1]) != maat_basis(draad[1]):
                afwijkend.append((r['artnr'], i, oud[1], s[sleutel]))
                draad = None
            if draad and not oud[1]:
                basis = maat_basis(draad[1])
                if basis not in spoed:
                    afwijkend.append((r['artnr'], i, '', s[sleutel]))
                    continue
                nieuw = (draad[0], f'{basis}-{spoed[basis]}', draad[2])
            elif draad:
                nieuw = (draad[0], oud[1], draad[2])
            elif oud[0] == 'BSPT' and oud[2] == 'binnen':
                nieuw = ('BSPP', oud[1], oud[2])
            else:
                continue
            if nieuw != oud:
                wijzigingen.append((r['artnr'], i, oud, nieuw))
                r[f'draadsoort_{i}'], r[f'draadmaat_{i}'], r[f'connectie_type_{i}'] = nieuw

    print(f'{len(rijen)} artikelen, {sum(1 for v in site.values() if v is None)} niet op de site gevonden')
    print(f'{len(wijzigingen)} draden gewijzigd in {len({w[0] for w in wijzigingen})} artikelen')
    for artnr, i, oud, nieuw in wijzigingen:
        print(f'  {artnr} draad {i}: {" ".join(oud)} -> {" ".join(nieuw)}')
    print(f'{len(afwijkend)} draden waar de site een andere maat noemt (niet overgenomen):')
    for a in afwijkend:
        print('  ', a)
    if schrijf:
        with open(PAD, 'w', encoding='utf-8-sig', newline='') as f:
            w = csv.DictWriter(f, kop, lineterminator='\n')
            w.writeheader()
            w.writerows(rijen)
        print('Geschreven.')


main()
