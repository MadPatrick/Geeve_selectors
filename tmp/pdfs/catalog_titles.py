import re
from pathlib import Path


root = Path(__file__).resolve().parents[2]
text = (root / "tmp/pdfs/4100.txt").read_text(encoding="utf-8")
parts = re.split(r"\n===== PDF PAGE (\d+) =====\n", text)
title_words = re.compile(
    r"\b(?:adapter|connector|union|plug|cap|tee|elbow|stud|coupling|reducer|socket|locknut|ring)\b",
    re.IGNORECASE,
)
for index in range(1, len(parts), 2):
    page = int(parts[index])
    if page < 199:
        continue
    matches = []
    for line in parts[index + 1].splitlines():
        line = " ".join(line.split())
        if title_words.search(line) and not line.startswith(("Order code", "Parker Adapter", "Thread ")):
            if len(line) <= 110 and not re.match(r"^\d", line):
                matches.append(line)
    if matches:
        print(f"{page}: {' | '.join(dict.fromkeys(matches))}")
