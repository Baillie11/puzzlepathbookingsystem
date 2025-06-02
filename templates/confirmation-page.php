<?php
/**
 * Template Name: PuzzlePath Booking Confirmation
 * 
 * This template displays the booking confirmation after successful payment
 */

// Ensure this page is only accessible with proper booking parameters
if (!isset($_GET['booking_id']) || !isset($_GET['status']) || $_GET['status'] !== 'success') {
    wp_redirect(home_url());
    exit;
}

$booking_id = intval($_GET['booking_id']);

// Get booking details
global $wpdb;
$booking = $wpdb->get_row($wpdb->prepare(
    "SELECT b.*, e.title as event_title, e.event_date, e.location, e.price, c.discount_percent 
    FROM {$wpdb->prefix}pp_bookings b 
    LEFT JOIN {$wpdb->prefix}pp_events e ON b.event_id = e.id 
    LEFT JOIN {$wpdb->prefix}pp_coupons c ON b.coupon_code = c.code 
    WHERE b.id = %d AND b.payment_status = 'paid'",
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

<div class="puzzlepath-confirmation-page">
    <div class="confirmation-container">
        <div class="success-icon">✓</div>
        <h1>Booking Confirmed!</h1>
        <p class="thank-you">Thank you for your booking, <?php echo esc_html($booking->name); ?>!</p>
        
        <div class="confirmation-details">
            <h2>Booking Details</h2>
            <div class="detail-row">
                <span>Booking Reference:</span>
                <span>#<?php echo esc_html($booking_id); ?></span>
            </div>
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
            <?php if (!empty($booking->discount_percent)) : ?>
                <div class="detail-row">
                    <span>Original Price:</span>
                    <span>$<?php echo number_format($booking->price, 2); ?></span>
                </div>
                <div class="detail-row discount">
                    <span>Discount Applied:</span>
                    <span><?php echo esc_html($booking->discount_percent); ?>% off</span>
                </div>
            <?php endif; ?>
            <div class="detail-row total">
                <span>Total Paid:</span>
                <span>$<?php echo number_format($final_price, 2); ?></span>
            </div>
        </div>

        <div class="confirmation-message">
            <p>A confirmation email has been sent to <?php echo esc_html($booking->email); ?> with these details.</p>
            <p>Please save your booking reference: <strong>#<?php echo esc_html($booking_id); ?></strong></p>
        </div>

        <div class="next-steps">
            <h2>What's Next?</h2>
            <ul>
                <li>Save your booking reference number</li>
                <li>Check your email for the confirmation</li>
                <li>Add the event to your calendar</li>
                <li>Contact us if you have any questions</li>
            </ul>
        </div>

        <div class="actions">
            <a href="<?php echo esc_url(home_url()); ?>" class="button">Return to Homepage</a>
            <?php
            // Generate calendar link
            $event_start = strtotime($booking->event_date);
            $event_end = strtotime('+2 hours', $event_start); // Assuming 2-hour events
            $calendar_url = add_query_arg([
                'text' => urlencode($booking->event_title),
                'dates' => date('Ymd\\THis', $event_start) . '/' . date('Ymd\\THis', $event_end),
                'details' => urlencode("Booking Reference: #{$booking_id}\nLocation: {$booking->location}"),
                'location' => urlencode($booking->location)
            ], 'https://www.google.com/calendar/render?action=TEMPLATE');
            ?>
            <a href="<?php echo esc_url($calendar_url); ?>" class="button calendar" target="_blank">Add to Calendar</a>
        </div>
    </div>
</div>

<style>
.puzzlepath-confirmation-page {
    max-width: 800px;
    margin: 40px auto;
    padding: 20px;
}

.confirmation-container {
    background: #fff;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.success-icon {
    background: #28a745;
    color: white;
    width: 80px;
    height: 80px;
    line-height: 80px;
    font-size: 40px;
    border-radius: 50%;
    margin: 0 auto 20px;
}

.thank-you {
    font-size: 1.2em;
    color: #666;
    margin-bottom: 30px;
}

.confirmation-details {
    background: #fff5e6;
    padding: 20px;
    border-radius: 8px;
    margin: 30px 0;
    text-align: left;
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

.confirmation-message {
    margin: 30px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.next-steps {
    margin: 30px 0;
    text-align: left;
}

.next-steps ul {
    list-style-type: none;
    padding: 0;
}

.next-steps li {
    padding: 10px 0;
    padding-left: 30px;
    position: relative;
}

.next-steps li:before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #28a745;
}

.actions {
    margin-top: 40px;
    display: flex;
    gap: 20px;
    justify-content: center;
}

.button {
    display: inline-block;
    padding: 12px 24px;
    background: #ff8800;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    transition: background 0.3s;
}

.button:hover {
    background: #e67600;
    color: white;
}

.button.calendar {
    background: #28a745;
}

.button.calendar:hover {
    background: #218838;
}
</style>

<?php get_footer(); ?> 