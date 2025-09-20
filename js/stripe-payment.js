/**
 * Stripe Payment Processing for PuzzlePath Booking
 */

jQuery(document).ready(function($) {
    // Check if puzzlepath_data is available
    if (typeof puzzlepath_data === 'undefined') {
        console.error('PuzzlePath data is not available. Check wp_localize_script.');
        return;
    }
    
    var stripeEnabled = puzzlepath_data.publishable_key && puzzlepath_data.publishable_key.length > 0;
    
    // Initialize Stripe only if configured
    var stripe = null;
    var elements = null;
    var card = null;
    
    if (stripeEnabled) {
        stripe = Stripe(puzzlepath_data.publishable_key);
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

        // Create and mount the card element
        card = elements.create('card', {style: style});
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
    }

    // Handle form submission
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
        submitButton.prop('disabled', true).text('Processing...');

        // Collect form data
        var bookingData = {
            event_id: $('#event_id').val(),
            tickets: $('#tickets').val(),
            name: $('#name').val(),
            email: $('#email').val(),
            coupon_code: $('#coupon_code').val()
        };
        
        // Validate required fields
        if (!bookingData.event_id || !bookingData.name || !bookingData.email) {
            alert('Please fill in all required fields');
            submitButton.prop('disabled', false).text('Book Now');
            return;
        }
        
        // Check if this is a free booking (total = $0) - process as free booking
        var currentTotal = window.puzzlepathCurrentTotal || 0;
        if (currentTotal <= 0 && bookingData.event_id) {
            // Process free booking - create booking, send email, etc. (skip Stripe)
            processFreeBooking(bookingData);
            return;
        }

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
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(result) {
            if (result.bookingCode) {
                bookingCode = result.bookingCode;
            }
            
            if (result.clientSecret) {
                // Confirm the payment
                confirmPayment(result.clientSecret, cardElement, bookingData.name, bookingData.email);
            } else {
                $('#card-errors').text(result.message || 'There was an error setting up the payment.');
                submitButton.prop('disabled', false).text('Book Now');
            }
        })
        .catch(function(error) {
            $('#card-errors').text('Could not connect to payment server.');
            submitButton.prop('disabled', false).text('Book Now');
        });
    };
    
    var processFreeBooking = function(bookingData) {
        // Process free bookings - create database entry, send email, etc.
        fetch(puzzlepath_data.rest_url + 'payment/create-intent', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': puzzlepath_data.rest_nonce
            },
            body: JSON.stringify(bookingData),
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(result) {
            console.log('PuzzlePath Debug: Free booking response:', result);
            if (result.success && result.free_booking) {
                // Redirect to confirmation page with booking code
                var confirmationUrl = window.location.origin + '/booking-confirmation/?booking_code=' + encodeURIComponent(result.bookingCode) + '&event_id=' + encodeURIComponent(bookingData.event_id);
                window.location.href = confirmationUrl;
            } else {
                $('#card-errors').text(result.message || 'There was an error processing your free booking.');
                submitButton.prop('disabled', false).text('Book Now');
            }
        })
        .catch(function(error) {
            console.error('PuzzlePath Debug: Free booking error:', error);
            $('#card-errors').text('Could not process your booking. Please try again. Error: ' + error.message);
            submitButton.prop('disabled', false).text('Book Now');
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
                // Show error to customer
                $('#card-errors').text(result.error.message);
                submitButton.prop('disabled', false).text('Book Now');
            } else {
                // Payment succeeded
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
