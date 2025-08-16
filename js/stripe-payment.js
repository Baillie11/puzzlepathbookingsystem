/**
 * Stripe Payment Processing for PuzzlePath Booking
 */

jQuery(document).ready(function ($) {
    // Check if puzzlepath_data is available
    if (typeof puzzlepath_data === 'undefined' || !puzzlepath_data.publishable_key) {
        console.error('Stripe data is not available. Check wp_localize_script.');
        $('#submit-payment-btn').prop('disabled', true).text('Payment Unavailable');
        return;
    }

    // Initialize Stripe
    var stripe = Stripe(puzzlepath_data.publishable_key);
    
    // CORRECT: Set the locale for all elements to Australian English
    var elements = stripe.elements({locale: 'en-AU'});

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
    var card = elements.create('card', { style: style });
    if ($('#card-element').length) {
        card.mount('#card-element');
    }

    // Handle real-time validation errors from the card Element.
    card.on('change', function(event) {
        var displayError = document.getElementById('card-errors');
        if (event.error) {
            displayError.textContent = event.error.message;
        } else {
            displayError.textContent = '';
        }
    });

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
        submitButton.prop('disabled', true).text('Processing...');

        // Collect form data
        var bookingData = {
            event_id: $('#event_id').val(),
            tickets: $('#tickets').val(),
            name: $('#name').val(),
            email: $('#email').val(),
            coupon_code: $('#coupon_code').val()
        };

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
            return response.json();
        })
        .then(function(result) {
            if (result.bookingCode) {
                bookingCode = result.bookingCode;
            }
            if (result.clientSecret) {
                // Confirm the payment with the client secret
                confirmPayment(result.clientSecret, cardElement, bookingData.name, bookingData.email);
            } else {
                $('#card-errors').text(result.message || (result.data && result.data.message) || 'There was an error setting up the payment.');
                submitButton.prop('disabled', false).text('Book Now');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
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
