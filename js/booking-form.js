jQuery(document).ready(function($) {
    var basePrice = 0;
    var ticketCount = 1;
    var discountPercent = 0;
    var couponApplied = false;
    var couponPending = false;
    var couponRejected = false;
    var couponSequence = 0;
    var linkedCode = !window.ppRepeatHunter && window.ppDiscountLink ? window.ppDiscountLink.get() : '';
    var voucherNotice;
    if (linkedCode && $('#booking-form').length) {
        voucherNotice = $('<p role="status">').text('Your voucher is saved. Select a quest to apply it. ');
        var removeVoucher = $('<button type="button">').text('Remove voucher');
        voucherNotice.append(removeVoucher);
        $('#booking-form').prepend(voucherNotice);
        removeVoucher.on('click', function() {
            linkedCode = '';
            window.ppDiscountLink.clear();
            $('#coupon_code').val('').trigger('input');
            voucherNotice.remove();
        });
    }

    function resetCoupon() {
        couponSequence++;
        couponPending = false;
        couponRejected = false;
        couponApplied = false;
        discountPercent = 0;
        $('#apply-coupon').prop('disabled', false).text('Apply Coupon');
    }

    // Prevent a payment starting while a voucher is still being checked.
    function guardCoupon(event) {
        if (couponPending || couponRejected) {
            event.preventDefault();
            event.stopImmediatePropagation();
            if (couponPending) $('#coupon-message').text('Please wait while we check your discount.');
            else $('#coupon-message').text('Please retry or remove the voucher before booking.');
        }
    }
    document.getElementById('booking-form')?.addEventListener('submit', guardCoupon, true);
    document.getElementById('submit-payment')?.addEventListener('click', guardCoupon, true);

    function calculateAndDisplayTotal() {
        var finalTotal = 0;
        
        if (basePrice > 0) {
            // Round the final payable amount in cents, matching the server's Stripe amount.
            var subtotalCents = Math.round(basePrice * 100) * ticketCount;
            var totalCents = couponApplied ? Math.round(subtotalCents * (100 - discountPercent) / 100) : subtotalCents;
            var subtotal = subtotalCents / 100;
            var discountAmount = 0;
            
            if (couponApplied) {
                discountAmount = (subtotalCents - totalCents) / 100;
                $('#discount-line').show();
                $('#discount').text(discountAmount.toFixed(2));
            } else {
                $('#discount-line').hide();
            }

            finalTotal = totalCents / 100;

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
        
        // Simple logic: Just track the total for payment processing
        // No special UI changes or messages - keep it simple
        
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
        resetCoupon();
        $('#coupon_code').val('');
        $('#coupon-message').text('');


        calculateAndDisplayTotal();
        if (linkedCode && $(this).val()) {
            $('#coupon_code').val(linkedCode);
            $('#apply-coupon').trigger('click');
        }
    });

    // When the number of tickets changes
    $('#tickets').on('input', function() {
        ticketCount = parseInt($(this).val()) || 0;
        calculateAndDisplayTotal();
    });

    // Handle Apply Coupon button click
    $('#coupon_code').on('input', function() {
        resetCoupon();
        if (linkedCode && $(this).val().trim() !== linkedCode) {
            linkedCode = '';
            window.ppDiscountLink.clear();
            if (voucherNotice) voucherNotice.remove();
        }
        $('#coupon-message').text('');
        calculateAndDisplayTotal();
    });
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
        
        var sequence = ++couponSequence;
        couponPending = true;
        couponRejected = false;
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
                if (sequence !== couponSequence) return;
                if (response.success) {
                    discountPercent = parseFloat(response.data.discount_percent);
                    couponApplied = true;
                    calculateAndDisplayTotal(); // This will show the updated price and success message
                } else {
                    couponRejected = true;
                    couponApplied = false;
                    discountPercent = 0;
                    $('#coupon-message').text(response.data.message);
                    calculateAndDisplayTotal(); // Recalculate to remove any previous discount
                }
            },
            error: function() {
                if (sequence !== couponSequence) return;
                couponRejected = true;
                couponApplied = false;
                discountPercent = 0;
                $('#coupon-message').html('<span style="color: red;">An error occurred. Please try again.</span>');
                calculateAndDisplayTotal(); // Recalculate to remove any previous discount
            },
            complete: function() {
                if (sequence !== couponSequence) return;
                couponPending = false;
                applyButton.prop('disabled', false).text('Apply Coupon');
            }
        });
    });

    // Initialize global total variable
    window.puzzlepathCurrentTotal = 0;
    
    // Initial state
    calculateAndDisplayTotal();
    // Preserve the live site's quest selection from Upcoming Adventures links.
    var requestedEventId = new URLSearchParams(window.location.search).get('event_id');
    var eventSelect = document.getElementById('event_id');
    if (eventSelect && requestedEventId && /^\d+$/.test(requestedEventId)) {
        var matchingOption = Array.from(eventSelect.options).find(function(option) {
            return option.value === requestedEventId && !option.disabled;
        });
        if (matchingOption) $(eventSelect).val(requestedEventId);
    }
    if ($('#event_id').val()) $('#event_id').trigger('change');
});
