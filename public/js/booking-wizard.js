(function () {
    function buildUrl(path) {
        if (/^https?:/i.test(path)) {
            return path;
        }

        const needsIndex = window.location.pathname.includes('/index.php/');
        if (needsIndex && !path.startsWith('/index.php')) {
            const prefix = path.startsWith('/') ? '' : '/';
            return `/index.php${prefix}${path}`;
        }

        return path;
    }

    async function fetchJson(url) {
        const response = await fetch(buildUrl(url));
        let data = null;
        try {
            data = await response.json();
        } catch (error) {
            // Ignore JSON parse errors and let the status handling below surface the issue.
        }

        if (!response.ok) {
            const message = data && data.error ? data.error : `HTTP ${response.status}`;
            throw new Error(message);
        }

        return data;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('.booking-wizard-form');
        const steps = Array.from(document.querySelectorAll('.booking-step'));
        const stepperSteps = Array.from(document.querySelectorAll('.booking-stepper-step'));

        if (!form || steps.length === 0 || stepperSteps.length === 0) {
            return;
        }

        const $ownerSelect = $('[name$="[owner]"]');
        const $companySelect = $('[name$="[company]"]');

        if ($ownerSelect.length === 0 || $companySelect.length === 0) {
            return;
        }

        const ownerSelect = $ownerSelect.get(0);
        const companySelect = $companySelect.get(0);

        let currentStep = 0;

        function showStep(step) {
            steps.forEach((element, index) => {
                element.classList.toggle('active', index === step);
            });

            stepperSteps.forEach((element, index) => {
                element.classList.toggle('active', index === step);
                element.classList.toggle('completed', index < step);
            });

            currentStep = step;
        }

        function setStepLabel(stepIndex, text) {
            const stepElement = stepperSteps.find((element) => {
                const value = parseInt(element.dataset.step || '', 10);
                return Number.isInteger(value) && value === stepIndex;
            });

            if (!stepElement) {
                return;
            }

            const label = stepElement.querySelector('.booking-stepper-label');
            if (!label) {
                return;
            }

            if (text) {
                label.textContent = text;
                return;
            }

            label.textContent = stepElement.dataset.defaultLabel || label.textContent;
        }

        function clearSelect2Error($select) {
            const container = $select.next('.select2-container');
            container.find('.select2-selection').removeClass('is-invalid');
            const errorMessage = container.next('.select2-error-message');
            if (errorMessage.length) {
                errorMessage.remove();
            }
        }

        function setSelect2Error($select, message) {
            const container = $select.next('.select2-container');
            container.find('.select2-selection').addClass('is-invalid');
            const existing = container.next('.select2-error-message');
            if (existing.length) {
                existing.text(message);
            } else {
                $('<div>', {
                    class: 'select2-error-message',
                    text: message,
                }).insertAfter(container);
            }
        }

        $ownerSelect.select2({
            placeholder: 'Select an owner',
            allowClear: true,
            width: '100%',
        });

        $companySelect.select2({
            placeholder: 'Select a company',
            allowClear: true,
            width: '100%',
        });

        $companySelect.prop('disabled', true);
        $companySelect.empty().append(new Option('Select an owner first', '', true, true));

        $ownerSelect.on('change', async function () {
            clearSelect2Error($ownerSelect);
            clearSelect2Error($companySelect);

            const selectedText = $(this).find('option:selected').text().trim();
            const ownerId = $(this).val();

            setStepLabel(0, ownerId ? selectedText : null);

            if (!ownerId) {
                $companySelect.prop('disabled', true);
                $companySelect.empty().append(new Option('Select an owner first', '', true, true));
                $companySelect.trigger('change');
                setStepLabel(1, null);
                return;
            }

            $companySelect.prop('disabled', true);
            $companySelect.empty().append(new Option('Loading companies...', '', true, true));

            try {
                const companies = await fetchJson(`/company/api/companies?owner=${encodeURIComponent(ownerId)}`);
                $companySelect.prop('disabled', false);
                $companySelect.empty().append(new Option('Select a company', '', true, false));
                companies.forEach((company) => {
                    $companySelect.append(new Option(company.label, company.id, false, false));
                });
                $companySelect.val(null).trigger('change');
            } catch (error) {
                $companySelect.prop('disabled', false);
                $companySelect.empty().append(new Option(error.message || 'Error loading companies', '', true, true));
            }
        });

        $companySelect.on('change', function () {
            clearSelect2Error($companySelect);
            const selectedText = $(this).find('option:selected').text().trim();
            const value = $(this).val();
            setStepLabel(1, value ? selectedText : null);
        });

        document.querySelectorAll('[data-next-step]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetStep = parseInt(button.getAttribute('data-next-step') || '', 10);

                clearSelect2Error($ownerSelect);

                if (!ownerSelect.value) {
                    const message = ownerSelect.dataset.errorMessage || 'Please select an owner';
                    setSelect2Error($ownerSelect, message);
                    showStep(0);
                    return;
                }

                const step = Number.isNaN(targetStep) ? currentStep + 1 : targetStep;
                showStep(step);
            });
        });

        document.querySelectorAll('[data-prev-step]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetStep = parseInt(button.getAttribute('data-prev-step') || '', 10);
                const step = Number.isNaN(targetStep) ? Math.max(0, currentStep - 1) : targetStep;
                showStep(step);
            });
        });

        form.addEventListener('submit', (event) => {
            let valid = true;

            clearSelect2Error($ownerSelect);
            clearSelect2Error($companySelect);

            if (!ownerSelect.value) {
                setSelect2Error($ownerSelect, ownerSelect.dataset.errorMessage || 'Please select an owner');
                showStep(0);
                valid = false;
            }

            if (!companySelect.value) {
                setSelect2Error($companySelect, companySelect.dataset.errorMessage || 'Please select a company');
                showStep(1);
                valid = false;
            }

            if (!valid) {
                event.preventDefault();
            }
        });

        showStep(0);
    });
})();
