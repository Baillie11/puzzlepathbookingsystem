/**
 * Stripe Payment Processing for PuzzlePath Booking
 */

jQuery(document).ready(function ($) {
    // TEST: Basic load verification
    console.log('PuzzlePath Debug: stripe-payment.js loaded successfully!');
    console.log('PuzzlePath Debug: jQuery version:', $.fn.jquery);
    console.log('PuzzlePath Debug: puzzlepath_data available:', typeof puzzlepath_data !== 'undefined');
    
    if (typeof puzzlepath_data !== 'undefined') {
        console.log('PuzzlePath Debug: puzzlepath_data contents:', puzzlepath_data);
        console.log('PuzzlePath Debug: publishable_key value:', puzzlepath_data.publishable_key);
        console.log('PuzzlePath Debug: rest_url value:', puzzlepath_data.rest_url);
        console.log('PuzzlePath Debug: rest_nonce value:', puzzlepath_data.rest_nonce);
    }
    
    // Add visible alert for debugging (but don't stop execution)
    if (typeof puzzlepath_data === 'undefined') {
        console.error('ERROR: PuzzlePath data is not loaded! Check plugin configuration.');
        return; // Only return if completely undefined
    }
    
    // Check if puzzlepath_data is available
    if (typeof puzzlepath_data === 'undefined') {
        console.error('PuzzlePath data is not available. Check wp_localize_script.');
        alert('Booking system not properly loaded. Please refresh the page.');
        return;
    }
    
    // Check for essential data
    if (!puzzlepath_data.rest_url || !puzzlepath_data.rest_nonce) {
        console.error('PuzzlePath Debug: Missing essential REST API data!');
        console.error('rest_url:', puzzlepath_data.rest_url);
        console.error('rest_nonce:', puzzlepath_data.rest_nonce);
        alert('Booking system configuration error. Missing REST API data.');
        return;
    }
    
    var stripeEnabled = puzzlepath_data.publishable_key && puzzlepath_data.publishable_key.length > 0;
    console.log('PuzzlePath Debug: Stripe enabled:', stripeEnabled);
    
    if (!stripeEnabled) {
        console.log('PuzzlePath Debug: Stripe not configured - will handle free bookings only');
    }

    // Initialize Stripe only if configured
    var stripe = null;
    var elements = null;
    var card = null;
    
    if (stripeEnabled) {
        stripe = Stripe(puzzlepath_data.publishable_key);
        
        // CORRECT: Set the locale for all elements to Australian English
        elements = stripe.elements({locale: 'en-AU'});

        // Style the card element
        var style = {
            base: {
                color: '#32325d',
                fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                fontSmoothing: 'antialiased',
                fontSize: '16px',
                '::placeholder': {
                    color: '#aab7c4'
                }
            },
            invalid: {
                color: '#fa755a',
                iconColor: '#fa755a'
            }
        };

        // Create and mount the card element (single element for all card details)
        card = elements.create('card', { style: style });
        if ($('#card-element').length) {
            card.mount('#card-element');
            
            // Handle real-time validation errors from the card Element.
            card.on('change', function(event) {
                var displayError = document.getElementById('card-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }
            });
        }
    } else {
        // Hide card element if Stripe not available
        $('#card-element').hide();
        $('#card-errors').hide();
        console.log('PuzzlePath Debug: Card element hidden (Stripe not configured)');
    }

    // Handle form submission.
    var form = document.getElementById('booking-form');
    var submitButton = $('#submit-payment');

    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            payWithCard(stripe, card);
        });
    }

    submitButton.on('click', function(e) {
        e.preventDefault();
        payWithCard(stripe, card);
    });

    var bookingCode = null;

    var payWithCard = function(stripe, cardElement) {
        console.log('PuzzlePath Debug: payWithCard function called');
        
        submitButton.prop('disabled', true).text('Processing...');
        console.log('PuzzlePath Debug: Submit button disabled');

        // Collect form data
        var bookingData = {
            event_id: $('#event_id').val(),
            tickets: $('#tickets').val(),
            name: $('#name').val(),
            email: $('#email').val(),
            coupon_code: $('#coupon_code').val()
        };
        
        console.log('PuzzlePath Debug: Booking data collected:', bookingData);
        
        // Validate required fields
        if (!bookingData.event_id || !bookingData.name || !bookingData.email) {
            console.error('PuzzlePath Debug: Missing required fields');
            alert('Please fill in all required fields');
            submitButton.prop('disabled', false).text('Complete Free Booking');
            return;
        }
        
        // Check if this is a free booking (total = $0 from applied coupon) and bypass Stripe
        var currentTotal = window.puzzlepathCurrentTotal || 0;
        console.log('PuzzlePath Debug: Current total for payment processing:', currentTotal);
        
        // Only bypass Stripe if total is $0 AND we have a valid event selected
        // (This prevents bypassing when page initially loads with $0 total)
        if (currentTotal <= 0 && bookingData.event_id) {
            console.log('PuzzlePath Debug: Free booking detected (coupon applied), bypassing Stripe');
            handleFreeBooking(bookingData);
            return;
        }
        
        console.log('PuzzlePath Debug: About to make fetch request to:', puzzlepath_data.rest_url + 'payment/create-intent');
        console.log('PuzzlePath Debug: Request headers will include nonce:', puzzlepath_data.rest_nonce);

        // Create Payment Intent on the server
        fetch(puzzlepath_data.rest_url + 'payment/create-intent', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': puzzlepath_data.rest_nonce
            },
            body: JSON.stringify(bookingData),
        })
        .then(function(response) {
            console.log('PuzzlePath Debug: Fetch response received, status:', response.status);
            
            if (!response.ok) {
                console.error('PuzzlePath Debug: HTTP error', response.status, response.statusText);
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            }
            
            return response.json();
        })
        .then(function(result) {
            console.log('PuzzlePath Debug: JSON response parsed successfully');
            console.log('Payment response:', result);
            
            if (result.bookingCode) {
                bookingCode = result.bookingCode;
            }
            
            // Check if this is a free booking
            if (result.free_booking && result.success) {
                console.log('PuzzlePath Debug: Free booking detected, showing success screen');
                console.log('PuzzlePath Debug: Booking code:', result.bookingCode);
                console.log('PuzzlePath Debug: Hiding form and showing success');
                
                // Free booking - show success immediately
                $('#booking-form').hide();
                $('#payment-success').show();
                $('#booking-code').text(result.bookingCode);
                
                console.log('PuzzlePath Debug: Form hidden:', $('#booking-form').is(':hidden'));
                console.log('PuzzlePath Debug: Success shown:', $('#payment-success').is(':visible'));
                console.log('PuzzlePath Debug: Booking code element text:', $('#booking-code').text());
                
                return; // Don't process Stripe payment
            }
            
            if (result.clientSecret) {
                // Confirm the payment with the client secret
                confirmPayment(result.clientSecret, cardElement, bookingData.name, bookingData.email);
            } else {
                $('#card-errors').text(result.message || (result.data && result.data.message) || 'There was an error setting up the payment.');
                var buttonText = (window.puzzlepathCurrentTotal <= 0) ? 'Complete Free Booking' : 'Book Now';
                submitButton.prop('disabled', false).text(buttonText);
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            $('#card-errors').text('Could not connect to payment server.');
            var buttonText = (window.puzzlepathCurrentTotal <= 0) ? 'Complete Free Booking' : 'Book Now';
            submitButton.prop('disabled', false).text(buttonText);
        });
    };

    var handleFreeBooking = function(bookingData) {
        console.log('PuzzlePath Debug: Processing free booking directly');
        
        // Make request directly to payment endpoint (backend will detect zero total)
        fetch(puzzlepath_data.rest_url + 'payment/create-intent', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': puzzlepath_data.rest_nonce
            },
            body: JSON.stringify(bookingData),
        })
        .then(function(response) {
            console.log('PuzzlePath Debug: Free booking response received, status:', response.status);
            
            if (!response.ok) {
                console.error('PuzzlePath Debug: HTTP error', response.status, response.statusText);
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            }
            
            return response.json();
        })
        .then(function(result) {
            console.log('PuzzlePath Debug: Free booking response data:', result);
            
            if (result.success && result.free_booking) {
                console.log('PuzzlePath Debug: Free booking confirmed, showing success screen');
                
                // Create a more prominent success message
                var successHtml = `
                    <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0;">
                        <h2 style="color: #155724; margin-top: 0;">🎉 Booking Confirmed!</h2>
                        <p style="font-size: 16px; margin: 10px 0;">Thank you for your free booking!</p>
                        <p style="font-size: 18px; font-weight: bold; margin: 15px 0;">Your booking code is: <span style="background: #fff; padding: 5px 10px; border: 2px dashed #28a745; border-radius: 4px; font-family: monospace;">${result.bookingCode}</span></p>
                        <p style="font-size: 14px; color: #666; margin: 10px 0;">A confirmation email has been sent to your email address.</p>
                        <p style="font-size: 14px; color: #666; margin: 10px 0;">You can now access your quest at <a href="https://app.puzzlepath.com.au" target="_blank" style="color: #28a745; text-decoration: none;">app.puzzlepath.com.au</a></p>
                    </div>
                `;
                
                // Replace the entire booking form with success message
                $('#puzzlepath-booking-form').html(successHtml);
                
                // Scroll to the success message
                $('#puzzlepath-booking-form')[0].scrollIntoView({ behavior: 'smooth' });
                
                console.log('PuzzlePath Debug: Success screen displayed with booking code:', result.bookingCode);
            } else {
                // Handle error response
                var errorMessage = result.message || result.data?.message || 'There was an error processing your free booking.';
                $('#card-errors').text(errorMessage);
                submitButton.prop('disabled', false).text('Complete Free Booking');
            }
        })
        .catch(function(error) {
            console.error('PuzzlePath Debug: Free booking error:', error);
            $('#card-errors').text('Could not process your booking. Please try again.');
            submitButton.prop('disabled', false).text('Complete Free Booking');
        });
    };
    
    var confirmPayment = function(clientSecret, cardElement, customerName, customerEmail) {
        stripe.confirmCardPayment(clientSecret, {
            payment_method: {
                card: cardElement,
                billing_details: {
                    name: customerName,
                    email: customerEmail,
                }
            }
        }).then(function(result) {
            if (result.error) {
                // Show error to your customer
                $('#card-errors').text(result.error.message);
                submitButton.prop('disabled', false).text('Book Now');
            } else {
                // The payment has been processed!
                if (result.paymentIntent.status === 'succeeded') {
                    // Show success message
                    $('#booking-form').hide();
                    $('#payment-success').show();
                    if (bookingCode) {
                        $('#booking-code').text(bookingCode);
                    }
                }
            }
        });
    };
});
