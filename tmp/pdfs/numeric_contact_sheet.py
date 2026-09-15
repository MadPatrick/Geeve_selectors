from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


root = Path(__file__).resolve().parents[2]
images_dir = root / "adapters/images"
codes = [
    "2015", "2041", "2143", "2228", "2247", "2249", "2280", "2281", "2282",
    "2441", "2449", "2612", "2615", "2647", "2649", "2716", "2717", "2915",
    "2917", "2918", "2995", "2996", "7180", "7215", "72225", "7227", "7228",
    "7249", "7258", "7526", "8141", "8207", "8219", "8221", "8225", "8228",
    "8229", "8243", "8245", "8246", "8278", "8279", "8443", "8619", "8648", "8778",
]
columns = 6
cell_width = 220
cell_height = 190
label_height = 26
rows = (len(codes) + columns - 1) // columns
sheet = Image.new("RGB", (columns * cell_width, rows * cell_height), "#dadada")
draw = ImageDraw.Draw(sheet)
font = ImageFont.load_default(size=18)

for index, code in enumerate(codes):
    image = Image.open(images_dir / f"{code}.png").convert("RGB")
    image.thumbnail((cell_width - 16, cell_height - label_height - 12))
    cell_x = (index % columns) * cell_width
    cell_y = (index // columns) * cell_height
    x = cell_x + (cell_width - image.width) // 2
    y = cell_y + label_height + (cell_height - label_height - image.height) // 2
    sheet.paste(image, (x, y))
    draw.text((cell_x + 7, cell_y + 4), code, fill="black", font=font)

sheet.save(root / "tmp/pdfs/numeric-contact-sheet.png", optimize=True)
