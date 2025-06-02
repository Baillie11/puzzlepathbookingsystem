<?php
/*
Plugin Name: PuzzlePath Booking
Description: Custom booking system for PuzzlePath events with Stripe, discount codes, and email confirmation.
Version: 1.1.15
Author: Andrew Baillie - Click eCommerce
*/

defined('ABSPATH') or die('No script kiddies please!');

// Include required files
require_once(plugin_dir_path(__FILE__) . 'includes/settings.php');
require_once(plugin_dir_path(__FILE__) . 'includes/payment-processing.php');

// Register activation hook
register_activation_hook(__FILE__, 'puzzlepath_booking_install');

function puzzlepath_booking_install() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Create tables
    $table_events = $wpdb->prefix . 'pp_events';
    $table_bookings = $wpdb->prefix . 'pp_bookings';
    $table_coupons = $wpdb->prefix . 'pp_coupons';

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql_events = "CREATE TABLE $table_events (
        id INT NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        event_date DATETIME NOT NULL,
        location VARCHAR(255),
        price DECIMAL(10,2),
        seats INT,
        PRIMARY KEY(id)
    ) $charset_collate;";

    $sql_bookings = "CREATE TABLE $table_bookings (
        id INT NOT NULL AUTO_INCREMENT,
        event_id INT NOT NULL,
        name VARCHAR(255),
        email VARCHAR(255),
        phone VARCHAR(20),
        payment_status VARCHAR(50),
        coupon_code VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id)
    ) $charset_collate;";

    $sql_coupons = "CREATE TABLE $table_coupons (
        id INT NOT NULL AUTO_INCREMENT,
        code VARCHAR(50) UNIQUE,
        discount_percent INT,
        max_uses INT,
        times_used INT DEFAULT 0,
        expires_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id)
    ) $charset_collate;";

    dbDelta($sql_events);
    dbDelta($sql_bookings);
    dbDelta($sql_coupons);

    // Create necessary pages
    if (!get_option('puzzlepath_payment_page_id')) {
        $payment_page = array(
            'post_title'    => 'Payment',
            'post_content'  => '',
            'post_status'   => 'publish',
            'post_type'     => 'page'
        );
        $payment_page_id = wp_insert_post($payment_page);
        update_option('puzzlepath_payment_page_id', $payment_page_id);
        update_post_meta($payment_page_id, '_wp_page_template', 'templates/payment-page.php');
    }

    if (!get_option('puzzlepath_confirmation_page_id')) {
        $confirmation_page = array(
            'post_title'    => 'Booking Confirmation',
            'post_content'  => '',
            'post_status'   => 'publish',
            'post_type'     => 'page'
        );
        $confirmation_page_id = wp_insert_post($confirmation_page);
        update_option('puzzlepath_confirmation_page_id', $confirmation_page_id);
        update_post_meta($confirmation_page_id, '_wp_page_template', 'templates/confirmation-page.php');
    }
}

// Register template files
add_filter('template_include', 'puzzlepath_load_templates');
function puzzlepath_load_templates($template) {
    if (is_page()) {
        $page_template = get_post_meta(get_the_ID(), '_wp_page_template', true);
        
        if ($page_template === 'templates/payment-page.php') {
            return plugin_dir_path(__FILE__) . 'templates/payment-page.php';
        }
        
        if ($page_template === 'templates/confirmation-page.php') {
            return plugin_dir_path(__FILE__) . 'templates/confirmation-page.php';
        }
    }
    return $template;
}

add_action('admin_menu', 'puzzlepath_booking_admin_menu');
function puzzlepath_booking_admin_menu() {
    add_menu_page('PuzzlePath Events', 'PuzzlePath Events', 'manage_options', 'puzzlepath-events', 'puzzlepath_events_page');
    add_submenu_page('puzzlepath-events', 'Coupons', 'Coupons', 'manage_options', 'puzzlepath-coupons', 'puzzlepath_coupons_page');
}

function puzzlepath_events_page() {
    echo '<div class="wrap">';
    echo '<h1>PuzzlePath Events</h1>';
    
    // Add shortcode notice box
    echo '<div class="notice notice-info" style="padding: 15px; margin-bottom: 20px; border-left-color: #ffa500;">';
    echo '<h3 style="margin-top: 0;">📝 How to Display the Booking Form</h3>';
    echo '<p>Add this shortcode to any page or post where you want the booking form to appear:</p>';
    echo '<code style="background: #f5f5f5; padding: 8px 12px; border-radius: 4px; display: inline-block; margin: 5px 0;">[puzzlepath_booking]</code>';
    echo '</div>';
    
    global $wpdb;
    $table_events = $wpdb->prefix . 'pp_events';

    if (isset($_POST['pp_update_event'])) {
        $wpdb->update(
            $table_events,
            [
                'title' => sanitize_text_field($_POST['title']),
                'event_date' => sanitize_text_field($_POST['event_date']),
                'location' => sanitize_text_field($_POST['location']),
                'price' => floatval($_POST['price']),
                'seats' => intval($_POST['seats'])
            ],
            [ 'id' => intval($_POST['event_id']) ]
        );
        echo '<div class="updated"><p>Event updated successfully.</p></div>';
    }

    if (isset($_GET['delete_event'])) {
        $wpdb->delete($table_events, ['id' => intval($_GET['delete_event'])]);
        echo '<div class="updated"><p>Event deleted successfully.</p></div>';
    }

    if (isset($_POST['pp_add_event'])) {
        $wpdb->insert($table_events, [
            'title' => sanitize_text_field($_POST['title']),
            'event_date' => sanitize_text_field($_POST['event_date']),
            'location' => sanitize_text_field($_POST['location']),
            'price' => floatval($_POST['price']),
            'seats' => intval($_POST['seats'])
        ]);
        echo '<div class="updated"><p>Event added successfully.</p></div>';
    }

    $events = $wpdb->get_results("SELECT * FROM $table_events ORDER BY event_date ASC");

    echo '<form method="post">';
    echo '<h2>Add New Event</h2>';
    echo '<input type="text" name="title" placeholder="Event Title" required style="width: 100%; margin-bottom: 10px;" />';
    echo '<input type="datetime-local" name="event_date" required style="width: 100%; margin-bottom: 10px;" />';
    echo '<input type="text" name="location" placeholder="Location" style="width: 100%; margin-bottom: 10px;" />';
    echo '<input type="number" name="price" placeholder="Price" step="0.01" style="width: 100%; margin-bottom: 10px;" />';
    echo '<input type="number" name="seats" placeholder="Seats Available" style="width: 100%; margin-bottom: 10px;" />';
    echo '<button type="submit" name="pp_add_event" class="button button-primary">Add Event</button>';
    echo '</form>';

    echo '<h2 style="margin-top: 40px;">Existing Events</h2>';
    echo '<table class="widefat"><thead><tr><th>ID</th><th>Title</th><th>Date</th><th>Location</th><th>Price</th><th>Seats</th><th>Actions</th></tr></thead><tbody>';
    foreach ($events as $event) {
        echo '<tr>';
        echo '<td>' . intval($event->id) . '</td>';
        echo '<td>' . esc_html($event->title) . '</td>';
        echo '<td>' . esc_html($event->event_date) . '</td>';
        echo '<td>' . esc_html($event->location) . '</td>';
        echo '<td>$' . number_format($event->price, 2) . '</td>';
        echo '<td>' . intval($event->seats) . '</td>';
        echo '<td>';
        echo '<a href="?page=puzzlepath-events&edit_event=' . intval($event->id) . '" class="button">Edit</a> ';
       echo '<a href="?page=puzzlepath-events&delete_event=' . intval($event->id) . '" class="button" onclick="return confirm(\'Are you sure you want to delete this event?\');">Delete</a>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    if (isset($_GET['edit_event'])) {
        $edit_id = intval($_GET['edit_event']);
        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_events WHERE id = %d", $edit_id));

        if ($event) {
            echo '<h2 style="margin-top: 40px;">Edit Event</h2>';
            echo '<form method="post">';
            echo '<input type="hidden" name="event_id" value="' . $event->id . '" />';
            echo '<input type="text" name="title" value="' . esc_attr($event->title) . '" required style="width: 100%; margin-bottom: 10px;" />';
            echo '<input type="datetime-local" name="event_date" value="' . esc_attr(date('Y-m-d\TH:i', strtotime($event->event_date))) . '" required style="width: 100%; margin-bottom: 10px;" />';
            echo '<input type="text" name="location" value="' . esc_attr($event->location) . '" style="width: 100%; margin-bottom: 10px;" />';
            echo '<input type="number" name="price" value="' . esc_attr($event->price) . '" step="0.01" style="width: 100%; margin-bottom: 10px;" />';
            echo '<input type="number" name="seats" value="' . esc_attr($event->seats) . '" style="width: 100%; margin-bottom: 10px;" />';
            echo '<button type="submit" name="pp_update_event" class="button button-primary">Update Event</button>';
            echo '</form>';
        }
    }

    echo '</div>';
}

function puzzlepath_coupons_page() {
    echo '<div class="wrap">';
    echo '<h1>PuzzlePath Coupons</h1>';
    
    global $wpdb;
    $table_coupons = $wpdb->prefix . 'pp_coupons';

    if (isset($_POST['pp_add_coupon'])) {
        $code = sanitize_text_field($_POST['code']);
        $discount_percent = intval($_POST['discount_percent']);
        $max_uses = intval($_POST['max_uses']);
        $expires_at = sanitize_text_field($_POST['expires_at']);

        // Check if coupon code already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_coupons WHERE code = %s",
            $code
        ));

        if ($existing) {
            echo '<div class="error"><p>Error: Coupon code already exists.</p></div>';
        } else {
            $result = $wpdb->insert(
                $table_coupons,
                [
                    'code' => $code,
                    'discount_percent' => $discount_percent,
                    'max_uses' => $max_uses,
                    'expires_at' => $expires_at,
                    'times_used' => 0
                ],
                ['%s', '%d', '%d', '%s', '%d']
            );

            if ($result === false) {
                echo '<div class="error"><p>Error: Failed to add coupon. Please try again.</p></div>';
            } else {
                echo '<div class="updated"><p>Coupon added successfully.</p></div>';
            }
        }
    }

    if (isset($_GET['delete_coupon'])) {
        $delete_id = intval($_GET['delete_coupon']);
        $result = $wpdb->delete($table_coupons, ['id' => $delete_id], ['%d']);
        
        if ($result === false) {
            echo '<div class="error"><p>Error: Failed to delete coupon.</p></div>';
        } else {
            echo '<div class="updated"><p>Coupon deleted successfully.</p></div>';
        }
    }

    echo '<form method="post">';
    echo '<h2>Add New Coupon</h2>';

    echo '<label for="code"><strong>Coupon Code:</strong><br><small>What users will enter at checkout (e.g. PUZZLE10)</small></label><br>';
    echo '<input type="text" name="code" id="code" required style="width: 100%; margin-bottom: 15px;" />';

    echo '<label for="discount_percent"><strong>Discount Percentage:</strong><br><small>How much this coupon will take off (e.g. 10 for 10%)</small></label><br>';
    echo '<input type="number" name="discount_percent" id="discount_percent" min="1" max="100" required style="width: 100%; margin-bottom: 15px;" />';

    echo '<label for="max_uses"><strong>Maximum Uses:</strong><br><small>Total number of times this coupon can be used</small></label><br>';
    echo '<input type="number" name="max_uses" id="max_uses" required style="width: 100%; margin-bottom: 15px;" />';

    echo '<label for="expires_at"><strong>Expiry Date:</strong><br><small>Date/time after which the coupon will no longer be valid</small></label><br>';
    echo '<input type="datetime-local" name="expires_at" id="expires_at" required style="width: 100%; margin-bottom: 20px;" />';

    echo '<button type="submit" name="pp_add_coupon" class="button button-primary">Add Coupon</button>';
    echo '</form>';

    echo '<h2 style="margin-top: 40px;">Existing Coupons</h2>';
    $coupons = $wpdb->get_results("SELECT * FROM $table_coupons ORDER BY created_at DESC");
    
    if ($coupons) {
        echo '<table class="widefat"><thead><tr><th>Code</th><th>Discount</th><th>Uses</th><th>Max Uses</th><th>Expires</th><th>Actions</th></tr></thead><tbody>';
        foreach ($coupons as $coupon) {
            echo '<tr>';
            echo '<td>' . esc_html($coupon->code) . '</td>';
            echo '<td>' . esc_html($coupon->discount_percent) . '%</td>';
            echo '<td>' . esc_html($coupon->times_used) . '</td>';
            echo '<td>' . esc_html($coupon->max_uses) . '</td>';
            echo '<td>' . esc_html($coupon->expires_at) . '</td>';
            echo '<td>';
            echo '<a href="?page=puzzlepath-coupons&delete_coupon=' . intval($coupon->id) . '" class="button" onclick="return confirm(\'Are you sure you want to delete this coupon?\');">Delete</a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No coupons found.</p>';
    }
    
    echo '</div>';
}

// Enqueue JS for AJAX coupon validation
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('puzzlepath-booking-js', plugin_dir_url(__FILE__) . 'puzzlepath-booking.js', array('jquery'), '1.1.11', true);
    wp_localize_script('puzzlepath-booking-js', 'puzzlepathBooking', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('pp_apply_coupon')
    ));
});

// AJAX handler for coupon validation
add_action('wp_ajax_pp_apply_coupon', 'pp_apply_coupon_ajax');
add_action('wp_ajax_nopriv_pp_apply_coupon', 'pp_apply_coupon_ajax');
function pp_apply_coupon_ajax() {
    try {
        // Prevent any unwanted output
        @ob_clean();
        
        // Verify nonce
        if (!check_ajax_referer('pp_apply_coupon', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.']);
            exit;
        }

        global $wpdb;
        $code = isset($_POST['coupon']) ? sanitize_text_field($_POST['coupon']) : '';
        
        if (empty($code)) {
            wp_send_json_error(['message' => 'Please enter a coupon code.']);
            exit;
        }

        $coupon = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pp_coupons WHERE code = %s AND (expires_at > NOW() OR expires_at IS NULL) AND (times_used < max_uses OR max_uses = 0)",
            $code
        ));

        if ($wpdb->last_error) {
            wp_send_json_error(['message' => 'Database error: ' . $wpdb->last_error]);
            exit;
        }

        if ($coupon) {
            wp_send_json_success([
                'message' => 'Coupon applied! ' . intval($coupon->discount_percent) . '% discount.',
                'discount_percent' => intval($coupon->discount_percent)
            ]);
        } else {
            wp_send_json_error(['message' => 'Invalid or expired coupon code.']);
        }
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

add_shortcode('puzzlepath_booking', 'puzzlepath_booking_form');
function puzzlepath_booking_form() {
    if (!isset($_POST['pp_submit_booking'])) {
        ob_start(); ?>
        <form method="post" class="puzzlepath-booking-form">
            <label for="event">Select Event:</label>
            <select name="event" id="event"><?php puzzlepath_render_event_options(); ?></select><br>
            <input type="text" name="name" placeholder="Your Name" required><br>
            <input type="email" name="email" placeholder="Your Email" required><br>
            <input type="tel" name="phone" placeholder="Your Contact Number" required><br>
            <div style="display:flex;gap:8px;align-items:center;">
                <input type="text" name="coupon" id="pp-coupon" placeholder="Coupon Code (Optional)" style="flex:1;">
                <button type="button" id="pp-apply-coupon" style="background:#ffa500;color:#fff;border:none;padding:10px 16px;border-radius:6px;cursor:pointer;">Apply</button>
            </div>
            <div id="pp-coupon-feedback" style="min-height:24px;margin-bottom:8px;color:#d2691e;"></div>
            <button type="submit" name="pp_submit_booking">Book Now</button>
        </form>
        <style>
            .puzzlepath-booking-form {
                background-color: #fff5e6;
                padding: 20px;
                border-radius: 12px;
                box-shadow: 0 0 10px rgba(255, 149, 0, 0.3);
                max-width: 400px;
                margin: 20px auto;
                font-family: 'Arial', sans-serif;
            }
            .puzzlepath-booking-form input,
            .puzzlepath-booking-form select {
                width: 100%;
                padding: 10px;
                margin: 8px 0;
                border: 2px solid #ffa500;
                border-radius: 6px;
                background: #fff;
            }
            .puzzlepath-booking-form button[type="submit"] {
                background-color: #ff8800;
                color: white;
                border: none;
                padding: 12px;
                font-size: 16px;
                border-radius: 6px;
                cursor: pointer;
                transition: background 0.3s;
            }
            .puzzlepath-booking-form button[type="submit"]:hover {
                background-color: #e67600;
            }
        </style>
        <?php return ob_get_clean();
    } else {
        // Handle form submission
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'pp_bookings';
        $table_events = $wpdb->prefix . 'pp_events';
        
        $event_id = intval($_POST['event']);
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $coupon_code = isset($_POST['coupon']) ? sanitize_text_field($_POST['coupon']) : '';
        
        // Verify event exists and has available seats
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_events WHERE id = %d",
            $event_id
        ));
        
        if (!$event) {
            return '<div class="error">Error: Invalid event selected.</div>';
        }
        
        // Check if coupon is valid if provided
        if (!empty($coupon_code)) {
            $coupon = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}pp_coupons WHERE code = %s AND (expires_at > NOW() OR expires_at IS NULL) AND (times_used < max_uses OR max_uses = 0)",
                $coupon_code
            ));
            
            if (!$coupon) {
                return '<div class="error">Error: Invalid or expired coupon code.</div>';
            }
        }
        
        // Insert booking
        $result = $wpdb->insert(
            $table_bookings,
            [
                'event_id' => $event_id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'payment_status' => 'pending',
                'coupon_code' => $coupon_code
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            return '<div class="error">Error: Failed to create booking. Please try again.</div>';
        }
        
        // Update coupon usage if applicable
        if (!empty($coupon_code) && $coupon) {
            $wpdb->update(
                $wpdb->prefix . 'pp_coupons',
                ['times_used' => $coupon->times_used + 1],
                ['id' => $coupon->id],
                ['%d'],
                ['%d']
            );
        }
        
        // Redirect to payment page with booking_id and name
        $booking_id = $wpdb->insert_id;
        $redirect_url = site_url('/payment/?booking_id=' . $booking_id . '&name=' . urlencode($name));
        wp_redirect($redirect_url);
        exit;
    }
}

function puzzlepath_render_event_options() {
    global $wpdb;
    $table_events = $wpdb->prefix . 'pp_events';
    $events = $wpdb->get_results("SELECT * FROM $table_events ORDER BY event_date ASC");
    foreach ($events as $event) {
        echo '<option value="' . esc_attr($event->id) . '">' . esc_html($event->title . ' - ' . date('M j, Y g:i a', strtotime($event->event_date))) . '</option>';
    }
} 