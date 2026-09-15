from pathlib import Path

from PIL import Image, ImageChops, ImageOps


ROOT = Path(__file__).resolve().parents[2]
SOURCES = ROOT / "tmp/pdfs/index480"
OUTPUT = ROOT / "tmp/pdfs/restyled_preview"


# Crop coordinates were measured on the 120 dpi review renders. The script
# scales them to the 480 dpi source renders and excludes captions/table rules.
CROPS = {
    "2015": ("4300-p36.png", 1020, (245, 420, 350, 490)),       # XHX6
    "2041": ("4100-p198.png", 992, (220, 415, 310, 486)),      # PTR34M
    "2143": ("4100-p198.png", 992, (220, 185, 310, 250)),      # G4MK4
    "2228": ("4100-p2.png", 992, (420, 195, 495, 255)),        # TT4MX
    "2247": ("4100-p198.png", 992, (220, 550, 315, 620)),      # 0107
    "2249": ("4100-p198.png", 992, (220, 885, 315, 952)),      # F3T4
    "2282": ("4300-p91.png", 1020, (675, 145, 770, 224)),      # WH-M-KDS
    "2612": ("4100-p197.png", 992, (215, 882, 315, 955)),      # F6MK4
    "7228": ("4100-p2.png", 992, (420, 195, 495, 255)),        # TT4MX
    "8141": ("4100-p198.png", 992, (350, 190, 425, 250)),      # FNMK4
    "8221": ("4300-p36.png", 1020, (105, 420, 220, 495)),      # WGTX
    "8225": ("4300-p36.png", 1020, (800, 292, 925, 360)),      # WFTX
    "8228": ("4300-p36.png", 1020, (800, 292, 925, 360)),      # WFTX
    "8243": ("4300-p36.png", 1020, (245, 695, 355, 760)),      # F4OMX
    "8245": ("4100-p2.png", 992, (615, 650, 710, 725)),        # BBMTX
    "8246": ("4100-p197.png", 992, (455, 780, 550, 842)),      # F8OHG5
}


def trim_white(image: Image.Image, margin: int = 16) -> Image.Image:
    rgb = image.convert("RGB")
    white = Image.new("RGB", rgb.size, "white")
    difference = ImageChops.difference(rgb, white).convert("L")
    difference = difference.point(lambda value: 255 if value > 12 else 0)
    bbox = difference.getbbox()
    if bbox is None:
        raise ValueError("crop contains no visible drawing")
    left, top, right, bottom = bbox
    return rgb.crop((
        max(0, left - margin),
        max(0, top - margin),
        min(rgb.width, right + margin),
        min(rgb.height, bottom + margin),
    ))


def trim_sparse_guides(image: Image.Image, margin: int = 16) -> Image.Image:
    """Ignore one-pixel centre lines when determining the adapter boundary."""
    rgb = image.convert("RGB")
    grey = rgb.convert("L")
    mask = grey.point(lambda value: 1 if value < 245 else 0)
    columns = [sum(mask.getpixel((x, y)) for y in range(mask.height)) for x in range(mask.width)]
    rows = [sum(mask.getpixel((x, y)) for x in range(mask.width)) for y in range(mask.height)]
    dense_columns = [index for index, count in enumerate(columns) if count >= 8]
    dense_rows = [index for index, count in enumerate(rows) if count >= 8]
    if not dense_columns or not dense_rows:
        return trim_white(rgb, margin)
    cropped = rgb.crop((
        dense_columns[0],
        dense_rows[0],
        dense_columns[-1] + 1,
        dense_rows[-1] + 1,
    ))
    return ImageOps.expand(cropped, border=margin, fill="white")


def crop_adapter(filename: str, reference_width: int, box: tuple[int, int, int, int]) -> Image.Image:
    source = Image.open(SOURCES / filename).convert("RGB")
    scale = source.width / reference_width
    scaled = tuple(round(value * scale) for value in box)
    image = trim_sparse_guides(source.crop(scaled))
    if max(image.size) > 500:
        image.thumbnail((500, 500), Image.Resampling.LANCZOS)
    # Normalize near-white antialiasing from the PDF renderer to a clean white background.
    pixels = image.load()
    for y in range(image.height):
        for x in range(image.width):
            r, g, b = pixels[x, y]
            if r >= 250 and g >= 250 and b >= 250:
                pixels[x, y] = (255, 255, 255)
    return image


OUTPUT.mkdir(parents=True, exist_ok=True)
for code, (filename, reference_width, box) in CROPS.items():
    result = crop_adapter(filename, reference_width, box)
    destination = OUTPUT / f"{code}.png"
    result.save(destination, optimize=True)
    print(f"{destination.name}\t{result.width}x{result.height}")
