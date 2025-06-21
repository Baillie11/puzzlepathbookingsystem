jQuery(document).ready(function($) {

    function updatePriceDisplay() {
        var selectedOption = $('#event option:selected');
        var price = selectedOption.data('price');

        if (price) {
            $('#price-display .price-original').text('$' + parseFloat(price).toFixed(2));
            $('#price-display .price-final').text('$' + parseFloat(price).toFixed(2));
            $('#price-display .price-discount-line').hide();
            $('#price-display').show();
        } else {
            $('#price-display').hide();
        }
        $('#coupon-result').hide();
    }

    // Update price when event changes
    $('#event').on('change', function() {
        updatePriceDisplay();
        $('#coupon').val(''); // Clear coupon on event change
    });

    // Handle Apply Coupon button click
    $('#apply-coupon-btn').on('click', function() {
        var couponCode = $('#coupon').val().trim();
        var eventId = $('#event').val();
        var applyButton = $(this);

        if (!eventId) {
            alert('Please select an event first.');
            return;
        }
        if (!couponCode) {
            alert('Please enter a coupon code.');
            return;
        }
        
        applyButton.prop('disabled', true).text('Applying...');

        $.ajax({
            url: puzzlepath_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'puzzlepath_apply_coupon',
                nonce: puzzlepath_ajax.nonce,
                coupon_code: couponCode,
                event_id: eventId
            },
            success: function(response) {
                $('#coupon-result').show();
                if (response.success) {
                    $('#coupon-result').removeClass('error').addClass('success').text(response.data.message);
                    $('#price-display .price-discount').text('-$' + response.data.discount_amount);
                    $('#price-display .price-final').text('$' + response.data.new_price);
                    $('#price-display .price-discount-line').show();
                } else {
                    $('#coupon-result').removeClass('success').addClass('error').text(response.data.message);
                    updatePriceDisplay(); // Reset price on error
                }
            },
            error: function() {
                $('#coupon-result').show().removeClass('success').addClass('error').text('An error occurred. Please try again.');
                updatePriceDisplay(); // Reset price on error
            },
            complete: function() {
                applyButton.prop('disabled', false).text('Apply');
            }
        });
    });
}); 