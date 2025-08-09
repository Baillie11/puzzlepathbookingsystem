<?php
/**
 * Plugin Name: PuzzlePath Booking
 * Description: A custom booking plugin for PuzzlePath with unified app integration.
 * Version: 2.4.0
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
        created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        title varchar(255) NOT NULL,
        hunt_code varchar(10) DEFAULT NULL,
        hunt_name varchar(255) DEFAULT NULL,
        hosting_type varchar(20) DEFAULT 'hosted' NOT NULL,
        event_date datetime,
        location varchar(255) NOT NULL,
        price float NOT NULL,
        seats int(11) NOT NULL,
        PRIMARY KEY  (id)
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
    
    update_option('puzzlepath_booking_version', '2.4.0');
}
register_activation_hook(__FILE__, 'puzzlepath_activate');

/**
 * Check if database needs updating on plugin load.
 */
function puzzlepath_update_db_check() {
    $current_version = get_option('puzzlepath_booking_version', '1.0');
    if (version_compare($current_version, '2.4.0', '<')) {
        puzzlepath_activate();
    }
}
add_action('plugins_loaded', 'puzzlepath_update_db_check');

/**
 * Centralized function to create all admin menus.
 */
function puzzlepath_register_admin_menus() {
    add_menu_page('PuzzlePath Bookings', 'PuzzlePath', 'manage_options', 'puzzlepath-booking', 'puzzlepath_events_page', 'dashicons-tickets-alt', 20);
    add_submenu_page('puzzlepath-booking', 'Events', 'Events', 'manage_options', 'puzzlepath-events', 'puzzlepath_events_page');
    add_submenu_page('puzzlepath-booking', 'Coupons', 'Coupons', 'manage_options', 'puzzlepath-coupons', 'puzzlepath_coupons_page');
    add_submenu_page('puzzlepath-booking', 'Settings', 'Settings', 'manage_options', 'puzzlepath-settings', 'puzzlepath_settings_page');
    if (class_exists('PuzzlePath_Stripe_Integration')) {
        $stripe_instance = PuzzlePath_Stripe_Integration::get_instance();
        add_submenu_page('puzzlepath-booking', 'Stripe Settings', 'Stripe Settings', 'manage_options', 'puzzlepath-stripe-settings', array($stripe_instance, 'stripe_settings_page_content'));
    }
    remove_submenu_page('puzzlepath-booking', 'puzzlepath-booking');
}
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
    if ($post && has_shortcode($post->post_content, 'puzzlepath_booking_form')) {
        wp_enqueue_style(
            'puzzlepath-booking-form-style',
            plugin_dir_url(__FILE__) . 'css/booking-form.css',
            array(),
            '2.4.0'
        );
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
        
        wp_enqueue_script(
            'puzzlepath-booking-form',
            plugin_dir_url(__FILE__) . 'js/booking-form.js',
            array('jquery'),
            '2.4.0',
            true
        );
        
        wp_enqueue_script(
            'puzzlepath-stripe-payment',
            plugin_dir_url(__FILE__) . 'js/stripe-payment.js',
            array('jquery', 'stripe-js'),
            '2.4.0',
            true
        );

        $test_mode = get_option('puzzlepath_stripe_test_mode', true);
        $publishable_key = $test_mode ? get_option('puzzlepath_stripe_publishable_key') : get_option('puzzlepath_stripe_live_publishable_key');

        wp_localize_script(
            'puzzlepath-stripe-payment',
            'puzzlepath_data',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'coupon_nonce' => wp_create_nonce('puzzlepath_coupon_nonce'),
                'publishable_key' => $publishable_key,
                'rest_url' => rest_url('puzzlepath/v1/'),
                'rest_nonce' => wp_create_nonce('wp_rest')
            )
        );
    }
}
add_action('wp_enqueue_scripts', 'puzzlepath_enqueue_scripts');

/**
 * AJAX handler for applying a coupon.
 */
function puzzlepath_apply_coupon_callback() {
    check_ajax_referer('puzzlepath_coupon_nonce', 'nonce');
    global $wpdb;
    $coupons_table = $wpdb->prefix . 'pp_coupons';
    $code = sanitize_text_field($_POST['coupon_code']);
    
    $coupon = $wpdb->get_row($wpdb->prepare("SELECT * FROM $coupons_table WHERE code = %s", $code));

    if (!$coupon) {
        wp_send_json_error(['message' => 'Invalid coupon code.']);
        return;
    }
    if ($coupon->expires_at && strtotime($coupon->expires_at) < time()) {
        wp_send_json_error(['message' => 'This coupon has expired.']);
        return;
    }
    if ($coupon->max_uses > 0 && $coupon->times_used >= $coupon->max_uses) {
        wp_send_json_error(['message' => 'This coupon has reached its usage limit.']);
        return;
    }
    wp_send_json_success([
        'discount_percent' => $coupon->discount_percent,
        'code' => $coupon->code
    ]);
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
        <form id="booking-form">
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
            'hunt_code' => $hunt_code,
            'hunt_name' => $hunt_name,
            'location' => $location,
            'price' => $price,
            'seats' => $seats,
            'hosting_type' => $hosting_type,
            'event_date' => $event_date,
        ];

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

// ========================= SETTINGS =========================

// Register settings
add_action('admin_init', 'puzzlepath_register_settings');
function puzzlepath_register_settings() {
    register_setting('puzzlepath_settings', 'puzzlepath_email_template');
    register_setting('puzzlepath_settings', 'puzzlepath_unified_app_url');
}

// Settings page content
function puzzlepath_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form action="options.php" method="post">
            <?php
            settings_fields('puzzlepath_settings');
            do_settings_sections('puzzlepath_settings');
            ?>
            <h2>Unified App Integration</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Unified App URL</th>
                    <td>
                        <input type="url" name="puzzlepath_unified_app_url" 
                               value="<?php echo esc_attr(get_option('puzzlepath_unified_app_url', 'https://app.puzzlepath.com.au')); ?>" 
                               class="regular-text">
                        <p class="description">Base URL of the unified PuzzlePath app for booking verification</p>
                    </td>
                </tr>
            </table>
            
            <h2>Email Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Email Template</th>
                    <td>
                        <?php
                        wp_editor(
                            get_option('puzzlepath_email_template', 'Dear {name},\n\nThank you for your booking!\n\nBooking Details:\nEvent: {event_title}\nDate: {event_date}\nPrice: {price}\nBooking Code: {booking_code}\n\nRegards,\nPuzzlePath Team'),
                            'puzzlepath_email_template',
                            array(
                                'textarea_name' => 'puzzlepath_email_template',
                                'textarea_rows' => 10,
                                'media_buttons' => false
                            )
                        );
                        ?>
                        <p class="description">Available placeholders: {name}, {event_title}, {event_date}, {price}, {booking_code}</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        
        <h2>Shortcode</h2>
        <p>Use this shortcode to display the booking form on any page or post:</p>
        <code>[puzzlepath_booking_form]</code>
        
        <h2>Quick Links</h2>
        <p>
            <a href="<?php echo admin_url('admin.php?page=puzzlepath-events'); ?>" class="button">Manage Events</a>
            <a href="<?php echo admin_url('admin.php?page=puzzlepath-coupons'); ?>" class="button">Manage Coupons</a>
        </p>
    </div>
    <?php
}

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

            $stripe_keys = $this->get_stripe_keys();
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
            
            $template = get_option('puzzlepath_email_template', 'Dear {name},\n\nThank you for your booking!\n\nBooking Details:\nEvent: {event_title}\nDate: {event_date}\nPrice: {price}\nBooking Code: {booking_code}\n\nRegards,\nPuzzlePath Team');
            
            $message = str_replace(
                ['{name}', '{event_title}', '{event_date}', '{price}', '{booking_code}'],
                [$booking->customer_name, $event ? $event->title : '', $event && $event->event_date ? $event->event_date : '', '$' . number_format($booking->total_price, 2), $booking_code],
                $template
            );
            
            wp_mail($to, $subject, $message);
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

        public function stripe_settings_page_content() {
            ?>
            <div class="wrap">
                <h1>Stripe Payment Settings</h1>
                <form method="post" action="options.php">
                    <?php settings_fields('puzzlepath_stripe_settings'); ?>
                    <?php do_settings_sections('puzzlepath-stripe-settings'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Test Mode</th>
                            <td>
                                <input type="checkbox" name="puzzlepath_stripe_test_mode" value="1" 
                                       <?php checked(get_option('puzzlepath_stripe_test_mode', true)); ?>>
                                <p class="description">Enable test mode for development.</p>
                            </td>
                        </tr>
                     
                        <tr valign="top">
                            <th scope="row">Test Publishable Key</th>
                            <td><input type="text" name="puzzlepath_stripe_publishable_key" value="<?php echo esc_attr( get_option('puzzlepath_stripe_publishable_key') ); ?>" class="regular-text"/></td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row">Test Secret Key</th>
                            <td><input type="password" name="puzzlepath_stripe_secret_key" value="<?php echo esc_attr( get_option('puzzlepath_stripe_secret_key') ); ?>" class="regular-text"/></td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">Live Publishable Key</th>
                            <td><input type="text" name="puzzlepath_stripe_live_publishable_key" value="<?php echo esc_attr( get_option('puzzlepath_stripe_live_publishable_key') ); ?>" class="regular-text"/></td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row">Live Secret Key</th>
                            <td><input type="password" name="puzzlepath_stripe_live_secret_key" value="<?php echo esc_attr( get_option('puzzlepath_stripe_live_secret_key') ); ?>" class="regular-text"/></td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">Webhook Signing Secret</th>
                            <td>
                                <input type="password" name="puzzlepath_stripe_webhook_secret" value="<?php echo esc_attr( get_option('puzzlepath_stripe_webhook_secret') ); ?>" class="regular-text"/>
                                <p class="description">Get this from your Stripe webhook settings.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(); ?>
                </form>

                <h2>Webhook Setup</h2>
                <p>For Stripe to notify your site about payment status, set up a webhook:</p>
                <p>1. Go to your <a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripe Webhooks settings</a>.</p>
                <p>2. Add this endpoint URL:</p>
                <p><code><?php echo home_url('/wp-json/puzzlepath/v1/stripe-webhook'); ?></code></p>
                <p>3. Select event: <code>charge.succeeded</code></p>
                <p>4. Copy the webhook signing secret to the field above.</p>
            </div>
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
