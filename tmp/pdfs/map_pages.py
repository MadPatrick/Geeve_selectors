import re
from pathlib import Path


codes = [
    "XHX6", "TT4MX", "TPLS", "LOHX6", "XHL6", "XHLO", "TT4ML",
    "F682EDML", "WJJLO", "WGTX", "WFTX", "BBMTX", "F5OG5",
    "F5OHG4", "F64OMX", "F8OHG", "FHG4", "G4MK4", "G4MLOSMO",
    "HP5ON", "K6PP4MX", "P5ON", "R6P4MLO", "R6P4MX", "V3MXS",
    "WNLML",
]

root = Path(__file__).resolve().parents[2]
for catalog in ("4300", "4100"):
    text = (root / f"tmp/pdfs/{catalog}.txt").read_text(encoding="utf-8")
    pages = re.split(r"\n===== PDF PAGE (\d+) =====\n", text)
    page_text = {int(pages[i]): pages[i + 1] for i in range(1, len(pages), 2)}
    print(f"[{catalog}]")
    for code in codes:
        hits = [number for number, content in page_text.items() if code.casefold() in content.casefold()]
        if hits:
            print(f"{code:10} {','.join(map(str, hits))}")
