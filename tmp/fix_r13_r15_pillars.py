import csv
import io
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
FILES = [
    ROOT / "hoses" / "data" / "artikelnummers_staal.csv",
    ROOT / "hoses" / "data" / "artikelnummers_rvs.csv",
]


for path in FILES:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        rows = list(csv.reader(handle))
    header = rows[0]
    index = {name: position for position, name in enumerate(header)}
    changes = []

    for row in rows[1:]:
        if row[index["Leverancier"]].strip().lower() != "parker":
            continue
        description = row[index["artnm"]].upper()
        if "R13" not in description and "R15" not in description:
            continue

        huls1 = row[index["2delig_1 - Huls"]].upper()
        expected_pillar1 = None
        if huls1.startswith("100V4-"):
            expected_pillar1 = "V4"
        elif huls1.startswith("100V6-"):
            expected_pillar1 = "V6"
        if expected_pillar1 and row[index["2delig_1 - Pilaar"]] != expected_pillar1:
            old = row[index["2delig_1 - Pilaar"]]
            row[index["2delig_1 - Pilaar"]] = expected_pillar1
            changes.append((row[0], "2delig_1 - Pilaar", old, expected_pillar1))

        huls2_index = index["2delig_2 - Huls"]
        pillar2_index = index["2delig_2 - Pilaar"]
        huls2 = row[huls2_index]
        if huls2.upper().startswith("Z34000-"):
            corrected = "Z37000-" + huls2.split("-", 1)[1]
            row[huls2_index] = corrected
            changes.append((row[0], "2delig_2 - Huls", huls2, corrected))
            huls2 = corrected
        if huls2.upper().startswith("Z37000-") and row[pillar2_index] != "30":
            old = row[pillar2_index]
            row[pillar2_index] = "30"
            changes.append((row[0], "2delig_2 - Pilaar", old, "30"))

    buffer = io.StringIO(newline="")
    csv.writer(buffer, lineterminator="\n").writerows(rows)
    path.write_bytes(b"\xef\xbb\xbf" + buffer.getvalue().encode("utf-8"))
    print(path.name, len(changes))
    for change in changes:
        print("\t".join(change))
