import 'datatables.net-responsive-dt/css/responsive.dataTables.css';
import 'datatables.net-responsive-dt';



document.addEventListener('DOMContentLoaded', function() {
    var table = $('#DataTable').DataTable({
        responsive: true,
        // searching: false,
        "order": [],
        lengthMenu: [10, 25, 50, 100, -1], // Add -1 for "All"
        pageLength: 50, // Set the initial page length
    });
});