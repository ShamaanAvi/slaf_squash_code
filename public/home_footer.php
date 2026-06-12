<?php
/**
 * Homepage Specific Footer
 * Handles cleanup and script loading for the root-level index page.
 */

// Safe Database Cleanup
// Since the homepage might just be a landing page, $conn existence is checked before closing.
if (isset($conn) && $conn instanceof mysqli) {
    try {
        mysqli_close($conn);
    } catch (Exception $e) {
        // Silent fail on close to prevent user-facing errors on the landing page
        error_log("DB Close Error on Index: " . $e->getMessage());
    }
}
?>

</div> 

<script 
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" 
    integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" 
    crossorigin="anonymous">
</script>

<script>
    /**
     * Tooltip and Popover Initialization
     * Common for homepages to explain features to new users.
     */
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize all Bootstrap tooltips if any exist
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    });
</script>

</body>
</html>