    </div><!-- End container-fluid -->
</div><!-- End content -->
</div><!-- End wrapper -->

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script>
$(document).ready(function() {
    // Sidebar toggle
    $('#sidebarCollapse').on('click', function() {
        $('#sidebar').toggleClass('active');
        $('#content').toggleClass('active');
    });
    
    // Auto close sidebar on small screens when clicking outside
    $(document).on('click', function(event) {
        if ($(window).width() <= 768) {
            if (!$(event.target).closest('#sidebar').length && !$(event.target).closest('#sidebarCollapse').length) {
                $('#sidebar').removeClass('active');
                $('#content').removeClass('active');
            }
        }
    });
    
    // Confirm delete
    $(document).on('click', '.delete-confirm', function(e) {
        if (!confirm('Are you sure you want to delete this record?')) {
            e.preventDefault();
        }
    });
    
    // Auto hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
});
</script>

</body>
</html>