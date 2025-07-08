$(function () {
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

    const $owner = $('#ownerSelect');
    const $showDeleted = $('#showDeleted');

    $.fn.dataTable.ext.errMode = 'none';
    const table = $('#companyTable').DataTable({
        serverSide: true,
        deferLoading: 0,
        searching: true,
        paging: true,
        ordering: true,
        pagingType: 'simple',
        dom: '<"d-flex justify-content-between align-items-center mb-2"f>tip',
        language: {
            search: ' ',
            searchPlaceholder: 'Search companies...'
        },
        ajax: {
            url: buildUrl('/company/api/integrations'),
            type: 'GET',
            data: function (d) {
                d.owner = $owner.val();
                d['exists[deletedAt]'] = $showDeleted.prop('checked') ? 'true' : 'false';
                d.searchIntegrationByTitleAndId = d.search.value;
                d.page = Math.floor(d.start / d.length) + 1;
                d.itemsPerPage = d.length;
                if (d.order && d.order.length) {
                    d['order[title]'] = d.order[0].dir;
                } else {
                    d['order[title]'] = 'asc';
                }
            }
        },
        columns: [
            { data: 'id' },
            { data: 'title' }
        ],
        order: [[1, 'asc']]
    });

    $owner.select2({width: '100%', placeholder: 'Select an owner'});

    $owner.on('change', function () {
        if ($owner.val()) {
            table.ajax.reload();
        } else {
            table.clear().draw();
        }
    });

    $showDeleted.on('change', function () {
        if ($owner.val()) {
            table.ajax.reload();
        }
    });
});
