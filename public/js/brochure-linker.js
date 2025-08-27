document.getElementById('linker-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);
    const resp = await fetch(form.action, { method: 'POST', body: data });
    const json = await resp.json();
    if (json.annotated) {
        document.getElementById('download-pdf').href = json.annotated;
        document.getElementById('download-json').href = json.json;
        document.getElementById('result').classList.remove('d-none');
    }
});
