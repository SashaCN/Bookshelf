"""Draws the Bookshelf app icons (a few books on a shelf) into public/.

Run from the repository root whenever the artwork changes:  python3 frontend/scripts/make-icons.py
Needs Pillow. The artwork is also written by hand in public/favicon.svg; keep both in step.
"""

from pathlib import Path

from PIL import Image, ImageDraw

OUT = Path(__file__).resolve().parent.parent / "public"
BASE = 1024
SCALE = 2  # drawn at twice the size and scaled down, which smooths the edges

ACCENT = (45, 71, 200)
ACCENT_TOP = (66, 94, 224)
BOOKS = [  # x, top, width, colour
    (262, 330, 110, (255, 255, 255)),
    (392, 270, 120, (205, 214, 255)),
    (532, 350, 100, (147, 165, 255)),
    (652, 300, 110, (255, 255, 255)),
]
SHELF_Y = 700
SHELF_HEIGHT = 30


def artwork() -> Image.Image:
    size = BASE * SCALE
    image = Image.new("RGB", (size, size), ACCENT)
    draw = ImageDraw.Draw(image)

    # A soft vertical gradient: a little lighter at the top.
    for y in range(size):
        t = y / size
        colour = tuple(round(top + (bottom - top) * t) for top, bottom in zip(ACCENT_TOP, ACCENT))
        draw.line([(0, y), (size, y)], fill=colour)

    def box(x: int, y: int, w: int, h: int) -> tuple[int, int, int, int]:
        return (x * SCALE, y * SCALE, (x + w) * SCALE, (y + h) * SCALE)

    for x, top, width, colour in BOOKS:
        draw.rounded_rectangle(box(x, top, width, SHELF_Y - top), radius=14 * SCALE, fill=colour)
        # Two thin bands on the spine.
        band = tuple(round(c * 0.82) for c in colour)
        draw.rectangle(box(x + 14, top + 40, width - 28, 8), fill=band)
        draw.rectangle(box(x + 14, top + 64, width - 28, 8), fill=band)

    draw.rounded_rectangle(box(222, SHELF_Y, 580, SHELF_HEIGHT), radius=SHELF_HEIGHT // 2 * SCALE, fill=(255, 255, 255))
    return image


def save(image: Image.Image, size: int, name: str) -> None:
    image.resize((size, size), Image.LANCZOS).save(OUT / name, optimize=True)
    print(f"{name} {size}x{size}")


if __name__ == "__main__":
    (OUT / "icons").mkdir(parents=True, exist_ok=True)
    art = artwork()
    save(art, 192, "icons/icon-192.png")
    save(art, 512, "icons/icon-512.png")
    # The artwork stays inside the central 60%, so the system can mask it into any shape.
    save(art, 512, "icons/icon-maskable-512.png")
    save(art, 180, "apple-touch-icon.png")
    save(art, 32, "favicon-32.png")
