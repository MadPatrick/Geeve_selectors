import csv
import re
import sys
from collections import Counter
from pathlib import Path

from pypdf import PdfReader


ROOT = Path(__file__).resolve().parents[3]
CSV_PATH = ROOT / "hoses" / "data" / "artikelnummers_accessoires.csv"
DOC_DIR = ROOT / "docs" / "hoses"


def norm(value: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", value.upper())


def candidates(row: dict[str, str]) -> list[str]:
    values = [row["artnr"], row["Artikelnr leverancier"]]
    result: list[str] = []
    for value in values:
        upper = value.upper().strip()
        result.extend([upper, upper.lstrip("0")])
        first = upper.split("-", 1)[0]
        result.extend([first, first.lstrip("0")])
        result.extend(re.findall(r"[A-Z]*\d+[A-Z]*", upper))
    return sorted({norm(v) for v in result if len(norm(v)) >= 3}, key=len, reverse=True)


def main() -> None:
    with CSV_PATH.open("r", encoding="utf-8-sig", newline="") as handle:
        rows = list(csv.DictReader(handle))
    missing = [row for row in rows if not row["Buitenmaat slang (mm)"].strip()]

    requested = sys.argv[1:]
    pdfs = [DOC_DIR / name for name in requested] if requested else sorted(DOC_DIR.glob("*.pdf"))
    pages: list[tuple[str, int, str, str]] = []
    for pdf in pdfs:
        reader = PdfReader(pdf)
        for page_no, page in enumerate(reader.pages, start=1):
            text = page.extract_text() or ""
            pages.append((pdf.name, page_no, text, norm(text)))

    print(f"missing_rows={len(missing)} pdfs={len(pdfs)} pages={len(pages)}")
    supplier_hits = Counter()
    for row in missing:
        hits: list[tuple[str, int, str]] = []
        for token in candidates(row):
            for pdf_name, page_no, text, normalized in pages:
                if token in normalized:
                    snippet = " | ".join(line.strip() for line in text.splitlines() if token in norm(line))[:260]
                    hits.append((pdf_name, page_no, snippet))
            if hits:
                break
        if hits:
            supplier_hits[row["Leverancier"]] += 1
            print("\t".join([
                row["artnr"],
                row["Leverancier"],
                row["Artikelnr leverancier"],
                "; ".join(f"{name}:p{page}:{snippet}" for name, page, snippet in hits[:4]),
            ]))
    print("supplier_hits=" + repr(dict(supplier_hits)))


if __name__ == "__main__":
    main()
