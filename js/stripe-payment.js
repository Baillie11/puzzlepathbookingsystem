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
        
        // Check if this is a free booking (total = $0) - do nothing
        var currentTotal = window.puzzlepathCurrentTotal || 0;
        if (currentTotal <= 0 && bookingData.event_id) {
            // For 100% discount, just show message and return - don't process booking
            $('#card-errors').html('<div style="color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px;">Total is $0.00. No payment required.</div>');
            submitButton.prop('disabled', false).text('Book Now');
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
