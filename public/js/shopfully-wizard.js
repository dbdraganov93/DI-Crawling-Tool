
let currentStep = 0;

function showStep(step) {
    for (let i = 0; i <= 3; i++) {
        const el = document.getElementById(`step-${i}`);
        el.style.display = (i === step ? 'block' : 'none');
    }

    document.querySelectorAll('#stepper .stepper-step').forEach((stepEl, index) => {
        stepEl.classList.remove('active', 'completed');
        if (index < step) {
            stepEl.classList.add('completed');
        } else if (index === step) {
            stepEl.classList.add('active');
        }
    });

    currentStep = step;
}

function prepareAndSubmit(e) {
    e.preventDefault();

    const form = document.querySelector('form');
    const steps = document.querySelectorAll('.step');
    const originalStep = currentStep;

    steps.forEach(step => step.style.display = 'block');

    const isValid = form.checkValidity();

    if (!isValid) {
        form.reportValidity();
        steps.forEach((step, index) => {
            step.style.display = (index === originalStep ? 'block' : 'none');
        });
        return;
    }

    steps.forEach((step, index) => {
        step.style.display = (index === originalStep ? 'block' : 'none');
    });

    const submitBtn = e.target;
    submitBtn.classList.add('btn-loading');

    setTimeout(() => {
        form.submit();
    }, 50);
}



function nextStep(step = currentStep + 1) {
    const currentStepElement = document.getElementById(`step-${currentStep}`);
    const inputs = currentStepElement.querySelectorAll('input, select, textarea');

    let isValid = true;

    inputs.forEach(input => {
        const $input = $(input);
        const isSelect2 = $input.hasClass('select2-hidden-accessible');

        $input.next('.select2-container').find('.select2-selection').removeClass('is-invalid');
        $input.next('.select2-container').next('.select2-error-message')?.remove();

        if (isSelect2 && input.required && !input.value) {
            isValid = false;

            $input.next('.select2-container').find('.select2-selection')
                .addClass('is-invalid');

            const msg = document.createElement('div');
            msg.className = 'text-danger select2-error-message mt-1';
            msg.innerText = input.dataset.errorMessage || 'This field is required.';
            $input.next('.select2-container').after(msg);
        }

        if (!isSelect2 && !input.checkValidity()) {
            input.reportValidity();
            if (input.offsetParent !== null) input.focus();
            isValid = false;
        }
    });

    if (!isValid) return;

    if (step === 3) fillPreview();
    showStep(step);
}




function prevStep() {
    if (currentStep > 0) showStep(currentStep - 1);
}

function addNumberField() {

    const wrapper = document.getElementById('numbers-wrapper');
    const prototype = wrapper.dataset.prototype;
    const indexStart = wrapper.querySelectorAll('.number-entry[data-real="true"]').length;

    const bulkInput = document.getElementById('bulkNumbers');
    let inputValue = bulkInput?.value || '';
    const numbers = inputValue.split(',').map(s => s.trim()).filter(Boolean);

    if (numbers.length === 0) {
        const newForm = prototype.replace(/__name__/g, indexStart);
        const div = document.createElement('div');
        div.classList.add('number-entry', 'mb-2');
        div.setAttribute('data-real', 'true');
        div.innerHTML = newForm;
        wrapper.appendChild(div);
        return;
    }

    numbers.forEach((number, i) => {
        const index = indexStart + i;
        const newForm = prototype.replace(/__name__/g, index);
        const div = document.createElement('div');
        div.classList.add('number-entry', 'mb-2');
        div.setAttribute('data-real', 'true');
        div.innerHTML = newForm;

        const numberInput = div.querySelector('input[name$="[number]"]');
        if (numberInput) {
            numberInput.value = number;
            fetchAndSetDates(numberInput, div); // <-- добави този ред
        }


        wrapper.appendChild(div);
    });

    if (bulkInput) bulkInput.value = '';
}
function formatDateForDatetimeLocal(dateString, isEnd = false) {
    const date = new Date(dateString);
    if (isNaN(date)) return '';

    // Принуждаваме го в локален формат
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = isEnd ? '23' : '00';
    const minutes = isEnd ? '59' : '00';

    return `${year}-${month}-${day}T${hours}:${minutes}`;
}




async function fetchAndSetDates(numberInput, block) {
    const number = numberInput.value.trim();
    const localeInput = document.querySelector('[name$="[locale]"]');
    const locale = localeInput?.value?.trim();

    if (!number || !locale) return;

    try {
        const url = `/api/shopfully/brochure?brochure_number=${encodeURIComponent(number)}&locale=${encodeURIComponent(locale)}`;
        const response = await fetch(url);
        const data = await response.json();

        if (Array.isArray(data) && data.length > 0) {
            const startDate = data[0].start_date;
            const endDate = data[0].end_date;
            const startInput = block.querySelector('input[name$="[validity_start]"]');
            const endInput = block.querySelector('input[name$="[validity_end]"]');
            const visibilityStartInput = block.querySelector('input[name$="[visibility_start]"]');

            if (startInput && startDate) startInput.value = formatDateForDatetimeLocal(startDate);
            if (endInput && endDate) endInput.value = formatDateForDatetimeLocal(endDate, true); // <-- fix
            if (visibilityStartInput && startDate) visibilityStartInput.value = formatDateForDatetimeLocal(startDate);
        }
    } catch (e) {
        console.error('Failed to fetch brochure dates:', e);
    }
}



function fillPreview() {
    const companySelect = document.querySelector('[name$="[company]"]');
    const companyText = companySelect.options[companySelect.selectedIndex]?.text || '';
    document.getElementById('preview-company').textContent = companyText;

    const locale = document.querySelector('[name$="[locale]"]').value;
    document.getElementById('preview-locale').textContent = locale;

    const prefixInput = document.querySelector('[name$="[prefix]"]');
    const suffixInput = document.querySelector('[name$="[suffix]"]');

    const prefix = prefixInput?.value || '';
    const suffix = suffixInput?.value || '';

    document.getElementById('preview-prefix').textContent = prefix;
    document.getElementById('preview-suffix').textContent = suffix;

    const previewList = document.getElementById('preview-numbers');
    previewList.innerHTML = '';

    const wrappers = document.querySelectorAll('#numbers-wrapper .number-entry[data-real="true"]');
    let visibleIndex = 1;

    wrappers.forEach(block => {
        const numberInput = block.querySelector('input[name$="[number]"]');
        const pixelInput = block.querySelector('input[name$="[tracking_pixel]"]');
        const startInput = block.querySelector('input[name$="[validity_start]"]');
        const endInput = block.querySelector('input[name$="[validity_end]"]');
        const visibilityInput = block.querySelector('input[name$="[visibility_start]"]');
        if (!numberInput || !pixelInput) return;

        const number = numberInput.value.trim();
        const pixel = pixelInput.value.trim();
        const start = startInput?.value.trim() || '';
        const end = endInput?.value.trim() || '';
        const visibility = visibilityInput?.value.trim() || '';

        if (!number && !pixel && !start && !end && !visibility) return;

        const item = document.createElement('li');
        item.classList.add('list-group-item', 'preview-brochure-item');
        item.innerHTML = `
                    <div><strong>#${visibleIndex++}: ${number}</strong></div>
                    <div class="small text-muted">Pixel: ${pixel || '-'}</div>
                    <div class="small">Validity: ${start || '-'} - ${end || '-'}</div>
                    <div class="small">Visibility start: ${visibility || '-'}</div>
                `;
        previewList.appendChild(item);
    });
}



document.addEventListener('DOMContentLoaded', function () {
    new DataTable('#logsTable', {
        responsive: true,
        pageLength: 10,
        order: [[1, 'desc']],
        dom: '<"d-flex justify-content-between mb-2"lf>tip',
        language: {
            search: '',
            searchPlaceholder: 'Search logs...'
        }
    });
});


document.addEventListener('DOMContentLoaded', function () {
    const ownerSelect = document.querySelector('[name$="[owner]"]');
    const companySelect = document.querySelector('[name$="[company]"]');

    ownerSelect.addEventListener('change', async function () {
        const ownerId = this.value;
        companySelect.innerHTML = '<option value="">Loading companies...</option>';
        companySelect.disabled = true;

        try {
            const response = await fetch(`/company/api/companies?owner=${encodeURIComponent(ownerId)}`);
            const companies = await response.json();

            companySelect.innerHTML = '<option value="">Select a company</option>';

            companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.label;
                companySelect.appendChild(option);
            });

            companySelect.disabled = false;
        } catch (e) {
            companySelect.innerHTML = '<option value="">Error loading companies</option>';
            console.error('Error fetching companies:', e);
        }
        const timezoneInput = document.querySelector('[name$="[timezone]"]');

        try {
            const response = await fetch(`/company/api/timezone?owner=${encodeURIComponent(ownerId)}`);
            const data = await response.json();

            timezoneInput.value = data.timezone || '';
        } catch (e) {
            console.error('Failed to fetch timezone:', e);
        }
    });
});


document.addEventListener('DOMContentLoaded', function () {
    const $ownerSelect = $('[name$="[owner]"]');
    const $companySelect = $('[name$="[company]"]');

    $ownerSelect.select2({
        placeholder: 'Select an owner',
        allowClear: true,
        width: '100%'
    });

    $companySelect.select2({
        placeholder: 'Select a company',
        allowClear: true,
        width: '100%'
    });

    $ownerSelect.on('change', async function () {
        const selectedOwnerText = $(this).find('option:selected').text().trim();
        const ownerLabel = document.querySelector('.stepper-step[data-step="0"] .stepper-label');
        if (ownerLabel) {
            ownerLabel.textContent = selectedOwnerText || 'Owner';
        }

        const ownerId = $(this).val();

        this.setCustomValidity('');

        $companySelect.prop('disabled', true).empty().append(new Option('Loading companies...', '', false, false));

        try {
            const response = await fetch(`/company/api/companies?owner=${encodeURIComponent(ownerId)}`);
            const companies = await response.json();

            $companySelect.empty().append(new Option('Select a company', '', true, false));
            companies.forEach(company => {
                const option = new Option(company.label, company.id, false, false);
                $companySelect.append(option);
            });

            $companySelect.prop('disabled', false).trigger('change');
        } catch (error) {
            console.error('Error loading companies:', error);
            $companySelect.empty().append(new Option('Error loading companies', '', false, false));
            $companySelect.prop('disabled', false);
        }

        const timezoneInput = document.querySelector('[name$="[timezone]"]');
        try {
            const response = await fetch(`/company/api/timezone?owner=${encodeURIComponent(ownerId)}`);
            const data = await response.json();
            timezoneInput.value = data.timezone || '';
        } catch (e) {
            console.error('Failed to fetch timezone:', e);
        }

        const localeInput = document.querySelector('[name$="[locale]"]');
        try {
            const response = await fetch(`/api/shopfully/locale?ownerId=${encodeURIComponent(ownerId)}`);
            const data = await response.json();
            if (data.locale && localeInput) {
                localeInput.value = data.locale;
            }
        } catch (e) {
            console.error('Failed to fetch locale:', e);
        }
    });
    $companySelect.on('change', function () {
        this.setCustomValidity('');
        const selectedCompanyText = $(this).find('option:selected').text().trim();
        const companyLabel = document.querySelector('.stepper-step[data-step="1"] .stepper-label');
        if (companyLabel) {
            companyLabel.textContent = selectedCompanyText || 'Company';
        }
    });
});


document.addEventListener('DOMContentLoaded', function () {
    function sanitizeTrackingPixel(input) {
        let url = input.value.trim();

        if (!url) return;

        if (!url.startsWith('https://')) {
            if (url.startsWith('http://')) {
                url = 'https://' + url.slice(7);
            } else {
                url = 'https://' + url;
            }
        }

        if (url.includes('%%CACHEBUSTER%%')) {
            input.value = url;
            return;
        }

        const hasSemicolon = url.includes(';');
        const hasQuestion = url.includes('?');
        const hasAmp = url.includes('&');
        const isDoubleClickStyle = hasSemicolon && !hasQuestion; // DCM pattern

        let delimiter = '?';
        if (isDoubleClickStyle) {
            delimiter = ';';
        } else if (hasQuestion) {
            delimiter = hasAmp ? '&' : '?';
        }

        if (url.includes('[timestamp]')) {
            url = url.replace('[timestamp]', '%%CACHEBUSTER%%');
            input.value = url;
            return;
        }

        const ordRegex = /ord=([^;&?]*)/;
        if (ordRegex.test(url)) {
            url = url.replace(ordRegex, 'ord=%%CACHEBUSTER%%');
            input.value = url;
            return;
        }

        if (isDoubleClickStyle) {
            if (url.endsWith(';')) {
                url += 'ord=%%CACHEBUSTER%%';
            } else {
                url += ';ord=%%CACHEBUSTER%%';
            }
        } else {
            const sep = url.includes('?') ? (hasAmp ? '&' : '&') : '?';
            url += sep + 'ord=%%CACHEBUSTER%%';
        }

        input.value = url;
    }

    function attachListenersToTrackingPixels() {
        document.querySelectorAll('input[name$="[tracking_pixel]"]').forEach(input => {
            input.removeEventListener('blur', onBlurHandler);
            input.addEventListener('blur', onBlurHandler);
        });
    }

    function onBlurHandler(e) {
        sanitizeTrackingPixel(e.target);
    }

    attachListenersToTrackingPixels();

    const wrapper = document.getElementById('numbers-wrapper');
    const observer = new MutationObserver(() => {
        attachListenersToTrackingPixels();
    });
    observer.observe(wrapper, { childList: true, subtree: true });
});


document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.refresh-status-btn').forEach(button => {
        button.addEventListener('click', async function () {
            const logId = this.dataset.importId;
            const importId = this.dataset.importId;

            this.disabled = true;
            this.innerHTML = '⏳';
            function getBadgeClass(status) {
                const normalized = (status || '').toLowerCase();

                const successStatuses = ['done', 'success', 'completed'];
                const warningStatuses = ['skipped', 'partial', 'warning'];
                const dangerStatuses = ['failed', 'error', 'aborted'];
                const infoStatuses = ['running', 'processing', 'pending', 'queued', 'retrying'];

                if (successStatuses.includes(normalized)) return 'bg-success';
                if (warningStatuses.includes(normalized)) return 'bg-warning text-dark';
                if (dangerStatuses.includes(normalized)) return 'bg-danger';
                if (infoStatuses.includes(normalized)) return 'bg-info text-dark';

                return 'bg-secondary';
            }

            try {
                const response = await fetch(`/api/logs/${logId}/refresh`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ importId: importId })
                });

                const data = await response.json();
                if (data.success) {
                    const row = this.closest('tr');

                    const badgeClass = getBadgeClass(data.status);
                    const statusText = data.status ?? 'unknown';

                    const statusCell = row.querySelector('td:nth-child(5)');
                    if (statusCell) {
                        statusCell.innerHTML = `<span class="badge ${badgeClass}">${statusText}</span>`;
                    }

                    const jsonCell = row.querySelector('td:nth-child(7)');
                    if (jsonCell) {
                        jsonCell.innerHTML = `
            <span class="text-muted">
                Notices: ${data.noticesCount},
                Warnings: ${data.warningsCount},
                Errors: ${data.errorsCount}
            </span>
        `;
                    }
                } else {
                    alert('Failed to refresh status.');
                }
            } catch (err) {
                console.error(err);
                alert('Error fetching import status.');
            } finally {
                this.disabled = false;
                this.innerHTML = '🔄';
            }
        });
    });
});

function confirmReimport() {
    return new Promise(resolve => {
        const modalEl = document.getElementById('reimportConfirmModal');
        if (!modalEl) {
            resolve(confirm('Are you sure you want to rerun?'));
            return;
        }
        const modal = new bootstrap.Modal(modalEl);
        const yesBtn = modalEl.querySelector('.btn-yes');
        const noBtn = modalEl.querySelector('.btn-no');

        const cleanup = () => {
            yesBtn.removeEventListener('click', onYes);
            noBtn.removeEventListener('click', onNo);
        };
        const onYes = () => { cleanup(); modal.hide(); resolve(true); };
        const onNo = () => { cleanup(); modal.hide(); resolve(false); };

        yesBtn.addEventListener('click', onYes);
        noBtn.addEventListener('click', onNo);
        modal.show();
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.reimport-btn').forEach(button => {
        button.addEventListener('click', async function () {
            const confirmed = await confirmReimport();
            if (!confirmed) {
                return;
            }
            const logId = this.dataset.id;
            this.disabled = true;
            this.innerHTML = '⏳';
            try {
                const response = await fetch(`/api/logs/${logId}/reimport`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();

                if (!response.ok || !data.success) {
                    const msg = data.message || response.statusText;
                    alert(`Reimport failed: ${msg}`);
                    return;
                }

                const row = this.closest('tr');
                const countCell = row.querySelector('.reimport-count');
                if (countCell) {
                    countCell.textContent = data.reimportCount;
                }
            } catch (err) {
                console.error(err);
                alert('Error triggering reimport.');
            } finally {
                this.disabled = false;
                this.innerHTML = '⟳';
            }
        });
    });
});

