import fitz  # PyMuPDF
import json
import sys

def add_links_to_pdf(pdf_path, output_path, clickouts):
    doc = fitz.open(pdf_path)

    for clickout in clickouts:
        try:
            if not all(clickout.get(k) not in (None, "") for k in ("pageNumber", "x", "y", "width", "height", "url")):
                continue

            page_number = int(clickout["pageNumber"]) - 1
            page = doc[page_number]

            width, height = page.rect.width, page.rect.height

            x = float(clickout["x"]) * width
            y = float(clickout["y"]) * height
            w = float(clickout["width"]) * width
            h = float(clickout["height"]) * height

            rect = fitz.Rect(x, y, x + w, y + h)
            page.insert_link({
                "kind": fitz.LINK_URI,
                "from": rect,
                "uri": clickout["url"].strip(),
            })
        except Exception:
            # Skip malformed clickout definitions
            continue

    doc.save(output_path)
    doc.close()

if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("Usage: python add_links.py input.pdf clickouts.json output.pdf")
        sys.exit(1)

    input_pdf, clickouts_json, output_pdf = sys.argv[1:]
    with open(clickouts_json, 'r') as f:
        clickouts = json.load(f)

    add_links_to_pdf(input_pdf, output_pdf, clickouts)
