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

        const brochuresSubtitle = document.getElementById('brochure-results-subtitle');
        const brochuresLoading = document.getElementById('brochure-results-loading');
        const brochuresError = document.getElementById('brochure-results-error');
        const brochuresEmpty = document.getElementById('brochure-results-empty');
        const brochuresTableWrapper = document.getElementById('brochure-results-table-wrapper');
        const brochuresTableBody = document.querySelector('#brochure-results-table tbody');
        const brochuresSearchInput = document.getElementById('brochure-results-search');
        const brochuresPageSizeSelect = document.getElementById('brochure-results-page-size');
        const brochuresPagination = document.getElementById('brochure-results-pagination');
        const brochuresResultsCount = document.getElementById('brochure-results-count');
        const brochuresSelectionSummary = document.getElementById('brochure-selection-count');
        const bookingsSubtitle = document.getElementById('booking-results-subtitle');
        const bookingsLoading = document.getElementById('booking-results-loading');
        const bookingsError = document.getElementById('booking-results-error');
        const bookingsEmpty = document.getElementById('booking-results-empty');
        const bookingsTableWrapper = document.getElementById('booking-results-table-wrapper');
        const bookingsTableBody = document.querySelector('#booking-results-table tbody');
        const bookingsSearchInput = document.getElementById('booking-results-search');
        const bookingsPageSizeSelect = document.getElementById('booking-results-page-size');
        const bookingsPagination = document.getElementById('booking-results-pagination');
        const bookingsResultsCount = document.getElementById('booking-results-count');
        const finishButton = document.querySelector('#booking-step-3 button[type="submit"]');

        let brochuresTableManager = null;
        let bookingsTableManager = null;

        let brochuresAbortController = null;
        let lastLoadedBrochuresCompanyId = null;
        let lastLoadedBrochuresOwnerId = null;
        let lastLoadedBrochures = null;

        const selectedBrochureIds = new Set();

        updateBrochureSelectionSummary();

        let bookingsAbortController = null;
        let lastLoadedBookingsCompanyId = null;
        let lastLoadedBookingsOwnerId = null;
        let lastLoadedBookings = null;

        let currentStep = 0;

        brochuresTableManager = createTableManager({
            wrapper: brochuresTableWrapper,
            tableBody: brochuresTableBody,
            searchInput: brochuresSearchInput,
            pageSizeSelect: brochuresPageSizeSelect,
            paginationContainer: brochuresPagination,
            resultsCount: brochuresResultsCount,
            emptyMessage: 'No brochures to display.',
            getSearchText: (brochure) => {
                if (!brochure || typeof brochure !== 'object') {
                    return '';
                }

                const values = [
                    brochure.id,
                    brochure.brochureNumber,
                    brochure.title,
                    brochure.type,
                    brochure.variety,
                    brochure.languageCode,
                    brochure.validFrom,
                    brochure.validTo,
                    brochure.visibleFrom,
                ];

                return values
                    .filter((value) => value !== undefined && value !== null)
                    .map((value) => String(value))
                    .join(' ');
            },
            createRow: createBrochureRow,
        });

        bookingsTableManager = createTableManager({
            wrapper: bookingsTableWrapper,
            tableBody: bookingsTableBody,
            searchInput: bookingsSearchInput,
            pageSizeSelect: bookingsPageSizeSelect,
            paginationContainer: bookingsPagination,
            resultsCount: bookingsResultsCount,
            emptyMessage: 'No CPC bookings to display.',
            getSearchText: (booking) => {
                if (!booking || typeof booking !== 'object') {
                    return '';
                }

                const values = [
                    booking.id,
                    booking.title,
                    booking.target,
                    booking.budgetType,
                    booking.currency,
                    booking.costCenter,
                    booking.activeFrom,
                    booking.activeTo,
                ];

                return values
                    .filter((value) => value !== undefined && value !== null)
                    .map((value) => String(value))
                    .join(' ');
            },
            createRow: createBookingRow,
        });

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

        function hideBrochuresMessages() {
            if (brochuresLoading) {
                brochuresLoading.classList.add('d-none');
            }

            if (brochuresError) {
                brochuresError.classList.add('d-none');
            }

            if (brochuresEmpty) {
                brochuresEmpty.classList.add('d-none');
            }
        }

        function updateBrochuresSubtitle(companyLabel) {
            if (brochuresSubtitle) {
                brochuresSubtitle.textContent = companyLabel ? `Brochures for ${companyLabel}` : '';
            }
        }

        function updateBrochureSelectionSummary() {
            if (!brochuresSelectionSummary) {
                return;
            }

            const count = selectedBrochureIds.size;

            if (count === 0) {
                brochuresSelectionSummary.textContent = 'No brochures selected';
                return;
            }

            brochuresSelectionSummary.textContent = count === 1
                ? '1 brochure selected'
                : `${count} brochures selected`;
        }

        function getBrochureIdentifier(brochure) {
            if (!brochure || typeof brochure !== 'object') {
                return null;
            }

            const { id } = brochure;

            if (id === null || id === undefined) {
                return null;
            }

            const normalized = String(id).trim();
            return normalized === '' ? null : normalized;
        }

        function pruneSelectedBrochureIds(brochures) {
            if (selectedBrochureIds.size === 0) {
                return;
            }

            if (!Array.isArray(brochures) || brochures.length === 0) {
                selectedBrochureIds.clear();
                return;
            }

            const validIds = new Set();
            brochures.forEach((brochure) => {
                const identifier = getBrochureIdentifier(brochure);
                if (identifier) {
                    validIds.add(identifier);
                }
            });

            Array.from(selectedBrochureIds).forEach((identifier) => {
                if (!validIds.has(identifier)) {
                    selectedBrochureIds.delete(identifier);
                }
            });
        }

        function resetBrochuresState(options = {}) {
            const { resetLabel = false } = options;

            selectedBrochureIds.clear();
            updateBrochureSelectionSummary();

            if (brochuresAbortController) {
                brochuresAbortController.abort();
                brochuresAbortController = null;
            }

            hideBrochuresMessages();
            updateBrochuresSubtitle('');

            if (brochuresTableManager) {
                brochuresTableManager.reset();
            } else if (brochuresTableWrapper) {
                brochuresTableWrapper.classList.add('d-none');
            }

            if (resetLabel) {
                setStepLabel(2, null);
                lastLoadedBrochuresCompanyId = null;
                lastLoadedBrochuresOwnerId = null;
                lastLoadedBrochures = null;
            }
        }

        function showBrochuresLoading(companyLabel) {
            hideBrochuresMessages();
            updateBrochuresSubtitle(companyLabel);

            if (brochuresTableManager) {
                brochuresTableManager.reset();
            } else if (brochuresTableWrapper) {
                brochuresTableWrapper.classList.add('d-none');
            }

            if (brochuresLoading) {
                brochuresLoading.classList.remove('d-none');
            }
        }

        function showBrochuresError(message, companyLabel) {
            hideBrochuresMessages();
            updateBrochuresSubtitle(companyLabel);

            if (brochuresTableManager) {
                brochuresTableManager.reset();
            } else if (brochuresTableWrapper) {
                brochuresTableWrapper.classList.add('d-none');
            }

            if (brochuresError) {
                brochuresError.textContent = message || 'Unable to load brochures.';
                brochuresError.classList.remove('d-none');
            }
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

            if (bookingsTableManager) {
                bookingsTableManager.reset();
            } else if (bookingsTableWrapper) {
                bookingsTableWrapper.classList.add('d-none');
            }

            if (finishButton) {
                finishButton.disabled = false;
            }

            if (resetLabel) {
                setStepLabel(3, null);
                lastLoadedBookingsCompanyId = null;
                lastLoadedBookingsOwnerId = null;
                lastLoadedBookings = null;
            }
        }

        function showBookingsLoading(companyLabel) {
            hideBookingsMessages();
            updateBookingsSubtitle(companyLabel);

            if (bookingsTableManager) {
                bookingsTableManager.reset();
            } else if (bookingsTableWrapper) {
                bookingsTableWrapper.classList.add('d-none');
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

            if (bookingsTableManager) {
                bookingsTableManager.reset();
            } else if (bookingsTableWrapper) {
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

        function appendCell(row, value, options = {}) {
            if (!row) {
                return null;
            }

            const cell = document.createElement('td');
            cell.textContent = asDisplayValue(value);

            if (options && typeof options === 'object' && options.className) {
                cell.className = options.className;
            }

            row.appendChild(cell);
            return cell;
        }

        function parsePositiveInteger(value, fallback) {
            const parsed = Number.parseInt(value, 10);
            if (Number.isNaN(parsed) || parsed <= 0) {
                return fallback;
            }

            return parsed;
        }

        function createTableManager(options) {
            const {
                wrapper,
                tableBody,
                searchInput,
                pageSizeSelect,
                paginationContainer,
                resultsCount,
                getSearchText,
                createRow,
                emptyMessage = 'No results to display.',
            } = options || {};

            if (!tableBody) {
                return {
                    reset() {},
                    setData() {},
                    refresh() {},
                };
            }

            const computeSearchText = typeof getSearchText === 'function'
                ? getSearchText
                : (item) => (item ? String(item) : '');

            const buildRow = typeof createRow === 'function'
                ? createRow
                : () => document.createElement('tr');

            let rows = [];
            let filteredRows = [];
            let currentPage = 1;
            let pageSize = parsePositiveInteger(pageSizeSelect ? pageSizeSelect.value : '', 10);

            function updateVisibility() {
                if (!wrapper) {
                    return;
                }

                const hasData = rows.length > 0;
                wrapper.classList.toggle('d-none', !hasData);
            }

            function getColumnCount() {
                const table = tableBody.closest('table');
                if (!table) {
                    return 1;
                }

                const headerCells = table.querySelectorAll('thead th');
                if (headerCells.length > 0) {
                    return headerCells.length;
                }

                const bodyCells = table.querySelectorAll('tbody tr:first-child td');
                if (bodyCells.length > 0) {
                    return bodyCells.length;
                }

                return 1;
            }

            function renderEmptyRow(message) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = getColumnCount();
                cell.className = 'table-card-empty';
                cell.textContent = message || emptyMessage;
                row.appendChild(cell);
                tableBody.appendChild(row);
            }

            function updateResultsLabel(total) {
                if (!resultsCount) {
                    return;
                }

                if (total === 0) {
                    if (rows.length > 0) {
                        resultsCount.textContent = 'No results match your search.';
                    } else {
                        resultsCount.textContent = 'No results to display.';
                    }
                    return;
                }

                const startIndex = (currentPage - 1) * pageSize + 1;
                const endIndex = Math.min(total, startIndex + pageSize - 1);
                const label = total === 1
                    ? 'Showing 1 of 1 result'
                    : `Showing ${startIndex}–${endIndex} of ${total} results`;
                resultsCount.textContent = label;
            }

            function computeFilteredRows() {
                const term = searchInput ? searchInput.value.trim().toLowerCase() : '';

                if (!term) {
                    filteredRows = rows;
                    return;
                }

                filteredRows = rows.filter((entry) => entry.search.includes(term));
            }

            function renderPagination(totalPages) {
                if (!paginationContainer) {
                    return;
                }

                paginationContainer.innerHTML = '';

                if (totalPages <= 1) {
                    return;
                }

                const createPageButton = (label, targetPage, options = {}) => {
                    const { disabled = false, active = false, ariaLabel = null } = options;

                    const listItem = document.createElement('li');
                    listItem.className = 'page-item';

                    if (disabled) {
                        listItem.classList.add('disabled');
                    }

                    if (active) {
                        listItem.classList.add('active');
                    }

                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'page-link';
                    button.textContent = label;

                    if (ariaLabel) {
                        button.setAttribute('aria-label', ariaLabel);
                    }

                    if (!disabled) {
                        button.addEventListener('click', () => {
                            if (targetPage === currentPage) {
                                return;
                            }

                            currentPage = Math.min(Math.max(targetPage, 1), totalPages);
                            update();
                        });
                    }

                    listItem.appendChild(button);
                    paginationContainer.appendChild(listItem);
                };

                const addEllipsis = () => {
                    const listItem = document.createElement('li');
                    listItem.className = 'page-item disabled';
                    const span = document.createElement('span');
                    span.className = 'page-link';
                    span.textContent = '…';
                    listItem.appendChild(span);
                    paginationContainer.appendChild(listItem);
                };

                createPageButton('‹', currentPage - 1, {
                    disabled: currentPage === 1,
                    ariaLabel: 'Previous page',
                });

                const maxDisplayed = 5;
                let start = Math.max(1, currentPage - 2);
                let end = Math.min(totalPages, currentPage + 2);

                if (end - start + 1 < maxDisplayed) {
                    if (start === 1) {
                        end = Math.min(totalPages, start + maxDisplayed - 1);
                    } else if (end === totalPages) {
                        start = Math.max(1, end - maxDisplayed + 1);
                    }
                }

                if (start > 1) {
                    createPageButton('1', 1, { active: currentPage === 1 });
                    if (start > 2) {
                        addEllipsis();
                    }
                }

                for (let page = start; page <= end; page += 1) {
                    createPageButton(String(page), page, { active: page === currentPage });
                }

                if (end < totalPages) {
                    if (end < totalPages - 1) {
                        addEllipsis();
                    }

                    createPageButton(String(totalPages), totalPages, { active: currentPage === totalPages });
                }

                createPageButton('›', currentPage + 1, {
                    disabled: currentPage === totalPages,
                    ariaLabel: 'Next page',
                });
            }

            function renderRows() {
                tableBody.innerHTML = '';

                if (filteredRows.length === 0) {
                    if (rows.length === 0) {
                        renderEmptyRow(emptyMessage);
                    } else {
                        renderEmptyRow('No results match your search.');
                    }

                    return;
                }

                const startIndex = (currentPage - 1) * pageSize;
                const pageItems = filteredRows.slice(startIndex, startIndex + pageSize);

                pageItems.forEach((entry) => {
                    const row = buildRow(entry.item);
                    tableBody.appendChild(row);
                });
            }

            function update() {
                computeFilteredRows();

                const total = filteredRows.length;

                if (total === 0) {
                    currentPage = 1;
                } else {
                    const totalPages = Math.ceil(total / pageSize);
                    if (currentPage > totalPages) {
                        currentPage = totalPages;
                    }
                }

                renderRows();
                updateResultsLabel(total);
                const totalPages = total === 0 ? 1 : Math.ceil(total / pageSize);
                renderPagination(totalPages);
                updateVisibility();
            }

            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    currentPage = 1;
                    update();
                });
            }

            if (pageSizeSelect) {
                pageSizeSelect.addEventListener('change', () => {
                    pageSize = parsePositiveInteger(pageSizeSelect.value, pageSize);
                    currentPage = 1;
                    update();
                });
            }

            return {
                reset() {
                    rows = [];
                    filteredRows = [];
                    currentPage = 1;
                    pageSize = parsePositiveInteger(pageSizeSelect ? pageSizeSelect.value : '', pageSize);

                    if (searchInput) {
                        searchInput.value = '';
                    }

                    tableBody.innerHTML = '';

                    if (paginationContainer) {
                        paginationContainer.innerHTML = '';
                    }

                    if (resultsCount) {
                        resultsCount.textContent = '';
                    }

                    if (wrapper) {
                        wrapper.classList.add('d-none');
                    }
                },
                setData(data) {
                    const items = Array.isArray(data) ? data : [];

                    rows = items.map((item) => ({
                        item,
                        search: computeSearchText(item).toLowerCase(),
                    }));
                    filteredRows = rows;
                    currentPage = 1;

                    if (pageSizeSelect) {
                        pageSize = parsePositiveInteger(pageSizeSelect.value, pageSize);
                    }

                    update();
                },
                refresh() {
                    update();
                },
            };
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

        function formatLanguageCode(code) {
            if (typeof code !== 'string') {
                return '—';
            }

            const trimmed = code.trim();
            return trimmed === '' ? '—' : trimmed.toUpperCase();
        }

        function createBrochureRow(brochure) {
            const row = document.createElement('tr');

            const brochureId = getBrochureIdentifier(brochure);
            if (brochureId) {
                row.dataset.brochureId = brochureId;
            }

            const selectionCell = document.createElement('td');
            selectionCell.className = 'table-selection-cell text-center';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input table-selection-checkbox';
            checkbox.disabled = !brochureId;

            const labelParts = [];
            if (brochure && typeof brochure.title === 'string' && brochure.title.trim() !== '') {
                labelParts.push(brochure.title.trim());
            }
            if (brochure && brochure.brochureNumber) {
                labelParts.push(`#${brochure.brochureNumber}`);
            }

            let accessibleLabel = 'Select brochure';
            if (labelParts.length > 0) {
                accessibleLabel = `Select ${labelParts.join(' – ')}`;
            } else if (brochureId) {
                accessibleLabel = `Select brochure ${brochureId}`;
            }

            checkbox.setAttribute('aria-label', accessibleLabel);
            checkbox.title = accessibleLabel;

            if (brochureId) {
                checkbox.value = brochureId;
                const isSelected = selectedBrochureIds.has(brochureId);
                checkbox.checked = isSelected;

                if (isSelected) {
                    row.classList.add('table-active');
                }

                checkbox.addEventListener('change', () => {
                    if (checkbox.checked) {
                        selectedBrochureIds.add(brochureId);
                        row.classList.add('table-active');
                    } else {
                        selectedBrochureIds.delete(brochureId);
                        row.classList.remove('table-active');
                    }

                    updateBrochureSelectionSummary();
                });
            } else {
                checkbox.checked = false;
                checkbox.addEventListener('change', () => {
                    checkbox.checked = false;
                });
            }

            selectionCell.appendChild(checkbox);
            row.appendChild(selectionCell);

            appendCell(row, brochure && brochure.id !== undefined ? brochure.id : '—', {
                className: 'fw-semibold text-secondary',
            });
            appendCell(row, brochure && brochure.brochureNumber ? brochure.brochureNumber : '—', {
                className: 'text-uppercase text-muted',
            });
            appendCell(row, brochure && brochure.title ? brochure.title : '—', {
                className: 'fw-semibold text-dark',
            });
            appendCell(row, brochure && brochure.type ? brochure.type : '—');
            appendCell(row, formatDateRange(brochure ? brochure.validFrom : '', brochure ? brochure.validTo : ''));
            const visibleFrom = formatDateTime(brochure ? brochure.visibleFrom : '');
            appendCell(row, visibleFrom || '—');
            appendCell(row, brochure && brochure.variety ? brochure.variety : '—');
            appendCell(row, formatLanguageCode(brochure ? brochure.languageCode : null));

            return row;
        }

        function createBookingRow(booking) {
            const row = document.createElement('tr');

            appendCell(row, booking && booking.id !== undefined ? booking.id : '—', {
                className: 'fw-semibold text-secondary',
            });
            appendCell(row, booking && booking.title ? booking.title : '—', {
                className: 'fw-semibold text-dark',
            });
            appendCell(row, getBookingType(booking));
            appendCell(row, formatDateRange(booking ? booking.activeFrom : '', booking ? booking.activeTo : ''));
            appendCell(row, formatBudgetValue(booking));

            return row;
        }

        function renderBrochures(brochures, companyLabel) {
            hideBrochuresMessages();
            updateBrochuresSubtitle(companyLabel);

            if (!brochuresTableWrapper) {
                return;
            }

            const items = Array.isArray(brochures) ? brochures : [];

            pruneSelectedBrochureIds(items);
            updateBrochureSelectionSummary();

            if (items.length === 0) {
                if (brochuresTableManager) {
                    brochuresTableManager.reset();
                }
                if (brochuresEmpty) {
                    brochuresEmpty.classList.remove('d-none');
                }
                return;
            }

            if (brochuresTableManager) {
                brochuresTableManager.setData(items);
            }

            brochuresTableWrapper.classList.remove('d-none');
        }

        function loadBrochures(companyId, companyLabel, ownerId) {
            const normalizedCompanyId = typeof companyId === 'string' ? companyId.trim() : String(companyId);
            if (!normalizedCompanyId) {
                return;
            }

            const normalizedOwnerId = typeof ownerId === 'string' ? ownerId.trim() : ownerId;
            const ownerKey = normalizedOwnerId && normalizedOwnerId !== '' ? normalizedOwnerId : null;

            if (brochuresAbortController) {
                brochuresAbortController.abort();
                brochuresAbortController = null;
            }

            const stepLabel = companyLabel ? `Brochures (${companyLabel})` : null;

            if (
                lastLoadedBrochuresCompanyId === normalizedCompanyId &&
                lastLoadedBrochuresOwnerId === ownerKey &&
                Array.isArray(lastLoadedBrochures)
            ) {
                setStepLabel(2, stepLabel);
                renderBrochures(lastLoadedBrochures, companyLabel);
                return;
            }

            const controller = new AbortController();
            brochuresAbortController = controller;

            lastLoadedBrochuresCompanyId = normalizedCompanyId;
            lastLoadedBrochuresOwnerId = ownerKey;
            lastLoadedBrochures = null;

            setStepLabel(2, stepLabel);
            showBrochuresLoading(companyLabel);

            const params = new URLSearchParams({ companyId: normalizedCompanyId });
            if (ownerKey) {
                params.append('ownerId', ownerKey);
            }

            fetchJson(`/booking-wizard/api/brochures?${params.toString()}`, {
                signal: controller.signal,
            })
                .then((brochures) => {
                    if (brochuresAbortController !== controller) {
                        return;
                    }

                    lastLoadedBrochures = Array.isArray(brochures) ? brochures : [];
                    renderBrochures(lastLoadedBrochures, companyLabel);
                })
                .catch((error) => {
                    if (error && error.name === 'AbortError') {
                        return;
                    }

                    if (brochuresAbortController !== controller) {
                        return;
                    }

                    lastLoadedBrochuresCompanyId = null;
                    lastLoadedBrochuresOwnerId = null;
                    lastLoadedBrochures = null;
                    showBrochuresError(error && error.message ? error.message : 'Unable to load brochures.', companyLabel);
                })
                .finally(() => {
                    if (brochuresAbortController === controller) {
                        brochuresAbortController = null;
                    }
                });
        }

        function renderBookings(bookings, companyLabel) {
            hideBookingsMessages();
            updateBookingsSubtitle(companyLabel);

            if (!bookingsTableWrapper) {
                return;
            }

            const items = Array.isArray(bookings) ? bookings : [];

            if (items.length === 0) {
                if (bookingsTableManager) {
                    bookingsTableManager.reset();
                } else {
                    bookingsTableWrapper.classList.add('d-none');
                }
                if (bookingsEmpty) {
                    bookingsEmpty.classList.remove('d-none');
                }

                if (finishButton) {
                    finishButton.disabled = false;
                }

                return;
            }

            if (bookingsTableManager) {
                bookingsTableManager.setData(items);
            }

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
                lastLoadedBookingsCompanyId === normalizedCompanyId &&
                lastLoadedBookingsOwnerId === ownerKey &&
                Array.isArray(lastLoadedBookings)
            ) {
                setStepLabel(3, stepLabel);
                renderBookings(lastLoadedBookings, companyLabel);
                return;
            }

            const controller = new AbortController();
            bookingsAbortController = controller;

            lastLoadedBookingsCompanyId = normalizedCompanyId;
            lastLoadedBookingsOwnerId = ownerKey;
            lastLoadedBookings = null;

            setStepLabel(3, stepLabel);
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

                    lastLoadedBookingsCompanyId = null;
                    lastLoadedBookingsOwnerId = null;
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
            resetBrochuresState({ resetLabel: true });
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
            resetBrochuresState({ resetLabel: true });
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

                    if (step === 2) {
                        loadBrochures(companySelect.value, selectedText, ownerSelect.value);
                    } else if (step >= 3) {
                        loadBookings(companySelect.value, selectedText, ownerSelect.value);
                    }

                    return;
                }

                showStep(step);
            });
        });

        document.querySelectorAll('[data-prev-step]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetStep = parseInt(button.getAttribute('data-prev-step') || '', 10);
                const step = Number.isNaN(targetStep) ? Math.max(0, currentStep - 1) : targetStep;

                if (currentStep === 3 && step < 3 && bookingsAbortController) {
                    bookingsAbortController.abort();
                    bookingsAbortController = null;
                    if (finishButton) {
                        finishButton.disabled = false;
                    }
                }

                if (currentStep === 2 && step < 2 && brochuresAbortController) {
                    brochuresAbortController.abort();
                    brochuresAbortController = null;
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
