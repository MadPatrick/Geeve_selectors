import csv
from pathlib import Path

root = Path(__file__).resolve().parents[1]
data = root / "hoses" / "data"
files = [data / "artikelnummers_staal.csv", data / "artikelnummers_rvs.csv"]
loaded = {}

for path in files:
    raw = path.read_bytes()
    assert raw.startswith(b"\xef\xbb\xbf"), f"BOM ontbreekt: {path.name}"
    assert b"\r\n" not in raw, f"CRLF onverwacht: {path.name}"
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        physical_rows = list(csv.reader(handle, delimiter=","))
    assert all(len(row) == 30 for row in physical_rows), path.name
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        rows = list(csv.DictReader(handle, delimiter=","))
    assert len(rows) == 967, (path.name, len(rows))
    artnrs = [row["artnr"] for row in rows]
    assert len(artnrs) == len(set(artnrs)), f"duplicaten: {path.name}"
    loaded[path.name] = {row["artnr"]: row for row in rows}

    for row in rows:
        if row["Leverancier"] != "Parker" or not any(x in row["artnm"] for x in ("R13", "R15")):
            continue
        h1 = row["2delig_1 - Huls"]
        p1 = row["2delig_1 - Pilaar"]
        h2 = row["2delig_2 - Huls"]
        p2 = row["2delig_2 - Pilaar"]
        if h1.startswith("100V4-"):
            assert p1 == "V4", (path.name, row["artnr"], h1, p1)
        if h1.startswith("100V6-"):
            assert p1 == "V6", (path.name, row["artnr"], h1, p1)
        assert not h2.startswith("Z34000-"), (path.name, row["artnr"], h2)
        if h2.startswith("Z37000-"):
            assert p2 == "30", (path.name, row["artnr"], h2, p2)

assert set(loaded[files[0].name]) == set(loaded[files[1].name]), "Staal/RVS artnr-pariteit ontbreekt"

checks = [
    ("artikelnummers_staal.csv", "0449-20", "100V4-20", "V4", "", ""),
    ("artikelnummers_rvs.csv", "0449-20", "100V4-20C", "V4", "", ""),
    ("artikelnummers_staal.csv", "R42-12", "100V4-12", "V4", "Z37000-12", "30"),
    ("artikelnummers_rvs.csv", "R42-12", "100V4-12C", "V4", "Z37000-12RVS", "30"),
    ("artikelnummers_staal.csv", "R42-20", "100V6-20", "V6", "Z37000-20", "30"),
    ("artikelnummers_rvs.csv", "R42-20", "100V6-20C", "V6", "Z37000-20RVS", "30"),
]
for filename, artnr, h1, p1, h2, p2 in checks:
    row = loaded[filename][artnr]
    actual = (row["2delig_1 - Huls"], row["2delig_1 - Pilaar"], row["2delig_2 - Huls"], row["2delig_2 - Pilaar"])
    assert actual == (h1, p1, h2, p2), (filename, artnr, actual)

accessories = data / "artikelnummers_accessoires.csv"
raw = accessories.read_bytes()
assert raw.startswith(b"\xef\xbb\xbf"), "BOM ontbreekt: accessoires"
assert b"\r\n" in raw, "CRLF ontbreekt: accessoires"
with accessories.open("r", encoding="utf-8-sig", newline="") as handle:
    accessory_rows = list(csv.reader(handle, delimiter=";"))
assert all(len(row) == 14 for row in accessory_rows), "Accessoires bevat een afwijkend kolomaantal"

print("OK: 967 unieke rijen per fittingbestand, 30 kolommen, BOM/LF, volledige artnr-pariteit")
print("OK: alle Parker R13/R15 100V4->V4, 100V6->V6 en Z37000->30 combinaties kloppen")
print(f"OK: accessoires {len(accessory_rows) - 1} rijen, 14 kolommen, BOM/CRLF")
