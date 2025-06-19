document.getElementById('run-crawler-btn').addEventListener('click', function () {
    const terminalOutput = document.getElementById('command-output');
    terminalOutput.textContent = 'Running command...\n';

    fetch(crawlerRunUrl, {
        method: 'POST',
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                terminalOutput.textContent += data.output;
            } else {
                terminalOutput.textContent += 'Error: ' + data.error;
            }
        })
        .catch(error => {
            terminalOutput.textContent += 'An error occurred: ' + error.message;
        });
});
