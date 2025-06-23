jQuery(document).ready(function($) {
    var basePrice = 0;
    var ticketCount = 1;
    var discountPercent = 0;
    var couponApplied = false;

    function calculateAndDisplayTotal() {
        if (basePrice > 0) {
            var subtotal = basePrice * ticketCount;
            var discountAmount = 0;
            
            if (couponApplied) {
                discountAmount = subtotal * (discountPercent / 100);
            }

            var finalTotal = subtotal - discountAmount;

            $('#total-price').text('$' + finalTotal.toFixed(2));
            
            if (couponApplied) {
                 $('#coupon-feedback').text('Discount of ' + discountPercent + '% applied!').css('color', 'green');
            }

        } else {
            $('#total-price').text('$0.00');
        }
    }

    // When an event is selected
    $('#event_id').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        basePrice = parseFloat(selectedOption.data('price')) || 0;
        var maxSeats = parseInt(selectedOption.data('seats')) || 0;

        // Update the max attribute of the tickets input
        $('#tickets').attr('max', maxSeats);
        
        // Reset tickets if current count exceeds new max
        if (ticketCount > maxSeats) {
            $('#tickets').val(maxSeats);
            ticketCount = maxSeats;
        }

        // Reset coupon when event changes
        couponApplied = false;
        discountPercent = 0;
        $('#coupon_code').val('');
        $('#coupon-feedback').text('');


        calculateAndDisplayTotal();
    });

    // When the number of tickets changes
    $('#tickets').on('input', function() {
        ticketCount = parseInt($(this).val()) || 0;
        calculateAndDisplayTotal();
    });

    // Handle Apply Coupon button click
    $('#apply-coupon-btn').on('click', function() {
        var couponCode = $('#coupon_code').val().trim();
        var applyButton = $(this);

        if (!$('#event_id').val()) {
            $('#coupon-feedback').text('Please select an event first.').css('color', 'red');
            return;
        }
        if (!couponCode) {
            $('#coupon-feedback').text('Please enter a coupon code.').css('color', 'red');
            return;
        }
        
        applyButton.prop('disabled', true).text('Applying...');
        $('#coupon-feedback').text('');

        $.ajax({
            url: puzzlepath_data.ajax_url, // CORRECT: was puzzlepath_ajax
            type: 'POST',
            data: {
                action: 'apply_coupon', // CORRECT: was puzzlepath_apply_coupon
                nonce: puzzlepath_data.coupon_nonce, // CORRECT: was puzzlepath_ajax.nonce
                coupon_code: couponCode
            },
            success: function(response) {
                if (response.success) {
                    discountPercent = parseFloat(response.data.discount_percent);
                    couponApplied = true;
                    calculateAndDisplayTotal(); // This will show the updated price and success message
                } else {
                    couponApplied = false;
                    discountPercent = 0;
                    $('#coupon-feedback').text(response.data.message).css('color', 'red');
                    calculateAndDisplayTotal(); // Recalculate to remove any previous discount
                }
            },
            error: function() {
                couponApplied = false;
                discountPercent = 0;
                $('#coupon-feedback').text('An error occurred. Please try again.').css('color', 'red');
                calculateAndDisplayTotal(); // Recalculate to remove any previous discount
            },
            complete: function() {
                applyButton.prop('disabled', false).text('Apply');
            }
        });
    });

    // Initial state
    calculateAndDisplayTotal();
}); 