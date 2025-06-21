<?php
/*
Plugin Name: PuzzlePath Booking
Plugin URI: https://puzzlepath.com
Description: Custom booking system for PuzzlePath events with discount codes and email confirmation.
Version: 2.1.1
Author: Andrew Baillie - Click eCommerce
Author URI: https://clickecommerce.com
Text Domain: puzzlepath-booking
*/

defined('ABSPATH') or die('No script kiddies please!');

// Register activation hook
register_activation_hook(__FILE__, 'puzzlepath_activate_plugin');
function puzzlepath_activate_plugin() {
    global $wpdb;
    
    // Create events table with hosting type
    $table_events = $wpdb->prefix . 'pp_events';
    $sql_events = "CREATE TABLE IF NOT EXISTS $table_events (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        event_date datetime DEFAULT NULL,
        location varchar(255) NOT NULL,
        price decimal(10,2) NOT NULL,
        seats int NOT NULL,
        hosting_type enum('hosted', 'self_hosted') NOT NULL DEFAULT 'hosted',
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Create bookings table with booking reference
    $table_bookings = $wpdb->prefix . 'pp_bookings';
    $sql_bookings = "CREATE TABLE IF NOT EXISTS $table_bookings (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        event_id bigint(20) NOT NULL,
        name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        coupon_code varchar(50),
        booking_status varchar(50) NOT NULL DEFAULT 'confirmed',
        booking_reference varchar(20) UNIQUE NOT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        FOREIGN KEY  (event_id) REFERENCES $table_events(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    // Create coupons table
    $table_coupons = $wpdb->prefix . 'pp_coupons';
    $sql_coupons = "CREATE TABLE IF NOT EXISTS $table_coupons (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        code varchar(50) NOT NULL UNIQUE,
        discount_percent int NOT NULL,
        max_uses int NOT NULL DEFAULT 0,
        times_used int NOT NULL DEFAULT 0,
        expires_at datetime DEFAULT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_events);
    dbDelta($sql_bookings);
    dbDelta($sql_coupons);

    // Clear any transients and caches
    wp_cache_flush();
    delete_transient('puzzlepath_plugin_installed');
}

// Register deactivation hook
register_deactivation_hook(__FILE__, 'puzzlepath_deactivate_plugin');
function puzzlepath_deactivate_plugin() {
    // Clear any transients and caches
    wp_cache_flush();
    delete_transient('puzzlepath_plugin_installed');
}

// Register uninstall hook
register_uninstall_hook(__FILE__, 'puzzlepath_uninstall_plugin');
function puzzlepath_uninstall_plugin() {
    global $wpdb;

    // Drop plugin tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pp_bookings");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pp_coupons");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pp_events");

    // Delete plugin options
    delete_option('puzzlepath_email_template');
    delete_option('puzzlepath_booking_page_id');

    // Clear any remaining transients and caches
    wp_cache_flush();
    delete_transient('puzzlepath_plugin_installed');
}

// Function to update database structure
function puzzlepath_update_database() {
    global $wpdb;
    
    // Check if hosting_type column exists in events table
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_events LIKE 'hosting_type'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events ADD COLUMN hosting_type ENUM('hosted', 'self_hosted') NOT NULL DEFAULT 'hosted'");
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events MODIFY COLUMN event_date datetime DEFAULT NULL");
    }
    
    // Check if booking_reference column exists in bookings table
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_bookings LIKE 'booking_reference'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_bookings ADD COLUMN booking_reference varchar(20) UNIQUE NOT NULL DEFAULT ''");
    }
}
add_action('plugins_loaded', 'puzzlepath_update_database');

// Function to generate unique booking reference
function puzzlepath_generate_booking_reference() {
    global $wpdb;
    
    do {
        $reference = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}pp_bookings WHERE booking_reference = %s",
            $reference
        ));
    } while ($exists > 0);
    
    return $reference;
}

// If the booking page option is not set, create the page
function puzzlepath_create_booking_page_if_needed() {
    if (get_option('puzzlepath_booking_page_id')) {
        $page = get_post(get_option('puzzlepath_booking_page_id'));
        if($page && $page->post_status === 'publish') {
            return; // Page exists and is published
        }
    }

    $page_data = array(
        'post_title'    => 'PuzzlePath Booking',
        'post_content'  => '[puzzlepath_booking_form]',
        'post_status'   => 'publish',
        'post_author'   => 1,
        'post_type'     => 'page',
        'post_name'     => 'puzzlepath-booking-page'
    );

    $page_id = wp_insert_post($page_data);
    if ($page_id) {
        update_option('puzzlepath_booking_page_id', $page_id);
    }
}
add_action('admin_init', 'puzzlepath_create_booking_page_if_needed');

// Add action to handle form submission
add_action('init', 'puzzlepath_handle_booking_submission');
function puzzlepath_handle_booking_submission() {
    if (!isset($_POST['submit_booking']) || !isset($_POST['puzzlepath_booking_nonce']) || !wp_verify_nonce($_POST['puzzlepath_booking_nonce'], 'puzzlepath_booking_form_action')) {
        return;
    }

    global $wpdb;
    
    $event_id = intval($_POST['event_id']);
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $coupon = !empty($_POST['coupon']) ? sanitize_text_field($_POST['coupon']) : null;
    
    $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pp_events WHERE id = %d", $event_id));

    $redirect_url = wp_get_referer();
    if (!$redirect_url) {
        $booking_page_id = get_option('puzzlepath_booking_page_id');
        if ($booking_page_id) {
            $redirect_url = get_permalink($booking_page_id);
        } else {
            $redirect_url = home_url('/');
        }
    }

    if ($event && $event->seats > 0) {
        $booking_reference = puzzlepath_generate_booking_reference();
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'pp_bookings',
            array(
                'event_id' => $event_id,
                'name' => $name,
                'email' => $email,
                'coupon_code' => $coupon,
                'booking_status' => 'confirmed',
                'booking_reference' => $booking_reference
            )
        );

        if ($result) {
            $booking_id = $wpdb->insert_id;
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}pp_events SET seats = seats - 1 WHERE id = %d", $event_id));
            if ($coupon) {
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}pp_coupons SET times_used = times_used + 1 WHERE code = %s", $coupon));
            }
            puzzlepath_send_confirmation_email($booking_id, $event, $name, $email, $coupon, $booking_reference);
            $redirect_url = add_query_arg(['booking_status' => 'success', 'ref' => $booking_reference], $redirect_url);
        } else {
            $redirect_url = add_query_arg('booking_status', 'dberror', $redirect_url);
        }
    } else {
        $redirect_url = add_query_arg('booking_status', 'error', $redirect_url);
    }

    wp_safe_redirect($redirect_url);
    exit;
}

// Add shortcode for booking form
add_shortcode('puzzlepath_booking_form', 'puzzlepath_booking_form');
function puzzlepath_booking_form() {
    global $wpdb;
    $table_events = $wpdb->prefix . 'pp_events';
    
    $message = '';
    if (isset($_GET['booking_status'])) {
        if ($_GET['booking_status'] === 'success' && isset($_GET['ref'])) {
            $ref = sanitize_text_field($_GET['ref']);
            $message = '<div class="booking-success"><p>✅ Booking confirmed! Your reference is <strong>' . esc_html($ref) . '</strong>. An email is on its way.</p></div>';
        } elseif ($_GET['booking_status'] === 'dberror') {
            $message = '<div class="booking-error"><p>❌ Database error. Could not save your booking.</p></div>';
        } elseif ($_GET['booking_status'] === 'error') {
            $message = '<div class="booking-error"><p>❌ Invalid event or event is fully booked.</p></div>';
        }
    }
    
    $events = $wpdb->get_results("SELECT * FROM $table_events WHERE seats > 0 ORDER BY event_date ASC, title ASC");
    
    ob_start();
    ?>
    <div class="puzzlepath-booking-form">
        <h2>Book an Event</h2>
        
        <?php echo $message; ?>

        <form method="post" action="">
            <?php wp_nonce_field('puzzlepath_booking_form_action', 'puzzlepath_booking_nonce'); ?>
            <div class="form-group">
                <label for="event">Select Event:</label>
                <select name="event_id" id="event" required>
                    <option value="">Choose an event...</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo esc_attr($event->id); ?>" data-price="<?php echo esc_attr($event->price); ?>" data-hosting="<?php echo esc_attr($event->hosting_type); ?>">
                            <?php echo esc_html($event->title); ?> 
                            (<?php echo $event->hosting_type === 'hosted' ? 'Hosted' : 'Self Hosted (App)'; ?>)
                            <?php if ($event->hosting_type === 'hosted' && $event->event_date): ?>
                                - <?php echo date('F j, Y g:i a', strtotime($event->event_date)); ?>
                            <?php endif; ?>
                            - $<?php echo number_format($event->price, 2); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="name">Your Name:</label>
                <input type="text" name="name" id="name" required>
            </div>
            <div class="form-group">
                <label for="email">Your Email:</label>
                <input type="email" name="email" id="email" required>
            </div>
            <div class="form-group coupon-group">
                <div class="coupon-input">
                    <label for="coupon">Coupon Code (optional):</label>
                    <input type="text" name="coupon" id="coupon">
                </div>
                <button type="button" id="apply-coupon-btn">Apply</button>
            </div>
            <div id="coupon-result"></div>

            <div id="price-display" style="display:none;">
                <h4>Price Summary</h4>
                <p class="price-line price-original-line">Original Price: <span class="price-original"></span></p>
                <p class="price-line price-discount-line" style="display:none;">Discount: <span class="price-discount"></span></p>
                <p class="price-line price-final-line"><strong>Total Price:</strong> <span class="price-final"></span></p>
            </div>

            <button type="submit" name="submit_booking">Book Now</button>
        </form>
    </div>
    <style>
        .puzzlepath-booking-form { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #FFA500; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group select, .form-group input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background-color: white; }
        .coupon-group { display: flex; align-items: flex-end; gap: 10px; }
        .coupon-input { flex-grow: 1; }
        #apply-coupon-btn { padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; height: 35px; }
        #apply-coupon-btn:hover { background: #0056b3; }
        #apply-coupon-btn:disabled { background: #cccccc; }
        #coupon-result { margin: 10px 0; padding: 10px; border-radius: 4px; display: none; }
        #coupon-result.success { background-color: #d4edda; color: #155724; }
        #coupon-result.error { background-color: #f8d7da; color: #721c24; }
        #price-display { margin-top: 20px; padding: 15px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; }
        #price-display h4 { margin-top: 0; }
        .price-line { margin: 5px 0; display: flex; justify-content: space-between; }
        .price-final-line { font-size: 1.1em; border-top: 1px solid #ccc; padding-top: 10px; margin-top: 10px; }
        button[type="submit"] { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin-top: 20px; }
        button[type="submit"]:hover { background: #218838; }
        .booking-success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .booking-error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
    </style>
    <?php
    
    return ob_get_clean();
}

// Function to send confirmation email
function puzzlepath_send_confirmation_email($booking_id, $event, $name, $email, $coupon = null, $booking_reference = null) {
    $subject = 'Booking Confirmation - ' . $event->title;
    
    $discount_amount = 0;
    if ($coupon) {
        global $wpdb;
        $coupon_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pp_coupons WHERE code = %s", $coupon));
        if ($coupon_data) {
            $discount_amount = ($event->price * $coupon_data->discount_percent) / 100;
        }
    }
    $final_price = $event->price - $discount_amount;
    
    $message_body = "Dear {$name},\n\n";
    $message_body .= "Thank you for your booking!\n\n";
    $message_body .= "Booking Details:\n";
    $message_body .= "Reference: {$booking_reference}\n";
    $message_body .= "Event: {$event->title}\n";
    if ($event->hosting_type === 'hosted' && $event->event_date) {
        $message_body .= "Date: " . date('F j, Y g:i a', strtotime($event->event_date)) . "\n";
    }
    $message_body .= "Location: {$event->location}\n";
    $message_body .= "Price: $" . number_format($event->price, 2) . "\n";
    
    if ($coupon) {
        $message_body .= "Coupon Used: {$coupon}\n";
        $message_body .= "Discount: -$" . number_format($discount_amount, 2) . "\n";
        $message_body .= "Final Price: $" . number_format($final_price, 2) . "\n";
    }
    
    $message_body .= "\nWe look forward to seeing you!\n\n";
    $message_body .= "Regards,\nPuzzlePath Team";
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    wp_mail($email, $subject, $message_body, $headers);
}

// Enqueue scripts and styles
function puzzlepath_booking_scripts() {
    wp_enqueue_script('puzzlepath-booking', plugin_dir_url(__FILE__) . 'js/booking-form.js', array('jquery'), '1.0.2', true);
    wp_localize_script('puzzlepath-booking', 'puzzlepath_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('puzzlepath-coupon-nonce')
    ));
}
add_action('wp_enqueue_scripts', 'puzzlepath_booking_scripts');

// AJAX handler for applying coupon
function puzzlepath_apply_coupon_callback() {
    check_ajax_referer('puzzlepath-coupon-nonce', 'nonce');

    global $wpdb;
    $coupon_code = sanitize_text_field($_POST['coupon_code']);
    $event_id = intval($_POST['event_id']);

    if (empty($coupon_code) || empty($event_id)) {
        wp_send_json_error(array('message' => 'Please select an event and enter a coupon code.'));
    }

    // Get event price
    $event = $wpdb->get_row($wpdb->prepare("SELECT price FROM {$wpdb->prefix}pp_events WHERE id = %d", $event_id));
    if (!$event) {
        wp_send_json_error(array('message' => 'Invalid event.'));
    }
    $original_price = (float) $event->price;

    // Get coupon details
    $coupon = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pp_coupons WHERE code = %s", $coupon_code));
    
    // Validate coupon
    if (!$coupon) {
        wp_send_json_error(array('message' => 'Invalid coupon code.'));
    }
    if ($coupon->expires_at && strtotime($coupon->expires_at) < time()) {
        wp_send_json_error(array('message' => 'This coupon has expired.'));
    }
    if ($coupon->max_uses > 0 && $coupon->times_used >= $coupon->max_uses) {
        wp_send_json_error(array('message' => 'This coupon has reached its usage limit.'));
    }

    $discount_percent = (int) $coupon->discount_percent;
    $discount_amount = ($original_price * $discount_percent) / 100;
    $new_price = $original_price - $discount_amount;

    wp_send_json_success(array(
        'original_price' => number_format($original_price, 2),
        'discount_amount' => number_format($discount_amount, 2),
        'new_price' => number_format($new_price, 2),
        'discount_percent' => $discount_percent,
        'message' => "Success! {$discount_percent}% discount applied."
    ));
}
add_action('wp_ajax_puzzlepath_apply_coupon', 'puzzlepath_apply_coupon_callback');
add_action('wp_ajax_nopriv_puzzlepath_apply_coupon', 'puzzlepath_apply_coupon_callback');

// Include required files
require_once(plugin_dir_path(__FILE__) . 'includes/settings.php');
require_once(plugin_dir_path(__FILE__) . 'includes/events.php');
require_once(plugin_dir_path(__FILE__) . 'includes/coupons.php');