import fitz
import sys
import argparse
import json
import pytesseract
from PIL import Image


def extract(pdf_path: str, lang: str = "eng") -> None:
    """Extract text blocks with normalized coordinates from the PDF."""

    doc = fitz.open(pdf_path)
    pages = []
    for page_number, page in enumerate(doc, start=1):
        width, height = page.rect.width, page.rect.height
        blocks = []

        for block in page.get_text("blocks"):
            x0, y0, x1, y1, text, *_ = block
            text = text.strip()
            if not text:
                continue
            blocks.append(
                {
                    "text": text,
                    "x": x0 / width,
                    "y": y0 / height,
                    "width": (x1 - x0) / width,
                    "height": (y1 - y0) / height,
                }
            )

        if not blocks:
            # Fallback to OCR when the page contains no text blocks
            pix = page.get_pixmap()
            img = Image.frombytes("RGB", [pix.width, pix.height], pix.samples)
            ocr = pytesseract.image_to_data(img, output_type=pytesseract.Output.DICT, lang=lang)
            n = len(ocr.get("text", []))
            for i in range(n):
                text = ocr["text"][i].strip()
                if not text:
                    continue
                x = ocr["left"][i] / pix.width
                y = ocr["top"][i] / pix.height
                w = ocr["width"][i] / pix.width
                h = ocr["height"][i] / pix.height
                blocks.append(
                    {
                        "text": text,
                        "x": x,
                        "y": y,
                        "width": w,
                        "height": h,
                    }
                )

        pages.append({"page": page_number, "blocks": blocks})

    doc.close()
    print(json.dumps(pages))

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Extract text from PDF')
    parser.add_argument('pdf', help='input PDF')
    parser.add_argument('--lang', default='eng', help='OCR language')
    args = parser.parse_args()
    extract(args.pdf, args.lang)
