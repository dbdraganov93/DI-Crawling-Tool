import sys
import json
import fitz  # PyMuPDF
#COMMAND FOR DOCKER: pip install pymupdf
def annotate_pdf(pdf_path, output_path, clickouts):
    doc = fitz.open(pdf_path)

    for clickout in clickouts:
        flyer = clickout["FlyerGib"]
        url = flyer["publication_url"].replace("{{client.id}}", "client123")\
                                      .replace("{{utm_source}}", "shopfully")\
                                      .replace("{{Store.id}}", "store123")

        # Dummy page and coordinates (replace with real mapping if available)
        page_num = flyer.get("page", 0)  # optional enhancement
        x, y, w, h = 50, 100, 100, 30

        rect = fitz.Rect(x, y, x + w, y + h)
        page = doc[page_num]
        page.insert_link({
            "kind": fitz.LINK_URI,
            "from": rect,
            "uri": url
        })

    doc.save(output_path)

if __name__ == "__main__":
    # Arguments: input.pdf output.pdf clickouts.json
    pdf_path = sys.argv[1]
    output_path = sys.argv[2]
    json_path = sys.argv[3]

    with open(json_path, "r") as f:
        clickouts = json.load(f)

    annotate_pdf(pdf_path, output_path, clickouts)
