from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


root = Path(__file__).resolve().parents[2]
source = root / "tmp/pdfs/rendered"
items = sorted(source.glob("*.png"), key=lambda path: path.name)
thumb_width = 320
label_height = 28
columns = 4
rows = (len(items) + columns - 1) // columns
thumbs = []
for path in items:
    image = Image.open(path).convert("RGB")
    image.thumbnail((thumb_width, 460))
    thumbs.append((path.name, image.copy()))

cell_height = max(image.height for _, image in thumbs) + label_height
sheet = Image.new("RGB", (columns * thumb_width, rows * cell_height), "#d9d9d9")
draw = ImageDraw.Draw(sheet)
font = ImageFont.load_default(size=18)
for index, (name, image) in enumerate(thumbs):
    x = (index % columns) * thumb_width
    y = (index // columns) * cell_height
    sheet.paste(image, (x + (thumb_width - image.width) // 2, y + label_height))
    draw.text((x + 6, y + 5), name, fill="black", font=font)

sheet.save(root / "tmp/pdfs/contact-sheet.png")
