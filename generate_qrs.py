#!/usr/bin/env python3
"""Generate high-resolution PNG and vector SVG QR codes for restaurant tables.

Each QR code encodes the Mini App start link for one table:

    https://t.me/<BOT_USERNAME>/<APP_NAME>?startapp=r<RESTAURANT_ID>_t<table>

The `startapp` value must match exactly what `TableResolver::resolve()`
expects (`r{restaurant_id}_t{table_code}` — see app/Services/TableResolver.php),
otherwise every printed code fails with "QR sessiya parametri noto'g'ri".

Requires: pip install "qrcode[pil]"
"""

from __future__ import annotations

import concurrent.futures
from pathlib import Path

import qrcode
import qrcode.image.svg
from qrcode.constants import ERROR_CORRECT_M

# --- Dynamic config -----------------------------------------------------
BOT_USERNAME = "qr_dasturxon_bot"
APP_NAME = "qrmenu"  # BotFather's Mini App short name — confirm via @BotFather -> /myapps
RESTAURANT_ID = 1  # matches `restaurants.id`
TABLE_COUNT = 20
OUTPUT_DIR = Path("qr_codes")

# High-resolution raster: box_size is pixels per QR module.
PNG_BOX_SIZE = 20
PNG_BORDER = 4  # quiet zone, in modules (QR spec minimum is 4)


def table_url(table_number: int) -> str:
    start_param = f"r{RESTAURANT_ID}_t{table_number}"
    return f"https://t.me/{BOT_USERNAME}/{APP_NAME}?startapp={start_param}"


def generate_one(table_number: int) -> str:
    url = table_url(table_number)

    png_qr = qrcode.QRCode(error_correction=ERROR_CORRECT_M, box_size=PNG_BOX_SIZE, border=PNG_BORDER)
    png_qr.add_data(url)
    png_qr.make(fit=True)
    png_qr.make_image(fill_color="black", back_color="white").save(OUTPUT_DIR / f"stol_{table_number}.png")

    svg_qr = qrcode.QRCode(
        error_correction=ERROR_CORRECT_M,
        box_size=PNG_BOX_SIZE,
        border=PNG_BORDER,
        image_factory=qrcode.image.svg.SvgPathImage,
    )
    svg_qr.add_data(url)
    svg_qr.make(fit=True)
    svg_qr.make_image().save(OUTPUT_DIR / f"stol_{table_number}.svg")

    return f"Stol {table_number}: {url}"


def main() -> None:
    OUTPUT_DIR.mkdir(exist_ok=True)

    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as executor:
        for result in executor.map(generate_one, range(1, TABLE_COUNT + 1)):
            print(result)

    print(f"\n{TABLE_COUNT} ta stol uchun PNG+SVG '{OUTPUT_DIR}/' papkasiga saqlandi.")


if __name__ == "__main__":
    main()
