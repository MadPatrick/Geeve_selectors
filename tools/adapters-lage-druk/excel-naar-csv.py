#!/usr/bin/env python3
"""Zet 'Malleable gr 32.xlsx' (Artcode, ArtOmschrijving, AG, Credcode, ArtcodeLev) om naar
artikelnummers_lage_druk.csv in het formaat van data/adapters/artikelnummers_adapters.csv.

Gebruik: python3 excel-naar-csv.py <bron.xlsx> <doel.csv>   (vereist openpyxl)

Alles wat uit artikelcode en omschrijving AFGELEID is (draadsoort, maat, uiteinden, hoek, familie)
staat in de tabellen hieronder; zie README.md voor wat er niet herkend wordt.
"""
import csv, re, sys
import openpyxl

COLS = ['artnr', 'cross_ref', 'familie_code', 'familie_naam', 'omschrijving', 'hoek',
        'draadsoort_1', 'draadmaat_1', 'connectie_type_1', 'draadsoort_2', 'draadmaat_2',
        'connectie_type_2', 'tube_od_mm', 'tube_od_inch', 'bron_pagina', 'drukklasse']

TPI = {
    'BSPT': {'1/8': 28, '1/4': 19, '3/8': 19, '1/2': 14, '3/4': 14, '1': 11, '1 1/4': 11,
            '1 1/2': 11, '2': 11, '2 1/2': 11, '3': 11, '4': 11},
    'NPT/NPTF': {'1/8': 27, '1/4': 18, '3/8': 18, '1/2': 14, '3/4': 14, '1': '11.5',
                 '1 1/4': '11.5', '1 1/2': '11.5', '2': '11.5', '2 1/2': 8, '3': 8, '4': 8},
}
NOSLASH = {'18': '1/8', '14': '1/4', '38': '3/8', '12': '1/2', '34': '3/4', '112': '1 1/2',
           '114': '1 1/4', '212': '2 1/2'}
B, N, W = 'buiten', 'binnen', 'wartelend'

def maat(tok):
    """'11/4' -> '1 1/4', '3/8' -> '3/8', '2' -> '2'."""
    m = re.fullmatch(r'(\d+)/(\d+)', tok)
    if m:
        a, d = m.groups()
        return f'{a[:-1]} {a[-1]}/{d}' if len(a) > 1 else f'{a}/{d}'
    return NOSLASH.get(tok, tok) if not re.fullmatch(r'[1-9]', tok) else tok

# (trefwoord in omschrijving, familie_naam, hoek, uiteinde 1, uiteinde 2) - eerste treffer wint.
# Uiteinden volgen EN 10242: A1/G1/M2/B1/C1/U11/UA11/T1 = binnendraad, A4/G4/N4/U12/UA12 = buiten+binnen,
# N8/G8/T8/T9 = buitendraad. None = geen draadeinde aan die kant.
def regels(d, code):
    iso = re.search(r'ISO/EN ([A-Z]+\d+)', d)
    iso = iso.group(1) if iso else ''
    if re.search(r'GASPIJP|FLENS|BOORDRING|MUURPLAAT', d): return ('Pipe/flens/overig', 'Overige', None, None)
    if 'LASBOCHT' in d: return ('Weld elbow', 'haaks', None, None)
    if 'LASDRAADSOK' in d: return ('Socket weld/thread', 'recht', N, None)
    if re.search(r'LASSOK|WELDING SOCKET', d): return ('Welding socket', 'recht', None, None)
    if 'WELDING CAP' in d: return ('Welding cap', 'Cap', None, None)
    if 'TEE,WELDING' in d: return ('Welding tee', 'T-stuk', None, None)
    if 'AANLAS' in d: return ('Weld nipple', 'recht', None, None)
    if re.search(r'LONG SWEEP BEND', d):
        h = '45°' if '45' in d else 'haaks'
        e = {'G1': (N, N), 'G4': (B, N), 'G8': (B, B)}.get(iso, (None, None))
        if code.startswith('41-'): e = (N, N)   # omschrijving 'G11/2/45°' is G1 + maat; serie 41 = G1 (binnen/binnen)
        return ('Long sweep bend ' + ('45°' if h == '45°' else '90°'), h, *e)
    if re.search(r'UNION ELBOW', d):
        return ('Union elbow', 'haaks', *{'UA11': (N, N), 'UA12': (B, N)}.get(iso, (None, None)))
    if 'UNION' in d:
        return ('Union', 'recht', *{'U11': (N, N), 'U12': (B, N)}.get(iso, (N, N) if 'FEMALE/FEMALE' in d else (B, B) if 'MALE/MALE' in d else (None, None)))
    if 'KOPPELING 3 DELIG' in d: return ('Union (3-delig)', 'recht', N, N)
    if re.search(r'SIDE OUTLET (ELBOW|TEE)', d): return ('Side outlet ' + ('elbow' if 'ELBOW' in d else 'tee'), 'Overige', N, N)
    if re.search(r'ELBOW 45|KNIE BIN/BIN 45|KNIE BIN / BIN 45', d):
        return ('Elbow 45°', '45°', *((B, N) if iso == 'A4/45°' or 'A4' in d else (N, N)))
    if re.search(r'KNIE BIN ?/ ?BUI', d): return ('Elbow 90°', 'haaks', N, B)
    if re.search(r'KNIE MALE/ ?FEM', d): return ('Elbow 90°', 'haaks', B, N)
    if re.search(r'ELBOW 90|KNIE BIN', d):
        return ('Elbow 90°', 'haaks', *((B, N) if iso == 'A4' or 'A4' in d else (N, N)))
    if 'Y-PIECE' in d: return ('Y-piece', 'Overige', N, N)
    if re.search(r'TEE EQUAL MALE/FEMALE|TEE, EQUAL MALE/FEMALE', d): return ('Tee', 'T-stuk', B, N)
    if re.search(r'\bTEE\b|T-STUK', d): return ('Tee', 'T-stuk', N, N)
    if re.search(r'CROSS|KRUIS', d): return ('Cross', 'kruis', N, N)
    if 'BACKNUT' in d: return ('Backnut', 'Overige', N, N)
    if re.search(r'HALVE SOCKET|SOCKET|DRAADSOK', d): return ('Socket', 'recht', N, N)
    if re.search(r'SOK NIPPEL BIN ?/ ?BUI', d): return ('Socket nipple', 'recht', N, B)
    if re.search(r'HEXAGON NIPPLE|DRAADNIPPEL REDUCEER|DRAADNIPPEL|PIJPNIPPEL', d): return ('Nipple', 'recht', B, B)
    if re.search(r'REDUCING BUSH|VERLOOPRING', d): return ('Reducing bush', 'recht', B, N)
    if re.search(r'PLUG|DRAADPLUG', d): return ('Plug', 'Plug', B, None)
    if re.search(r'\bCAP\b|DOP', d): return ('Cap', 'Cap', N, None)
    return ('', 'Overige', None, None)

def omzetten(artnr, omschr):
    d = omschr.upper().strip()
    m = re.match(r'^(3000LBS|150LBS)-', artnr)
    prefix = m.group(1) if m else ''
    rest = artnr[len(prefix) + 1:] if prefix else artnr
    naam, hoek, e1, e2 = regels(d, artnr)
    sw = bool(re.search(r'(?<![A-Z])SW(?![A-Z])|SWF', rest + ' ' + d)) and 'LASSOK' not in d
    if sw and prefix:
        e1 = e2 = None   # SW = socket weld: lasuiteinden, geen draad
    serie, maten = '', []
    mm = re.match(r'^(SP)-(\d+)$', rest)
    if mm:
        serie, maten = 'SP', [maat(mm.group(2))]
    else:
        mm = re.match(r'^(\d+[A-Z]?)-(\d+(?:/\d+)?)(?:X(\d+(?:/\d+)?))?(?:X(\d+(?:/\d+)?))?', rest)
        if mm:
            serie = mm.group(1)
            maten = [maat(t) for t in mm.groups()[1:] if t]
            if naam == 'Nipple' and serie in ('2300', '23B') and len(maten) > 1:
                maten = maten[:1]          # tweede getal is de lengte in mm, geen maat
            if serie == '400': maten = []
    if re.search(r'MUURPLAAT|FLENS|BOORDRING|GASPIJP|LASBOCHT', d): maten = []
    soort = 'NPT/NPTF' if (prefix or 'NPT' in d) else 'BSPT'
    if e1 is None and e2 is None: soort = ''; maten = []
    m1 = maten[0] if maten else ''
    m2 = maten[1] if len(maten) > 1 else m1
    def vol(mt):
        if not mt or not soort: return mt
        t = TPI[soort].get(mt)
        return f'{mt}-{t}' if t else mt
    return {
        'artnr': artnr, 'cross_ref': '', 'familie_code': (prefix + '-' if prefix else '') + serie if serie else '',
        'familie_naam': naam, 'omschrijving': omschr.strip(), 'hoek': hoek,
        'draadsoort_1': soort if e1 else '', 'draadmaat_1': vol(m1) if e1 else '', 'connectie_type_1': e1 or '',
        'draadsoort_2': soort if e2 or (e1 and e2) else '', 'draadmaat_2': vol(m2) if e2 else '',
        'connectie_type_2': e2 or '', 'tube_od_mm': '', 'tube_od_inch': '', 'bron_pagina': '', 'drukklasse': 'laag',
    }

if __name__ == '__main__':
    ws = openpyxl.load_workbook(sys.argv[1], read_only=True).active
    with open(sys.argv[2], 'w', newline='', encoding='utf-8-sig') as f:
        w = csv.DictWriter(f, COLS, lineterminator='\n'); w.writeheader()
        seen = set()
        for r in ws.iter_rows(min_row=2, values_only=True):
            if not r[0] or not r[1] or str(r[0]).strip() in seen: continue
            if str(r[0]).strip().upper().endswith('RVS'): continue   # RVS-artikelen zijn dubbel met staal (Patrick 2026-10-21)
            seen.add(str(r[0]).strip())
            w.writerow(omzetten(str(r[0]).strip(), str(r[1])))
