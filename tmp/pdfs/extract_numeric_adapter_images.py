from pathlib import Path
import shutil

from PIL import Image, ImageChops


ROOT = Path(__file__).resolve().parents[2]
HIRES = ROOT / "tmp/pdfs/hires"
IMAGES = ROOT / "adapters/images"


# Coordinates are measured on the 120 dpi verification renders. The source
# images are rendered at 240 dpi, so the crop is scaled to the actual width.
CROPS = {
    "xhx6": ("4300-p47.png", 1020, (360, 610, 500, 725)),
    "ptr34m": ("4100-p238.png", 992, (640, 230, 900, 460)),
    "g4mk4": ("4100-p232.png", 992, (650, 220, 920, 450)),
    "tt4mx": ("4100-p79.png", 992, (690, 220, 925, 475)),
    "0107": ("4100-p241.png", 992, (650, 220, 925, 455)),
    "f3t4": ("4100-p249.png", 992, (640, 230, 920, 455)),
    "whk_m_cs": ("4300-p90.png", 1020, (550, 1090, 635, 1175)),
    "whk_m": ("4300-p90.png", 1020, (690, 1090, 770, 1175)),
    "wh_m_kds": ("4300-p91.png", 1020, (700, 145, 780, 220)),
    "f6mk4": ("4100-p225.png", 992, (650, 220, 910, 450)),
    "fnmk4": ("4100-p234.png", 992, (730, 220, 920, 450)),
    "ring": ("4300-p39.png", 1020, (245, 1050, 330, 1135)),
    "f63p4": ("4100-p253.png", 992, (640, 760, 920, 970)),
    "wgtx": ("4100-p73.png", 992, (690, 220, 930, 450)),
    "wftx": ("4300-p46.png", 1020, (360, 650, 520, 820)),
    "f4omx": ("4100-p31.png", 992, (730, 220, 925, 410)),
    "bbmtx": ("4100-p21.png", 992, (680, 230, 930, 450)),
    "f8ohg": ("4100-p220.png", 992, (650, 230, 925, 460)),
}

CROP_TARGETS = {
    "2015": "xhx6",
    "2041": "ptr34m",
    "2143": "g4mk4",
    "2228": "tt4mx",
    "2247": "0107",
    "2249": "f3t4",
    "2280": "whk_m",
    "2281": "whk_m_cs",
    "2282": "wh_m_kds",
    "2612": "f6mk4",
    "7228": "tt4mx",
    "8141": "fnmk4",
    "8207": "ring",
    "8219": "f63p4",
    "8221": "wgtx",
    "8225": "wftx",
    "8228": "wftx",
    "8243": "f4omx",
    "8245": "bbmtx",
    "8246": "f8ohg",
}

COPY_TARGETS = {
    "2441": "2444",
    "2449": "2419",
    "2615": "2613",
    "2647": "2644",
    "2649": "2648",
    "2716": "2714",
    "2717": "2715",
    "2915": "8624",
    "2917": "2943",
    "2918": "2641",
    "2995": "8624",
    "2996": "8624",
    "7180": "TPLS",
    "7215": "LOHX6",
    "72225": "XHL6",
    "7227": "XHLO",
    "7249": "F682EDML",
    "7258": "WF5OLO",
    "7526": "WJJLO",
    "8229": "8248",
    "8278": "8284",
    "8279": "8284",
    "8443": "8457",
    "8619": "8624",
    "8648": "7648",
    "8778": "8777",
}


def trim_white(image: Image.Image, margin: int = 12) -> Image.Image:
    white = Image.new("RGB", image.size, "white")
    difference = ImageChops.difference(image.convert("RGB"), white).convert("L")
    difference = difference.point(lambda value: 255 if value > 10 else 0)
    bbox = difference.getbbox()
    if bbox is None:
        raise ValueError("crop contains no visible drawing")
    left, top, right, bottom = bbox
    left = max(0, left - margin)
    top = max(0, top - margin)
    right = min(image.width, right + margin)
    bottom = min(image.height, bottom + margin)
    return image.crop((left, top, right, bottom))


def crop_source(key: str) -> Image.Image:
    filename, reference_width, box = CROPS[key]
    source = Image.open(HIRES / filename).convert("RGB")
    scale = source.width / reference_width
    scaled_box = tuple(round(value * scale) for value in box)
    result = trim_white(source.crop(scaled_box))
    if max(result.size) > 500:
        result.thumbnail((500, 500), Image.Resampling.LANCZOS)
    return result


destinations = set(CROP_TARGETS) | set(COPY_TARGETS)
if len(destinations) != 46:
    raise RuntimeError(f"expected 46 numeric adapter images, got {len(destinations)}")

for code in sorted(destinations):
    destination = IMAGES / f"{code}.png"
    if destination.exists() and code in COPY_TARGETS:
        continue
    if code in CROP_TARGETS:
        crop_source(CROP_TARGETS[code]).save(destination, optimize=True)
    else:
        source = IMAGES / f"{COPY_TARGETS[code]}.png"
        if not source.exists():
            raise FileNotFoundError(source)
        shutil.copyfile(source, destination)

for code in sorted(destinations):
    destination = IMAGES / f"{code}.png"
    with Image.open(destination) as source:
        image = source.convert("RGB")
    if max(image.size) > 500:
        image.thumbnail((500, 500), Image.Resampling.LANCZOS)
        image.save(destination, optimize=True)
    print(f"{code}.png\t{image.width}x{image.height}")
