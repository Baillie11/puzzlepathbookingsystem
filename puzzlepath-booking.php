<?php
/**
 * Plugin Name: PuzzlePath Booking
 * Description: A custom booking plugin for PuzzlePath with unified app integration.
 * Version: 2.8.3
 * Author: Andrew Baillie
 */

defined('ABSPATH') or die('No script kiddies please!');

/**
 * Activation hook to create/update database tables.
 */
function puzzlepath_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Events Table - Enhanced with hunt codes
    $table_name = $wpdb->prefix . 'pp_events';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        title varchar(255) NOT NULL,
        hunt_code varchar(10) DEFAULT NULL,
        hunt_name varchar(255) DEFAULT NULL,
        hosting_type varchar(20) DEFAULT 'hosted' NOT NULL,
        event_date datetime,
        location varchar(255) NOT NULL,
        price float NOT NULL,
        seats int(11) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY hunt_code (hunt_code)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Coupons Table
    $table_name = $wpdb->prefix . 'pp_coupons';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        code varchar(50) NOT NULL,
        discount_percent int(3) NOT NULL,
        max_uses int(11) DEFAULT 0 NOT NULL,
        times_used int(11) DEFAULT 0 NOT NULL,
        expires_at datetime,
        PRIMARY KEY  (id),
        UNIQUE KEY code (code)
    ) $charset_collate;";
    dbDelta($sql);

    // Bookings Table - Enhanced for unified app compatibility
    $table_name = $wpdb->prefix . 'pp_bookings';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        event_id mediumint(9) NOT NULL,
        hunt_id varchar(10) DEFAULT NULL,
        customer_name varchar(255) NOT NULL,
        customer_email varchar(255) NOT NULL,
        participant_names text DEFAULT NULL,
        tickets int(11) NOT NULL,
        total_price float NOT NULL,
        coupon_id mediumint(9),
        payment_status varchar(50) DEFAULT 'pending' NOT NULL,
        stripe_payment_intent_id varchar(255),
        booking_code varchar(25),
        booking_date date DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);
    
    
    // Create compatibility view for unified app
    $view_name = $wpdb->prefix . 'pp_bookings_unified';
    $wpdb->query("DROP VIEW IF EXISTS $view_name");
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $events_table = $wpdb->prefix . 'pp_events';
    $wpdb->query("CREATE VIEW $view_name AS 
        SELECT 
            b.id,
            b.booking_code,
            b.hunt_id,
            e.hunt_code,
            e.hunt_name,
            e.title as event_title,
            e.location,
            e.event_date,
            b.customer_name,
            b.customer_email,
            b.participant_names,
            b.tickets as participant_count,
            b.total_price,
            b.booking_date,
            b.created_at,
            b.payment_status
        FROM $bookings_table b
        LEFT JOIN $events_table e ON b.event_id = e.id");
    
    // Force database schema update for event_date column
    $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events MODIFY COLUMN event_date datetime DEFAULT NULL");
    
    // Fix created_at column for events table
    $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events MODIFY COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL");
    
    // Add duration_minutes column if it doesn't exist
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_events LIKE 'duration_minutes'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events ADD COLUMN duration_minutes int(11) DEFAULT NULL AFTER price");
    }
    
    // Add medal_image_url column if it doesn't exist
    $medal_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_events LIKE 'medal_image_url'");
    if (empty($medal_column_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events ADD COLUMN medal_image_url varchar(500) DEFAULT NULL AFTER duration_minutes");
    }
    
    // Add display_on_site column if it doesn't exist
    $display_column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}pp_events LIKE 'display_on_site'");
    if (empty($display_column_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}pp_events ADD COLUMN display_on_site tinyint(1) DEFAULT 0 AFTER medal_image_url");
    }
    
    // Ensure unified app compatibility by updating existing bookings
    puzzlepath_fix_unified_app_compatibility();
    
    update_option('puzzlepath_booking_version', '2.8.3');
}

register_activation_hook(__FILE__, 'puzzlepath_activate');

/**
 * Fix unified app compatibility for existing bookings
 */
function puzzlepath_fix_unified_app_compatibility() {
    global $wpdb;
    
    // Update existing bookings to have correct hunt_id from events
    $wpdb->query("
        UPDATE {$wpdb->prefix}pp_bookings b 
        LEFT JOIN {$wpdb->prefix}pp_events e ON b.event_id = e.id 
        SET b.hunt_id = e.hunt_code 
        WHERE b.hunt_id IS NULL OR b.hunt_id = '' OR b.hunt_id != e.hunt_code
    ");
    
    // Recreate the unified view with better field mapping
    $view_name = $wpdb->prefix . 'pp_bookings_unified';
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $events_table = $wpdb->prefix . 'pp_events';
    
    $wpdb->query("DROP VIEW IF EXISTS $view_name");
    $wpdb->query("CREATE VIEW $view_name AS 
        SELECT 
            b.id,
            b.booking_code,
            COALESCE(b.hunt_id, e.hunt_code) as hunt_id,
            e.hunt_code,
            e.hunt_name,
            e.title as event_title,
            e.location,
            e.event_date,
            b.customer_name,
            b.customer_email,
            b.participant_names,
            b.tickets as participant_count,
            b.total_price,
            b.booking_date,
            b.created_at,
            b.payment_status,
            CASE 
                WHEN b.payment_status = 'paid' THEN 'confirmed'
                WHEN b.payment_status = 'pending' THEN 'pending'
                ELSE 'cancelled'
            END as status
        FROM $bookings_table b
        LEFT JOIN $events_table e ON b.event_id = e.id
        WHERE b.payment_status IN ('paid', 'pending')");
}

/**
 * Check if database needs updating on plugin load.
 */
function puzzlepath_update_db_check() {
    $current_version = get_option('puzzlepath_booking_version', '1.0');
    if (version_compare($current_version, '2.8.3', '<')) {
        puzzlepath_activate();
        // Generate hunt codes for existing events that don't have them
        puzzlepath_generate_missing_hunt_codes();
        // Update payment statuses for existing bookings
        puzzlepath_update_payment_statuses();
    }
}
add_action('plugins_loaded', 'puzzlepath_update_db_check');

/**
 * Display admin notice after payment status migration
 */
function puzzlepath_payment_migration_admin_notice() {
    $migrated_count = get_transient('puzzlepath_payment_migration_notice');
    if ($migrated_count) {
        delete_transient('puzzlepath_payment_migration_notice');
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>PuzzlePath Booking:</strong> Successfully updated ' . $migrated_count . ' booking(s) from "succeeded" to "paid" status. Your revenue calculations should now work correctly.</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'puzzlepath_payment_migration_admin_notice');

/**
 * Manual migration function - can be called via URL parameter
 */
function puzzlepath_manual_payment_migration() {
    if (isset($_GET['puzzlepath_migrate']) && current_user_can('manage_options')) {
        if (wp_verify_nonce($_GET['nonce'], 'puzzlepath_migrate_payments')) {
            puzzlepath_update_payment_statuses();
            wp_redirect(admin_url('admin.php?page=puzzlepath-bookings&message=migration_complete'));
            exit;
        }
    }
}
add_action('admin_init', 'puzzlepath_manual_payment_migration');

/**
 * Generate unique hunt code based on event details
 * Format: First letter of each word in title + location abbreviation + sequential number
 * Example: "Escape Room Adventure" in "Brisbane" -> "ERAB1", "ERAB2", etc.
 */
function puzzlepath_generate_hunt_code($event_data) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    
    // Get first letter of each word in title (max 3 letters)
    $title_words = explode(' ', $event_data['title']);
    $title_prefix = '';
    foreach ($title_words as $word) {
        if (strlen($title_prefix) < 3 && !empty($word)) {
            $title_prefix .= strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $word), 0, 1));
        }
    }
    
    // Ensure we have at least 1 character from title, pad if needed
    if (empty($title_prefix)) {
        $title_prefix = 'E'; // Default to 'E' for Event
    }
    
    // Get location abbreviation (first 2 letters, uppercase)
    $location_prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $event_data['location']), 0, 2));
    if (empty($location_prefix)) {
        $location_prefix = 'XX';
    } elseif (strlen($location_prefix) == 1) {
        $location_prefix .= 'X';
    }
    
    // Generate base pattern (e.g., "ERABR")
    $base_pattern = $title_prefix . $location_prefix;
    
    // Find the next sequential number for this pattern
    $existing_codes = $wpdb->get_col($wpdb->prepare(
        "SELECT hunt_code FROM $events_table WHERE hunt_code LIKE %s ORDER BY hunt_code DESC",
        $base_pattern . '%'
    ));
    
    $next_number = 1;
    if (!empty($existing_codes)) {
        foreach ($existing_codes as $code) {
            // Extract number from end of code
            $number = intval(preg_replace('/[^0-9]/', '', substr($code, strlen($base_pattern))));
            if ($number >= $next_number) {
                $next_number = $number + 1;
            }
        }
    }
    
    // Format as base + number (e.g., "ERABR1", "ERABR2")
    // Keep within 10 character limit
    $full_code = $base_pattern . $next_number;
    if (strlen($full_code) > 10) {
        // Truncate base pattern if needed
        $available_chars = 10 - strlen($next_number);
        $base_pattern = substr($base_pattern, 0, $available_chars);
        $full_code = $base_pattern . $next_number;
    }
    
    return $full_code;
}

/**
 * Update payment statuses from 'succeeded' to 'paid' for consistency
 */
function puzzlepath_update_payment_statuses() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    
    // Update all 'succeeded' statuses to 'paid'
    $wpdb->query("
        UPDATE {$bookings_table}
        SET payment_status = 'paid'
        WHERE payment_status = 'succeeded'
    ");
    
    // Log the status update
    $rows_affected = $wpdb->rows_affected;
    error_log("PuzzlePath payment status migration: Updated {$rows_affected} bookings from 'succeeded' to 'paid'");
    
    // Show admin notice if any updates were made
    if ($rows_affected > 0) {
        set_transient('puzzlepath_payment_migration_notice', $rows_affected, 30);
    }
}

/**
 * Generate hunt codes for existing events that don't have them
 */
function puzzlepath_generate_missing_hunt_codes() {
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    
    // Get all events without hunt codes
    $events = $wpdb->get_results(
        "SELECT * FROM $events_table WHERE hunt_code IS NULL OR hunt_code = ''"
    );
    
    foreach ($events as $event) {
        $event_data = [
            'title' => $event->title,
            'location' => $event->location
        ];
        
        $hunt_code = puzzlepath_generate_hunt_code($event_data);
        
        // Also generate a hunt name if it doesn't exist
        $hunt_name = !empty($event->hunt_name) ? $event->hunt_name : $event->title . ' - ' . $event->location;
        
        $wpdb->update(
            $events_table,
            [
                'hunt_code' => $hunt_code,
                'hunt_name' => $hunt_name
            ],
            ['id' => $event->id]
        );
    }
}

/**
 * Centralized function to create all admin menus.
 */
function puzzlepath_register_admin_menus() {
    add_menu_page('PuzzlePath Bookings', 'PuzzlePath', 'manage_options', 'puzzlepath-booking', 'puzzlepath_events_page', 'dashicons-tickets-alt', 20);
    add_submenu_page('puzzlepath-booking', 'Bookings', 'Bookings', 'manage_options', 'puzzlepath-bookings', 'puzzlepath_bookings_page');
    add_submenu_page('puzzlepath-booking', 'Events', 'Events', 'manage_options', 'puzzlepath-events', 'puzzlepath_events_page');
    add_submenu_page('puzzlepath-booking', 'Coupons', 'Coupons', 'manage_options', 'puzzlepath-coupons', 'puzzlepath_coupons_page');
    add_submenu_page('puzzlepath-booking', 'Quests', 'Quests', 'manage_options', 'puzzlepath-quests', 'puzzlepath_quests_page');
    add_submenu_page('puzzlepath-booking', 'Quest Import', 'Quest Import', 'edit_posts', 'puzzlepath-quest-import', 'puzzlepath_quest_import_page');
    add_submenu_page('puzzlepath-booking', 'Settings', 'Settings', 'manage_options', 'puzzlepath-settings', 'puzzlepath_settings_page');
    if (class_exists('PuzzlePath_Stripe_Integration')) {
        $stripe_instance = PuzzlePath_Stripe_Integration::get_instance();
        add_submenu_page('puzzlepath-booking', 'Stripe Settings', 'Stripe Settings', 'manage_options', 'puzzlepath-stripe-settings', array($stripe_instance, 'stripe_settings_page_content'));
    }
    remove_submenu_page('puzzlepath-booking', 'puzzlepath-booking');
};
add_action('admin_menu', 'puzzlepath_register_admin_menus');


/**
 * Handle non-payment form submission (deprecated, but kept for safety).
 */
function puzzlepath_handle_booking_submission() {
    // This is now handled by the Stripe payment flow.
}
add_action('init', 'puzzlepath_handle_booking_submission');

/**
 * Enqueue scripts and styles.
 */
function puzzlepath_enqueue_scripts() {
    if (is_admin()) {
        wp_enqueue_script('jquery');
        return;
    }

    global $post;
    
    // Check multiple conditions for when to load scripts
    $should_load_scripts = false;
    
    // Condition 1: Post content contains shortcode
    if ($post && has_shortcode($post->post_content, 'puzzlepath_booking_form')) {
        $should_load_scripts = true;
    }
    
    // Condition 2: Current page URL suggests it's a booking test page
    if (strpos($_SERVER['REQUEST_URI'], 'booking') !== false || 
        strpos($_SERVER['REQUEST_URI'], 'simple-booking-test') !== false) {
        $should_load_scripts = true;
    }
    
    // Condition 3: Query parameter indicates shortcode will be used
    if (isset($_GET['show_booking_form']) || 
        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'puzzlepath') !== false)) {
        $should_load_scripts = true;
    }
    
    if ($should_load_scripts) {
        wp_enqueue_style(
            'puzzlepath-booking-form-style',
            plugin_dir_url(__FILE__) . 'css/booking-form.css',
            array(),
            '2.7.5'
        );
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
        
        wp_enqueue_script(
            'puzzlepath-booking-form',
            plugin_dir_url(__FILE__) . 'js/booking-form.js',
            array('jquery'),
            '2.9.0', // Fixed 100% coupon auto-booking issue
            true
        );
        
        wp_enqueue_script(
            'puzzlepath-stripe-payment',
            plugin_dir_url(__FILE__) . 'js/stripe-payment.js',
            array('jquery', 'stripe-js'),
            '2.9.0', // Fixed 100% coupon auto-booking issue
            true
        );

        $test_mode = get_option('puzzlepath_stripe_test_mode', true);
        $publishable_key = $test_mode ? get_option('puzzlepath_stripe_publishable_key') : get_option('puzzlepath_stripe_live_publishable_key');

        // Localize script for both stripe payment AND booking form
        $localize_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'coupon_nonce' => wp_create_nonce('puzzlepath_coupon_nonce'),
            'publishable_key' => $publishable_key,
            'rest_url' => rest_url('puzzlepath/v1/'),
            'rest_nonce' => wp_create_nonce('wp_rest')
        );
        
        wp_localize_script('puzzlepath-booking-form', 'puzzlepath_data', $localize_data);
        wp_localize_script('puzzlepath-stripe-payment', 'puzzlepath_data', $localize_data);
    }
}
add_action('wp_enqueue_scripts', 'puzzlepath_enqueue_scripts');

/**
 * AJAX handler for applying a coupon.
 */
function puzzlepath_apply_coupon_callback() {
    // Debug: Log the request
    error_log('PuzzlePath Coupon AJAX called. POST data: ' . print_r($_POST, true));
    
    try {
        check_ajax_referer('puzzlepath_coupon_nonce', 'nonce');
    } catch (Exception $e) {
        error_log('PuzzlePath Coupon: Nonce verification failed: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Security verification failed.']);
        return;
    }
    
    global $wpdb;
    $coupons_table = $wpdb->prefix . 'pp_coupons';
    $code = sanitize_text_field($_POST['coupon_code']);
    
    error_log('PuzzlePath Coupon: Looking for coupon code: ' . $code);
    
    $coupon = $wpdb->get_row($wpdb->prepare("SELECT * FROM $coupons_table WHERE code = %s", $code));

    if (!$coupon) {
        error_log('PuzzlePath Coupon: Coupon not found');
        wp_send_json_error(['message' => 'Invalid coupon code.']);
        return;
    }
    
    error_log('PuzzlePath Coupon: Found coupon: ' . print_r($coupon, true));
    
    if ($coupon->expires_at && strtotime($coupon->expires_at) < time()) {
        error_log('PuzzlePath Coupon: Coupon expired');
        wp_send_json_error(['message' => 'This coupon has expired.']);
        return;
    }
    if ($coupon->max_uses > 0 && $coupon->times_used >= $coupon->max_uses) {
        error_log('PuzzlePath Coupon: Coupon usage limit reached');
        wp_send_json_error(['message' => 'This coupon has reached its usage limit.']);
        return;
    }
    
    $response = [
        'discount_percent' => $coupon->discount_percent,
        'code' => $coupon->code
    ];
    
    error_log('PuzzlePath Coupon: Success response: ' . print_r($response, true));
    wp_send_json_success($response);
}
add_action('wp_ajax_apply_coupon', 'puzzlepath_apply_coupon_callback');
add_action('wp_ajax_nopriv_apply_coupon', 'puzzlepath_apply_coupon_callback');

/**
 * The main shortcode for displaying the booking form.
 */
function puzzlepath_booking_form_shortcode($atts) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    
    $events = $wpdb->get_results("SELECT * FROM $events_table WHERE seats > 0 ORDER BY event_date ASC");
    
    if (empty($events)) {
        return '<p>No events available for booking at this time.</p>';
    }
    
    ob_start();
    ?>
    <div id="puzzlepath-booking-form">
        <h3>Book Your PuzzlePath Experience</h3>
        <form id="booking-form" action="" onsubmit="return false;">
            <div class="form-group">
                <label for="event_id">Select Event:</label>
                <select name="event_id" id="event_id" required>
                    <option value="">-- Select an Event --</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo esc_attr($event->id); ?>" 
                                data-price="<?php echo esc_attr($event->price); ?>"
                                data-seats="<?php echo esc_attr($event->seats); ?>">
                            <?php 
                            echo esc_html($event->title);
                            if (!empty($event->hunt_name)) {
                                echo ' - ' . esc_html($event->hunt_name);
                            }
                            if (!empty($event->hunt_code)) {
                                echo ' (' . esc_html($event->hunt_code) . ')';
                            }
                            if ($event->event_date) {
                                echo ' - ' . date('F j, Y, g:i a', strtotime($event->event_date));
                            }
                            echo ' - $' . number_format($event->price, 2) . ' - ' . $event->seats . ' seats left';
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" name="name" id="name" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" name="email" id="email" required>
            </div>
            
            <div class="form-group">
                <label for="tickets">Number of Tickets:</label>
                <input type="number" name="tickets" id="tickets" min="1" max="10" value="1" required>
            </div>
            
            <div class="form-group">
                <label for="coupon_code">Coupon Code (optional):</label>
                <input type="text" name="coupon_code" id="coupon_code">
                <button type="button" id="apply-coupon">Apply Coupon</button>
            </div>
            
            <div id="coupon-message"></div>
            
            <div class="price-summary">
                <p>Subtotal: $<span id="subtotal">0.00</span></p>
                <p id="discount-line" style="display: none;">Discount: -$<span id="discount">0.00</span></p>
                <p><strong>Total: $<span id="total">0.00</span></strong></p>
            </div>
            
            <div id="card-element">
                <!-- Stripe Elements will create form elements here -->
            </div>
            <div id="card-errors" role="alert"></div>
            
            <button type="submit" id="submit-payment">Book Now</button>
        </form>
        
        <div id="payment-success" style="display: none;">
            <h3>Booking Confirmed!</h3>
            <p>Thank you for your booking. Your booking code is: <strong id="booking-code"></strong></p>
            <p>A confirmation email has been sent to your email address.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('puzzlepath_booking_form', 'puzzlepath_booking_form_shortcode');

// ========================= EVENTS MANAGEMENT =========================

/**
 * Display the main page for managing events.
 */
function puzzlepath_events_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pp_events';

    // Handle form submissions for adding/editing events
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['puzzlepath_event_nonce'])) {
        if (!wp_verify_nonce($_POST['puzzlepath_event_nonce'], 'puzzlepath_save_event')) {
            wp_die('Security check failed.');
        }

        $id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $title = sanitize_text_field($_POST['title']);
        $hunt_code = !empty($_POST['hunt_code']) ? sanitize_text_field($_POST['hunt_code']) : null;
        $hunt_name = !empty($_POST['hunt_name']) ? sanitize_text_field($_POST['hunt_name']) : null;
        $location = sanitize_text_field($_POST['location']);
        $price = floatval($_POST['price']);
        $seats = intval($_POST['seats']);
        $hosting_type = in_array($_POST['hosting_type'], ['hosted', 'self_hosted']) ? $_POST['hosting_type'] : 'hosted';
        $event_date = ($hosting_type === 'hosted' && !empty($_POST['event_date'])) ? sanitize_text_field($_POST['event_date']) : null;

        $data = [
            'title' => $title,
            'location' => $location,
            'price' => $price,
            'seats' => $seats,
            'hosting_type' => $hosting_type,
            'event_date' => $event_date,
            'created_at' => current_time('mysql'),
        ];
        
        // Auto-generate hunt code if not provided
        if (empty($hunt_code)) {
            $hunt_code = puzzlepath_generate_hunt_code(['title' => $title, 'location' => $location]);
        }
        
        // Auto-generate hunt name if not provided
        if (empty($hunt_name)) {
            $hunt_name = $title . ' - ' . $location;
        }
        
        $data['hunt_code'] = $hunt_code;
        $data['hunt_name'] = $hunt_name;

        if ($id > 0) {
            $wpdb->update($table_name, $data, ['id' => $id]);
        } else {
            $wpdb->insert($table_name, $data);
        }
        
        wp_redirect(admin_url('admin.php?page=puzzlepath-events&message=1'));
        exit;
    }

    // Handle event deletion
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['event_id'])) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'puzzlepath_delete_event_' . $_GET['event_id'])) {
            wp_die('Security check failed.');
        }
        $id = intval($_GET['event_id']);
        $wpdb->delete($table_name, ['id' => $id]);
        wp_redirect(admin_url('admin.php?page=puzzlepath-events&message=2'));
        exit;
    }

    $edit_event = null;
    if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['event_id'])) {
        $edit_event = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['event_id'])));
    }
    ?>
    <div class="wrap">
        <h1>Events</h1>

        <?php if (isset($_GET['message'])): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo $_GET['message'] == 1 ? 'Event saved successfully.' : 'Event deleted successfully.'; ?></p>
            </div>
        <?php endif; ?>

        <h2><?php echo $edit_event ? 'Edit Event' : 'Add New Event'; ?></h2>
        <form method="post" action="">
            <input type="hidden" name="event_id" value="<?php echo $edit_event ? esc_attr($edit_event->id) : ''; ?>">
            <?php wp_nonce_field('puzzlepath_save_event', 'puzzlepath_event_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="title">Title</label></th>
                    <td><input type="text" name="title" id="title" value="<?php echo $edit_event ? esc_attr($edit_event->title) : ''; ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hunt_code">Hunt Code</label></th>
                    <td>
                        <input type="text" name="hunt_code" id="hunt_code" value="<?php echo $edit_event ? esc_attr($edit_event->hunt_code) : ''; ?>" class="small-text" maxlength="10">
                        <p class="description">Hunt code for unified app integration (e.g., BB, EP, etc.)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hunt_name">Hunt Name</label></th>
                    <td>
                        <input type="text" name="hunt_name" id="hunt_name" value="<?php echo $edit_event ? esc_attr($edit_event->hunt_name) : ''; ?>" class="regular-text">
                        <p class="description">Descriptive hunt name (e.g., "Brisbane City Hunt", "Escape the Prison")</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hosting_type">Hosting Type</label></th>
                    <td>
                        <select name="hosting_type" id="hosting_type">
                            <option value="hosted" <?php selected($edit_event ? $edit_event->hosting_type : '', 'hosted'); ?>>Hosted</option>
                            <option value="self_hosted" <?php selected($edit_event ? $edit_event->hosting_type : '', 'self_hosted'); ?>>Self Hosted (App)</option>
                        </select>
                    </td>
                </tr>
                <tr id="event_date_row">
                    <th scope="row"><label for="event_date">Event Date</label></th>
                    <td><input type="datetime-local" name="event_date" id="event_date" value="<?php echo $edit_event && $edit_event->event_date ? date('Y-m-d\TH:i', strtotime($edit_event->event_date)) : ''; ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="location">Location</label></th>
                    <td><input type="text" name="location" id="location" value="<?php echo $edit_event ? esc_attr($edit_event->location) : ''; ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="price">Price</label></th>
                    <td><input type="number" step="0.01" name="price" id="price" value="<?php echo $edit_event ? esc_attr($edit_event->price) : ''; ?>" class="small-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="seats">Seats</label></th>
                    <td><input type="number" name="seats" id="seats" value="<?php echo $edit_event ? esc_attr($edit_event->seats) : ''; ?>" class="small-text" required></td>
                </tr>
            </table>
            <?php submit_button($edit_event ? 'Update Event' : 'Add Event'); ?>
        </form>

        <hr/>
        
        <h2>All Events</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Hunt</th>
                    <th>Hosting Type</th>
                    <th>Event Date</th>
                    <th>Location</th>
                    <th>Price</th>
                    <th>Seats Left</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $events = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
                foreach ($events as $event) {
                    echo '<tr>';
                    echo '<td>' . esc_html($event->title) . '</td>';
                    $hunt_display = '';
                    if (!empty($event->hunt_name)) {
                        $hunt_display = esc_html($event->hunt_name);
                        if (!empty($event->hunt_code)) {
                            $hunt_display .= ' (' . esc_html($event->hunt_code) . ')';
                        }
                    } elseif (!empty($event->hunt_code)) {
                        $hunt_display = esc_html($event->hunt_code);
                    } else {
                        $hunt_display = 'N/A';
                    }
                    echo '<td>' . $hunt_display . '</td>';
                    echo '<td>' . ($event->hosting_type === 'hosted' ? 'Hosted' : 'Self Hosted (App)') . '</td>';
                    echo '<td>' . ($event->event_date ? date('F j, Y, g:i a', strtotime($event->event_date)) : 'N/A') . '</td>';
                    echo '<td>' . esc_html($event->location) . '</td>';
                    echo '<td>$' . number_format($event->price, 2) . '</td>';
                    echo '<td>' . esc_html($event->seats) . '</td>';
                    echo '<td>';
                    echo '<a href="' . admin_url('admin.php?page=puzzlepath-events&action=edit&event_id=' . $event->id) . '">Edit</a> | ';
                    $delete_nonce = wp_create_nonce('puzzlepath_delete_event_' . $event->id);
                    echo '<a href="' . admin_url('admin.php?page=puzzlepath-events&action=delete&event_id=' . $event->id . '&_wpnonce=' . $delete_nonce) . '" onclick="return confirm(\'Are you sure you want to delete this event?\')">Delete</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <script>
    jQuery(document).ready(function($) {
        function toggleEventDate() {
            if ($('#hosting_type').val() === 'hosted') {
                $('#event_date_row').show();
            } else {
                $('#event_date_row').hide();
            }
        }
        toggleEventDate();
        $('#hosting_type').on('change', toggleEventDate);
    });
    </script>
    <?php
}

// ========================= QUEST IMPORT SYSTEM =========================

/**
 * Display the Quest Import page
 */
function puzzlepath_quest_import_page() {
    
    if (!current_user_can('edit_posts')) {
        wp_die(__('You do not have sufficient permissions to access this page. Current user roles: ' . implode(', ', $user_roles)));
    }
    
    $import_result = null;
    
    // Handle JSON import submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['puzzlepath_import_nonce'])) {
        if (!wp_verify_nonce($_POST['puzzlepath_import_nonce'], 'puzzlepath_import_quest')) {
            wp_die('Security check failed.');
        }
        
        $json_data = stripslashes($_POST['quest_json']); // Don't sanitize JSON - it breaks the format
        $import_result = puzzlepath_process_quest_import($json_data);
    }
    ?>
    <div class="wrap">
        <h1>🧩 Quest Import</h1>
        <p>Import quest data from ChatGPT in JSON format. Paste the complete JSON output from the ChatGPT Quest Builder.</p>
        
        <?php if ($import_result): ?>
            <?php if ($import_result['success']): ?>
                <div class="notice notice-success">
                    <h3>✅ Import Successful!</h3>
                    <p><strong>Quest:</strong> <?php echo esc_html($import_result['quest_title']); ?> (<?php echo esc_html($import_result['hunt_code']); ?>)</p>
                    <p><strong>Clues Imported:</strong> <?php echo intval($import_result['clues_count']); ?></p>
                    <p><a href="<?php echo admin_url('admin.php?page=puzzlepath-quests'); ?>">View in Quest Management</a></p>
                </div>
            <?php else: ?>
                <div class="notice notice-error">
                    <h3>❌ Import Failed</h3>
                    <p><strong>Error:</strong> <?php echo esc_html($import_result['error']); ?></p>
                    <?php if (isset($import_result['details'])): ?>
                        <details>
                            <summary>Technical Details</summary>
                            <pre><?php echo esc_html($import_result['details']); ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="quest-import-form">
            <h2>Import Quest JSON</h2>
            <form method="post" action="">
                <?php wp_nonce_field('puzzlepath_import_quest', 'puzzlepath_import_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="quest_json">Quest JSON Data</label></th>
                        <td>
                            <textarea name="quest_json" id="quest_json" rows="20" cols="100" class="large-text code" placeholder='Paste your ChatGPT quest JSON here...

Example:
{
  "quest": {
    "title": "Sample Quest",
    "hunt_code": "SQ001",
    ...
  },
  "clues": [
    {
      "clue_order": 1,
      "title": "First Clue",
      ...
    }
  ]
}'><?php echo (isset($_POST['quest_json']) && (!$import_result || !$import_result['success'])) ? esc_textarea($_POST['quest_json']) : ''; ?></textarea>
                            <p class="description">Paste the complete JSON output from ChatGPT Quest Builder. Make sure it includes both "quest" and "clues" sections.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('🚀 Import Quest', 'primary', 'submit', false, ['style' => 'font-size: 16px; padding: 10px 20px;']); ?>
            </form>
        </div>
        
        <div class="quest-import-help" style="margin-top: 40px; padding: 20px; background: #f9f9f9; border-left: 4px solid #2271b1;">
            <h3>📋 Import Format Requirements</h3>
            <p>Your JSON must include:</p>
            <ul>
                <li><strong>quest</strong> object with: title, hunt_code, location, price, hosting_type</li>
                <li><strong>clues</strong> array with: clue_order, title, clue_text, answer_text</li>
            </ul>
            
            <h4>🎯 Supported Quest Types:</h4>
            <ul>
                <li><strong>hosted</strong> - Live events with specific dates</li>
                <li><strong>self-hosted</strong> - Customer-scheduled experiences</li>
                <li><strong>anytime</strong> - Digital/remote quests</li>
            </ul>
            
            <h4>🗺️ Clue Features:</h4>
            <ul>
                <li>GPS coordinates (latitude/longitude)</li>
                <li>Multiple answer types (exact, partial, numeric)</li>
                <li>Hints and penalty hints</li>
                <li>Image and audio URLs</li>
                <li>Geofencing radius</li>
                <li>Point values and time limits</li>
            </ul>
        </div>
    </div>
    
    <style>
    .quest-import-form textarea {
        font-family: 'Courier New', monospace;
        background: #f8f9fa;
        border: 2px dashed #ddd;
    }
    .quest-import-form textarea:focus {
        border-color: #2271b1;
        background: #fff;
    }
    .quest-import-help {
        border-radius: 6px;
    }
    .quest-import-help h3 {
        margin-top: 0;
        color: #2271b1;
    }
    .quest-import-help ul {
        margin-left: 20px;
    }
    .quest-import-help li {
        margin-bottom: 8px;
    }
    </style>
    <?php
}

/**
 * Process quest import from JSON data
 */
function puzzlepath_process_quest_import($json_data) {
    global $wpdb;
    
    try {
        // Clean up JSON data
        $json_data = trim($json_data);
        
        // Validate JSON
        $data = json_decode($json_data, true);
        $json_error = json_last_error();
        
        if ($json_error !== JSON_ERROR_NONE) {
            $error_msg = 'Invalid JSON format: ' . json_last_error_msg();
            if ($json_error === JSON_ERROR_SYNTAX) {
                $error_msg .= '. Check for missing commas, quotes, or brackets.';
            }
            return [
                'success' => false,
                'error' => $error_msg,
                'details' => 'JSON Error Code: ' . $json_error . "\nFirst 500 chars: " . substr($json_data, 0, 500)
            ];
        }
        
        // Validate required structure
        $validation_result = puzzlepath_validate_quest_json($data);
        if (!$validation_result['valid']) {
            return [
                'success' => false,
                'error' => $validation_result['error'],
                'details' => implode("\n", $validation_result['details'])
            ];
        }
        
        // Start database transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Import quest
            $quest_result = puzzlepath_import_quest_data($data['quest']);
            if (!$quest_result['success']) {
                throw new Exception('Quest import failed: ' . $quest_result['error']);
            }
            
            $event_id = $quest_result['event_id'];
            $hunt_code = $data['quest']['hunt_code'];
            
            // Import clues
            $clues_result = puzzlepath_import_clues_data($data['clues'], $event_id);
            if (!$clues_result['success']) {
                throw new Exception('Clues import failed: ' . $clues_result['error']);
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return [
                'success' => true,
                'quest_title' => $data['quest']['title'],
                'hunt_code' => $hunt_code,
                'event_id' => $event_id,
                'clues_count' => $clues_result['clues_count']
            ];
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            throw $e;
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'details' => error_get_last() ? print_r(error_get_last(), true) : 'No additional error details'
        ];
    }
}

/**
 * Validate quest JSON structure
 */
function puzzlepath_validate_quest_json($data) {
    $errors = [];
    
    // Check main structure
    if (!isset($data['quest']) || !isset($data['clues'])) {
        return [
            'valid' => false,
            'error' => 'JSON must contain both "quest" and "clues" sections',
            'details' => ['Missing required top-level keys: quest, clues']
        ];
    }
    
    // Validate quest data
    $quest = $data['quest'];
    $required_quest_fields = ['title', 'hunt_code', 'location', 'price'];
    
    foreach ($required_quest_fields as $field) {
        if (empty($quest[$field])) {
            $errors[] = "Quest missing required field: {$field}";
        }
    }
    
    // Validate hunt_code format
    if (isset($quest['hunt_code']) && strlen($quest['hunt_code']) > 10) {
        $errors[] = 'Hunt code must be 10 characters or less';
    }
    
    // Validate hosting_type
    if (isset($quest['hosting_type']) && !in_array($quest['hosting_type'], ['hosted', 'self-hosted', 'anytime'])) {
        $errors[] = 'Invalid hosting_type. Must be: hosted, self-hosted, or anytime';
    }
    
    // Validate clues
    if (!is_array($data['clues']) || empty($data['clues'])) {
        $errors[] = 'Clues must be a non-empty array';
    } else {
        foreach ($data['clues'] as $i => $clue) {
            $clue_errors = puzzlepath_validate_clue_data($clue, $i + 1);
            $errors = array_merge($errors, $clue_errors);
        }
        
        // Check clue order sequence
        $orders = array_column($data['clues'], 'clue_order');
        $expected_orders = range(1, count($data['clues']));
        if (array_diff($expected_orders, $orders)) {
            $errors[] = 'Clue orders must be sequential starting from 1';
        }
    }
    
    return [
        'valid' => empty($errors),
        'error' => empty($errors) ? '' : 'Validation failed',
        'details' => $errors
    ];
}

/**
 * Validate individual clue data
 */
function puzzlepath_validate_clue_data($clue, $clue_number) {
    $errors = [];
    $required_fields = ['clue_order', 'clue_text', 'answer_text'];
    
    foreach ($required_fields as $field) {
        if (empty($clue[$field])) {
            $errors[] = "Clue #{$clue_number} missing required field: {$field}";
        }
    }
    
    // Validate answer_type
    if (isset($clue['answer_type']) && !in_array($clue['answer_type'], ['exact', 'partial', 'numeric', 'multiple_choice'])) {
        $errors[] = "Clue #{$clue_number} has invalid answer_type. Must be: exact, partial, numeric, or multiple_choice";
    }
    
    // Validate coordinates if provided
    if (isset($clue['latitude']) && (abs($clue['latitude']) > 90)) {
        $errors[] = "Clue #{$clue_number} has invalid latitude (must be between -90 and 90)";
    }
    
    if (isset($clue['longitude']) && (abs($clue['longitude']) > 180)) {
        $errors[] = "Clue #{$clue_number} has invalid longitude (must be between -180 and 180)";
    }
    
    return $errors;
}

/**
 * Import quest data into pp_events table only
 */
function puzzlepath_import_quest_data($quest_data) {
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    
    try {
        // Check if hunt_code already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$events_table} WHERE hunt_code = %s",
            $quest_data['hunt_code']
        ));
        
        if ($existing) {
            return [
                'success' => false,
                'error' => 'Hunt code "' . $quest_data['hunt_code'] . '" already exists. Please use a different hunt code.'
            ];
        }
        
        // Map JSON data to pp_events table structure
        $db_data = [
            'title' => sanitize_text_field($quest_data['title']),
            'hunt_code' => sanitize_text_field($quest_data['hunt_code']),
            'hunt_name' => isset($quest_data['hunt_name']) ? sanitize_text_field($quest_data['hunt_name']) : sanitize_text_field($quest_data['title']),
            'hosting_type' => isset($quest_data['hosting_type']) ? sanitize_text_field($quest_data['hosting_type']) : 'self-hosted',
            'event_date' => !empty($quest_data['event_date']) ? $quest_data['event_date'] : null,
            'location' => sanitize_text_field($quest_data['location']),
            'price' => floatval($quest_data['price']),
            'seats' => isset($quest_data['seats']) ? intval($quest_data['seats']) : 50,
            'duration_minutes' => isset($quest_data['duration_minutes']) ? intval($quest_data['duration_minutes']) : null,
            'medal_image_url' => isset($quest_data['medal_image_url']) ? esc_url_raw($quest_data['medal_image_url']) : null,
            'display_on_site' => isset($quest_data['display_on_site']) ? intval($quest_data['display_on_site']) : 0
        ];
        
        $result = $wpdb->insert($events_table, $db_data);
        
        if ($result === false) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $wpdb->last_error
            ];
        }
        
        return [
            'success' => true,
            'event_id' => $wpdb->insert_id
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Exception during quest import: ' . $e->getMessage()
        ];
    }
}

/**
 * Import clues data into pp_clues table
 */
function puzzlepath_import_clues_data($clues_data, $event_id) {
    global $wpdb;
    $clues_table = $wpdb->prefix . 'pp_clues';
    
    try {
        $imported_count = 0;
        
        foreach ($clues_data as $clue) {
            // Map JSON data to pp_clues table structure
            $db_data = [
                'hunt_id' => intval($event_id), // This is the event ID from pp_events
                'clue_order' => intval($clue['clue_order']),
                'title' => isset($clue['title']) ? sanitize_text_field($clue['title']) : '',
                'clue_text' => sanitize_textarea_field($clue['clue_text']),
                'task_description' => isset($clue['task_description']) ? sanitize_textarea_field($clue['task_description']) : null,
                'hint_text' => isset($clue['hint_text']) ? sanitize_textarea_field($clue['hint_text']) : null,
                'answer' => sanitize_text_field($clue['answer_text']), // Your table uses 'answer' not 'answer_text'
                'latitude' => isset($clue['latitude']) ? floatval($clue['latitude']) : null,
                'longitude' => isset($clue['longitude']) ? floatval($clue['longitude']) : null,
                'geofence_radius' => isset($clue['geofence_radius']) ? intval($clue['geofence_radius']) : null,
                'image_url' => isset($clue['image_url']) ? esc_url_raw($clue['image_url']) : null,
                'is_active' => isset($clue['is_active']) ? intval($clue['is_active']) : 1
            ];
            
            // Handle alternative answers - store as hint_text if not already used
            if (isset($clue['alternative_answers']) && !empty($clue['alternative_answers']) && empty($db_data['hint_text'])) {
                $db_data['hint_text'] = 'Alternative answers: ' . implode(', ', $clue['alternative_answers']);
            } elseif (isset($clue['alternative_answers']) && !empty($clue['alternative_answers'])) {
                // Append to existing hint_text
                $db_data['hint_text'] .= ' | Alternative answers: ' . implode(', ', $clue['alternative_answers']);
            }
            
            // Handle penalty hint - append to hint_text if exists
            if (isset($clue['penalty_hint']) && !empty($clue['penalty_hint'])) {
                if (empty($db_data['hint_text'])) {
                    $db_data['hint_text'] = 'Penalty hint: ' . $clue['penalty_hint'];
                } else {
                    $db_data['hint_text'] .= ' | Penalty hint: ' . $clue['penalty_hint'];
                }
            }
            
            $result = $wpdb->insert($clues_table, $db_data);
            
            if ($result === false) {
                throw new Exception('Failed to insert clue #' . $clue['clue_order'] . ': ' . $wpdb->last_error);
            }
            
            $imported_count++;
        }
        
        return [
            'success' => true,
            'clues_count' => $imported_count
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ========================= COUPONS MANAGEMENT =========================

/**
 * Display the main page for managing coupons.
 */
function puzzlepath_coupons_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pp_coupons';

    // Handle form submissions for adding/editing coupons
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['puzzlepath_coupon_nonce'])) {
        if (!wp_verify_nonce($_POST['puzzlepath_coupon_nonce'], 'puzzlepath_save_coupon')) {
            wp_die('Security check failed.');
        }

        $id = isset($_POST['coupon_id']) ? intval($_POST['coupon_id']) : 0;
        $code = sanitize_text_field($_POST['code']);
        $discount_percent = intval($_POST['discount_percent']);
        $max_uses = intval($_POST['max_uses']);
        $expires_at = !empty($_POST['expires_at']) ? sanitize_text_field($_POST['expires_at']) : null;

        $data = [
            'code' => $code,
            'discount_percent' => $discount_percent,
            'max_uses' => $max_uses,
            'expires_at' => $expires_at,
        ];

        if ($id > 0) {
            $wpdb->update($table_name, $data, ['id' => $id]);
        } else {
            $wpdb->insert($table_name, $data);
        }

        wp_redirect(admin_url('admin.php?page=puzzlepath-coupons&message=1'));
        exit;
    }

    // Handle coupon deletion
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['coupon_id'])) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'puzzlepath_delete_coupon_' . $_GET['coupon_id'])) {
            wp_die('Security check failed.');
        }
        $id = intval($_GET['coupon_id']);
        $wpdb->delete($table_name, ['id' => $id]);
        wp_redirect(admin_url('admin.php?page=puzzlepath-coupons&message=2'));
        exit;
    }

    $edit_coupon = null;
    if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['coupon_id'])) {
        $edit_coupon = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['coupon_id'])));
    }
    ?>
    <div class="wrap">
        <h1>Coupons</h1>

        <?php if (isset($_GET['message'])): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo $_GET['message'] == 1 ? 'Coupon saved successfully.' : 'Coupon deleted successfully.'; ?></p>
            </div>
        <?php endif; ?>

        <h2><?php echo $edit_coupon ? 'Edit Coupon' : 'Add New Coupon'; ?></h2>
        <form method="post" action="">
            <input type="hidden" name="coupon_id" value="<?php echo $edit_coupon ? esc_attr($edit_coupon->id) : ''; ?>">
            <?php wp_nonce_field('puzzlepath_save_coupon', 'puzzlepath_coupon_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="code">Coupon Code</label></th>
                    <td><input type="text" name="code" id="code" value="<?php echo $edit_coupon ? esc_attr($edit_coupon->code) : ''; ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="discount_percent">Discount (%)</label></th>
                    <td><input type="number" name="discount_percent" id="discount_percent" value="<?php echo $edit_coupon ? esc_attr($edit_coupon->discount_percent) : ''; ?>" class="small-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="max_uses">Max Uses</label></th>
                    <td><input type="number" name="max_uses" id="max_uses" value="<?php echo $edit_coupon ? esc_attr($edit_coupon->max_uses) : '0'; ?>" class="small-text">
                    <p class="description">Set to 0 for unlimited uses.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="expires_at">Expires At</label></th>
                    <td><input type="datetime-local" name="expires_at" id="expires_at" value="<?php echo $edit_coupon && $edit_coupon->expires_at ? date('Y-m-d\TH:i', strtotime($edit_coupon->expires_at)) : ''; ?>"></td>
                </tr>
            </table>
            <?php submit_button($edit_coupon ? 'Update Coupon' : 'Add Coupon'); ?>
        </form>

        <hr/>
        
        <h2>All Coupons</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Discount</th>
                    <th>Usage</th>
                    <th>Expires At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $coupons = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
                foreach ($coupons as $coupon) {
                    echo '<tr>';
                    echo '<td>' . esc_html($coupon->code) . '</td>';
                    echo '<td>' . esc_html($coupon->discount_percent) . '%</td>';
                    echo '<td>' . esc_html($coupon->times_used) . ' / ' . ($coupon->max_uses > 0 ? esc_html($coupon->max_uses) : '∞') . '</td>';
                    echo '<td>' . ($coupon->expires_at ? date('F j, Y, g:i a', strtotime($coupon->expires_at)) : 'Never') . '</td>';
                    echo '<td>';
                    echo '<a href="' . admin_url('admin.php?page=puzzlepath-coupons&action=edit&coupon_id=' . $coupon->id) . '">Edit</a> | ';
                    $delete_nonce = wp_create_nonce('puzzlepath_delete_coupon_' . $coupon->id);
                    echo '<a href="' . admin_url('admin.php?page=puzzlepath-coupons&action=delete&coupon_id=' . $coupon->id . '&_wpnonce=' . $delete_nonce) . '" onclick="return confirm(\'Are you sure you want to delete this coupon?\')">Delete</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}


// ========================= INCLUDES =========================

// Load settings functions
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';

// ========================= STRIPE INTEGRATION =========================

// Check if Stripe library is available (only load if composer installed)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';

    class PuzzlePath_Stripe_Integration {
        private static $instance;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_action('admin_init', array($this, 'register_stripe_settings'));
            add_action('rest_api_init', array($this, 'register_rest_endpoints'));
        }

        public function register_stripe_settings() {
            register_setting('puzzlepath_stripe_settings', 'puzzlepath_stripe_test_mode');
            register_setting('puzzlepath_stripe_settings', 'puzzlepath_stripe_publishable_key');
            register_setting('puzzlepath_stripe_settings', 'puzzlepath_stripe_secret_key');
            register_setting('puzzlepath_stripe_settings', 'puzzlepath_stripe_live_publishable_key');
            register_setting('puzzlepath_stripe_settings', 'puzzlepath_stripe_live_secret_key');
            register_setting('puzzlepath_stripe_settings', 'puzzlepath_stripe_webhook_secret');
        }

        public function register_rest_endpoints() {
            register_rest_route('puzzlepath/v1', '/payment/create-intent', array(
                'methods' => 'POST',
                'callback' => array($this, 'create_payment_intent'),
                'permission_callback' => '__return_true'
            ));

            register_rest_route('puzzlepath/v1', '/stripe-webhook', array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_webhook'),
                'permission_callback' => '__return_true'
            ));

            register_rest_route('puzzlepath/v1', '/booking-status', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_booking_status'),
                'permission_callback' => '__return_true'
            ));
            
            // Unified App endpoints
            register_rest_route('puzzlepath/v1', '/bookings', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_unified_bookings'),
                'permission_callback' => '__return_true'
            ));
            
            register_rest_route('puzzlepath/v1', '/booking/(?P<code>[a-zA-Z0-9\-]+)', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_booking_by_code'),
                'permission_callback' => '__return_true'
            ));
            
            register_rest_route('puzzlepath/v1', '/hunts', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_hunts_list'),
                'permission_callback' => '__return_true'
            ));
        }

        private function get_stripe_keys() {
            $test_mode = get_option('puzzlepath_stripe_test_mode', true);
            if ($test_mode) {
                return [
                    'publishable' => get_option('puzzlepath_stripe_publishable_key'),
                    'secret' => get_option('puzzlepath_stripe_secret_key'),
                ];
            } else {
                return [
                    'publishable' => get_option('puzzlepath_stripe_live_publishable_key'),
                    'secret' => get_option('puzzlepath_stripe_live_secret_key'),
                ];
            }
        }

        public function create_payment_intent($request) {
            global $wpdb;
            $params = $request->get_json_params();

            if (empty($params['event_id']) || empty($params['tickets'])) {
                return new WP_Error('missing_params', 'Missing event_id or tickets', array('status' => 400));
            }

            $event_id = intval($params['event_id']);
            $tickets = intval($params['tickets']);
            $coupon_code = isset($params['coupon_code']) ? sanitize_text_field($params['coupon_code']) : null;

            $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pp_events WHERE id = %d", $event_id));

            if (!$event || $event->seats < $tickets) {
                return new WP_Error('invalid_event', 'Event not found or not enough seats.', array('status' => 400));
            }

            $total_price = $event->price * $tickets;
            $coupon_id = null;

            if ($coupon_code) {
                $coupon = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pp_coupons WHERE code = %s AND (expires_at IS NULL OR expires_at > NOW()) AND (max_uses = 0 OR times_used < max_uses)", $coupon_code));
                if ($coupon) {
                    $total_price = $total_price - ($total_price * ($coupon->discount_percent / 100));
                    $coupon_id = $coupon->id;
                }
            }

            // Handle free bookings (100% discount or $0 total)
            if ($total_price <= 0) {
                return $this->process_free_booking($event_id, $tickets, $params, $event, $coupon_id);
            }

            // Get Stripe keys and validate them before proceeding
            $stripe_keys = $this->get_stripe_keys();
            if (empty($stripe_keys['secret'])) {
                return new WP_Error('stripe_config_error', 'Stripe secret key not configured. Please check your Stripe settings.', array('status' => 500));
            }
            
            // Set the API key for Stripe
            \Stripe\Stripe::setApiKey($stripe_keys['secret']);

            try {
                // Generate unique booking code with hunt integration
                $booking_code = $this->generate_unique_booking_code($event);
                
                // Create pending booking
                $booking_data = [
                    'event_id' => $event_id,
                    'hunt_id' => $event->hunt_code,
                    'customer_name' => sanitize_text_field($params['name']),
                    'customer_email' => sanitize_email($params['email']),
                    'tickets' => $tickets,
                    'total_price' => $total_price,
                    'coupon_id' => $coupon_id,
                    'payment_status' => 'pending',
                    'booking_code' => $booking_code,
                    'booking_date' => current_time('mysql')
                ];
                
                $wpdb->insert("{$wpdb->prefix}pp_bookings", $booking_data);
                $booking_id = $wpdb->insert_id;

                $payment_intent = \Stripe\PaymentIntent::create([
                    'amount' => $total_price * 100,
                    'currency' => 'aud',
                    'metadata' => [
                        'booking_id' => $booking_id,
                        'event_id' => $event_id,
                        'tickets' => $tickets,
                    ],
                ]);

                $wpdb->update("{$wpdb->prefix}pp_bookings", 
                    ['stripe_payment_intent_id' => $payment_intent->id],
                    ['id' => $booking_id]
                );

                return new WP_REST_Response([
                    'clientSecret' => $payment_intent->client_secret,
                    'bookingId' => $booking_id,
                    'bookingCode' => $booking_code
                ], 200);

            } catch (Exception $e) {
                return new WP_Error('stripe_error', $e->getMessage(), array('status' => 500));
            }
        }

        public function handle_webhook($request) {
            $payload = $request->get_body();
            $sig_header = $request->get_header('stripe_signature');
            $endpoint_secret = get_option('puzzlepath_stripe_webhook_secret');
            $event = null;

            try {
                $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
            } catch(\UnexpectedValueException $e) {
                return new WP_Error('invalid_payload', 'Invalid payload', array('status' => 400));
            } catch(\Stripe\Exception\SignatureVerificationException $e) {
                return new WP_Error('invalid_signature', 'Invalid signature', array('status' => 400));
            }

            if ($event->type == 'charge.succeeded') {
                $payment_intent = $event->data->object;
                $booking_code = $this->fulfill_booking($payment_intent->id);
                return new WP_REST_Response(array('status' => 'success', 'booking_code' => $booking_code), 200);
            }

            return new WP_REST_Response(array('status' => 'success'), 200);
        }
        
        private function fulfill_booking($payment_intent_id) {
            global $wpdb;
            $bookings_table = $wpdb->prefix . 'pp_bookings';
            $events_table = $wpdb->prefix . 'pp_events';
            $coupons_table = $wpdb->prefix . 'pp_coupons';

            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings_table WHERE stripe_payment_intent_id = %s", $payment_intent_id));

            if ($booking && $booking->payment_status === 'pending') {
                $wpdb->update($bookings_table, 
                    ['payment_status' => 'succeeded'], 
                    ['id' => $booking->id]
                );

                $wpdb->query($wpdb->prepare("UPDATE $events_table SET seats = seats - %d WHERE id = %d", $booking->tickets, $booking->event_id));

                if ($booking->coupon_id) {
                    $wpdb->query($wpdb->prepare("UPDATE $coupons_table SET times_used = times_used + 1 WHERE id = %d", $booking->coupon_id));
                }

                $this->send_confirmation_email($booking, $booking->booking_code);

                return $booking->booking_code;
            }
            return null;
        }

        private function send_confirmation_email($booking, $booking_code) {
            $to = $booking->customer_email;
            $subject = 'Your PuzzlePath Booking Confirmation';
            global $wpdb;
            $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pp_events WHERE id = %d", $booking->event_id));
            
            // Get HTML template (fallback to default if not set) 
            $template = get_option('puzzlepath_email_template', puzzlepath_get_default_html_template());
            
            // Get plugin URL for logo with fallback
            $plugin_url = plugin_dir_url(__FILE__);
            $logo_url = $plugin_url . 'images/puzzlepath-logo.png';
            
            // Check if logo exists, use fallback if not
            $logo_path = plugin_dir_path(__FILE__) . 'images/puzzlepath-logo.png';
            if (!file_exists($logo_path)) {
                $logo_url = 'https://via.placeholder.com/150x60/3F51B5/ffffff?text=PuzzlePath';
                error_log('PuzzlePath Email: Logo not found, using placeholder: ' . $logo_path);
            }
            
            // Format event date
            $formatted_date = $event && $event->event_date ? date('F j, Y \a\t g:i A', strtotime($event->event_date)) : 'TBD';
            
            // Create app URL with booking code pre-filled
            $app_url_with_booking = 'https://app.puzzlepath.com.au?booking=' . urlencode($booking_code);
            
            // Replace placeholders
            $html_message = str_replace(
                ['{name}', '{event_title}', '{event_date}', '{price}', '{booking_code}', '{logo_url}', '{app_url}'],
                [
                    $booking->customer_name, 
                    $event ? $event->title : 'Your Event', 
                    $formatted_date,
                    '$' . number_format($booking->total_price, 2), 
                    $booking_code,
                    $logo_url,
                    $app_url_with_booking
                ],
                $template
            );
            
            // Create a unique filter name to avoid conflicts
            $filter_name = 'puzzlepath_mail_content_type_' . uniqid();
            
            // Set content type to HTML with a unique filter
            add_filter('wp_mail_content_type', function() { return 'text/html'; }, 10, 0);
            
            // Set headers for HTML email (remove Content-Type since we're using the filter)
            $headers = [];
            $headers[] = 'From: PuzzlePath <bookings@puzzlepath.com.au>';
            
            // Debug logging (only if WP_DEBUG is enabled)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('PuzzlePath Email Debug: Attempting to send HTML email to ' . $to);
                error_log('PuzzlePath Email Debug: Subject: ' . $subject);
                error_log('PuzzlePath Email Debug: Logo URL: ' . $logo_url);
                error_log('PuzzlePath Email Debug: App URL: https://app.puzzlepath.com.au');
                
                add_action('wp_mail_failed', function($wp_error) {
                    error_log('PuzzlePath Email Debug: wp_mail failed: ' . $wp_error->get_error_message());
                });
            }
            
            // Add PHPMailer action to set plain-text alternative and ensure HTML content type
            $phpmailer_callback = function($phpmailer) use ($html_message) {
                // Ensure we're sending HTML
                $phpmailer->isHTML(true);
                $phpmailer->CharSet = 'UTF-8';
                
                // Generate plain-text version for AltBody
                $plain_text = wp_strip_all_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html_message));
                $plain_text = html_entity_decode($plain_text, ENT_QUOTES, 'UTF-8');
                $phpmailer->AltBody = $plain_text;
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('PuzzlePath Email Debug: PHPMailer ContentType set to: ' . $phpmailer->ContentType);
                }
            };
            
            add_action('phpmailer_init', $phpmailer_callback);
            
            $mail_result = wp_mail($to, $subject, $html_message, $headers);
            
            // Clean up filters and actions
            remove_filter('wp_mail_content_type', function() { return 'text/html'; }, 10);
            remove_action('phpmailer_init', $phpmailer_callback);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if ($mail_result) {
                    error_log('PuzzlePath Email Debug: HTML email sent successfully');
                } else {
                    error_log('PuzzlePath Email Debug: HTML email failed to send');
                }
            }
            
            return $mail_result;
        }
        

        /**
         * Process free booking (100% discount or $0 total)
         */
        private function process_free_booking($event_id, $tickets, $params, $event, $coupon_id) {
            global $wpdb;
            
            try {
                // Generate unique booking code
                $booking_code = $this->generate_unique_booking_code($event);
                
                // Create completed booking (no payment required)
                $booking_data = [
                    'event_id' => $event_id,
                    'hunt_id' => $event->hunt_code,
                    'customer_name' => sanitize_text_field($params['name']),
                    'customer_email' => sanitize_email($params['email']),
                    'tickets' => $tickets,
                    'total_price' => 0.00, // Free booking
                    'coupon_id' => $coupon_id,
                    'payment_status' => 'succeeded', // Mark as succeeded since no payment needed
                    'booking_code' => $booking_code,
                    'booking_date' => current_time('mysql')
                ];
                
                $wpdb->insert("{$wpdb->prefix}pp_bookings", $booking_data);
                $booking_id = $wpdb->insert_id;

                // Update event seats immediately (no payment processing delay)
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}pp_events SET seats = seats - %d WHERE id = %d",
                    $tickets,
                    $event_id
                ));

                // Update coupon usage if applicable
                if ($coupon_id) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->prefix}pp_coupons SET times_used = times_used + 1 WHERE id = %d",
                        $coupon_id
                    ));
                }

                // Send confirmation email
                $booking = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}pp_bookings WHERE id = %d",
                    $booking_id
                ));
                
                // Always log email attempts for free bookings (debugging)
                error_log('PuzzlePath Free Booking: Attempting to send confirmation email to ' . $booking->customer_email);
                error_log('PuzzlePath Free Booking: Booking code: ' . $booking_code);
                
                $email_result = $this->send_confirmation_email($booking, $booking_code);
                
                // Log email result
                if ($email_result) {
                    error_log('PuzzlePath Free Booking: Confirmation email sent successfully');
                } else {
                    error_log('PuzzlePath Free Booking: Confirmation email FAILED to send');
                }

                // Return success response with special flag for free booking
                return new WP_REST_Response([
                    'success' => true,
                    'free_booking' => true,
                    'bookingId' => $booking_id,
                    'bookingCode' => $booking_code,
                    'message' => 'Booking confirmed successfully!'
                ], 200);

            } catch (Exception $e) {
                return new WP_Error('free_booking_error', $e->getMessage(), array('status' => 500));
            }
        }

        /**
         * Generate unique booking code with hunt code integration
         */
        private function generate_unique_booking_code($event = null) {
            global $wpdb;
            $bookings_table = $wpdb->prefix . 'pp_bookings';
            
            do {
                if ($event && !empty($event->hunt_code)) {
                    // Hunt-specific format: HuntCode-YYYYMMDD-XXXX
                    $date_part = date('Ymd');
                    $random_part = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $code = strtoupper($event->hunt_code) . '-' . $date_part . '-' . $random_part;
                } else {
                    // Default PP format
                    $code = 'PP-' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
                }
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bookings_table WHERE booking_code = %s", $code));
            } while ($exists > 0);
            
            return $code;
        }

        public function get_booking_status($request) {
            global $wpdb;
            $payment_intent_id = $request->get_param('payment_intent');
            if (!$payment_intent_id) {
                return new WP_Error('missing_param', 'Missing payment_intent parameter', array('status' => 400));
            }
            $booking = $wpdb->get_row($wpdb->prepare("SELECT booking_code, payment_status FROM {$wpdb->prefix}pp_bookings WHERE stripe_payment_intent_id = %s", $payment_intent_id));
            if (!$booking) {
                return new WP_REST_Response(['status' => 'pending'], 200);
            }
            if ($booking->payment_status === 'succeeded' && $booking->booking_code) {
                return new WP_REST_Response(['status' => 'succeeded', 'booking_code' => $booking->booking_code], 200);
            }
            return new WP_REST_Response(['status' => $booking->payment_status], 200);
        }

        /**
         * Get unified bookings data for the unified app
         */
        public function get_unified_bookings($request) {
            global $wpdb;
            $unified_view = $wpdb->prefix . 'pp_bookings_unified';
            
            // Get query parameters
            $hunt_id = $request->get_param('hunt_id');
            $status = $request->get_param('status'); 
            $date_from = $request->get_param('date_from');
            $date_to = $request->get_param('date_to');
            $limit = intval($request->get_param('limit')) ?: 50;
            $offset = intval($request->get_param('offset')) ?: 0;
            $search = $request->get_param('search');
            
            // Build WHERE clauses
            $where_clauses = [];
            $where_values = [];
            
            if ($hunt_id) {
                $where_clauses[] = 'hunt_id = %s';
                $where_values[] = $hunt_id;
            }
            
            if ($status) {
                $where_clauses[] = 'status = %s';
                $where_values[] = $status;
            }
            
            if ($date_from) {
                $where_clauses[] = 'DATE(created_at) >= %s';
                $where_values[] = $date_from;
            }
            
            if ($date_to) {
                $where_clauses[] = 'DATE(created_at) <= %s';
                $where_values[] = $date_to;
            }
            
            if ($search) {
                $where_clauses[] = '(customer_name LIKE %s OR customer_email LIKE %s OR booking_code LIKE %s)';
                $search_term = '%' . $wpdb->esc_like($search) . '%';
                $where_values[] = $search_term;
                $where_values[] = $search_term;
                $where_values[] = $search_term;
            }
            
            $where_sql = '';
            if (!empty($where_clauses)) {
                $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
            }
            
            // Get total count
            $count_query = "SELECT COUNT(*) FROM $unified_view $where_sql";
            $total_count = empty($where_values) ? 
                $wpdb->get_var($count_query) : 
                $wpdb->get_var($wpdb->prepare($count_query, $where_values));
            
            // Get bookings
            $query = "SELECT * FROM $unified_view $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
            $query_values = array_merge($where_values, [$limit, $offset]);
            $bookings = $wpdb->get_results($wpdb->prepare($query, $query_values));
            
            // Format response
            $formatted_bookings = [];
            foreach ($bookings as $booking) {
                $formatted_bookings[] = [
                    'id' => intval($booking->id),
                    'booking_code' => $booking->booking_code,
                    'hunt_id' => $booking->hunt_id,
                    'hunt_code' => $booking->hunt_code,
                    'hunt_name' => $booking->hunt_name,
                    'event_title' => $booking->event_title,
                    'location' => $booking->location,
                    'event_date' => $booking->event_date,
                    'customer_name' => $booking->customer_name,
                    'customer_email' => $booking->customer_email,
                    'participant_names' => $booking->participant_names,
                    'participant_count' => intval($booking->participant_count),
                    'total_price' => floatval($booking->total_price),
                    'booking_date' => $booking->booking_date,
                    'created_at' => $booking->created_at,
                    'payment_status' => $booking->payment_status,
                    'status' => $booking->status
                ];
            }
            
            return new WP_REST_Response([
                'bookings' => $formatted_bookings,
                'total_count' => intval($total_count),
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total_count
            ], 200);
        }
        
        /**
         * Get a specific booking by booking code
         */
        public function get_booking_by_code($request) {
            global $wpdb;
            $unified_view = $wpdb->prefix . 'pp_bookings_unified';
            $booking_code = $request->get_param('code');
            
            if (!$booking_code) {
                return new WP_Error('missing_code', 'Missing booking code parameter', array('status' => 400));
            }
            
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $unified_view WHERE booking_code = %s",
                $booking_code
            ));
            
            if (!$booking) {
                return new WP_Error('booking_not_found', 'Booking not found', array('status' => 404));
            }
            
            $formatted_booking = [
                'id' => intval($booking->id),
                'booking_code' => $booking->booking_code,
                'hunt_id' => $booking->hunt_id,
                'hunt_code' => $booking->hunt_code,
                'hunt_name' => $booking->hunt_name,
                'event_title' => $booking->event_title,
                'location' => $booking->location,
                'event_date' => $booking->event_date,
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_email,
                'participant_names' => $booking->participant_names,
                'participant_count' => intval($booking->participant_count),
                'total_price' => floatval($booking->total_price),
                'booking_date' => $booking->booking_date,
                'created_at' => $booking->created_at,
                'payment_status' => $booking->payment_status,
                'status' => $booking->status
            ];
            
            return new WP_REST_Response(['booking' => $formatted_booking], 200);
        }
        
        /**
         * Get list of available hunts/events for the unified app
         */
        public function get_hunts_list($request) {
            global $wpdb;
            $events_table = $wpdb->prefix . 'pp_events';
            
            // Get query parameters
            $active_only = $request->get_param('active_only') !== 'false'; // Default to true
            $hosting_type = $request->get_param('hosting_type');
            
            // Build WHERE clauses
            $where_clauses = [];
            $where_values = [];
            
            if ($active_only) {
                $where_clauses[] = 'seats > 0';
            }
            
            if ($hosting_type) {
                $where_clauses[] = 'hosting_type = %s';
                $where_values[] = $hosting_type;
            }
            
            // Only include events with hunt codes for unified app
            $where_clauses[] = 'hunt_code IS NOT NULL AND hunt_code != \'\''; 
            
            $where_sql = '';
            if (!empty($where_clauses)) {
                $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
            }
            
            $query = "SELECT id, title, hunt_code, hunt_name, hosting_type, event_date, location, price, seats, created_at FROM $events_table $where_sql ORDER BY event_date ASC, created_at DESC";
            
            $events = empty($where_values) ? 
                $wpdb->get_results($query) : 
                $wpdb->get_results($wpdb->prepare($query, $where_values));
            
            $formatted_hunts = [];
            foreach ($events as $event) {
                $formatted_hunts[] = [
                    'id' => intval($event->id),
                    'title' => $event->title,
                    'hunt_code' => $event->hunt_code,
                    'hunt_name' => $event->hunt_name,
                    'hosting_type' => $event->hosting_type,
                    'event_date' => $event->event_date,
                    'location' => $event->location,
                    'price' => floatval($event->price),
                    'seats_available' => intval($event->seats),
                    'created_at' => $event->created_at
                ];
            }
            
            return new WP_REST_Response([
                'hunts' => $formatted_hunts,
                'total_count' => count($formatted_hunts)
            ], 200);
        }

        public function stripe_settings_page_content() {
            // Check for save message
            $message = '';
            if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
                $message = '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
            }
            
            $test_mode = get_option('puzzlepath_stripe_test_mode', true);
            $test_pub_key = get_option('puzzlepath_stripe_publishable_key', '');
            $test_secret_key = get_option('puzzlepath_stripe_secret_key', '');
            $live_pub_key = get_option('puzzlepath_stripe_live_publishable_key', '');
            $live_secret_key = get_option('puzzlepath_stripe_live_secret_key', '');
            
            ?>
            <div class="wrap">
                <h1>🔒 Stripe Payment Settings</h1>
                
                <?php echo $message; ?>
                
                <!-- Current Status -->
                <div class="notice notice-info">
                    <p><strong>Current Mode:</strong> 
                        <?php if ($test_mode): ?>
                            🧪 <span style="color: #d63638;">TEST MODE</span> - No real money will be processed
                        <?php else: ?>
                            💰 <span style="color: #00a32a;">LIVE MODE</span> - Real payments will be processed!
                        <?php endif; ?>
                    </p>
                </div>
                
                <form method="post" action="options.php">
                    <?php settings_fields('puzzlepath_stripe_settings'); ?>
                    
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Mode Toggle</th>
                            <td>
                                <div class="stripe-mode-toggle">
                                    <input type="checkbox" id="stripe-mode-toggle" name="puzzlepath_stripe_test_mode" value="1" 
                                           <?php checked($test_mode); ?> style="display: none;">
                                    <label for="stripe-mode-toggle" class="toggle-switch">
                                        <span class="toggle-slider"></span>
                                        <span class="toggle-label-left">LIVE</span>
                                        <span class="toggle-label-right">TEST</span>
                                    </label>
                                </div>
                                <p class="description">Toggle between test mode (safe for development) and live mode (real payments)</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h2>🔑 API Keys</h2>
                    <p>Get your API keys from your <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard</a></p>
                    
                    <table class="form-table">
                        <!-- Test Keys Section -->
                        <tr valign="top" class="test-keys-section">
                            <th scope="row" colspan="2"><h3 style="margin: 0; color: #d63638;">🧪 Test Keys (for development)</h3></th>
                        </tr>
                        <tr valign="top" class="test-keys-section">
                            <th scope="row">Test Publishable Key</th>
                            <td>
                                <input type="text" name="puzzlepath_stripe_publishable_key" 
                                       value="<?php echo esc_attr($test_pub_key); ?>" 
                                       class="regular-text" 
                                       placeholder="pk_test_..." 
                                       style="font-family: monospace;"/>
                                <p class="description">Starts with <code>pk_test_</code> - Safe to use in frontend code</p>
                            </td>
                        </tr>
                        
                        <tr valign="top" class="test-keys-section">
                            <th scope="row">Test Secret Key</th>
                            <td>
                                <input type="password" name="puzzlepath_stripe_secret_key" 
                                       value="<?php echo esc_attr($test_secret_key); ?>" 
                                       class="regular-text" 
                                       placeholder="sk_test_..." 
                                       style="font-family: monospace;"/>
                                <p class="description">Starts with <code>sk_test_</code> - Keep this secure! Used on the server only.</p>
                            </td>
                        </tr>
                        
                        <!-- Live Keys Section -->
                        <tr valign="top" class="live-keys-section" style="border-top: 2px solid #ddd;">
                            <th scope="row" colspan="2"><h3 style="margin: 20px 0 10px 0; color: #00a32a;">💰 Live Keys (for real payments)</h3></th>
                        </tr>
                        <tr valign="top" class="live-keys-section">
                            <th scope="row">Live Publishable Key</th>
                            <td>
                                <input type="text" name="puzzlepath_stripe_live_publishable_key" 
                                       value="<?php echo esc_attr($live_pub_key); ?>" 
                                       class="regular-text" 
                                       placeholder="pk_live_..." 
                                       style="font-family: monospace;"/>
                                <p class="description">Starts with <code>pk_live_</code> - Used for live payments</p>
                            </td>
                        </tr>
                        
                        <tr valign="top" class="live-keys-section">
                            <th scope="row">Live Secret Key</th>
                            <td>
                                <input type="password" name="puzzlepath_stripe_live_secret_key" 
                                       value="<?php echo esc_attr($live_secret_key); ?>" 
                                       class="regular-text" 
                                       placeholder="sk_live_..." 
                                       style="font-family: monospace;"/>
                                <p class="description">Starts with <code>sk_live_</code> - EXTREMELY SENSITIVE! Keep secure!</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h2>🎯 Key Status Check</h2>
                    <table class="form-table">
                        <tr>
                            <th>Current Configuration:</th>
                            <td>
                                <?php 
                                $current_pub = $test_mode ? $test_pub_key : $live_pub_key;
                                $current_secret = $test_mode ? $test_secret_key : $live_secret_key;
                                ?>
                                <p><strong>Publishable Key:</strong> 
                                    <?php if ($current_pub): ?>
                                        ✅ Configured (<?php echo substr($current_pub, 0, 12); ?>...)
                                    <?php else: ?>
                                        ❌ Not configured
                                    <?php endif; ?>
                                </p>
                                <p><strong>Secret Key:</strong> 
                                    <?php if ($current_secret): ?>
                                        ✅ Configured (<?php echo substr($current_secret, 0, 12); ?>...)
                                    <?php else: ?>
                                        ❌ Not configured
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('💾 Save Stripe Settings', 'primary', 'submit', false); ?>
                </form>
                
                <hr>
                
                <h2>🇦🇺 Australian Test Credit Cards</h2>
                <table class="widefat striped">
                    <thead>
                        <tr><th>Purpose</th><th>Card Number</th><th>Card Type</th><th>Result</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Successful Payment</td><td><code>4000000560000004</code></td><td>Visa (AU)</td><td>✅ Approved</td></tr>
                        <tr><td>Successful Payment</td><td><code>5200828282828210</code></td><td>Mastercard (AU)</td><td>✅ Approved</td></tr>
                        <tr><td>Authentication Required</td><td><code>4000002500003155</code></td><td>Visa (AU)</td><td>🔐 3D Secure</td></tr>
                        <tr><td>Declined Payment</td><td><code>4000000000000002</code></td><td>Visa</td><td>❌ Generic Decline</td></tr>
                        <tr><td>Insufficient Funds</td><td><code>4000000000009995</code></td><td>Visa</td><td>💳 Insufficient Funds</td></tr>
                        <tr><td>Processing Error</td><td><code>4000000000000119</code></td><td>Visa</td><td>⚠️ Processing Error</td></tr>
                    </tbody>
                </table>
                <p><em>Use any future expiry date (like 12/34) and any 3-digit CVC for testing. Australian cards use AUD currency by default.</em></p>
            </div>
            
            <style>
            .form-table th { width: 200px; }
            .regular-text { width: 400px; }
            .notice h3 { margin-top: 0; }
            
            /* Toggle Switch Styles */
            .stripe-mode-toggle {
                margin-bottom: 10px;
            }
            
            .toggle-switch {
                position: relative;
                display: inline-block;
                width: 120px;
                height: 34px;
                cursor: pointer;
                background-color: #00a32a;
                border-radius: 34px;
                transition: background-color 0.3s;
            }
            
            .toggle-switch:hover {
                opacity: 0.8;
            }
            
            .toggle-slider {
                position: absolute;
                top: 2px;
                left: 2px;
                width: 30px;
                height: 30px;
                background-color: white;
                border-radius: 50%;
                transition: transform 0.3s;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            
            .toggle-label-left,
            .toggle-label-right {
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                font-size: 12px;
                font-weight: bold;
                color: white;
                pointer-events: none;
            }
            
            .toggle-label-left {
                left: 10px;
            }
            
            .toggle-label-right {
                right: 10px;
            }
            
            /* When checkbox is checked (test mode) */
            #stripe-mode-toggle:checked + .toggle-switch {
                background-color: #d63638;
            }
            
            #stripe-mode-toggle:checked + .toggle-switch .toggle-slider {
                transform: translateX(86px);
            }
            </style>
            
            <script>
            jQuery(document).ready(function($) {
                // Update mode display when toggle is clicked
                $('#stripe-mode-toggle').on('change', function() {
                    var isTestMode = $(this).is(':checked');
                    var modeText = isTestMode ? 
                        '🧪 <span style="color: #d63638;">TEST MODE</span> - No real money will be processed' : 
                        '💰 <span style="color: #00a32a;">LIVE MODE</span> - Real payments will be processed!';
                    
                    $('.notice-info p').html('<strong>Current Mode:</strong> ' + modeText);
                });
            });
            </script>
            <?php
        }
    }

    // Initialize the Stripe integration
    PuzzlePath_Stripe_Integration::get_instance();
} else {
    // Show admin notice if Stripe library not installed
    add_action('admin_notices', function() {
        echo '<div class="error"><p>PuzzlePath Booking: The Stripe PHP library is not installed. Please run "composer install" in the plugin directory.</p></div>';
    });
}

// ========================= BOOKINGS MANAGEMENT =========================

/**
 * Display the comprehensive bookings management page.
 */
function puzzlepath_bookings_page() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $events_table = $wpdb->prefix . 'pp_events';
    $coupons_table = $wpdb->prefix . 'pp_coupons';
    
    // Handle actions
    if (isset($_GET['action']) && isset($_GET['booking_id'])) {
        $booking_id = intval($_GET['booking_id']);
        
        switch ($_GET['action']) {
            case 'refund':
                if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'puzzlepath_refund_' . $booking_id)) {
                    $result = puzzlepath_process_refund($booking_id);
                    if ($result['success']) {
                        wp_redirect(admin_url('admin.php?page=puzzlepath-bookings&message=refunded'));
                    } else {
                        wp_redirect(admin_url('admin.php?page=puzzlepath-bookings&error=' . urlencode($result['error'])));
                    }
                    exit;
                }
                break;
                
            case 'resend_email':
                if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'puzzlepath_resend_' . $booking_id)) {
                    puzzlepath_resend_confirmation_email($booking_id);
                    wp_redirect(admin_url('admin.php?page=puzzlepath-bookings&message=email_sent'));
                    exit;
                }
                break;
        }
    }
    
    // Handle bulk actions
    if (isset($_POST['action']) && $_POST['action'] !== '-1' && isset($_POST['booking_ids'])) {
        check_admin_referer('bulk-bookings');
        $action = sanitize_text_field($_POST['action']);
        $booking_ids = array_map('intval', $_POST['booking_ids']);
        
        switch ($action) {
            case 'bulk_refund':
                $refunded_count = 0;
                foreach ($booking_ids as $booking_id) {
                    $result = puzzlepath_process_refund($booking_id);
                    if ($result['success']) $refunded_count++;
                }
                wp_redirect(admin_url('admin.php?page=puzzlepath-bookings&message=bulk_refunded&count=' . $refunded_count));
                exit;
                break;
                
            case 'bulk_email':
                foreach ($booking_ids as $booking_id) {
                    puzzlepath_resend_confirmation_email($booking_id);
                }
                wp_redirect(admin_url('admin.php?page=puzzlepath-bookings&message=bulk_emails_sent&count=' . count($booking_ids)));
                exit;
                break;
        }
    }
    
    // Handle CSV export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        puzzlepath_export_bookings_csv();
        exit;
    }
    
    // Get filter parameters
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $event_filter = isset($_GET['event_id']) ? intval($_GET['event_id']) : '';
    $hunt_filter = isset($_GET['hunt_code']) ? sanitize_text_field($_GET['hunt_code']) : '';
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // Get sorting parameters
    $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
    $order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
    
    // Validate orderby parameter
    $allowed_columns = [
        'id' => 'b.id',
        'booking_code' => 'b.booking_code',
        'customer_name' => 'b.customer_name',
        'customer_email' => 'b.customer_email',
        'event_title' => 'e.title',
        'hunt_name' => 'e.hunt_name',
        'tickets' => 'b.tickets',
        'total_price' => 'b.total_price',
        'payment_status' => 'b.payment_status',
        'created_at' => 'b.created_at',
        'event_date' => 'e.event_date'
    ];
    
    $order_column = isset($allowed_columns[$orderby]) ? $allowed_columns[$orderby] : 'b.created_at';
    
    // Build query
    $where_clauses = [];
    $where_values = [];
    
    if ($status_filter) {
        $where_clauses[] = 'b.payment_status = %s';
        $where_values[] = $status_filter;
    }
    
    if ($event_filter) {
        $where_clauses[] = 'b.event_id = %d';
        $where_values[] = $event_filter;
    }
    
    if ($hunt_filter) {
        $where_clauses[] = 'e.hunt_code = %s';
        $where_values[] = $hunt_filter;
    }
    
    if ($date_from) {
        $where_clauses[] = 'DATE(b.created_at) >= %s';
        $where_values[] = $date_from;
    }
    
    if ($date_to) {
        $where_clauses[] = 'DATE(b.created_at) <= %s';
        $where_values[] = $date_to;
    }
    
    if ($search) {
        $where_clauses[] = '(b.customer_name LIKE %s OR b.customer_email LIKE %s OR b.booking_code LIKE %s)';
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_values[] = $search_term;
        $where_values[] = $search_term;
        $where_values[] = $search_term;
    }
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    // Pagination
    $items_per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM $bookings_table b 
                   LEFT JOIN $events_table e ON b.event_id = e.id 
                   LEFT JOIN $coupons_table c ON b.coupon_id = c.id 
                   $where_sql";
    
    if (!empty($where_values)) {
        $total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
    } else {
        $total_items = $wpdb->get_var($count_query);
    }
    
    $total_pages = ceil($total_items / $items_per_page);
    
    // Get bookings
    $query = "SELECT b.*, e.title as event_title, e.hunt_code, e.hunt_name, e.event_date, c.code as coupon_code
             FROM $bookings_table b 
             LEFT JOIN $events_table e ON b.event_id = e.id
             LEFT JOIN $coupons_table c ON b.coupon_id = c.id
             $where_sql
             ORDER BY $order_column $order
             LIMIT %d OFFSET %d";
    
    $query_values = array_merge($where_values, [$items_per_page, $offset]);
    $bookings = $wpdb->get_results($wpdb->prepare($query, $query_values));
    
    // Get summary statistics
    $stats = puzzlepath_get_booking_stats($where_sql, $where_values);
    
    ?>
    <div class="wrap">
        <h1>Bookings Management</h1>
        
        <?php if (isset($_GET['message'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php 
                    switch ($_GET['message']) {
                        case 'refunded':
                            echo 'Booking refunded successfully.';
                            break;
                        case 'email_sent':
                            echo 'Confirmation email sent successfully.';
                            break;
                        case 'bulk_refunded':
                            $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
                            echo sprintf('%d booking(s) refunded successfully.', $count);
                            break;
                        case 'bulk_emails_sent':
                            $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
                            echo sprintf('Confirmation emails sent for %d booking(s).', $count);
                            break;
                        case 'migration_complete':
                            echo 'Payment status migration completed successfully!';
                            break;
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html(urldecode($_GET['error'])); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Summary Statistics -->
        <div class="booking-stats" style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #2271b1; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #2271b1;">Total Bookings</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo $stats['total_bookings']; ?></p>
            </div>
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #00a32a; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #00a32a;">Total Revenue</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;">$<?php echo number_format($stats['total_revenue'], 2); ?></p>
            </div>
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #dba617; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #dba617;">Pending Payments</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo $stats['pending_payments']; ?></p>
            </div>
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #d63638; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #d63638;">Total Participants</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo $stats['total_participants']; ?></p>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" style="display: inline-flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="puzzlepath-bookings">
                    
                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
                        <option value="paid" <?php selected($status_filter, 'paid'); ?>>Paid</option>
                        <option value="failed" <?php selected($status_filter, 'failed'); ?>>Failed</option>
                        <option value="refunded" <?php selected($status_filter, 'refunded'); ?>>Refunded</option>
                    </select>
                    
                    <select name="event_id">
                        <option value="">All Events</option>
                        <?php
                        $events = $wpdb->get_results("SELECT id, title FROM $events_table ORDER BY title");
                        foreach ($events as $event) {
                            echo '<option value="' . $event->id . '"' . selected($event_filter, $event->id, false) . '>' . esc_html($event->title) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <select name="hunt_code">
                        <option value="">All Hunts</option>
                        <?php
                        $hunts = $wpdb->get_results("SELECT DISTINCT hunt_code, hunt_name FROM $events_table WHERE hunt_code IS NOT NULL AND hunt_code != '' ORDER BY hunt_code");
                        foreach ($hunts as $hunt) {
                            $label = $hunt->hunt_name ? $hunt->hunt_name . ' (' . $hunt->hunt_code . ')' : $hunt->hunt_code;
                            echo '<option value="' . esc_attr($hunt->hunt_code) . '"' . selected($hunt_filter, $hunt->hunt_code, false) . '>' . esc_html($label) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="From Date">
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="To Date">
                    
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search bookings...">
                    
                    <input type="submit" class="button" value="Filter">
                    
                    <?php if ($status_filter || $event_filter || $hunt_filter || $date_from || $date_to || $search): ?>
                        <a href="<?php echo admin_url('admin.php?page=puzzlepath-bookings'); ?>" class="button">Clear Filters</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="alignright actions">
                <a href="<?php echo admin_url('admin.php?page=puzzlepath-bookings&export=csv&' . http_build_query($_GET)); ?>" class="button">Export CSV</a>
            </div>
        </div>
        
        <!-- Bookings Table -->
        <form method="post">
            <?php wp_nonce_field('bulk-bookings'); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action">
                        <option value="-1">Bulk Actions</option>
                        <option value="bulk_refund">Refund Selected</option>
                        <option value="bulk_email">Resend Confirmation Emails</option>
                    </select>
                    <input type="submit" class="button action" value="Apply">
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>
                        <th class="manage-column sortable <?php echo ($orderby === 'booking_code') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'booking_code', 'order' => ($orderby === 'booking_code' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Booking Code</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo ($orderby === 'customer_name') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'customer_name', 'order' => ($orderby === 'customer_name' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Customer</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo ($orderby === 'event_title') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'event_title', 'order' => ($orderby === 'event_title' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Event</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo ($orderby === 'hunt_name') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'hunt_name', 'order' => ($orderby === 'hunt_name' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Hunt</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo ($orderby === 'tickets') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'tickets', 'order' => ($orderby === 'tickets' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Tickets</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo ($orderby === 'total_price') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'total_price', 'order' => ($orderby === 'total_price' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Total</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo ($orderby === 'payment_status') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'payment_status', 'order' => ($orderby === 'payment_status' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Status</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column sortable <?php echo ($orderby === 'created_at') ? ($order === 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                            <a href="<?php echo add_query_arg(array('orderby' => 'created_at', 'order' => ($orderby === 'created_at' && $order === 'ASC') ? 'desc' : 'asc')); ?>">
                                <span>Date</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 20px;">No bookings found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <th class="check-column"><input type="checkbox" name="booking_ids[]" value="<?php echo $booking->id; ?>"></th>
                                <td><strong><?php echo esc_html($booking->booking_code); ?></strong></td>
                                <td>
                                    <strong><?php echo esc_html($booking->customer_name); ?></strong><br>
                                    <small><?php echo esc_html($booking->customer_email); ?></small>
                                </td>
                                <td>
                                    <?php echo esc_html($booking->event_title); ?><br>
                                    <?php if ($booking->event_date): ?>
                                        <small><?php echo date('M j, Y g:i A', strtotime($booking->event_date)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if ($booking->hunt_name) {
                                        echo esc_html($booking->hunt_name);
                                        if ($booking->hunt_code) {
                                            echo '<br><small>(' . esc_html($booking->hunt_code) . ')</small>';
                                        }
                                    } elseif ($booking->hunt_code) {
                                        echo esc_html($booking->hunt_code);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $booking->tickets; ?></td>
                                <td>$<?php echo number_format($booking->total_price, 2); ?>
                                    <?php if ($booking->coupon_code): ?>
                                        <br><small>Coupon: <?php echo esc_html($booking->coupon_code); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $status_colors = [
                                        'pending' => '#dba617',
                                        'paid' => '#00a32a', 
                                        'failed' => '#d63638',
                                        'refunded' => '#8c8f94'
                                    ];
                                    $status_color = isset($status_colors[$booking->payment_status]) ? $status_colors[$booking->payment_status] : '#8c8f94';
                                    ?>
                                    <span style="background: <?php echo $status_color; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                                        <?php echo esc_html($booking->payment_status); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($booking->created_at)); ?></td>
                                <td>
                                    <a href="#" onclick="showBookingDetails(<?php echo $booking->id; ?>); return false;" title="View Details">👁️</a>
                                    <a href="#" onclick="editBookingDetails(<?php echo $booking->id; ?>); return false;" title="Edit Booking">✏️</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=puzzlepath-bookings&action=resend_email&booking_id=' . $booking->id), 'puzzlepath_resend_' . $booking->id); ?>" title="Resend Email">📧</a>
                                    <?php if ($booking->payment_status === 'paid'): ?>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=puzzlepath-bookings&action=refund&booking_id=' . $booking->id), 'puzzlepath_refund_' . $booking->id); ?>" 
                                           onclick="return confirm('Are you sure you want to refund this booking?');" title="Refund">💸</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo $total_items; ?> items</span>
                        <?php
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        echo $page_links;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Booking Details Modal -->
    <div id="booking-details-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div style="background-color: #fefefe; margin: 2% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 5px; max-height: 90vh; position: relative;">
            <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px;" onclick="closeBookingDetails()">&times;</span>
            <div id="booking-details-content" style="margin-top: 10px;">
                Loading...
            </div>
        </div>
    </div>
    
    <!-- Edit Booking Modal -->
    <div id="edit-booking-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div style="background-color: #fefefe; margin: 1% auto 2% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 5px; max-height: 95vh; overflow-y: auto; position: relative;">
            <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px; z-index: 10;" onclick="closeEditBooking()">&times;</span>
            <div id="edit-booking-content" style="margin-top: 10px; padding-right: 10px;">
                Loading...
            </div>
        </div>
    </div>
    
    <style>
    .manage-column.sortable a {
        text-decoration: none;
        color: inherit;
        display: block;
        position: relative;
    }
    .manage-column.sortable a:hover {
        color: #0073aa;
    }
    .manage-column.sortable .sorting-indicator {
        float: right;
        width: 0;
        height: 0;
        margin-top: 8px;
        margin-right: 7px;
    }
    .manage-column.sortable.asc .sorting-indicator {
        border-left: 4px solid transparent;
        border-right: 4px solid transparent;
        border-bottom: 8px solid #444;
    }
    .manage-column.sortable.desc .sorting-indicator {
        border-left: 4px solid transparent;
        border-right: 4px solid transparent;
        border-top: 8px solid #444;
    }
    .manage-column.sortable:not(.asc):not(.desc) .sorting-indicator {
        opacity: 0.3;
        border-left: 4px solid transparent;
        border-right: 4px solid transparent;
        border-top: 8px solid #444;
    }
    .manage-column.sortable:not(.asc):not(.desc):hover .sorting-indicator {
        opacity: 0.8;
    }
    
    /* Compact styling for edit modal */
    #edit-booking-modal .form-table th {
        padding: 10px 10px 10px 0;
        width: 150px;
    }
    #edit-booking-modal .form-table td {
        padding: 10px 10px 10px 0;
    }
    #edit-booking-modal .form-table tr {
        border-bottom: 1px solid #f1f1f1;
    }
    #edit-booking-modal .regular-text, #edit-booking-modal .large-text {
        width: 100%;
        max-width: 300px;
    }
    #edit-booking-modal .small-text {
        width: 80px;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Select all functionality
        $('#cb-select-all-1').on('click', function() {
            $('input[name="booking_ids[]"]').prop('checked', this.checked);
        });
    });
    
    function showBookingDetails(bookingId) {
        document.getElementById('booking-details-modal').style.display = 'block';
        document.getElementById('booking-details-content').innerHTML = 'Loading...';
        
        // AJAX call to get booking details
        jQuery.post(ajaxurl, {
            action: 'get_booking_details',
            booking_id: bookingId,
            nonce: '<?php echo wp_create_nonce('booking_details_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                document.getElementById('booking-details-content').innerHTML = response.data;
            } else {
                document.getElementById('booking-details-content').innerHTML = 'Error loading booking details.';
            }
        });
    }
    
    function closeBookingDetails() {
        document.getElementById('booking-details-modal').style.display = 'none';
    }
    
    function editBookingDetails(bookingId) {
        document.getElementById('edit-booking-modal').style.display = 'block';
        document.getElementById('edit-booking-content').innerHTML = 'Loading...';
        
        // AJAX call to get edit booking form
        jQuery.post(ajaxurl, {
            action: 'get_edit_booking_form',
            booking_id: bookingId,
            nonce: '<?php echo wp_create_nonce('edit_booking_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                document.getElementById('edit-booking-content').innerHTML = response.data;
            } else {
                document.getElementById('edit-booking-content').innerHTML = 'Error loading booking form.';
            }
        });
    }
    
    function closeEditBooking() {
        document.getElementById('edit-booking-modal').style.display = 'none';
    }
    
    function saveBookingChanges(bookingId) {
        var form = document.getElementById('edit-booking-form');
        var formData = new FormData(form);
        formData.append('action', 'save_booking_changes');
        formData.append('booking_id', bookingId);
        formData.append('nonce', '<?php echo wp_create_nonce('save_booking_nonce'); ?>');
        
        // Show loading
        document.getElementById('edit-booking-save-btn').disabled = true;
        document.getElementById('edit-booking-save-btn').textContent = 'Saving...';
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Booking updated successfully!');
                    closeEditBooking();
                    location.reload(); // Refresh the page to show changes
                } else {
                    alert('Error: ' + response.data);
                    document.getElementById('edit-booking-save-btn').disabled = false;
                    document.getElementById('edit-booking-save-btn').textContent = 'Save Changes';
                }
            },
            error: function() {
                alert('An error occurred while saving changes.');
                document.getElementById('edit-booking-save-btn').disabled = false;
                document.getElementById('edit-booking-save-btn').textContent = 'Save Changes';
            }
        });
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        var modal = document.getElementById('booking-details-modal');
        var editModal = document.getElementById('edit-booking-modal');
        if (event.target == modal) {
            modal.style.display = 'none';
        } else if (event.target == editModal) {
            editModal.style.display = 'none';
        }
    }
    </script>
    <?php
}

/**
 * Get booking statistics
 */
function puzzlepath_get_booking_stats($where_sql = '', $where_values = []) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $events_table = $wpdb->prefix . 'pp_events';
    
    $base_query = "FROM $bookings_table b LEFT JOIN $events_table e ON b.event_id = e.id $where_sql";
    
    $stats = [];
    
    // Total bookings
    $query = "SELECT COUNT(*) $base_query";
    $stats['total_bookings'] = empty($where_values) ? $wpdb->get_var($query) : $wpdb->get_var($wpdb->prepare($query, $where_values));
    
    // Total revenue (paid bookings only)
    $revenue_where = $where_sql ? $where_sql . " AND b.payment_status = 'paid'" : "WHERE b.payment_status = 'paid'";
    $query = "SELECT COALESCE(SUM(b.total_price), 0) FROM $bookings_table b LEFT JOIN $events_table e ON b.event_id = e.id $revenue_where";
    $stats['total_revenue'] = empty($where_values) ? $wpdb->get_var($query) : $wpdb->get_var($wpdb->prepare($query, array_merge($where_values, ['paid'])));
    
    // Pending payments
    $pending_where = $where_sql ? $where_sql . " AND b.payment_status = 'pending'" : "WHERE b.payment_status = 'pending'";
    $query = "SELECT COUNT(*) FROM $bookings_table b LEFT JOIN $events_table e ON b.event_id = e.id $pending_where";
    $stats['pending_payments'] = empty($where_values) ? $wpdb->get_var($query) : $wpdb->get_var($wpdb->prepare($query, array_merge($where_values, ['pending'])));
    
    // Total participants
    $query = "SELECT COALESCE(SUM(b.tickets), 0) $base_query";
    $stats['total_participants'] = empty($where_values) ? $wpdb->get_var($query) : $wpdb->get_var($wpdb->prepare($query, $where_values));
    
    return $stats;
}

/**
 * Process refund through Stripe
 */
function puzzlepath_process_refund($booking_id) {
    global $wpdb;
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pp_bookings WHERE id = %d", 
        $booking_id
    ));
    
    if (!$booking || $booking->payment_status !== 'paid') {
        return ['success' => false, 'error' => 'Booking not found or not eligible for refund.'];
    }
    
    if (!class_exists('\Stripe\Stripe')) {
        return ['success' => false, 'error' => 'Stripe library not available.'];
    }
    
    try {
        // Get Stripe keys
        $test_mode = get_option('puzzlepath_stripe_test_mode', true);
        $secret_key = $test_mode ? 
            get_option('puzzlepath_stripe_secret_key') : 
            get_option('puzzlepath_stripe_live_secret_key');
        
        // Validate secret key before setting
        if (empty($secret_key)) {
            return ['success' => false, 'error' => 'Stripe secret key not configured. Please check your Stripe settings.'];
        }
        
        \Stripe\Stripe::setApiKey($secret_key);
        
        // Create refund
        $refund = \Stripe\Refund::create([
            'payment_intent' => $booking->stripe_payment_intent_id,
            'reason' => 'requested_by_customer'
        ]);
        
        if ($refund->status === 'succeeded') {
            // Update booking status
            $wpdb->update(
                $wpdb->prefix . 'pp_bookings',
                ['payment_status' => 'refunded'],
                ['id' => $booking_id]
            );
            
            // Restore event seats
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}pp_events SET seats = seats + %d WHERE id = %d",
                $booking->tickets,
                $booking->event_id
            ));
            
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Refund failed: ' . $refund->failure_reason];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Refund error: ' . $e->getMessage()];
    }
}

/**
 * Resend confirmation email
 */
function puzzlepath_resend_confirmation_email($booking_id) {
    global $wpdb;
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pp_bookings WHERE id = %d", 
        $booking_id
    ));
    
    if ($booking && class_exists('PuzzlePath_Stripe_Integration')) {
        $stripe_instance = PuzzlePath_Stripe_Integration::get_instance();
        if (method_exists($stripe_instance, 'send_confirmation_email')) {
            // Use reflection to call private method
            $reflection = new ReflectionClass($stripe_instance);
            $method = $reflection->getMethod('send_confirmation_email');
            $method->setAccessible(true);
            $method->invoke($stripe_instance, $booking, $booking->booking_code);
        }
    }
}

/**
 * Export bookings to CSV
 */
function puzzlepath_export_bookings_csv() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $events_table = $wpdb->prefix . 'pp_events';
    $coupons_table = $wpdb->prefix . 'pp_coupons';
    
    // Apply same filters as the main page
    $where_clauses = [];
    $where_values = [];
    
    // ... (copy filter logic from main function)
    
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    $query = "SELECT b.*, e.title as event_title, e.hunt_code, e.hunt_name, e.event_date, c.code as coupon_code
             FROM $bookings_table b 
             LEFT JOIN $events_table e ON b.event_id = e.id
             LEFT JOIN $coupons_table c ON b.coupon_id = c.id
             $where_sql
             ORDER BY b.created_at DESC";
    
    $bookings = empty($where_values) ? $wpdb->get_results($query) : $wpdb->get_results($wpdb->prepare($query, $where_values));
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="puzzlepath-bookings-' . date('Y-m-d-H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Booking ID',
        'Booking Code', 
        'Customer Name',
        'Customer Email',
        'Event Title',
        'Hunt Code',
        'Hunt Name',
        'Event Date',
        'Tickets',
        'Total Price',
        'Coupon Code',
        'Payment Status',
        'Booking Date',
        'Participant Names'
    ]);
    
    // CSV data
    foreach ($bookings as $booking) {
        fputcsv($output, [
            $booking->id,
            $booking->booking_code,
            $booking->customer_name,
            $booking->customer_email,
            $booking->event_title,
            $booking->hunt_code,
            $booking->hunt_name,
            $booking->event_date ? date('Y-m-d H:i:s', strtotime($booking->event_date)) : '',
            $booking->tickets,
            $booking->total_price,
            $booking->coupon_code,
            $booking->payment_status,
            $booking->created_at,
            $booking->participant_names
        ]);
    }
    
    fclose($output);
}

/**
 * AJAX handler for booking details
 */
function puzzlepath_get_booking_details_ajax() {
    check_ajax_referer('booking_details_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    global $wpdb;
    $booking_id = intval($_POST['booking_id']);
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, e.title as event_title, e.hunt_code, e.hunt_name, e.event_date, e.location, c.code as coupon_code, c.discount_percent
         FROM {$wpdb->prefix}pp_bookings b 
         LEFT JOIN {$wpdb->prefix}pp_events e ON b.event_id = e.id
         LEFT JOIN {$wpdb->prefix}pp_coupons c ON b.coupon_id = c.id
         WHERE b.id = %d", 
        $booking_id
    ));
    
    if (!$booking) {
        wp_send_json_error('Booking not found');
        return;
    }
    
    ob_start();
    ?>
    <h2>Booking Details #<?php echo $booking->id; ?></h2>
    
    <table class="form-table">
        <tr>
            <th>Booking Code:</th>
            <td><strong><?php echo esc_html($booking->booking_code); ?></strong></td>
        </tr>
        <tr>
            <th>Customer:</th>
            <td><?php echo esc_html($booking->customer_name); ?> (<?php echo esc_html($booking->customer_email); ?>)</td>
        </tr>
        <tr>
            <th>Event:</th>
            <td><?php echo esc_html($booking->event_title); ?></td>
        </tr>
        <?php if ($booking->hunt_name || $booking->hunt_code): ?>
        <tr>
            <th>Hunt:</th>
            <td>
                <?php echo esc_html($booking->hunt_name ?: $booking->hunt_code); ?>
                <?php if ($booking->hunt_name && $booking->hunt_code): ?>
                    (<?php echo esc_html($booking->hunt_code); ?>)
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($booking->event_date): ?>
        <tr>
            <th>Event Date:</th>
            <td><?php echo date('F j, Y, g:i A', strtotime($booking->event_date)); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($booking->location): ?>
        <tr>
            <th>Location:</th>
            <td><?php echo esc_html($booking->location); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Tickets:</th>
            <td><?php echo $booking->tickets; ?></td>
        </tr>
        <tr>
            <th>Total Price:</th>
            <td>$<?php echo number_format($booking->total_price, 2); ?></td>
        </tr>
        <?php if ($booking->coupon_code): ?>
        <tr>
            <th>Coupon:</th>
            <td><?php echo esc_html($booking->coupon_code); ?> (<?php echo $booking->discount_percent; ?>% off)</td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Payment Status:</th>
            <td>
                <span style="background: <?php 
                    echo $booking->payment_status === 'paid' ? '#00a32a' :
                         ($booking->payment_status === 'pending' ? '#dba617' : '#d63638'); 
                ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                    <?php echo esc_html($booking->payment_status); ?>
                </span>
            </td>
        </tr>
        <?php if ($booking->stripe_payment_intent_id): ?>
        <tr>
            <th>Stripe Payment ID:</th>
            <td><code><?php echo esc_html($booking->stripe_payment_intent_id); ?></code></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Booking Date:</th>
            <td><?php echo date('F j, Y, g:i A', strtotime($booking->created_at)); ?></td>
        </tr>
        <?php if ($booking->participant_names): ?>
        <tr>
            <th>Participant Names:</th>
            <td><?php echo nl2br(esc_html($booking->participant_names)); ?></td>
        </tr>
        <?php endif; ?>
    </table>
    
    <div style="margin-top: 20px;">
        <button type="button" class="button button-primary" onclick="closeBookingDetails(); editBookingDetails(<?php echo $booking->id; ?>);">Edit Booking</button>
        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=puzzlepath-bookings&action=resend_email&booking_id=' . $booking->id), 'puzzlepath_resend_' . $booking->id); ?>" class="button">Resend Confirmation Email</a>
        <?php if ($booking->payment_status === 'paid'): ?>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=puzzlepath-bookings&action=refund&booking_id=' . $booking->id), 'puzzlepath_refund_' . $booking->id); ?>" 
               class="button button-secondary" onclick="return confirm('Are you sure you want to refund this booking?');">Process Refund</a>
        <?php endif; ?>
    </div>
    <?php
    
    $content = ob_get_clean();
    wp_send_json_success($content);
}
add_action('wp_ajax_get_booking_details', 'puzzlepath_get_booking_details_ajax');

/**
 * AJAX handler for edit booking form
 */
function puzzlepath_get_edit_booking_form_ajax() {
    check_ajax_referer('edit_booking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    global $wpdb;
    $booking_id = intval($_POST['booking_id']);
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, e.title as event_title, e.hunt_code, e.hunt_name, e.event_date, e.location, c.code as coupon_code, c.discount_percent
         FROM {$wpdb->prefix}pp_bookings b 
         LEFT JOIN {$wpdb->prefix}pp_events e ON b.event_id = e.id
         LEFT JOIN {$wpdb->prefix}pp_coupons c ON b.coupon_id = c.id
         WHERE b.id = %d", 
        $booking_id
    ));
    
    if (!$booking) {
        wp_send_json_error('Booking not found');
        return;
    }
    
    ob_start();
    ?>
    <h2 style="margin: 0 0 15px 0; padding-right: 40px;">Edit Booking #<?php echo $booking->id; ?></h2>
    
    <form id="edit-booking-form">
        <table class="form-table" style="margin-top: 0;">
            <tr>
                <th><label for="edit-booking-code">Booking Code:</label></th>
                <td><input type="text" id="edit-booking-code" name="booking_code" value="<?php echo esc_attr($booking->booking_code); ?>" class="regular-text" readonly style="background: #f7f7f7;" /></td>
            </tr>
            <tr>
                <th><label for="edit-customer-name">Customer Name:</label></th>
                <td><input type="text" id="edit-customer-name" name="customer_name" value="<?php echo esc_attr($booking->customer_name); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="edit-customer-email">Customer Email:</label></th>
                <td><input type="email" id="edit-customer-email" name="customer_email" value="<?php echo esc_attr($booking->customer_email); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="edit-event-title">Event:</label></th>
                <td><input type="text" id="edit-event-title" value="<?php echo esc_attr($booking->event_title); ?>" class="regular-text" readonly style="background: #f7f7f7;" /></td>
            </tr>
            <?php if ($booking->hunt_name || $booking->hunt_code): ?>
            <tr>
                <th>Hunt:</th>
                <td>
                    <input type="text" value="<?php echo esc_attr($booking->hunt_name ?: $booking->hunt_code); ?><?php echo ($booking->hunt_name && $booking->hunt_code) ? ' (' . $booking->hunt_code . ')' : ''; ?>" class="regular-text" readonly style="background: #f7f7f7;" />
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><label for="edit-tickets">Number of Tickets:</label></th>
                <td><input type="number" id="edit-tickets" name="tickets" value="<?php echo esc_attr($booking->tickets); ?>" min="1" max="50" class="small-text" required /></td>
            </tr>
            <tr>
                <th><label for="edit-total-price">Total Price ($):</label></th>
                <td><input type="number" id="edit-total-price" name="total_price" value="<?php echo esc_attr($booking->total_price); ?>" step="0.01" min="0" class="regular-text" required /></td>
            </tr>
            <?php if ($booking->coupon_code): ?>
            <tr>
                <th>Coupon:</th>
                <td><input type="text" value="<?php echo esc_attr($booking->coupon_code); ?> (<?php echo $booking->discount_percent; ?>% off)" class="regular-text" readonly style="background: #f7f7f7;" /></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><label for="edit-payment-status">Payment Status:</label></th>
                <td>
                    <select id="edit-payment-status" name="payment_status" class="regular-text" required>
                        <option value="pending" <?php selected($booking->payment_status, 'pending'); ?>>Pending</option>
                        <option value="paid" <?php selected($booking->payment_status, 'paid'); ?>>Paid</option>
                        <option value="failed" <?php selected($booking->payment_status, 'failed'); ?>>Failed</option>
                        <option value="refunded" <?php selected($booking->payment_status, 'refunded'); ?>>Refunded</option>
                    </select>
                </td>
            </tr>
            <?php if ($booking->participant_names): ?>
            <tr>
                <th><label for="edit-participant-names">Participant Names:</label></th>
                <td><textarea id="edit-participant-names" name="participant_names" rows="4" class="large-text"><?php echo esc_textarea($booking->participant_names); ?></textarea></td>
            </tr>
            <?php endif; ?>
        </table>
    </form>
    
    <div style="margin-top: 20px; padding: 15px 0; border-top: 1px solid #ddd; background: #f9f9f9; margin-left: -20px; margin-right: -20px; padding-left: 20px; padding-right: 20px;">
        <button type="button" id="edit-booking-save-btn" class="button button-primary" onclick="saveBookingChanges(<?php echo $booking->id; ?>)" style="margin-right: 10px;">Save Changes</button>
        <button type="button" class="button" onclick="closeEditBooking()">Cancel</button>
        <div style="clear: both;"></div>
    </div>
    <?php
    
    $content = ob_get_clean();
    wp_send_json_success($content);
}
add_action('wp_ajax_get_edit_booking_form', 'puzzlepath_get_edit_booking_form_ajax');

/**
 * AJAX handler for saving booking changes
 */
function puzzlepath_save_booking_changes_ajax() {
    check_ajax_referer('save_booking_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    global $wpdb;
    $booking_id = intval($_POST['booking_id']);
    
    // Validate input
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $tickets = intval($_POST['tickets']);
    $total_price = floatval($_POST['total_price']);
    $payment_status = sanitize_text_field($_POST['payment_status']);
    $participant_names = isset($_POST['participant_names']) ? sanitize_textarea_field($_POST['participant_names']) : '';
    
    // Validation
    if (empty($customer_name) || empty($customer_email) || $tickets < 1 || $total_price < 0) {
        wp_send_json_error('Please fill in all required fields with valid values.');
        return;
    }
    
    if (!is_email($customer_email)) {
        wp_send_json_error('Please enter a valid email address.');
        return;
    }
    
    if (!in_array($payment_status, ['pending', 'paid', 'failed', 'refunded'])) {
        wp_send_json_error('Invalid payment status.');
        return;
    }
    
    // Update booking
    $update_data = [
        'customer_name' => $customer_name,
        'customer_email' => $customer_email,
        'tickets' => $tickets,
        'total_price' => $total_price,
        'payment_status' => $payment_status
    ];
    
    if ($participant_names !== '') {
        $update_data['participant_names'] = $participant_names;
    }
    
    $result = $wpdb->update(
        $wpdb->prefix . 'pp_bookings',
        $update_data,
        ['id' => $booking_id],
        ['%s', '%s', '%d', '%f', '%s', '%s'],
        ['%d']
    );
    
    if ($result === false) {
        wp_send_json_error('Database error: Could not update booking.');
        return;
    }
    
    wp_send_json_success('Booking updated successfully!');
}
add_action('wp_ajax_save_booking_changes', 'puzzlepath_save_booking_changes_ajax');


/**
 * AJAX handler for quest details
 */
function puzzlepath_get_quest_details_ajax() {
    // Add debugging
    error_log('Quest details AJAX called with data: ' . print_r($_POST, true));
    
    try {
        check_ajax_referer('quest_details_nonce', 'nonce');
    } catch (Exception $e) {
        wp_send_json_error('Nonce verification failed: ' . $e->getMessage());
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    $clues_table = $wpdb->prefix . 'pp_clues';
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    
    $quest_id = intval($_POST['quest_id']);
    
    if (!$quest_id) {
        wp_send_json_error('Invalid quest ID provided');
        return;
    }
    
    $quest = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$events_table} WHERE id = %d", 
        $quest_id
    ));
    
    if (!$quest) {
        wp_send_json_error('Quest not found with ID: ' . $quest_id);
        return;
    }
    
    if ($wpdb->last_error) {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
        return;
    }
    
    // Get clue count and booking stats
    $clue_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$clues_table} WHERE hunt_id = %d AND is_active = 1",
        $quest->id
    ));
    
    $booking_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) as total_bookings, SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_bookings, SUM(CASE WHEN payment_status = 'paid' THEN total_price ELSE 0 END) as total_revenue FROM {$bookings_table} WHERE event_id = %d",
        $quest_id
    ));
    
    try {
        ob_start();
        ?>
        <h2 style="margin: 0 0 15px 0; padding-right: 40px;">Quest Details: <?php echo esc_html($quest->title); ?></h2>
    
    <table class="form-table" style="margin-top: 0;">
        <tr>
            <th>Quest Code:</th>
            <td><strong><?php echo esc_html($quest->hunt_code); ?></strong></td>
        </tr>
        <tr>
            <th>Quest Name:</th>
            <td><?php echo esc_html($quest->title); ?></td>
        </tr>
        <?php if ($quest->hunt_name && $quest->hunt_name != $quest->title): ?>
        <tr>
            <th>Hunt Name:</th>
            <td><?php echo esc_html($quest->hunt_name); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Location:</th>
            <td><?php echo esc_html($quest->location); ?></td>
        </tr>
        <tr>
            <th>Quest Type:</th>
            <td><span style="background: <?php echo $quest->hosting_type === 'hosted' ? '#00a32a' : '#2271b1'; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                <?php echo esc_html($quest->hosting_type === 'hosted' ? 'LIVE' : 'ANYTIME'); ?>
            </span></td>
        </tr>
        <?php if ($quest->event_date): ?>
        <tr>
            <th>Event Date:</th>
            <td><?php echo date('F j, Y, g:i A', strtotime($quest->event_date)); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Price:</th>
            <td>$<?php echo number_format($quest->price, 2); ?></td>
        </tr>
        <tr>
            <th>Seats Available:</th>
            <td><?php echo $quest->seats; ?> seats</td>
        </tr>
        <tr>
            <th>Number of Clues:</th>
            <td><?php echo $clue_count ?: 0; ?> clues</td>
        </tr>
        <?php if ($quest->duration_minutes): ?>
        <tr>
            <th>Duration:</th>
            <td>
                <?php 
                $hours = floor($quest->duration_minutes / 60);
                $minutes = $quest->duration_minutes % 60;
                if ($hours > 0 && $minutes > 0) {
                    echo $hours . 'h ' . $minutes . 'm';
                } elseif ($hours > 0) {
                    echo $hours . ' hour' . ($hours > 1 ? 's' : '');
                } else {
                    echo $minutes . ' minutes';
                }
                ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php if ($quest->medal_image_url): ?>
        <tr>
            <th>Medal Image:</th>
            <td>
                <img src="<?php echo esc_url($quest->medal_image_url); ?>" alt="Quest Medal" style="max-width: 120px; max-height: 120px; border: 2px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" />
                <p><small><a href="<?php echo esc_url($quest->medal_image_url); ?>" target="_blank">View full size</a></small></p>
            </td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Total Bookings:</th>
            <td><?php echo $booking_stats->total_bookings ?: 0; ?> bookings</td>
        </tr>
        <tr>
            <th>Paid Bookings:</th>
            <td><?php echo $booking_stats->paid_bookings ?: 0; ?> paid</td>
        </tr>
        <tr>
            <th>Total Revenue:</th>
            <td>$<?php echo number_format($booking_stats->total_revenue ?: 0, 2); ?></td>
        </tr>
        <tr>
            <th>Status:</th>
            <td>
                <span style="background: <?php echo in_array($quest->hosting_type, ['hosted', 'self-hosted']) ? '#00a32a' : '#d63638'; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                    <?php echo in_array($quest->hosting_type, ['hosted', 'self-hosted']) ? 'ACTIVE' : 'INACTIVE'; ?>
                </span>
            </td>
        </tr>
        <tr>
            <th>Display on Site:</th>
            <td>
                <span style="background: <?php echo $quest->display_on_site ? '#00a32a' : '#666'; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase;">
                    <?php echo $quest->display_on_site ? '👁️ VISIBLE' : '🚫 HIDDEN'; ?>
                </span>
                <p><small><?php echo $quest->display_on_site ? 'This quest appears on the public website' : 'This quest is hidden from public view'; ?></small></p>
            </td>
        </tr>
        <tr>
            <th>Created:</th>
            <td><?php echo date('F j, Y, g:i A', strtotime($quest->created_at)); ?></td>
        </tr>
    </table>
    
    <div style="margin-top: 20px; padding: 15px 0; border-top: 1px solid #ddd; background: #f9f9f9; margin-left: -20px; margin-right: -20px; padding-left: 20px; padding-right: 20px;">
        <button type="button" class="button button-primary" onclick="closeQuestDetails(); editQuest(<?php echo $quest->id; ?>);" style="margin-right: 10px;">Edit Quest</button>
        <button type="button" class="button" onclick="closeQuestDetails(); manageClues(<?php echo $quest->id; ?>);">Manage Clues</button>
        <button type="button" class="button" onclick="closeQuestDetails()">Close</button>
    </div>
    <?php
    
        $content = ob_get_clean();
        wp_send_json_success($content);
        
    } catch (Exception $e) {
        if (ob_get_level()) {
            ob_end_clean();
        }
        wp_send_json_error('Error generating content: ' . $e->getMessage());
    }
}
add_action('wp_ajax_get_quest_details', 'puzzlepath_get_quest_details_ajax');

/**
 * AJAX handler for edit quest form
 */
function puzzlepath_get_edit_quest_form_ajax() {
    check_ajax_referer('edit_quest_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    
    $quest_id = intval($_POST['quest_id']);
    
    if (!$quest_id) {
        wp_send_json_error('Invalid quest ID provided');
        return;
    }
    
    $quest = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$events_table} WHERE id = %d", 
        $quest_id
    ));
    
    if (!$quest) {
        wp_send_json_error('Quest not found with ID: ' . $quest_id);
        return;
    }
    
    ob_start();
    ?>
    <h2 style="margin: 0 0 15px 0; padding-right: 40px;">Edit Quest: <?php echo esc_html($quest->title); ?></h2>
    
    <form id="edit-quest-form">
        <table class="form-table" style="margin-top: 0;">
            <tr>
                <th><label for="edit-quest-code">Quest Code:</label></th>
                <td><input type="text" id="edit-quest-code" name="hunt_code" value="<?php echo esc_attr($quest->hunt_code); ?>" class="regular-text" readonly style="background: #f7f7f7;" /></td>
            </tr>
            <tr>
                <th><label for="edit-quest-title">Quest Name:</label></th>
                <td><input type="text" id="edit-quest-title" name="title" value="<?php echo esc_attr($quest->title); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="edit-hunt-name">Hunt Name:</label></th>
                <td><input type="text" id="edit-hunt-name" name="hunt_name" value="<?php echo esc_attr($quest->hunt_name); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="edit-location">Location:</label></th>
                <td><input type="text" id="edit-location" name="location" value="<?php echo esc_attr($quest->location); ?>" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="edit-hosting-type">Quest Type:</label></th>
                <td>
                    <select id="edit-hosting-type" name="hosting_type" class="regular-text" required>
                        <option value="self-hosted" <?php selected($quest->hosting_type, 'self-hosted'); ?>>ANYTIME Quest (Self-hosted)</option>
                        <option value="hosted" <?php selected($quest->hosting_type, 'hosted'); ?>>LIVE Quest (Scheduled)</option>
                        <option value="inactive" <?php selected($quest->hosting_type, 'inactive'); ?>>Inactive</option>
                    </select>
                </td>
            </tr>
            <?php if ($quest->hosting_type === 'hosted'): ?>
            <tr>
                <th><label for="edit-event-date">Event Date & Time:</label></th>
                <td><input type="datetime-local" id="edit-event-date" name="event_date" value="<?php echo $quest->event_date ? date('Y-m-d\TH:i', strtotime($quest->event_date)) : ''; ?>" class="regular-text" /></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><label for="edit-price">Price ($):</label></th>
                <td><input type="number" id="edit-price" name="price" value="<?php echo esc_attr($quest->price); ?>" step="0.01" min="0" class="regular-text" required /></td>
            </tr>
            <tr>
                <th><label for="edit-seats">Available Seats:</label></th>
                <td><input type="number" id="edit-seats" name="seats" value="<?php echo esc_attr($quest->seats); ?>" min="1" max="1000" class="small-text" required /></td>
            </tr>
            <tr>
                <th><label for="edit-duration">Duration (minutes):</label></th>
                <td>
                    <input type="number" id="edit-duration" name="duration_minutes" value="<?php echo esc_attr($quest->duration_minutes ?: ''); ?>" min="0" max="600" class="small-text" placeholder="e.g., 90" />
                    <p class="description">Expected time to complete the quest in minutes (optional)</p>
                </td>
            </tr>
            <tr>
                <th><label for="edit-medal-image">Medal Image:</label></th>
                <td>
                    <div id="medal-image-container">
                        <?php if ($quest->medal_image_url): ?>
                            <div id="current-medal-image" style="margin-bottom: 10px;">
                                <img src="<?php echo esc_url($quest->medal_image_url); ?>" alt="Current Medal" style="max-width: 100px; max-height: 100px; border: 2px solid #ddd; border-radius: 5px;" />
                                <p><small>Current medal image</small></p>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="edit-medal-image" name="medal_image" accept="image/*" style="margin-bottom: 5px;" />
                        <input type="hidden" id="edit-medal-image-url" name="medal_image_url" value="<?php echo esc_attr($quest->medal_image_url ?: ''); ?>" />
                        <p class="description">Upload a medal image for quest completion (JPG, PNG, GIF - max 4MB)</p>
                        <?php if ($quest->medal_image_url): ?>
                            <p><button type="button" class="button" onclick="removeMedalImage()">Remove Current Image</button></p>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label for="edit-display-on-site">Display on Site:</label></th>
                <td>
                    <label style="display: flex; align-items: center;">
                        <input type="checkbox" id="edit-display-on-site" name="display_on_site" value="1" <?php echo $quest->display_on_site ? 'checked' : ''; ?> style="margin-right: 8px;" />
                        <span>Make this quest visible on the public website</span>
                    </label>
                    <p class="description">When checked, this quest will appear in public listings and be available for booking</p>
                </td>
            </tr>
        </table>
    </form>
    
    <div style="margin-top: 20px; padding: 15px 0; border-top: 1px solid #ddd; background: #f9f9f9; margin-left: -20px; margin-right: -20px; padding-left: 20px; padding-right: 20px;">
        <button type="button" id="quest-save-btn" class="button button-primary" onclick="saveQuestChanges(<?php echo $quest->id; ?>)" style="margin-right: 10px;">Save Changes</button>
        <button type="button" class="button" onclick="closeEditQuest()">Cancel</button>
        <div style="clear: both;"></div>
    </div>
    <?php
    
    $content = ob_get_clean();
    wp_send_json_success($content);
}
add_action('wp_ajax_get_edit_quest_form', 'puzzlepath_get_edit_quest_form_ajax');

/**
 * AJAX handler for saving quest changes
 */
function puzzlepath_save_quest_changes_ajax() {
    check_ajax_referer('save_quest_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    
    $quest_id = intval($_POST['quest_id']);
    
    if (!$quest_id) {
        wp_send_json_error('Invalid quest ID provided');
        return;
    }
    
    // Validate and sanitize input
    $title = sanitize_text_field($_POST['title']);
    $hunt_name = sanitize_text_field($_POST['hunt_name']);
    $location = sanitize_text_field($_POST['location']);
    $hosting_type = sanitize_text_field($_POST['hosting_type']);
    $price = floatval($_POST['price']);
    $seats = intval($_POST['seats']);
    $duration_minutes = !empty($_POST['duration_minutes']) ? intval($_POST['duration_minutes']) : null;
    $medal_image_url = sanitize_text_field($_POST['medal_image_url']);
    $display_on_site = isset($_POST['display_on_site']) ? 1 : 0;
    $event_date = !empty($_POST['event_date']) ? sanitize_text_field($_POST['event_date']) : null;
    
    // Handle medal image upload
    if (!empty($_FILES['medal_image']['name'])) {
        $upload_result = puzzlepath_handle_medal_image_upload($_FILES['medal_image']);
        if ($upload_result['success']) {
            $medal_image_url = $upload_result['url'];
        } else {
            wp_send_json_error('Medal image upload failed: ' . $upload_result['error']);
            return;
        }
    }
    
    // Validation
    if (empty($title) || empty($location) || $price < 0 || $seats < 1) {
        wp_send_json_error('Please fill in all required fields with valid values.');
        return;
    }
    
    if (!in_array($hosting_type, ['hosted', 'self-hosted', 'inactive'])) {
        wp_send_json_error('Invalid hosting type.');
        return;
    }
    
    // Prepare update data
    $update_data = [
        'title' => $title,
        'hunt_name' => $hunt_name,
        'location' => $location,
        'hosting_type' => $hosting_type,
        'price' => $price,
        'seats' => $seats,
        'duration_minutes' => $duration_minutes,
        'medal_image_url' => $medal_image_url,
        'display_on_site' => $display_on_site
    ];
    
    if ($event_date) {
        $update_data['event_date'] = date('Y-m-d H:i:s', strtotime($event_date));
    } elseif ($hosting_type !== 'hosted') {
        $update_data['event_date'] = null;
    }
    
    // Update quest
    $result = $wpdb->update(
        $events_table,
        $update_data,
        ['id' => $quest_id],
        ['%s', '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%d'],
        ['%d']
    );
    
    if ($result === false) {
        wp_send_json_error('Database error: Could not update quest. ' . $wpdb->last_error);
        return;
    }
    
    wp_send_json_success('Quest updated successfully!');
}
add_action('wp_ajax_save_quest_changes', 'puzzlepath_save_quest_changes_ajax');

/**
 * AJAX handler for toggling quest display on site
 */
function puzzlepath_toggle_quest_display_ajax() {
    check_ajax_referer('toggle_quest_display_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    
    $quest_id = intval($_POST['quest_id']);
    $display_on_site = intval($_POST['display_on_site']);
    
    if (!$quest_id) {
        wp_send_json_error('Invalid quest ID provided');
        return;
    }
    
    // Update display status
    $result = $wpdb->update(
        $events_table,
        ['display_on_site' => $display_on_site],
        ['id' => $quest_id],
        ['%d'],
        ['%d']
    );
    
    if ($result === false) {
        wp_send_json_error('Database error: Could not update display status. ' . $wpdb->last_error);
        return;
    }
    
    wp_send_json_success($display_on_site ? 'Quest is now visible on site' : 'Quest is now hidden from site');
}
add_action('wp_ajax_toggle_quest_display', 'puzzlepath_toggle_quest_display_ajax');

/**
 * AJAX handler for quest clues management
 */
function puzzlepath_get_quest_clues_ajax() {
    check_ajax_referer('quest_clues_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    $clues_table = $wpdb->prefix . 'pp_clues';
    
    $quest_id = intval($_POST['quest_id']);
    
    if (!$quest_id) {
        wp_send_json_error('Invalid quest ID provided');
        return;
    }
    
    // Get quest info
    $quest = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$events_table} WHERE id = %d", 
        $quest_id
    ));
    
    if (!$quest) {
        wp_send_json_error('Quest not found with ID: ' . $quest_id);
        return;
    }
    
    // Get all clues for this quest
    $clues = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$clues_table} WHERE hunt_id = %d ORDER BY clue_order ASC",
        $quest->id
    ));
    
    ob_start();
    ?>
    <h2 style="margin: 0 0 15px 0; padding-right: 40px;">Manage Clues: <?php echo esc_html($quest->title); ?></h2>
    
    <div style="margin-bottom: 20px; padding: 10px; background: #f0f0f1; border-radius: 4px;">
        <strong>Quest:</strong> <?php echo esc_html($quest->title); ?> (<?php echo esc_html($quest->hunt_code); ?>)<br>
        <strong>Location:</strong> <?php echo esc_html($quest->location); ?><br>
        <strong>Total Clues:</strong> <?php echo count($clues); ?>
    </div>
    
    <?php if (empty($clues)): ?>
        <div style="text-align: center; padding: 40px; background: #fff; border: 2px dashed #ddd; border-radius: 4px;">
            <h3>No Clues Found</h3>
            <p>This quest doesn't have any clues yet.</p>
            <p>Clues should be linked to the hunt_code: <strong><?php echo esc_html($quest->hunt_code); ?></strong></p>
            <button type="button" class="button button-primary" onclick="addNewClue('<?php echo esc_js($quest->hunt_code); ?>')">Add First Clue</button>
        </div>
    <?php else: ?>
        <div style="margin-bottom: 10px;">
            <button type="button" class="button button-primary" onclick="addNewClue('<?php echo esc_js($quest->hunt_code); ?>')">Add New Clue</button>
        </div>
        
        <div class="clues-list">
            <?php foreach ($clues as $clue): ?>
                <div class="clue-item" style="background: #fff; margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 10px 0; color: #2271b1;">
                                Clue #<?php echo $clue->clue_order; ?>
                                <?php if ($clue->title): ?>
                                    - <?php echo esc_html($clue->title); ?>
                                <?php endif; ?>
                                <span style="font-size: 12px; color: <?php echo $clue->is_active ? '#00a32a' : '#d63638'; ?>; margin-left: 10px;">
                                    <?php echo $clue->is_active ? '●ACTIVE' : '●INACTIVE'; ?>
                                </span>
                            </h4>
                            
                            <div style="margin-bottom: 10px;">
                                <strong>Clue Text:</strong><br>
                                <div style="background: #f9f9f9; padding: 8px; border-radius: 3px; margin-top: 5px;">
                                    <?php echo esc_html($clue->clue_text ?: 'No clue text'); ?>
                                </div>
                            </div>
                            
                            <?php if ($clue->task_description): ?>
                            <div style="margin-bottom: 10px;">
                                <strong>Task:</strong><br>
                                <div style="background: #f0f6ff; padding: 8px; border-radius: 3px; margin-top: 5px;">
                                    <?php echo esc_html($clue->task_description); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($clue->hint_text): ?>
                            <div style="margin-bottom: 10px;">
                                <strong>Hint:</strong><br>
                                <div style="background: #fff3cd; padding: 8px; border-radius: 3px; margin-top: 5px;">
                                    <?php echo esc_html($clue->hint_text); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($clue->latitude && $clue->longitude): ?>
                            <div style="margin-bottom: 10px;">
                                <strong>Location:</strong> 
                                Lat: <?php echo $clue->latitude; ?>, Lng: <?php echo $clue->longitude; ?>
                                <?php if ($clue->geofence_radius): ?>
                                    (<?php echo $clue->geofence_radius; ?>m radius)
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($clue->image_url): ?>
                            <div style="margin-bottom: 10px;">
                                <strong>Image:</strong> 
                                <a href="<?php echo esc_url($clue->image_url); ?>" target="_blank">View Image</a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="margin-left: 15px;">
                            <button type="button" class="button button-small" onclick="editClue(<?php echo $clue->id; ?>)" title="Edit Clue">✏️ Edit</button>
                            <button type="button" class="button button-small" onclick="toggleClueStatus(<?php echo $clue->id; ?>, <?php echo $clue->is_active ? 'false' : 'true'; ?>)" title="<?php echo $clue->is_active ? 'Deactivate' : 'Activate'; ?> Clue">
                                <?php echo $clue->is_active ? '🚫' : '✅'; ?>
                            </button>
                            <button type="button" class="button button-small" onclick="deleteClue(<?php echo $clue->id; ?>)" title="Delete Clue" style="color: #d63638;">🗑️</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 20px; padding: 15px 0; border-top: 1px solid #ddd; background: #f9f9f9; margin-left: -20px; margin-right: -20px; padding-left: 20px; padding-right: 20px;">
        <button type="button" class="button" onclick="closeManageClues()">Close</button>
        <div style="clear: both;"></div>
    </div>
    
    <script>
    function addNewClue(huntCode) {
        alert('Add New Clue functionality coming soon for hunt: ' + huntCode);
    }
    
    function editClue(clueId) {
        alert('Edit Clue functionality coming soon for clue ID: ' + clueId);
    }
    
    function toggleClueStatus(clueId, newStatus) {
        if (confirm('Are you sure you want to ' + (newStatus === 'true' ? 'activate' : 'deactivate') + ' this clue?')) {
            alert('Toggle clue status functionality coming soon');
        }
    }
    
    function deleteClue(clueId) {
        if (confirm('Are you sure you want to delete this clue? This action cannot be undone.')) {
            alert('Delete clue functionality coming soon');
        }
    }
    </script>
    <?php
    
    $content = ob_get_clean();
    wp_send_json_success($content);
}
add_action('wp_ajax_get_quest_clues', 'puzzlepath_get_quest_clues_ajax');

/**
 * Quest Management Page
 */
function puzzlepath_quests_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    global $wpdb;
    $events_table = $wpdb->prefix . 'pp_events';
    $clues_table = $wpdb->prefix . 'pp_clues';
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $completions_table = $wpdb->prefix . 'pp_quest_completions';
    
    // Handle actions
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'deactivate':
                if (isset($_GET['event_id']) && wp_verify_nonce($_GET['_wpnonce'], 'deactivate_quest_' . $_GET['event_id'])) {
                    $event_id = intval($_GET['event_id']);
                    
                    $wpdb->update(
                        $events_table,
                        ['hosting_type' => 'inactive'],
                        ['id' => $event_id]
                    );
                    
                    wp_redirect(admin_url('admin.php?page=puzzlepath-quests&message=deactivated'));
                    exit;
                }
                break;
                
            case 'activate':
                if (isset($_GET['event_id']) && wp_verify_nonce($_GET['_wpnonce'], 'activate_quest_' . $_GET['event_id'])) {
                    $event_id = intval($_GET['event_id']);
                    
                    // Get the current quest to determine what type to restore it to
                    $quest = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$events_table} WHERE id = %d", $event_id));
                    
                    if ($quest) {
                        // Default to 'self-hosted' if unsure, or 'hosted' if it has a specific event_date
                        $new_hosting_type = $quest->event_date ? 'hosted' : 'self-hosted';
                        
                        $wpdb->update(
                            $events_table,
                            ['hosting_type' => $new_hosting_type],
                            ['id' => $event_id]
                        );
                    }
                    
                    wp_redirect(admin_url('admin.php?page=puzzlepath-quests&message=activated'));
                    exit;
                }
                break;
                
            case 'delete':
                if (isset($_GET['event_id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_quest_' . $_GET['event_id'])) {
                    $event_id = intval($_GET['event_id']);
                    
                    // Get quest details before deletion for logging
                    $quest = $wpdb->get_row($wpdb->prepare("SELECT hunt_code, title FROM {$events_table} WHERE id = %d", $event_id));
                    
                    if ($quest) {
                        // Delete associated clues first
                        $wpdb->delete($clues_table, ['hunt_id' => $event_id]);
                        
                        // Delete the quest/event
                        $wpdb->delete($events_table, ['id' => $event_id]);
                        
                        // Note: We're not deleting bookings or completions to preserve historical data
                        // If you want to delete those too, uncomment the following lines:
                        // $wpdb->delete($bookings_table, ['event_id' => $event_id]);
                        // $wpdb->delete($completions_table, ['event_id' => $event_id]);
                    }
                    
                    wp_redirect(admin_url('admin.php?page=puzzlepath-quests&message=deleted'));
                    exit;
                }
                break;
        }
    }
    
    // Get all quests/events with clue counts and booking stats
    $quests = $wpdb->get_results("
        SELECT 
            e.*,
            e.title as quest_name,
            e.hunt_code as quest_code,
            e.hunt_name,
            COALESCE(clue_counts.clue_count, 0) as clue_count,
            COALESCE(booking_stats.total_bookings, 0) as total_completions,
            COALESCE(booking_stats.paid_bookings, 0) as paid_completions,
            'quest' as quest_type,
            CASE 
                WHEN e.hosting_type IN ('hosted', 'self-hosted') THEN 1
                WHEN e.hosting_type = 'inactive' THEN 0
                ELSE 1
            END as is_active
        FROM {$events_table} e
        LEFT JOIN (
            SELECT hunt_id, COUNT(*) as clue_count 
            FROM {$clues_table} 
            WHERE is_active = 1 
            GROUP BY hunt_id
        ) clue_counts ON e.id = clue_counts.hunt_id
        LEFT JOIN (
            SELECT 
                event_id,
                COUNT(*) as total_bookings,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_bookings
            FROM {$bookings_table}
            GROUP BY event_id
        ) booking_stats ON e.id = booking_stats.event_id
        WHERE e.hunt_code IS NOT NULL AND e.hunt_code != ''
        ORDER BY e.created_at DESC
    ");
    
    ?>
    <div class="wrap">
        <h1>Quest Management 
            <a href="#" class="page-title-action" onclick="showAddQuestModal(); return false;">Add New Quest</a>
        </h1>
        
        <?php if (isset($_GET['message'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php 
                    switch($_GET['message']) {
                        case 'added': echo 'Quest created successfully!'; break;
                        case 'updated': echo 'Quest updated successfully!'; break;
                        case 'activated': echo 'Quest activated successfully!'; break;
                        case 'deactivated': echo 'Quest deactivated successfully!'; break;
                        case 'deleted': echo 'Quest deleted successfully!'; break;
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Quest Statistics -->
        <div class="quest-stats" style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #2271b1; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #2271b1;">Total Quests</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo count($quests); ?></p>
            </div>
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #00a32a; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #00a32a;">Active Quests</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo count(array_filter($quests, function($q) { return $q->is_active; })); ?></p>
            </div>
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #dba617; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #dba617;">Total Clues</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo array_sum(array_column($quests, 'clue_count')); ?></p>
            </div>
            <div class="stat-box" style="background: #fff; padding: 15px; border-left: 4px solid #d63638; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="margin: 0; color: #d63638;">Total Completions</h3>
                <p style="font-size: 24px; margin: 5px 0; font-weight: bold;"><?php echo array_sum(array_column($quests, 'total_completions')); ?></p>
            </div>
        </div>
        
        <!-- Quests Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Quest Code</th>
                    <th>Quest Name</th>
                    <th>Location</th>
                    <th>Quest Type</th>
                    <th>Clues</th>
                    <th>Duration</th>
                    <th>Completions</th>
                    <th>Status</th>
                    <th>Display</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quests)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 20px;">No quests found. <a href="#" onclick="showAddQuestModal()">Create your first quest</a>.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($quests as $quest): ?>
                        <tr>
                            <td><strong><?php echo esc_html($quest->quest_code ?: $quest->hunt_code); ?></strong></td>
                            <td>
                                <strong><?php echo esc_html($quest->quest_name ?: $quest->title); ?></strong>
                                <?php if ($quest->hunt_name && $quest->hunt_name != $quest->title): ?>
                                    <br><small style="color: #666;"><?php echo esc_html($quest->hunt_name); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($quest->location ?: 'Not specified'); ?></td>
                            <td>
                                <span class="quest-type type-<?php echo esc_attr($quest->hosting_type); ?>" style="padding: 2px 6px; border-radius: 3px; font-size: 11px; text-transform: uppercase; color: white; background: <?php echo $quest->hosting_type === 'hosted' ? '#00a32a' : '#2271b1'; ?>;">
                                    <?php echo esc_html($quest->hosting_type === 'hosted' ? 'LIVE' : 'ANYTIME'); ?>
                                </span><br>
                                <small>Quest Type</small>
                            </td>
                            <td>
                                <strong><?php echo $quest->clue_count; ?></strong> clues
                                <?php if ($quest->total_clues && $quest->clue_count != $quest->total_clues): ?>
                                    <br><small style="color: #d63638;">(Expected: <?php echo $quest->total_clues; ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if ($quest->duration_minutes && $quest->duration_minutes > 0) {
                                    $hours = floor($quest->duration_minutes / 60);
                                    $minutes = $quest->duration_minutes % 60;
                                    
                                    if ($hours > 0 && $minutes > 0) {
                                        $duration_text = $hours . 'h ' . $minutes . 'm';
                                    } elseif ($hours > 0) {
                                        $duration_text = $hours . ' hour' . ($hours > 1 ? 's' : '');
                                    } else {
                                        $duration_text = $minutes . ' min';
                                    }
                                    echo '<span style="color: #2271b1; font-weight: 600;">⏱️ ' . $duration_text . '</span>';
                                } else {
                                    echo '<span style="color: #999;">Duration: TBD</span>';
                                }
                                ?><br>
                                <small>Price: $<?php echo number_format($quest->price, 2); ?></small>
                                <?php if ($quest->event_date): ?>
                                    <br><small style="color: #666;">Next: <?php echo date('M j, Y', strtotime($quest->event_date)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo $quest->total_completions; ?></strong> times
                            </td>
                            <td>
                                <span class="quest-status status-<?php echo $quest->is_active ? 'active' : 'inactive'; ?>" style="padding: 3px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase; color: white; background: <?php echo $quest->is_active ? '#00a32a' : '#d63638'; ?>;">
                                    <?php echo $quest->is_active ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <label class="display-toggle" style="display: inline-flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" 
                                           id="display_<?php echo $quest->id; ?>" 
                                           <?php echo $quest->display_on_site ? 'checked' : ''; ?> 
                                           onchange="toggleQuestDisplay(<?php echo $quest->id; ?>, this.checked)"
                                           style="margin: 0; margin-right: 5px;" />
                                    <span style="font-size: 11px; color: #666;"><?php echo $quest->display_on_site ? 'Visible' : 'Hidden'; ?></span>
                                </label>
                            </td>
                            <td>
                                <a href="#" onclick="showQuestDetails(<?php echo $quest->id; ?>); return false;" title="View Details">👁️</a>
                                <a href="#" onclick="editQuest(<?php echo $quest->id; ?>); return false;" title="Edit Quest">✏️</a>
                                <a href="#" onclick="manageClues(<?php echo $quest->id; ?>); return false;" title="Manage Clues">🧩</a>
                                <?php if ($quest->is_active): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=puzzlepath-quests&action=deactivate&event_id=' . $quest->id), 'deactivate_quest_' . $quest->id); ?>" 
                                       onclick="return confirm('Are you sure you want to deactivate this quest?');" title="Deactivate Quest">🚫</a>
                                <?php else: ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=puzzlepath-quests&action=activate&event_id=' . $quest->id), 'activate_quest_' . $quest->id); ?>" 
                                       onclick="return confirm('Are you sure you want to activate this quest?');" title="Activate Quest">✅</a>
                                <?php endif; ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=puzzlepath-quests&action=delete&event_id=' . $quest->id), 'delete_quest_' . $quest->id); ?>" 
                                   onclick="return confirmDeleteQuest('<?php echo esc_js($quest->quest_name ?: $quest->title); ?>', '<?php echo esc_js($quest->quest_code ?: $quest->hunt_code); ?>');" 
                                   title="Delete Quest" style="color: #d63638; text-decoration: none;">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Modals will be added here -->
    <?php puzzlepath_quest_modals(); ?>
    
    <style>
    .difficulty-easy { background: #00a32a !important; }
    .difficulty-medium { background: #dba617 !important; }
    .difficulty-hard { background: #d63638 !important; }
    .difficulty-expert { background: #8c8f94 !important; }
    </style>
    
    <script>
    // Quest Details Modal
    function showQuestDetails(questId) {
        document.getElementById('quest-details-modal').style.display = 'block';
        document.getElementById('quest-details-content').innerHTML = 'Loading...';
        
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'get_quest_details',
            quest_id: questId,
            nonce: '<?php echo wp_create_nonce('quest_details_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                document.getElementById('quest-details-content').innerHTML = response.data;
            } else {
                document.getElementById('quest-details-content').innerHTML = 'Error loading quest details: ' + (response.data || 'Unknown error');
            }
        }).fail(function(xhr, status, error) {
            document.getElementById('quest-details-content').innerHTML = 'AJAX Error: ' + error + '<br>Status: ' + status + '<br>Response: ' + xhr.responseText;
        });
    }
    
    function closeQuestDetails() {
        document.getElementById('quest-details-modal').style.display = 'none';
    }
    
    // Edit Quest Modal
    function editQuest(questId) {
        document.getElementById('edit-quest-modal').style.display = 'block';
        document.getElementById('edit-quest-content').innerHTML = 'Loading...';
        
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'get_edit_quest_form',
            quest_id: questId,
            nonce: '<?php echo wp_create_nonce('edit_quest_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                document.getElementById('edit-quest-content').innerHTML = response.data;
            } else {
                document.getElementById('edit-quest-content').innerHTML = 'Error loading quest form: ' + (response.data || 'Unknown error');
            }
        }).fail(function(xhr, status, error) {
            document.getElementById('edit-quest-content').innerHTML = 'AJAX Error: ' + error;
        });
    }
    
    function closeEditQuest() {
        document.getElementById('edit-quest-modal').style.display = 'none';
    }
    
    function saveQuestChanges(questId) {
        var form = document.getElementById('edit-quest-form');
        var formData = new FormData(form);
        formData.append('action', 'save_quest_changes');
        formData.append('quest_id', questId);
        formData.append('nonce', '<?php echo wp_create_nonce('save_quest_nonce'); ?>');
        
        document.getElementById('quest-save-btn').disabled = true;
        document.getElementById('quest-save-btn').textContent = 'Saving...';
        
        jQuery.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Quest updated successfully!');
                    closeEditQuest();
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    document.getElementById('quest-save-btn').disabled = false;
                    document.getElementById('quest-save-btn').textContent = 'Save Changes';
                }
            },
            error: function() {
                alert('An error occurred while saving changes.');
                document.getElementById('quest-save-btn').disabled = false;
                document.getElementById('quest-save-btn').textContent = 'Save Changes';
            }
        });
    }
    
    // Manage Clues Modal
    function manageClues(questId) {
        document.getElementById('manage-clues-modal').style.display = 'block';
        document.getElementById('manage-clues-content').innerHTML = 'Loading...';
        
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'get_quest_clues',
            quest_id: questId,
            nonce: '<?php echo wp_create_nonce('quest_clues_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                document.getElementById('manage-clues-content').innerHTML = response.data;
            } else {
                document.getElementById('manage-clues-content').innerHTML = 'Error loading clues: ' + (response.data || 'Unknown error');
            }
        }).fail(function(xhr, status, error) {
            document.getElementById('manage-clues-content').innerHTML = 'AJAX Error: ' + error;
        });
    }
    
    function closeManageClues() {
        document.getElementById('manage-clues-modal').style.display = 'none';
    }
    
    // Add Quest Modal
    function showAddQuestModal() {
        document.getElementById('add-quest-modal').style.display = 'block';
        document.getElementById('add-quest-content').innerHTML = 'Loading...';
        
        jQuery.post(ajaxurl, {
            action: 'get_add_quest_form',
            nonce: '<?php echo wp_create_nonce('add_quest_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                document.getElementById('add-quest-content').innerHTML = response.data;
            } else {
                document.getElementById('add-quest-content').innerHTML = 'Error loading form.';
            }
        });
    }
    
    function closeAddQuest() {
        document.getElementById('add-quest-modal').style.display = 'none';
    }
    
    function createNewQuest() {
        var form = document.getElementById('add-quest-form');
        var formData = new FormData(form);
        formData.append('action', 'create_new_quest');
        formData.append('nonce', '<?php echo wp_create_nonce('create_quest_nonce'); ?>');
        
        document.getElementById('create-quest-btn').disabled = true;
        document.getElementById('create-quest-btn').textContent = 'Creating...';
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Quest created successfully!');
                    closeAddQuest();
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    document.getElementById('create-quest-btn').disabled = false;
                    document.getElementById('create-quest-btn').textContent = 'Create Quest';
                }
            },
            error: function() {
                alert('An error occurred while creating quest.');
                document.getElementById('create-quest-btn').disabled = false;
                document.getElementById('create-quest-btn').textContent = 'Create Quest';
            }
        });
    }
    
    // Medal Image Functions
    function removeMedalImage() {
        if (confirm('Are you sure you want to remove the current medal image?')) {
            document.getElementById('current-medal-image').style.display = 'none';
            document.getElementById('edit-medal-image-url').value = '';
            document.getElementById('edit-medal-image').value = '';
        }
    }
    
    // Handle medal image file selection
    document.addEventListener('DOMContentLoaded', function() {
        // Add event listener when the edit modal content is loaded
        jQuery(document).on('change', '#edit-medal-image', function(e) {
            var file = e.target.files[0];
            if (file) {
                // Validate file size (4MB max)
                if (file.size > 4 * 1024 * 1024) {
                    alert('File size must be less than 4MB');
                    e.target.value = '';
                    return;
                }
                
                // Validate file type
                if (!file.type.match('image.*')) {
                    alert('Please select a valid image file');
                    e.target.value = '';
                    return;
                }
                
                // Show preview
                var reader = new FileReader();
                reader.onload = function(e) {
                    var currentImage = document.getElementById('current-medal-image');
                    if (currentImage) {
                        currentImage.querySelector('img').src = e.target.result;
                        currentImage.querySelector('p small').textContent = 'New medal image (preview)';
                        currentImage.style.display = 'block';
                    } else {
                        // Create preview if none exists
                        var preview = '<div id="current-medal-image" style="margin-bottom: 10px;">' +
                                     '<img src="' + e.target.result + '" alt="Medal Preview" style="max-width: 100px; max-height: 100px; border: 2px solid #ddd; border-radius: 5px;" />' +
                                     '<p><small>New medal image (preview)</small></p>' +
                                     '</div>';
                        document.getElementById('medal-image-container').insertAdjacentHTML('afterbegin', preview);
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    });
    
    // Toggle Quest Display on Site
    function toggleQuestDisplay(questId, isDisplayed) {
        var statusText = document.querySelector('#display_' + questId).parentNode.querySelector('span');
        var originalText = statusText.textContent;
        statusText.textContent = 'Updating...';
        
        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'toggle_quest_display',
            quest_id: questId,
            display_on_site: isDisplayed ? 1 : 0,
            nonce: '<?php echo wp_create_nonce('toggle_quest_display_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                statusText.textContent = isDisplayed ? 'Visible' : 'Hidden';
                statusText.style.color = isDisplayed ? '#00a32a' : '#666';
            } else {
                alert('Error: ' + response.data);
                document.getElementById('display_' + questId).checked = !isDisplayed;
                statusText.textContent = originalText;
            }
        }).fail(function() {
            alert('Network error occurred while updating display status');
            document.getElementById('display_' + questId).checked = !isDisplayed;
            statusText.textContent = originalText;
        });
    }
    
    // Delete Quest Confirmation
    function confirmDeleteQuest(questName, questCode) {
        var message = 'Are you sure you want to delete the quest "' + questName + '" (' + questCode + ')?\n\n' +
                     'This action will permanently delete:\n' +
                     '• The quest and all its details\n' +
                     '• All associated clues\n\n' +
                     'This action cannot be undone!\n\n' +
                     'Note: Booking history and completion records will be preserved.';
        
        return confirm(message);
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        var detailsModal = document.getElementById('quest-details-modal');
        var editModal = document.getElementById('edit-quest-modal');
        var cluesModal = document.getElementById('manage-clues-modal');
        var addModal = document.getElementById('add-quest-modal');
        
        if (event.target == detailsModal) {
            detailsModal.style.display = 'none';
        } else if (event.target == editModal) {
            editModal.style.display = 'none';
        } else if (event.target == cluesModal) {
            cluesModal.style.display = 'none';
        } else if (event.target == addModal) {
            addModal.style.display = 'none';
        }
    }
    </script>
    <?php
}

/**
 * Handle medal image upload
 */
function puzzlepath_handle_medal_image_upload($file) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'error' => 'Upload error: ' . $file['error']
        ];
    }
    
    // Validate file size (4MB max)
    if ($file['size'] > 4 * 1024 * 1024) {
        return [
            'success' => false,
            'error' => 'File size must be less than 4MB'
        ];
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        return [
            'success' => false,
            'error' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.'
        ];
    }
    
    // Set up upload directory
    $upload_dir = wp_upload_dir();
    $puzzlepath_dir = $upload_dir['basedir'] . '/puzzlepath-medals';
    $puzzlepath_url = $upload_dir['baseurl'] . '/puzzlepath-medals';
    
    // Create directory if it doesn't exist
    if (!file_exists($puzzlepath_dir)) {
        wp_mkdir_p($puzzlepath_dir);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'medal-' . time() . '-' . wp_generate_password(8, false) . '.' . $file_extension;
    $file_path = $puzzlepath_dir . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return [
            'success' => true,
            'url' => $puzzlepath_url . '/' . $filename,
            'path' => $file_path
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Failed to move uploaded file'
        ];
    }
}

/**
 * Quest modals container
 */
function puzzlepath_quest_modals() {
    ?>
    <!-- Quest Details Modal -->
    <div id="quest-details-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div style="background-color: #fefefe; margin: 50px auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 5px; max-height: calc(100vh - 100px); overflow-y: auto; position: relative; min-height: 200px;">
            <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px;" onclick="closeQuestDetails()">&times;</span>
            <div id="quest-details-content" style="margin-top: 10px;">
                Loading...
            </div>
        </div>
    </div>
    
    <!-- Edit Quest Modal -->
    <div id="edit-quest-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div style="background-color: #fefefe; margin: 20px auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 5px; max-height: calc(100vh - 40px); overflow-y: auto; position: relative; min-height: 200px;">
            <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px; z-index: 10;" onclick="closeEditQuest()">&times;</span>
            <div id="edit-quest-content" style="margin-top: 10px; padding-right: 10px;">
                Loading...
            </div>
        </div>
    </div>
    
    <!-- Manage Clues Modal -->
    <div id="manage-clues-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div style="background-color: #fefefe; margin: 20px auto; padding: 20px; border: 1px solid #888; width: 95%; max-width: 900px; border-radius: 5px; max-height: calc(100vh - 40px); overflow-y: auto; position: relative; min-height: 200px;">
            <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px; z-index: 10;" onclick="closeManageClues()">&times;</span>
            <div id="manage-clues-content" style="margin-top: 10px; padding-right: 10px;">
                Loading...
            </div>
        </div>
    </div>
    
    <!-- Add Quest Modal -->
    <div id="add-quest-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow-y: auto;">
        <div style="background-color: #fefefe; margin: 20px auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 700px; border-radius: 5px; max-height: calc(100vh - 40px); overflow-y: auto; position: relative; min-height: 200px;">
            <span style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 15px; top: 10px; z-index: 10;" onclick="closeAddQuest()">&times;</span>
            <div id="add-quest-content" style="margin-top: 10px; padding-right: 10px;">
                Loading...
            </div>
        </div>
    </div>
    <?php
}
