$(document).ready(function() {
    $.fn.dataTable.ext.errMode = 'none';
    $('#userTable').DataTable({
        pagingType: 'simple',
        responsive: true,
        dom: '<"d-flex justify-content-between align-items-center mb-2"f>tip',
        language: {
            search: ' ',
            searchPlaceholder: 'Search users...'
        }
    });
});
