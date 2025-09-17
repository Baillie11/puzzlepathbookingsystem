jQuery(document).ready(function($) {
    var basePrice = 0;
    var ticketCount = 1;
    var discountPercent = 0;
    var couponApplied = false;

    function calculateAndDisplayTotal() {
        var finalTotal = 0;
        
        if (basePrice > 0) {
            var subtotal = basePrice * ticketCount;
            var discountAmount = 0;
            
            if (couponApplied) {
                discountAmount = subtotal * (discountPercent / 100);
                $('#discount-line').show();
                $('#discount').text(discountAmount.toFixed(2));
            } else {
                $('#discount-line').hide();
            }

            finalTotal = subtotal - discountAmount;

            $('#subtotal').text(subtotal.toFixed(2));
            $('#total').text(finalTotal.toFixed(2));
            
            if (couponApplied) {
                $('#coupon-message').html('<span style="color: green;">✓ Discount of ' + discountPercent + '% applied!</span>');
            }

        } else {
            $('#subtotal').text('0.00');
            $('#total').text('0.00');
            $('#discount-line').hide();
        }
        
        // Handle free bookings - hide payment fields and update UI
        if (finalTotal <= 0) {
            // Hide card elements with multiple selector attempts
            $('#card-element').hide();
            $('#card-element').parent().hide();
            $('#card-errors').hide();
            $('[id*="card"]').not('#card-errors').hide(); // Hide any element with 'card' in ID
            $('.card-container, .payment-section, .stripe-elements').hide();
            
            // Update submit button
            $('#submit-payment').text('Complete Free Booking');
            
            // Add free booking notice
            if (!$('#free-booking-notice').length) {
                $('<div id="free-booking-notice" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; font-weight: bold; text-align: center;"><strong>🎉 Free Booking!</strong><br/>No payment required - just click to confirm your booking.</div>').insertBefore('#submit-payment');
            }
        } else {
            // Show card elements for paid bookings
            $('#card-element').show();
            $('#card-element').parent().show();
            $('#card-errors').show();
            $('[id*="card"]').show();
            $('.card-container, .payment-section, .stripe-elements').show();
            
            // Reset submit button
            $('#submit-payment').text('Book Now');
            
            // Remove free booking notice
            $('#free-booking-notice').remove();
        }
        
        // Make the final total globally accessible for stripe-payment.js
        window.puzzlepathCurrentTotal = finalTotal;
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
        $('#coupon-message').text('');


        calculateAndDisplayTotal();
    });

    // When the number of tickets changes
    $('#tickets').on('input', function() {
        ticketCount = parseInt($(this).val()) || 0;
        calculateAndDisplayTotal();
    });

    // Handle Apply Coupon button click
    $('#apply-coupon').on('click', function() {
        var couponCode = $('#coupon_code').val().trim();
        var applyButton = $(this);

        if (!$('#event_id').val()) {
            $('#coupon-message').html('<span style="color: red;">Please select an event first.</span>');
            return;
        }
        if (!couponCode) {
            $('#coupon-message').html('<span style="color: red;">Please enter a coupon code.</span>');
            return;
        }
        
        applyButton.prop('disabled', true).text('Applying...');
        $('#coupon-message').text('');

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
                    $('#coupon-message').html('<span style="color: red;">' + response.data.message + '</span>');
                    calculateAndDisplayTotal(); // Recalculate to remove any previous discount
                }
            },
            error: function() {
                couponApplied = false;
                discountPercent = 0;
                $('#coupon-message').html('<span style="color: red;">An error occurred. Please try again.</span>');
                calculateAndDisplayTotal(); // Recalculate to remove any previous discount
            },
            complete: function() {
                applyButton.prop('disabled', false).text('Apply Coupon');
            }
        });
    });

    // Initialize global total variable
    window.puzzlepathCurrentTotal = 0;
    
    // Initial state
    calculateAndDisplayTotal();
});
