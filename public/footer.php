<?php
/**
 * Global Footer
 * This file closes the main container opened in header.php, 
 * loads essential scripts, and handles cleanup.
 */

// Database Cleanup
// Closes the connection once the page is fully rendered as good practice.
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
?>

</div> <script 
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
    integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" 
    crossorigin="anonymous">
</script>

<script>
    // Auto-dismiss alerts after 5 seconds to keep the UI clean
    document.addEventListener('DOMContentLoaded', function() {
        var alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
</script>

</body>
</html>