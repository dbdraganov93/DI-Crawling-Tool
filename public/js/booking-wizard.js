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

    async function fetchJson(url, options = {}) {
        const response = await fetch(buildUrl(url), options);
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

        const bookingsSubtitle = document.getElementById('booking-results-subtitle');
        const bookingsLoading = document.getElementById('booking-results-loading');
        const bookingsError = document.getElementById('booking-results-error');
        const bookingsEmpty = document.getElementById('booking-results-empty');
        const bookingsTableWrapper = document.getElementById('booking-results-table-wrapper');
        const bookingsTableBody = document.querySelector('#booking-results-table tbody');
        const finishButton = document.querySelector('#booking-step-2 button[type="submit"]');

        let bookingsAbortController = null;
        let lastLoadedCompanyId = null;
        let lastLoadedOwnerId = null;
        let lastLoadedBookings = null;

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

        function hideBookingsMessages() {
            if (bookingsLoading) {
                bookingsLoading.classList.add('d-none');
            }

            if (bookingsError) {
                bookingsError.classList.add('d-none');
            }

            if (bookingsEmpty) {
                bookingsEmpty.classList.add('d-none');
            }
        }

        function updateBookingsSubtitle(companyLabel) {
            if (bookingsSubtitle) {
                bookingsSubtitle.textContent = companyLabel ? `CPC bookings for ${companyLabel}` : '';
            }
        }

        function resetBookingsState(options = {}) {
            const { resetLabel = false } = options;

            if (bookingsAbortController) {
                bookingsAbortController.abort();
                bookingsAbortController = null;
            }

            hideBookingsMessages();
            updateBookingsSubtitle('');

            if (bookingsTableWrapper) {
                bookingsTableWrapper.classList.add('d-none');
            }

            if (bookingsTableBody) {
                bookingsTableBody.innerHTML = '';
            }

            if (finishButton) {
                finishButton.disabled = false;
            }

            if (resetLabel) {
                setStepLabel(2, null);
                lastLoadedCompanyId = null;
                lastLoadedOwnerId = null;
                lastLoadedBookings = null;
            }
        }

        function showBookingsLoading(companyLabel) {
            hideBookingsMessages();
            updateBookingsSubtitle(companyLabel);

            if (bookingsTableWrapper) {
                bookingsTableWrapper.classList.add('d-none');
            }

            if (bookingsTableBody) {
                bookingsTableBody.innerHTML = '';
            }

            if (bookingsLoading) {
                bookingsLoading.classList.remove('d-none');
            }

            if (finishButton) {
                finishButton.disabled = true;
            }
        }

        function showBookingsError(message, companyLabel) {
            hideBookingsMessages();
            updateBookingsSubtitle(companyLabel);

            if (bookingsTableWrapper) {
                bookingsTableWrapper.classList.add('d-none');
            }

            if (bookingsError) {
                bookingsError.textContent = message || 'Unable to load CPC bookings.';
                bookingsError.classList.remove('d-none');
            }

            if (finishButton) {
                finishButton.disabled = false;
            }
        }

        function asDisplayValue(value, fallback = '—') {
            if (value === null || value === undefined) {
                return fallback;
            }

            const stringValue = String(value);
            return stringValue.trim() === '' ? fallback : stringValue;
        }

        function appendCell(row, value) {
            if (!row) {
                return;
            }

            const cell = document.createElement('td');
            cell.textContent = asDisplayValue(value);
            row.appendChild(cell);
        }

        function formatDateTime(value) {
            if (!value) {
                return '';
            }

            const date = new Date(value);
            if (Number.isNaN(date.getTime())) {
                return '';
            }

            try {
                return new Intl.DateTimeFormat(undefined, {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                }).format(date);
            } catch (error) {
                return date.toISOString();
            }
        }

        function formatDateRange(start, end) {
            const startText = formatDateTime(start);
            const endText = formatDateTime(end);

            if (startText && endText) {
                return `${startText} → ${endText}`;
            }

            return startText || endText || '—';
        }

        function formatCurrencyValue(amount, currency) {
            if (!Number.isFinite(amount)) {
                return '—';
            }

            const normalizedCurrency = currency && typeof currency === 'string' && currency.trim()
                ? currency.trim()
                : 'EUR';

            try {
                return new Intl.NumberFormat(undefined, {
                    style: 'currency',
                    currency: normalizedCurrency,
                }).format(amount);
            } catch (error) {
                return `${amount.toFixed(2)} ${normalizedCurrency}`;
            }
        }

        function sumBudgets(budgets) {
            if (!Array.isArray(budgets)) {
                return 0;
            }

            return budgets.reduce((total, entry) => {
                if (!entry || typeof entry !== 'object') {
                    return total;
                }

                const rawBudget = entry.budget;
                let numericValue = 0;

                if (typeof rawBudget === 'number') {
                    numericValue = rawBudget;
                } else if (typeof rawBudget === 'string') {
                    const parsed = Number.parseFloat(rawBudget);
                    numericValue = Number.isNaN(parsed) ? 0 : parsed;
                }

                return total + numericValue;
            }, 0);
        }

        function getBookingType(booking) {
            if (!booking || typeof booking !== 'object') {
                return '—';
            }

            const target = typeof booking.target === 'string' ? booking.target.trim() : '';
            if (target) {
                return target;
            }

            const budgetType = typeof booking.budgetType === 'string' ? booking.budgetType.trim() : '';
            if (budgetType) {
                return budgetType;
            }

            return '—';
        }

        function formatBudgetValue(booking) {
            if (!booking || typeof booking !== 'object') {
                return '—';
            }

            const total = sumBudgets(booking.budgets);
            const currency = typeof booking.currency === 'string' ? booking.currency : 'EUR';

            return formatCurrencyValue(total, currency);
        }

        function renderBookings(bookings, companyLabel) {
            hideBookingsMessages();
            updateBookingsSubtitle(companyLabel);

            if (!bookingsTableWrapper || !bookingsTableBody) {
                return;
            }

            bookingsTableBody.innerHTML = '';

            const items = Array.isArray(bookings) ? bookings : [];

            if (items.length === 0) {
                bookingsTableWrapper.classList.add('d-none');
                if (bookingsEmpty) {
                    bookingsEmpty.classList.remove('d-none');
                }

                if (finishButton) {
                    finishButton.disabled = false;
                }

                return;
            }

            items.forEach((booking) => {
                const row = document.createElement('tr');

                appendCell(row, booking && booking.id !== undefined ? booking.id : '—');
                appendCell(row, booking && booking.title ? booking.title : '—');
                appendCell(row, getBookingType(booking));
                appendCell(row, formatDateRange(booking ? booking.activeFrom : '', booking ? booking.activeTo : ''));
                appendCell(row, formatBudgetValue(booking));

                bookingsTableBody.appendChild(row);
            });

            bookingsTableWrapper.classList.remove('d-none');

            if (finishButton) {
                finishButton.disabled = false;
            }
        }

        function loadBookings(companyId, companyLabel, ownerId) {
            const normalizedCompanyId = typeof companyId === 'string' ? companyId.trim() : String(companyId);
            if (!normalizedCompanyId) {
                return;
            }

            const normalizedOwnerId = typeof ownerId === 'string' ? ownerId.trim() : ownerId;
            const ownerKey = normalizedOwnerId && normalizedOwnerId !== '' ? normalizedOwnerId : null;

            if (bookingsAbortController) {
                bookingsAbortController.abort();
                bookingsAbortController = null;
            }

            const stepLabel = companyLabel ? `Bookings (${companyLabel})` : null;

            if (
                lastLoadedCompanyId === normalizedCompanyId &&
                lastLoadedOwnerId === ownerKey &&
                Array.isArray(lastLoadedBookings)
            ) {
                setStepLabel(2, stepLabel);
                renderBookings(lastLoadedBookings, companyLabel);
                return;
            }

            const controller = new AbortController();
            bookingsAbortController = controller;

            lastLoadedCompanyId = normalizedCompanyId;
            lastLoadedOwnerId = ownerKey;
            lastLoadedBookings = null;

            setStepLabel(2, stepLabel);
            showBookingsLoading(companyLabel);

            const params = new URLSearchParams({ companyId: normalizedCompanyId });
            if (ownerKey) {
                params.append('ownerId', ownerKey);
            }

            fetchJson(`/booking-wizard/api/bookings?${params.toString()}`, {
                signal: controller.signal,
            })
                .then((bookings) => {
                    if (bookingsAbortController !== controller) {
                        return;
                    }

                    lastLoadedBookings = Array.isArray(bookings) ? bookings : [];
                    renderBookings(lastLoadedBookings, companyLabel);
                })
                .catch((error) => {
                    if (error && error.name === 'AbortError') {
                        return;
                    }

                    if (bookingsAbortController !== controller) {
                        return;
                    }

                    lastLoadedCompanyId = null;
                    lastLoadedOwnerId = null;
                    lastLoadedBookings = null;
                    showBookingsError(error && error.message ? error.message : 'Unable to load CPC bookings.', companyLabel);
                })
                .finally(() => {
                    if (bookingsAbortController === controller) {
                        bookingsAbortController = null;
                    }
                });
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
            setStepLabel(1, null);
            resetBookingsState({ resetLabel: true });

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
            resetBookingsState({ resetLabel: true });
            setStepLabel(1, value ? selectedText : null);
        });

        document.querySelectorAll('[data-next-step]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetStep = parseInt(button.getAttribute('data-next-step') || '', 10);

                clearSelect2Error($ownerSelect);
                clearSelect2Error($companySelect);

                if (!ownerSelect.value) {
                    const message = ownerSelect.dataset.errorMessage || 'Please select an owner';
                    setSelect2Error($ownerSelect, message);
                    showStep(0);
                    return;
                }

                if (currentStep === 0) {
                    const step = Number.isNaN(targetStep) ? currentStep + 1 : targetStep;
                    showStep(step);
                    return;
                }

                if (!companySelect.value) {
                    const message = companySelect.dataset.errorMessage || 'Please select a company';
                    setSelect2Error($companySelect, message);
                    showStep(1);
                    return;
                }

                const step = Number.isNaN(targetStep) ? currentStep + 1 : targetStep;

                if (step >= 2) {
                    const selectedText = $companySelect.find('option:selected').text().trim();
                    showStep(step);
                    loadBookings(companySelect.value, selectedText, ownerSelect.value);
                    return;
                }

                showStep(step);
            });
        });

        document.querySelectorAll('[data-prev-step]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetStep = parseInt(button.getAttribute('data-prev-step') || '', 10);
                const step = Number.isNaN(targetStep) ? Math.max(0, currentStep - 1) : targetStep;

                if (currentStep === 2 && step < 2 && bookingsAbortController) {
                    bookingsAbortController.abort();
                    bookingsAbortController = null;
                    if (finishButton) {
                        finishButton.disabled = false;
                    }
                }

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
