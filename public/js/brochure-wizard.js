function buildUrl(path) {
    const needsIndex = window.location.pathname.includes('/index.php/');
    if (/^https?:/i.test(path)) {
        return path;
    }
    if (needsIndex) {
        return path.startsWith('/index.php') ? path : '/index.php' + (path.startsWith('/') ? '' : '/') + path;
    }
    return path;
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('uploadForm');
    const progress = document.getElementById('progress');
    const result = document.getElementById('result');
    const downloadPdf = document.getElementById('downloadPdf');
    const downloadJson = document.getElementById('downloadJson');
    const editPdf = document.getElementById('editPdf');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = new FormData(form);
        progress.style.display = 'block';
        result.style.display = 'none';

        const resp = await fetch(buildUrl('/brochure/upload'), {method: 'POST', body: data});
        const json = await resp.json();
        if (json.job_id) {
            pollStatus(json.job_id);
        } else if (json.error) {
            progress.innerHTML = '<p class="text-danger">' + json.error + '</p>';
        } else {
            progress.innerHTML = '<p class="text-danger">Upload failed</p>';
        }
    });

    async function pollStatus(id) {
        const resp = await fetch(buildUrl('/brochure/status/' + id));
        const json = await resp.json();
        if (json.status === 'finished') {
            progress.style.display = 'none';
            downloadPdf.href = buildUrl('/brochure/download/' + id + '/pdf');
            downloadJson.href = buildUrl('/brochure/download/' + id + '/json');
             editPdf.href = buildUrl('/brochure/edit/' + id);
            result.style.display = 'block';
        } else if (json.status === 'failed') {
            progress.innerHTML = '<p class="text-danger">' + (json.error || 'Failed') + '</p>';
        } else {
            setTimeout(() => pollStatus(id), 3000);
        }
    }
});

