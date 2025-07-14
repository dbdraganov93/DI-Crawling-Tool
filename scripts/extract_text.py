import fitz
import io
import json
import sys
from PIL import Image
import pytesseract

def extract(pdf_path):
    doc = fitz.open(pdf_path)
    pages = []
    for i in range(len(doc)):
        page = doc[i]
        text = page.get_text().strip()
        if not text:
            pix = page.get_pixmap()
            img = Image.open(io.BytesIO(pix.tobytes('png')))
            text = pytesseract.image_to_string(img)
        pages.append({'page': i + 1, 'text': text})
    print(json.dumps(pages))

if __name__ == '__main__':
    if len(sys.argv) != 2:
        print('Usage: extract_text.py input.pdf', file=sys.stderr)
        sys.exit(1)
    extract(sys.argv[1])
