$(document).ready(function () {
    if ($('#status').val() != 'manuell / auslösergesteuert') {
        $('[id^="trigger"]').hide();
    } else {
        $('[id^="execution"]').hide();
    }

    $('#status').on('change', function () {
        if ($('#status').val() != 'manuell / auslösergesteuert') {
            $('[id^="trigger"]').hide();
            $('[id^="execution"]').show();
        } else {
            $('[id^="trigger"]').show();
            $('[id^="execution"]').hide();
        }
    });

    if ($('#taskType').val() == '0') {
        $('[id^="taskEnd"]').hide();
        $('[id^="weekDays"]').hide();
        $('[id^="ticketCheck"]').hide();
    }
    ;

    $('#taskType').on('change', function () {
        if ($('#taskType').val() == '0') {
            $('[id^="taskEnd"]').hide();
            $('[id^="weekDays"]').hide();
            $('[id^="ticketCheck"]').hide();
        }
        if ($('#taskType').val() == '1') {
            $('[id^="taskEnd"]').show();
            $('[id^="weekDays"]').show();
            $('[id^="ticketCheck"]').show();
        }
    });

    $('#company').on('change', function () {
            window.location.href = '/Task/index/show/company/' + $('#company').val();
    });

    $('#configCompanyId').on('change', function () {
        if ($('#configCompanyId').val() != '0') {
            window.location.href = '/Overview/companydetail/config/company/' + $('#configCompanyId').val();
        }
    });

    $('#detailSubmit').click(function () {
        if (document.location.toString().match('#'))
        {
            $(window).scrollTop();
        }
    });

    $('#companyDetailStart').datepicker({
        dateFormat: "dd.mm.yy",
        firstDay: 1,
        dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai',
            'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        showAnim: 'blind'
    }).val();

    $('#companyDetailEnd').datepicker({
        dateFormat: "dd.mm.yy",
        firstDay: 1,
        dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai',
            'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        showAnim: 'blind'
    }).val();

    $('#overviewDetailStart').datepicker({
        dateFormat: "dd.mm.yy",
        firstDay: 1,
        dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai',
            'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        showAnim: 'blind'
    }).val();

    $('#overviewDetailEnd').datepicker({
        dateFormat: "dd.mm.yy",
        firstDay: 1,
        dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai',
            'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        showAnim: 'blind'
    }).val();

    $('#taskStart').datepicker({
        dateFormat: "dd.mm.yy",
        firstDay: 1,
        minDate: 0,
        dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai',
            'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        showAnim: 'blind'
    }).val();

    $('#taskEnd').datepicker({
        dateFormat: "dd.mm.yy",
        firstDay: 1,
        minDate: 0,
        dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai',
            'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        showAnim: 'blind'
    }).val();

    $('#startCommercial').datepicker({
        dateFormat: "dd.mm.yy",
        firstDay: 1,
        dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai',
            'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        showAnim: 'blind'
    }).val();

    $('#endCommercial').datepicker({
        dateFormat: "dd.mm.yy",
        firstDay: 1,
        dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai',
            'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        showAnim: 'blind'
    }).val();

    $('#companyOverviewEnd').datepicker({
        dateFormat: "dd.mm.yy",
        firstDay: 1,
        dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai',
            'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        showAnim: 'blind'
    }).val();

    $('#companyAdStart').datepicker({
        dateFormat: "dd.mm.yy",
        firstDay: 1,
        dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai',
            'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        showAnim: 'blind'
    }).val();

    $('#companyAdEnd').datepicker({
        dateFormat: "dd.mm.yy",
        firstDay: 1,
        dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai',
            'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        showAnim: 'blind'
    }).val();

$('#assignmentStart').datepicker({
        dateFormat: "dd.mm.yy",
        firstDay: 1,
        dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai',
            'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        showAnim: 'blind',
        maxDate : '0'
    }).val();
    
    $('#assignmentEnd').datepicker({
        dateFormat: "dd.mm.yy",
        firstDay: 1,
        dayNames: ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'],
        dayNamesMin: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        monthNames: ['Januar', 'Februar', 'März', 'April', 'Mai',
            'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
        showAnim: 'blind',
        maxDate : '0'
    }).val();
    
    $('#days').on('change', function () {
        document.forms["crawlerInstableForm"].submit();
    });
});