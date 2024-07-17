import sys
import uuid
import pandas as pd
from pdfminer.high_level import extract_pages
from pdfminer.layout import LTTextContainer

def find_string_in_pdf(pdf_path, search_strings, links):
    results = []
    for page_layout in extract_pages(pdf_path):
        for element in page_layout:
            if isinstance(element, LTTextContainer):
                text = element.get_text()
                for index, string in enumerate(search_strings):
                    if str(string) in text:
                        result = {
                            "matched": string,
                            "pageNumber": page_layout.pageid,
                            "x": element.bbox[0],
                            "y": element.bbox[1],
                            "link": links[index]
                        }
                        results.append(result)
    return results

def main():

    pdf_filename = sys.argv[1]
    csv_filename = sys.argv[2]
    output_csv = '/tmp/' + str(uuid.uuid4()) + '.csv'

    # Read CSV file
    df = pd.read_csv(csv_filename, header=None, encoding='latin1', delimiter=';', on_bad_lines='skip')

    search_strings = df[0].tolist()  # Change to the correct column index for 'A'
    links = df[1].tolist()  # Column 'B'

    results = find_string_in_pdf(pdf_filename, search_strings, links)

    # Create a DataFrame and export to CSV with semicolon as delimiter
    results_df = pd.DataFrame(results)
    results_df.to_csv(output_csv, sep=',', index=False)

    print(output_csv)

if __name__ == "__main__":
    main()
