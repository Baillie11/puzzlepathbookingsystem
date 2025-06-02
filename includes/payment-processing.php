<?php
/**
 * Payment Processing Functions
 * 
 * Handles all payment-related functionality including Stripe integration
 */

// Make sure we don't expose any info if called directly
if (!defined('ABSPATH')) {
    exit;
}

// Include Stripe PHP SDK
if (!defined('PUZZLEPATH_PLUGIN_DIR')) {
    // Get the correct plugin directory path
    $plugin_dir = plugin_dir_path(dirname(__FILE__));
    // Remove any version number from the path
    $plugin_dir = preg_replace('/puzzlepath-booking-\d+\//', 'puzzlepath-booking/', $plugin_dir);
    define('PUZZLEPATH_PLUGIN_DIR', $plugin_dir);
}

$autoload_path = PUZZLEPATH_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once($autoload_path);
} else {
    wp_die('Stripe SDK not found. Please run composer install in the plugin directory: ' . $autoload_path);
}

// Initialize Stripe
function puzzlepath_init_stripe() {
    $stripe_secret_key = get_option('puzzlepath_stripe_secret_key');
    \Stripe\Stripe::setApiKey($stripe_secret_key);
}

// Add AJAX handlers for payment processing
add_action('wp_ajax_puzzlepath_process_payment', 'puzzlepath_process_payment');
add_action('wp_ajax_nopriv_puzzlepath_process_payment', 'puzzlepath_process_payment');

function puzzlepath_process_payment() {
    try {
        check_ajax_referer('puzzlepath_payment', 'nonce');
        
        // Get and validate parameters
        $payment_method_id = sanitize_text_field($_POST['payment_method_id']);
        $booking_id = intval($_POST['booking_id']);
        $amount = intval($_POST['amount']); // Amount in cents
        
        if (empty($payment_method_id) || empty($booking_id) || empty($amount)) {
            throw new Exception('Missing required payment information.');
        }
        
        // Get booking details
        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, e.title as event_title 
            FROM {$wpdb->prefix}pp_bookings b 
            LEFT JOIN {$wpdb->prefix}pp_events e ON b.event_id = e.id 
            WHERE b.id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            throw new Exception('Invalid booking.');
        }
        
        // Initialize Stripe
        puzzlepath_init_stripe();
        
        // Create payment intent
        $payment_intent = \Stripe\PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'usd',
            'payment_method' => $payment_method_id,
            'confirmation_method' => 'manual',
            'confirm' => true,
            'description' => "Booking #{$booking_id} - {$booking->event_title}",
            'metadata' => [
                'booking_id' => $booking_id,
                'event_title' => $booking->event_title,
                'customer_name' => $booking->name,
                'customer_email' => $booking->email
            ]
        ]);
        
        // Handle the payment intent status
        if ($payment_intent->status === 'succeeded') {
            // Update booking status
            $wpdb->update(
                $wpdb->prefix . 'pp_bookings',
                ['payment_status' => 'paid'],
                ['id' => $booking_id],
                ['%s'],
                ['%d']
            );
            
            // Send confirmation email
            puzzlepath_send_booking_confirmation($booking_id);
            
            // Return success response
            wp_send_json_success([
                'redirect_url' => add_query_arg([
                    'booking_id' => $booking_id,
                    'status' => 'success'
                ], get_permalink(get_option('puzzlepath_confirmation_page_id')))
            ]);
        } else {
            throw new Exception('Payment failed. Please try again.');
        }
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

function puzzlepath_send_booking_confirmation($booking_id) {
    global $wpdb;
    
    // Get booking details with event information
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, e.title as event_title, e.event_date, e.location, e.price, c.discount_percent 
        FROM {$wpdb->prefix}pp_bookings b 
        LEFT JOIN {$wpdb->prefix}pp_events e ON b.event_id = e.id 
        LEFT JOIN {$wpdb->prefix}pp_coupons c ON b.coupon_code = c.code 
        WHERE b.id = %d",
        $booking_id
    ));
    
    if (!$booking) {
        return;
    }
    
    // Calculate final price
    $final_price = $booking->price;
    if (!empty($booking->discount_percent)) {
        $final_price = $final_price * (1 - ($booking->discount_percent / 100));
    }
    
    // Prepare email content
    $subject = "Booking Confirmation - {$booking->event_title}";
    
    $message = "
    <h2>Thank you for your booking!</h2>
    
    <p>Dear {$booking->name},</p>
    
    <p>Your booking for {$booking->event_title} has been confirmed and paid. Here are your booking details:</p>
    
    <div style='background: #f5f5f5; padding: 20px; margin: 20px 0;'>
        <p><strong>Booking Reference:</strong> #{$booking_id}</p>
        <p><strong>Event:</strong> {$booking->event_title}</p>
        <p><strong>Date:</strong> " . date('F j, Y g:i a', strtotime($booking->event_date)) . "</p>
        <p><strong>Location:</strong> {$booking->location}</p>
        <p><strong>Amount Paid:</strong> $" . number_format($final_price, 2) . "</p>
    </div>
    
    <p>Please keep this email for your records. If you have any questions, please don't hesitate to contact us.</p>
    
    <p>We look forward to seeing you at the event!</p>
    
    <p>Best regards,<br>PuzzlePath Team</p>
    ";
    
    // Set headers for HTML email
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
    );
    
    // Send email
    wp_mail($booking->email, $subject, $message, $headers);
    
    // Also send notification to admin
    $admin_subject = "New Booking: {$booking->event_title} (#{$booking_id})";
    wp_mail(get_option('admin_email'), $admin_subject, $message, $headers);
} 