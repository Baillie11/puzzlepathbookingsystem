jQuery(document).ready(function($) {
    $('#pp-apply-coupon').on('click', function(e) {
        e.preventDefault();
        var coupon = $('#pp-coupon').val();
        var feedback = $('#pp-coupon-feedback');
        
        // Clear previous feedback
        feedback.text('Checking...').css('color', '#666');
        
        // Validate input
        if (!coupon || coupon.trim() === '') {
            feedback.css('color', '#d2691e').text('Please enter a coupon code.');
            return;
        }

        // Make AJAX request
        $.ajax({
            url: puzzlepathBooking.ajax_url,
            type: 'POST',
            data: {
                action: 'pp_apply_coupon',
                coupon: coupon,
                nonce: puzzlepathBooking.nonce
            },
            success: function(response) {
                if (response && response.success) {
                    feedback.css('color', '#228B22').text(response.data.message);
                } else {
                    feedback.css('color', '#d2691e').text(
                        response && response.data && response.data.message 
                        ? response.data.message 
                        : 'Error validating coupon. Please try again.'
                    );
                }
            },
            error: function(xhr, status, error) {
                feedback.css('color', '#d2691e').text('Error: Could not validate coupon. Please try again.');
                console.error('AJAX Error:', status, error);
            }
        });
    });
}); 