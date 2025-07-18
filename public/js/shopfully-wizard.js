
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

        function validateBrochureDates() {
            const blocks = document.querySelectorAll('#numbers-wrapper .number-entry[data-real="true"]');
            let valid = true;
            blocks.forEach(block => {
                const numberVal = block.querySelector('input[name$="[number]"]')?.value.trim();
                const pixelVal = block.querySelector('input[name$="[tracking_pixel]"]')?.value.trim();
                const startInput = block.querySelector('input[name$="[validity_start]"]');
                const endInput = block.querySelector('input[name$="[validity_end]"]');
                const visInput = block.querySelector('input[name$="[visibility_start]"]');

                if (!startInput || !endInput || !visInput) return;

                const hasContent = numberVal || pixelVal || startInput.value || endInput.value || visInput.value;
                if (!hasContent) return;

                [startInput, endInput, visInput].forEach(i => {
                    i.classList.remove('is-invalid');
                    i.setCustomValidity('');
                });

                if (!startInput.value) {
                    startInput.classList.add('is-invalid');
                    startInput.setCustomValidity('Please fill out this field');
                    startInput.reportValidity();
                    if (valid) startInput.focus();
                    valid = false;
                }

                if (!endInput.value) {
                    endInput.classList.add('is-invalid');
                    endInput.setCustomValidity('Please fill out this field');
                    endInput.reportValidity();
                    if (valid) endInput.focus();
                    valid = false;
                }

                if (!visInput.value) {
                    visInput.classList.add('is-invalid');
                    visInput.setCustomValidity('Please fill out this field');
                    visInput.reportValidity();
                    if (valid) visInput.focus();
                    valid = false;
                }
            });
            return valid;
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

            if (currentStep === 2 && step === 3) {
                if (!validateBrochureDates()) {
                    return;
                }
            }

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
                div.setAttribute('data-clickouts-count', '0');
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
                div.setAttribute('data-clickouts-count', '0');
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

        async function fetchJson(url) {
            const response = await fetch(buildUrl(url));
            let data = null;
            try {
                data = await response.json();
            } catch (e) {
                // non-JSON response
            }
            if (!response.ok) {
                const message = (data && data.error) ? data.error : `HTTP ${response.status}`;
                throw new Error(message);
            }
            return data;
        }




        async function fetchAndSetDates(numberInput, block) {
            const number = numberInput.value.trim();
            const localeInput = document.querySelector('[name$="[locale]"]');
            const locale = localeInput?.value?.trim();

            if (!number || !locale) return;

            try {
                const url = `/api/shopfully/brochure?brochure_number=${encodeURIComponent(number)}&locale=${encodeURIComponent(locale)}`;
                const data = await fetchJson(url);

                if (Array.isArray(data) && data.length > 0) {
                    const startDate = data[0].start_date;
                    const endDate = data[0].end_date;
                    block.dataset.clickoutsCount = data[0].clickouts_count ?? 0;
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

            const previewList = document.getElementById('preview-grid');
            if (!previewList) {
                console.warn('Preview grid element missing');
                return;
            }
            previewList.innerHTML = '';

            const wrappers = document.querySelectorAll('#numbers-wrapper .number-entry[data-real="true"]');
            let visibleIndex = 1;
            let totalClickouts = 0;

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

                const clickouts = parseInt(block.dataset.clickoutsCount || '0');
                totalClickouts += clickouts;

                const card = document.createElement('div');
                card.classList.add('preview-brochure-card');
                card.innerHTML = `
                    <div class="pdf-preview-grid"><canvas></canvas></div>
                    <div class="info">
                        <div><strong>#${visibleIndex++}: ${number}</strong></div>
                        <div class="small text-muted">Pixel: ${pixel || '-'}</div>
                        <div class="small">Validity: ${start || '-'} - ${end || '-'}</div>
                        <div class="small">Visibility start: ${visibility || '-'}</div>
                        <div class="small">${clickouts} clickout link${clickouts == 1 ? '' : 's'} found and applied</div>
                    </div>
                `;
                previewList.appendChild(card);

                const localeVal = document.querySelector('[name$="[locale]"]').value;
                fetch(buildUrl(`/api/shopfully/preview?brochure_number=${encodeURIComponent(number)}&locale=${encodeURIComponent(localeVal)}`))
                    .then(r => r.json())
                    .then(data => {
                        if (data.pdf_url) {
                            const container = card.querySelector('.pdf-preview-grid');
                            showPdfPreview(container, data.pdf_url, data.clickouts || []);
                        }
                    })
                    .catch(e => console.error('Preview load failed', e));
            });

            const clickoutsEl = document.getElementById('preview-clickouts');
            if (clickoutsEl) {
                clickoutsEl.textContent = `${totalClickouts} clickout${totalClickouts === 1 ? '' : 's'} found and set`;
            }

            // previews loaded individually above
        }

    

        document.addEventListener('DOMContentLoaded', function () {
            $.fn.dataTable.ext.errMode = 'none';
            const queueTable = document.querySelector('#queueTable');
            if (queueTable) {
                new DataTable('#queueTable', {
                    pageLength: 5,
                    pagingType: 'simple',
                    order: [[1, 'desc']],
                    searching: false
                });
            }
            new DataTable('#logsTable', {
                responsive: true,
                pageLength: 10,
                pagingType: 'simple',
                order: [[1, 'desc']],
                dom: '<"d-flex justify-content-between align-items-center mb-2"f>tip',
                language: {
                    search: ' ',
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
                    const companies = await fetchJson(`/company/api/companies?owner=${encodeURIComponent(ownerId)}`);

                    companySelect.innerHTML = '<option value="">Select a company</option>';

                    companies.forEach(company => {
                        const option = document.createElement('option');
                        option.value = company.id;
                        option.textContent = company.label;
                        companySelect.appendChild(option);
                    });

                    companySelect.disabled = false;
                } catch (e) {
                    companySelect.innerHTML = `<option value="">${e.message || 'Error loading companies'}</option>`;
                    console.error('Error fetching companies:', e);
                }
                const timezoneInput = document.querySelector('[name$="[timezone]"]');

                try {
                    const data = await fetchJson(`/company/api/timezone?owner=${encodeURIComponent(ownerId)}`);
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
                    const companies = await fetchJson(`/company/api/companies?owner=${encodeURIComponent(ownerId)}`);

                    $companySelect.empty().append(new Option('Select a company', '', true, false));
                    companies.forEach(company => {
                        const option = new Option(company.label, company.id, false, false);
                        $companySelect.append(option);
                    });

                    $companySelect.prop('disabled', false).trigger('change');
                } catch (error) {
                    console.error('Error loading companies:', error);
                    $companySelect.empty().append(new Option(error.message || 'Error loading companies', '', false, false));
                    $companySelect.prop('disabled', false);
                }

                const timezoneInput = document.querySelector('[name$="[timezone]"]');
                try {
                    const data = await fetchJson(`/company/api/timezone?owner=${encodeURIComponent(ownerId)}`);
                    timezoneInput.value = data.timezone || '';
                } catch (e) {
                    console.error('Failed to fetch timezone:', e);
                }

                const localeInput = document.querySelector('[name$="[locale]"]');
                try {
                    const data = await fetchJson(`/api/shopfully/locale?ownerId=${encodeURIComponent(ownerId)}`);
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
                        const response = await fetch(buildUrl(`/api/logs/${logId}/refresh`), {
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
                        const response = await fetch(buildUrl(`/api/logs/${logId}/reimport`), {
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

        function showPdfPreview(container, pdfUrl, clickouts) {
            if (!container) return;
            container.innerHTML = '<canvas></canvas>';
            const canvas = container.querySelector('canvas');
            const ctx = canvas.getContext('2d');
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.9.179/build/pdf.worker.min.js';
            const loadingTask = pdfjsLib.getDocument(pdfUrl);
            loadingTask.promise.then(pdf => {
                let pageNum = 1;
                if (clickouts.length > 0) {
                    const random = Math.floor(Math.random() * clickouts.length);
                    pageNum = parseInt(clickouts[random].pageNumber) || 1;
                }
                pdf.getPage(pageNum).then(page => {
                    const scale = container.clientWidth / page.getViewport({scale: 1}).width;
                    const viewport = page.getViewport({scale});
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    page.render({canvasContext: ctx, viewport}).promise.then(() => {
                        clickouts.filter(c => parseInt(c.pageNumber) === pageNum).forEach(c => {
                            const marker = document.createElement('div');
                            marker.className = 'pdf-preview-marker';
                            const x = parseFloat(c.x) * viewport.width;
                            const y = parseFloat(c.y) * viewport.height;
                            const w = parseFloat(c.width) * viewport.width;
                            const h = parseFloat(c.height) * viewport.height;
                            marker.style.left = `${x}px`;
                            marker.style.top = `${y}px`;
                            marker.style.width = `${w}px`;
                            marker.style.height = `${h}px`;
                            container.appendChild(marker);
                        });
                    });
                });
            }).catch(err => console.error(err));
        }
    
