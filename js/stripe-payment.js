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
            if (result.success && result.free_booking) {
                // Show success message but keep form visible
                var successHtml = '<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0;">' +
                    '<h2 style="color: #155724; margin-top: 0;">🎉 Booking Confirmed!</h2>' +
                    '<p style="font-size: 16px; margin: 10px 0;">Thank you for your free booking!</p>' +
                    '<p style="font-size: 18px; font-weight: bold; margin: 15px 0;">Your booking code is: <span style="background: #fff; padding: 5px 10px; border: 2px dashed #28a745; border-radius: 4px; font-family: monospace;">' + result.bookingCode + '</span></p>' +
                    '<p style="font-size: 14px; color: #666; margin: 10px 0;">A confirmation email has been sent to your email address.</p>' +
                    '<p style="font-size: 14px; color: #666; margin: 10px 0;">You can now access your quest at <a href="https://app.puzzlepath.com.au" target="_blank" style="color: #28a745; text-decoration: none;">app.puzzlepath.com.au</a></p>' +
                    '</div>';
                
                // Show success message above the form instead of replacing it
                $('#card-errors').html(successHtml);
                
                // Disable the form to prevent double bookings
                $('#booking-form input, #booking-form select, #booking-form button').prop('disabled', true);
                submitButton.text('Booking Complete');
                
                // Scroll to success message
                $('#card-errors')[0].scrollIntoView({ behavior: 'smooth' });
            } else {
                $('#card-errors').text(result.message || 'There was an error processing your free booking.');
                submitButton.prop('disabled', false).text('Book Now');
            }
        })
        .catch(function(error) {
            $('#card-errors').text('Could not process your booking. Please try again.');
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
