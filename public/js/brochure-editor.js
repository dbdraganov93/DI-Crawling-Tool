document.addEventListener('DOMContentLoaded', async () => {
    const { pdfUrl, jsonUrl } = window.editorData;
    const container = document.getElementById('pdfContainer');
    const addBtn = document.getElementById('addLink');
    const saveBtn = document.getElementById('savePdf');

    const pdfjsLib = window['pdfjsLib'];
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    const pdf = await pdfjsLib.getDocument(pdfUrl).promise;

    let links = [];
    if (jsonUrl) {
        try {
            const data = await fetch(jsonUrl).then(r => r.json());
            links = data.products.map(p => ({
                page: p.page,
                x: p.position?.x || 0,
                y: p.position?.y || 0,
                width: p.position?.width || 0.1,
                height: p.position?.height || 0.05,
                url: p.url || ''
            }));
        } catch (e) {
            console.warn('Failed to load json', e);
        }
    }

    for (let i = 1; i <= pdf.numPages; i++) {
        const page = await pdf.getPage(i);
        const viewport = page.getViewport({ scale: 1.5 });
        const pageDiv = document.createElement('div');
        pageDiv.className = 'page';
        container.appendChild(pageDiv);

        const canvas = document.createElement('canvas');
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        pageDiv.appendChild(canvas);
        const ctx = canvas.getContext('2d');
        await page.render({ canvasContext: ctx, viewport }).promise;

        const overlay = document.createElement('div');
        overlay.className = 'overlay';
        overlay.style.position = 'absolute';
        overlay.style.left = '0';
        overlay.style.top = '0';
        overlay.style.width = canvas.width + 'px';
        overlay.style.height = canvas.height + 'px';
        pageDiv.appendChild(overlay);

        links.filter(l => l.page === i).forEach(l => {
            overlay.appendChild(createRect(l, overlay));
        });
    }

    addBtn.addEventListener('click', () => {
        const overlay = container.querySelector('.page .overlay');
        if (overlay) {
            overlay.appendChild(createRect({ x:0.1, y:0.1, width:0.2, height:0.1, url:'', page:1 }, overlay));
        }
    });

    saveBtn.addEventListener('click', async () => {
        const pdfBytes = await fetch(pdfUrl).then(r => r.arrayBuffer());
        const pdfDoc = await PDFLib.PDFDocument.load(pdfBytes);
        const pages = container.querySelectorAll('.page');
        pages.forEach((pageDiv, index) => {
            const overlay = pageDiv.querySelector('.overlay');
            const rects = overlay.querySelectorAll('.link-rect');
            rects.forEach(r => {
                const w = overlay.clientWidth;
                const h = overlay.clientHeight;
                const x = parseFloat(r.style.left)/w;
                const y = parseFloat(r.style.top)/h;
                const rw = parseFloat(r.style.width)/w;
                const rh = parseFloat(r.style.height)/h;
                const url = r.dataset.url || '';
                const page = pdfDoc.getPage(index);
                const x1 = x * page.getWidth();
                const y1 = page.getHeight() - (y + rh) * page.getHeight();
                const x2 = (x + rw) * page.getWidth();
                const y2 = page.getHeight() - y * page.getHeight();
                page.drawRectangle({
                    x: x1,
                    y: y1,
                    width: x2 - x1,
                    height: y2 - y1,
                    borderColor: PDFLib.rgb(1, 0, 0),
                    borderWidth: 0,
                    color: PDFLib.rgb(1, 1, 1, 0)
                });
                const linkAnnot = pdfDoc.context.obj({
                    Type: 'Annot',
                    Subtype: 'Link',
                    Rect: [x1, y1, x2, y2],
                    Border: [0, 0, 0],
                    A: { Type: 'Action', S: 'URI', URI: PDFLib.PDFString.of(url) }
                });
                page.node.addAnnot(linkAnnot);
            });
        });
        const edited = await pdfDoc.save();
        const blob = new Blob([edited], {type:'application/pdf'});
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'edited.pdf';
        a.click();
    });

    function createRect(l, overlay) {
        const div = document.createElement('div');
        div.className = 'link-rect';
        const w = overlay.clientWidth;
        const h = overlay.clientHeight;
        div.style.left = (l.x * w) + 'px';
        div.style.top = (l.y * h) + 'px';
        div.style.width = (l.width * w) + 'px';
        div.style.height = (l.height * h) + 'px';
        div.dataset.url = l.url || '';

        div.addEventListener('dblclick', () => {
            const url = prompt('Link URL', div.dataset.url);
            if (url !== null) div.dataset.url = url;
        });
        div.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            div.remove();
        });

        interact(div).draggable({
            listeners: { move (event) { dragMoveListener(event); } }
        }).resizable({ edges: { left: true, right: true, bottom: true, top: true } })
          .on('resizemove', event => {
            const target = event.target;
            target.style.width = event.rect.width + 'px';
            target.style.height = event.rect.height + 'px';
            target.style.left = (parseFloat(target.style.left) + event.deltaRect.left) + 'px';
            target.style.top = (parseFloat(target.style.top) + event.deltaRect.top) + 'px';
          });

        return div;
    }

    function dragMoveListener (event) {
        const target = event.target;
        const x = (parseFloat(target.getAttribute('data-x')) || 0) + event.dx;
        const y = (parseFloat(target.getAttribute('data-y')) || 0) + event.dy;
        target.style.left = (parseFloat(target.style.left) + event.dx) + 'px';
        target.style.top = (parseFloat(target.style.top) + event.dy) + 'px';
        target.setAttribute('data-x', x);
        target.setAttribute('data-y', y);
    }
});
