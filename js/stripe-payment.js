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

    // Create and mount the individual card elements
    var cardNumber = elements.create('cardNumber', { style: style });
    if ($('#card-number-element').length) {
        cardNumber.mount('#card-number-element');
    }

    var cardExpiry = elements.create('cardExpiry', { style: style });
    if ($('#card-expiry-element').length) {
        cardExpiry.mount('#card-expiry-element');
    }

    var cardCvc = elements.create('cardCvc', { style: style });
    if ($('#card-cvc-element').length) {
        cardCvc.mount('#card-cvc-element');
    }

    // CORRECT: Create the postal code element without the invalid 'options'
    var postalCode = elements.create('postalCode', {
        style: style,
        placeholder: 'Postcode',
    });
    if ($('#postal-code-element').length) {
        postalCode.mount('#postal-code-element');
    }

    // Handle real-time validation errors from the card Elements.
    var elementsToValidate = [cardNumber, cardExpiry, cardCvc, postalCode];
    elementsToValidate.forEach(function(element) {
        element.on('change', function(event) {
            var displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });
    });

    // Handle form submission.
    var form = document.getElementById('puzzlepath-booking-form');
    var submitButton = $('#submit-payment-btn');

    form.addEventListener('submit', function(event) {
        event.preventDefault();
        payWithCard(stripe, cardNumber, cardExpiry, cardCvc, postalCode);
    });

    submitButton.on('click', function(e) {
        e.preventDefault();
        payWithCard(stripe, cardNumber, cardExpiry, cardCvc, postalCode);
    });

    var payWithCard = function(stripe, cardNumberElement, cardExpiryElement, cardCvcElement, postalCodeElement) {
        submitButton.prop('disabled', true);

        // Collect form data
        var bookingData = {
            event_id: $('#event_id').val(),
            tickets: $('#tickets').val(),
            name: $('#customer_name').val(),
            email: $('#customer_email').val(),
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
            if (result.clientSecret) {
                // Confirm the payment with the client secret
                confirmPayment(result.clientSecret, cardNumberElement, cardExpiryElement, cardCvcElement, postalCodeElement, bookingData.email);
            } else {
                $('#card-errors').text(result.message || (result.data && result.data.message) || 'There was an error setting up the payment.');
                submitButton.prop('disabled', false);
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            $('#card-errors').text('Could not connect to payment server.');
            submitButton.prop('disabled', false);
        });
    };

    var confirmPayment = function(clientSecret, cardNumberElement, cardExpiryElement, cardCvcElement, postalCodeElement, customerEmail) {
        stripe.confirmCardPayment(clientSecret, {
            payment_method: {
                card: cardNumberElement,
                billing_details: {
                    name: $('#customer_name').val(),
                    email: customerEmail,
                }
            }
        }).then(function(result) {
            if (result.error) {
                // Show error to your customer
                $('#card-errors').text(result.error.message);
                submitButton.prop('disabled', false);
            } else {
                // The payment has been processed!
                if (result.paymentIntent.status === 'succeeded') {
                    $('#puzzlepath-booking-form').hide();
                    $('#payment-success-message').show();
                }
            }
        });
    };
});
