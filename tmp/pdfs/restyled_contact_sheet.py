from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


ROOT = Path(__file__).resolve().parents[2]
IMAGES = ROOT / "tmp/pdfs/restyled_preview"
CODES = sorted(path.stem for path in IMAGES.glob("*.png"))
columns = 4
cell_width = 260
cell_height = 220
label_height = 28
rows = (len(CODES) + columns - 1) // columns
sheet = Image.new("RGB", (columns * cell_width, rows * cell_height), "#dadada")
draw = ImageDraw.Draw(sheet)
font = ImageFont.load_default(size=18)

for index, code in enumerate(CODES):
    image = Image.open(IMAGES / f"{code}.png").convert("RGB")
    image.thumbnail((cell_width - 18, cell_height - label_height - 14))
    cell_x = (index % columns) * cell_width
    cell_y = (index // columns) * cell_height
    x = cell_x + (cell_width - image.width) // 2
    y = cell_y + label_height + (cell_height - label_height - image.height) // 2
    sheet.paste(image, (x, y))
    draw.text((cell_x + 7, cell_y + 4), code, fill="black", font=font)

sheet.save(ROOT / "tmp/pdfs/restyled-contact-sheet.png", optimize=True)
