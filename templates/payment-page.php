<?php
/**
 * Template Name: PuzzlePath Payment Page
 * 
 * This template handles the payment processing for PuzzlePath bookings
 */

// Ensure this page is only accessible with proper booking parameters
if (!isset($_GET['booking_id']) || !isset($_GET['name'])) {
    wp_redirect(home_url());
    exit;
}

$booking_id = intval($_GET['booking_id']);
$customer_name = sanitize_text_field($_GET['name']);

// Get booking details
global $wpdb;
$booking = $wpdb->get_row($wpdb->prepare(
    "SELECT b.*, e.title as event_title, e.price, e.event_date, e.location, c.discount_percent 
    FROM {$wpdb->prefix}pp_bookings b 
    LEFT JOIN {$wpdb->prefix}pp_events e ON b.event_id = e.id 
    LEFT JOIN {$wpdb->prefix}pp_coupons c ON b.coupon_code = c.code 
    WHERE b.id = %d",
    $booking_id
));

if (!$booking) {
    wp_redirect(home_url());
    exit;
}

// Calculate final price
$final_price = $booking->price;
if (!empty($booking->discount_percent)) {
    $final_price = $final_price * (1 - ($booking->discount_percent / 100));
}

get_header();
?>

<div class="puzzlepath-payment-page">
    <div class="payment-container">
        <h1>Complete Your Booking</h1>
        
        <div class="booking-details">
            <h2>Booking Summary</h2>
            <div class="detail-row">
                <span>Event:</span>
                <span><?php echo esc_html($booking->event_title); ?></span>
            </div>
            <div class="detail-row">
                <span>Date:</span>
                <span><?php echo esc_html(date('F j, Y g:i a', strtotime($booking->event_date))); ?></span>
            </div>
            <div class="detail-row">
                <span>Location:</span>
                <span><?php echo esc_html($booking->location); ?></span>
            </div>
            <div class="detail-row">
                <span>Name:</span>
                <span><?php echo esc_html($booking->name); ?></span>
            </div>
            <?php if (!empty($booking->discount_percent)) : ?>
                <div class="detail-row">
                    <span>Original Price:</span>
                    <span>$<?php echo number_format($booking->price, 2); ?></span>
                </div>
                <div class="detail-row discount">
                    <span>Discount:</span>
                    <span><?php echo esc_html($booking->discount_percent); ?>% off</span>
                </div>
            <?php endif; ?>
            <div class="detail-row total">
                <span>Total to Pay:</span>
                <span>$<?php echo number_format($final_price, 2); ?></span>
            </div>
        </div>

        <div class="payment-form">
            <h2>Payment Details</h2>
            <form id="payment-form">
                <input type="hidden" id="booking_id" value="<?php echo esc_attr($booking_id); ?>">
                <input type="hidden" id="amount" value="<?php echo esc_attr($final_price); ?>">
                
                <div id="card-element">
                    <!-- Stripe Card Element will be inserted here -->
                </div>
                <div id="card-errors" role="alert"></div>

                <button type="submit" id="submit-payment">
                    <span class="spinner" style="display: none;"></span>
                    <span class="button-text">Pay Now</span>
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.puzzlepath-payment-page {
    max-width: 800px;
    margin: 40px auto;
    padding: 20px;
}

.payment-container {
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
}

.booking-details {
    margin-bottom: 40px;
    padding: 20px;
    background: #fff5e6;
    border-radius: 8px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255, 165, 0, 0.2);
}

.detail-row.total {
    border-bottom: none;
    font-weight: bold;
    font-size: 1.2em;
    margin-top: 10px;
    padding-top: 20px;
    border-top: 2px solid rgba(255, 165, 0, 0.4);
}

.detail-row.discount {
    color: #28a745;
}

.payment-form {
    margin-top: 30px;
}

#card-element {
    padding: 15px;
    border: 2px solid #ffa500;
    border-radius: 6px;
    background: #fff;
    margin-bottom: 20px;
}

#card-errors {
    color: #dc3545;
    margin-bottom: 20px;
    min-height: 20px;
}

#submit-payment {
    background: #ff8800;
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
    width: 100%;
    position: relative;
}

#submit-payment:hover {
    background: #e67600;
}

.spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
    position: absolute;
    left: calc(50% - 10px);
    top: calc(50% - 10px);
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<script src="https://js.stripe.com/v3/"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Stripe
    const stripe = Stripe('<?php echo esc_js(get_option('puzzlepath_stripe_publishable_key')); ?>');
    const elements = stripe.elements();

    // Create card Element
    const card = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#32325d',
                '::placeholder': {
                    color: '#aab7c4'
                }
            },
            invalid: {
                color: '#dc3545',
                iconColor: '#dc3545'
            }
        }
    });

    // Mount the card Element
    card.mount('#card-element');

    // Handle form submission
    const form = document.getElementById('payment-form');
    const submitButton = document.getElementById('submit-payment');
    const spinner = submitButton.querySelector('.spinner');
    const buttonText = submitButton.querySelector('.button-text');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        
        // Disable the submit button to prevent multiple submissions
        submitButton.disabled = true;
        spinner.style.display = 'block';
        buttonText.style.opacity = '0';

        try {
            const {paymentMethod, error} = await stripe.createPaymentMethod({
                type: 'card',
                card: card,
                billing_details: {
                    name: '<?php echo esc_js($booking->name); ?>',
                    email: '<?php echo esc_js($booking->email); ?>'
                }
            });

            if (error) {
                throw error;
            }

            // Send payment method ID to server
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'puzzlepath_process_payment',
                    nonce: '<?php echo wp_create_nonce('puzzlepath_payment'); ?>',
                    payment_method_id: paymentMethod.id,
                    booking_id: '<?php echo esc_js($booking_id); ?>',
                    amount: '<?php echo esc_js($final_price * 100); ?>' // Convert to cents for Stripe
                })
            });

            const result = await response.json();

            if (result.success) {
                window.location.href = result.data.redirect_url;
            } else {
                throw new Error(result.data.message || 'Payment failed');
            }
        } catch (error) {
            const errorElement = document.getElementById('card-errors');
            errorElement.textContent = error.message;
            submitButton.disabled = false;
            spinner.style.display = 'none';
            buttonText.style.opacity = '1';
        }
    });
});
</script>

<?php get_footer(); ?> 