from pathlib import Path

from pypdf import PdfReader


def extract(source: Path, target: Path) -> None:
    reader = PdfReader(source)
    with target.open("w", encoding="utf-8") as output:
        for page_number, page in enumerate(reader.pages, start=1):
            output.write(f"\n===== PDF PAGE {page_number} =====\n")
            output.write(page.extract_text() or "")
            output.write("\n")


root = Path(__file__).resolve().parents[2]
jobs = (
    (root / "adapters/docs/4300_Catalog_Cover.pdf", root / "tmp/pdfs/4300.txt"),
    (root / "adapters/docs/CAT-4100UK.pdf", root / "tmp/pdfs/4100.txt"),
)
for source, target in jobs:
    if not target.exists():
        extract(source, target)
