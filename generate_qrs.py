#!/usr/bin/env python3
"""Generate high-resolution PNG and vector SVG QR codes for restaurant tables.

Each QR code encodes our own https:// "bridge" URL for one table:

    https://<APP_URL>/t/r<RESTAURANT_ID>_t<table>

That bridge route (routes/web.php, GET /t/{startParam}) just 302-redirects
on to the real Telegram Mini App start link — it is NOT the t.me link
itself. This exists because some third-party QR scanner apps (anything
other than the phone's native camera) refuse to hand a t.me:// deep link
off to Telegram, while every scanner opens a plain https:// URL.

The `startapp` value forwarded by the bridge must match exactly what
`TableResolver::resolve()` expects (`r{restaurant_id}_t{table_code}` —
see app/Services/TableResolver.php), otherwise every printed code fails
with "QR sessiya parametri noto'g'ri".

The PNG output has a warning line burned into the image below the QR code
("Faqat telefon kamerasi orqali skanerlang — boshqa skaner ilovalar
ishlamasligi mumkin") — edit WARNING_TEXT below to change/translate it.
The SVG output stays a pure vector QR code with no text overlay (meant
for handing to a print shop / further design work).

Requires: pip install "qrcode[pil]"
"""

from __future__ import annotations

import concurrent.futures
from pathlib import Path

import qrcode
import qrcode.image.svg
from PIL import Image, ImageDraw, ImageFont
from qrcode.constants import ERROR_CORRECT_M

# --- Dynamic config -----------------------------------------------------
APP_URL = "https://qr-dasturxon.uz"  # our own domain — the bridge lives here
BOT_USERNAME = "qr_dasturxon_bot"
APP_NAME = "qrmenu"  # BotFather's Mini App short name — confirm via @BotFather -> /myapps
RESTAURANT_ID = 1  # matches `restaurants.id`
TABLE_COUNT = 20
OUTPUT_DIR = Path("qr_codes")

WARNING_TEXT = "Faqat telefon kamerasi orqali skanerlang — boshqa skaner ilovalar ishlamasligi mumkin"
WARNING_FONT_CANDIDATES = [
    "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
    "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",
]
WARNING_FONT_SIZE = 22
WARNING_COLOR = "#8a7a66"  # matches --ink-soft in qr-generator.html
WARNING_PADDING = 24  # px above the text, below the QR code

# High-resolution raster: box_size is pixels per QR module.
PNG_BOX_SIZE = 20
PNG_BORDER = 4  # quiet zone, in modules (QR spec minimum is 4)


def _load_warning_font() -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    for path in WARNING_FONT_CANDIDATES:
        if Path(path).exists():
            return ImageFont.truetype(path, WARNING_FONT_SIZE)
    return ImageFont.load_default(size=WARNING_FONT_SIZE)


def _wrap_text(text: str, font, max_width: int, draw: ImageDraw.ImageDraw) -> list[str]:
    words = text.split(" ")
    lines: list[str] = []
    current = ""
    for word in words:
        candidate = f"{current} {word}".strip()
        if draw.textlength(candidate, font=font) <= max_width:
            current = candidate
        else:
            if current:
                lines.append(current)
            current = word
    if current:
        lines.append(current)
    return lines


def _with_warning_text(qr_image: Image.Image) -> Image.Image:
    font = _load_warning_font()
    probe = ImageDraw.Draw(qr_image)
    lines = _wrap_text(WARNING_TEXT, font, qr_image.width - 2 * PNG_BOX_SIZE, probe)

    line_height = font.getbbox("Ay")[3] + 6
    text_block_height = WARNING_PADDING + len(lines) * line_height + WARNING_PADDING // 2

    canvas = Image.new("RGB", (qr_image.width, qr_image.height + text_block_height), "white")
    canvas.paste(qr_image, (0, 0))

    draw = ImageDraw.Draw(canvas)
    y = qr_image.height + WARNING_PADDING // 2
    for line in lines:
        width = draw.textlength(line, font=font)
        x = (canvas.width - width) / 2
        draw.text((x, y), line, font=font, fill=WARNING_COLOR)
        y += line_height

    return canvas


def table_start_param(table_number: int) -> str:
    return f"r{RESTAURANT_ID}_t{table_number}"


def table_bridge_url(table_number: int) -> str:
    return f"{APP_URL.rstrip('/')}/t/{table_start_param(table_number)}"


def table_telegram_url(table_number: int) -> str:
    return f"https://t.me/{BOT_USERNAME}/{APP_NAME}?startapp={table_start_param(table_number)}"


def generate_one(table_number: int) -> str:
    url = table_bridge_url(table_number)

    png_qr = qrcode.QRCode(error_correction=ERROR_CORRECT_M, box_size=PNG_BOX_SIZE, border=PNG_BORDER)
    png_qr.add_data(url)
    png_qr.make(fit=True)
    qr_image = png_qr.make_image(fill_color="black", back_color="white").convert("RGB")
    _with_warning_text(qr_image).save(OUTPUT_DIR / f"stol_{table_number}.png")

    svg_qr = qrcode.QRCode(
        error_correction=ERROR_CORRECT_M,
        box_size=PNG_BOX_SIZE,
        border=PNG_BORDER,
        image_factory=qrcode.image.svg.SvgPathImage,
    )
    svg_qr.add_data(url)
    svg_qr.make(fit=True)
    svg_qr.make_image().save(OUTPUT_DIR / f"stol_{table_number}.svg")

    return f"Stol {table_number}: {url}  ->  {table_telegram_url(table_number)}"


def main() -> None:
    OUTPUT_DIR.mkdir(exist_ok=True)

    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as executor:
        for result in executor.map(generate_one, range(1, TABLE_COUNT + 1)):
            print(result)

    print(f"\n{TABLE_COUNT} ta stol uchun PNG+SVG '{OUTPUT_DIR}/' papkasiga saqlandi.")


if __name__ == "__main__":
    main()
