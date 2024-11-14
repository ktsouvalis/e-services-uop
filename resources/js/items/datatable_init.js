import 'datatables.net-responsive-dt/css/responsive.dataTables.css';
import 'datatables.net-responsive-dt';
import 'datatables.net-dt';
import jQuery from 'jquery';
window.$ = window.jQuery = jQuery;

$(function () {

    $('#DataTable thead tr #search').each(function() {
        
        var title = $(this).text();
        
        $(this).html(`
            <div>${title}</div>
            <div class="">
                <input type="text" class="include-search p-1" style="width:100%;font-size:small;" placeholder="+" />
                <input type="text" class="exclude-search p-1 mt-1" style="width:100%;font-size:small;" placeholder="-" />
            </div>
        `);
    });

    // DataTable
    var table = $('#DataTable').DataTable({
        // searching: false,
        "order": [],
        lengthMenu: [10, 25, 50, 100, -1], // Add -1 for "All"
        pageLength: 50, // Set the initial page length
        dom: 'lrtip',
        initComplete: function () {
            // Apply the search
            this.api().columns().every(function () {
                var that = this;
                var includeColumn = $('input.include-search', this.header());
                var excludeColumn = $('input.exclude-search', this.header());

                includeColumn.on('keyup change clear', function () {
                    var includeValue = this.value;
                    var excludeValue = excludeColumn.val();
                    var regex;

                    if (includeValue) {
                        if (excludeValue) {
                            regex = `^(?=.*${includeValue})(?!.*${excludeValue})`;
                        } else {
                            regex = `.*${includeValue}`;
                        }
                    } else {
                        regex = excludeValue ? `^(?!.*${excludeValue}).*` : '';
                    }

                    that.search(regex, true, false).draw();
                }).on('click', function (e) {
                    e.stopPropagation();
                    that.search($(this).val()).draw();
                });

                excludeColumn.on('keyup change clear', function () {
                    var excludeValue = this.value;
                    var includeValue = includeColumn.val();
                    var regex;

                    if (excludeValue) {
                        if (includeValue) {
                            regex = `^(?=.*${includeValue})(?!.*${excludeValue})`;
                        } else {
                            regex = `^(?!.*${excludeValue}).*`;
                        }
                    } else {
                        regex = includeValue ? `.*${includeValue}` : '';
                    }

                    that.search(regex, true, false).draw();
                }).on('click', function (e) {
                    e.stopPropagation();
                    that.search($(this).val()).draw();
                });
            });
        },
    });
});