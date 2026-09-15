from pathlib import Path

from PIL import Image, ImageChops, ImageOps


ROOT = Path(r"C:\Users\patrick\Documents\GitHub\Geeve_selectors")
IMAGES = ROOT / "adapters/images"
GENERATED = Path(r"C:\Users\patrick\.codex\generated_images\01a09ab5-7a36-7932-bffa-4d19d9431317")

SOURCES = {
    "2015": "exec-6c9ef150-56ae-4aac-9742-601117b60b06.png",
    "2041": "exec-c791ad89-3649-42ea-beab-b5e62ca5a301.png",
    "2143": "exec-02b456a7-4c63-44a0-91a7-b4d2a64e29eb.png",
    "2228": "exec-c5f49dbe-2b3e-4db1-9792-e2e2a2df40c7.png",
    "2247": "exec-582bcfc9-8433-43d0-bc37-1f37f5b27173.png",
    "2249": "exec-1a404c4d-ebdb-47d8-818d-c033b8382770.png",
    "2282": "exec-f342bfa4-2686-4cac-8b72-413d1737087f.png",
    "2612": "exec-54595ead-0a98-4acb-82c9-b7b2e1f430c0.png",
    "8141": "exec-91349e27-cf09-4e6d-9ffb-2fac116b23c6.png",
    "8219": "exec-7fe9c4ea-763e-44b1-91da-5d238fb26d91.png",
    "8221": "exec-0388f129-7a6e-4da9-97cd-de3fd35b1184.png",
    "8225": "exec-bf9808ca-3587-4a8d-a686-c27388fb8eae.png",
    "8243": "exec-ac95e6fe-2822-4a62-947f-fc31d613b3a8.png",
    "8245": "exec-1192970d-60f0-4f76-8d7f-627317ae05e0.png",
    "8246": "exec-b507d43a-33c8-4f0c-ba1e-d78ca9c968ef.png",
}


def normalize(source: Path) -> Image.Image:
    image = Image.open(source).convert("RGB")
    # Generated backgrounds are subtly off-white. A conservative threshold
    # finds the actual product while keeping pale metal highlights intact.
    grey = image.convert("L")
    mask = grey.point(lambda value: 255 if value < 238 else 0)
    bbox = mask.getbbox()
    if bbox is None:
        raise ValueError(f"no adapter found in {source}")
    left, top, right, bottom = bbox
    pad = max(18, round(max(image.size) * 0.015))
    image = image.crop((
        max(0, left - pad),
        max(0, top - pad),
        min(image.width, right + pad),
        min(image.height, bottom + pad),
    ))
    pixels = image.load()
    for y in range(image.height):
        for x in range(image.width):
            r, g, b = pixels[x, y]
            if r >= 246 and g >= 246 and b >= 246:
                pixels[x, y] = (255, 255, 255)
    if max(image.size) > 500:
        image.thumbnail((500, 500), Image.Resampling.LANCZOS)
    return image


for code, filename in SOURCES.items():
    result = normalize(GENERATED / filename)
    destination = IMAGES / f"{code}.png"
    result.save(destination, optimize=True)
    print(f"{destination.name}\t{result.width}x{result.height}")

# These catalog families use the same drawing and should remain pixel-identical.
for target, source in {"7228": "2228", "8228": "8225"}.items():
    image = Image.open(IMAGES / f"{source}.png").convert("RGB")
    image.save(IMAGES / f"{target}.png", optimize=True)
    print(f"{target}.png\t{image.width}x{image.height}\t(copy of {source})")
