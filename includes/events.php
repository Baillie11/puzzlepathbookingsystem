<?php
defined('ABSPATH') or die('No script kiddies please!');

// The admin menu for this page is now registered in the main plugin file.

/**
 * Display the main page for managing events.
 */
function puzzlepath_events_page() {
    // Start output buffering to prevent headers already sent errors
    if (!ob_get_level()) {
        ob_start();
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'pp_events';

    // Handle form submissions for adding/editing events
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['puzzlepath_event_nonce'])) {
        if (!wp_verify_nonce($_POST['puzzlepath_event_nonce'], 'puzzlepath_save_event')) {
            wp_die('Security check failed.');
        }

        $id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $title = sanitize_text_field($_POST['title']);
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
        ];

        if ($id > 0) {
            $wpdb->update($table_name, $data, ['id' => $id]);
        } else {
            // Generate a unique event_uid
            do {
                $event_uid = 'EVT-' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE event_uid = %s", $event_uid));
            } while ($exists > 0);
            $data['event_uid'] = $event_uid;
            $wpdb->insert($table_name, $data);
        }
        
        // Clear any output buffer and redirect to avoid form resubmission
        if (ob_get_level()) {
            ob_end_clean();
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
        
        // Clear any output buffer and redirect
        if (ob_get_level()) {
            ob_end_clean();
        }
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
                    <th>Event ID</th>
                    <th>Title</th>
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
                    echo '<td>' . esc_html($event->event_uid ?: $event->id) . '</td>';
                    echo '<td>' . esc_html($event->title) . '</td>';
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
                $('#event_date').attr('required', true);
            } else {
                $('#event_date_row').hide();
                $('#event_date').removeAttr('required').val('');
            }
        }

        $('#hosting_type').change(toggleEventDate);
        toggleEventDate(); // Initialize on page load
    });
    </script>
    <?php
}

<<<<<<< HEAD
﻿<?php
=======
<?php
>>>>>>> 7de96ad7ad0c01e25fd6b5b7ca87ba80255f8500
defined('ABSPATH') or die('No script kiddies please!');

// The admin menu for this page is now registered in the main plugin file.

/**
 * Display the main page for managing events.
 */
function puzzlepath_events_page() {
<<<<<<< HEAD
    // Start output buffering to prevent headers already sent errors
    if (!headers_sent()) {
        ob_start();
    }
    
=======
<<<<<<< HEAD
    // Start output buffering to prevent headers already sent errors
    if (!ob_get_level()) {
        ob_start();
    }
    
=======
>>>>>>> 7de96ad7ad0c01e25fd6b5b7ca87ba80255f8500
>>>>>>> 134009d7a5c615cc2b666ba7f0b8a81bd0c72397
    global $wpdb;
    $table_name = $wpdb->prefix . 'pp_events';

    // Handle form submissions for adding/editing events
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['puzzlepath_event_nonce'])) {
        if (!wp_verify_nonce($_POST['puzzlepath_event_nonce'], 'puzzlepath_save_event')) {
            wp_die('Security check failed.');
        }

        $id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $title = sanitize_text_field($_POST['title']);
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
        ];

        if ($id > 0) {
            $wpdb->update($table_name, $data, ['id' => $id]);
        } else {
<<<<<<< HEAD
            // Generate a unique event_uid
            do {
                $event_uid = 'EVT-' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE event_uid = %s", $event_uid));
            } while ($exists > 0);
            $data['event_uid'] = $event_uid;
            $wpdb->insert($table_name, $data);
        }
        
        // Use JavaScript redirect since wp_redirect is failing due to headers already sent
        echo '<script type="text/javascript">window.location.href = "' . admin_url('admin.php?page=puzzlepath-events&message=1') . '";</script>';
=======
            $wpdb->insert($table_name, $data);
        }
        
        // Clear any output buffer and redirect to avoid form resubmission
        if (ob_get_level()) {
            ob_end_clean();
        }
        wp_redirect(admin_url('admin.php?page=puzzlepath-events&message=1'));
>>>>>>> 7de96ad7ad0c01e25fd6b5b7ca87ba80255f8500
        exit;
    }

    // Handle event deletion
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['event_id'])) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'puzzlepath_delete_event_' . $_GET['event_id'])) {
            wp_die('Security check failed.');
        }
        $id = intval($_GET['event_id']);
        $wpdb->delete($table_name, ['id' => $id]);
        
        // Clear any output buffer and redirect
        if (ob_get_level()) {
            ob_end_clean();
        }
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
<<<<<<< HEAD
                    <th>Event ID</th>
=======
>>>>>>> 7de96ad7ad0c01e25fd6b5b7ca87ba80255f8500
                    <th>Title</th>
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
<<<<<<< HEAD
                    echo '<td>' . esc_html($event->event_uid) . '</td>';
=======
>>>>>>> 7de96ad7ad0c01e25fd6b5b7ca87ba80255f8500
                    echo '<td>' . esc_html($event->title) . '</td>';
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
<<<<<<< HEAD
}

// Bookings page function - menu is registered in main plugin file

function puzzlepath_bookings_page() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'pp_bookings';
    $events_table = $wpdb->prefix . 'pp_events';

    // Handle edit (simple inline for now)
    if (isset($_POST['edit_booking_id']) && current_user_can('manage_options')) {
        $booking_id = intval($_POST['edit_booking_id']);
        $customer_name = sanitize_text_field($_POST['customer_name']);
        $customer_email = sanitize_email($_POST['customer_email']);
        $payment_status = sanitize_text_field($_POST['payment_status']);
        $wpdb->update($bookings_table, [
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'payment_status' => $payment_status,
        ], ['id' => $booking_id]);
        echo '<div class="updated"><p>Booking updated.</p></div>';
    }

    // Get all bookings (not just hosted)
    $bookings = $wpdb->get_results(
        "SELECT b.*, e.title as event_title, e.event_date, e.hosting_type FROM $bookings_table b
         JOIN $events_table e ON b.event_id = e.id
         ORDER BY b.created_at DESC"
    );
    $status_options = [
        'pending' => 'Pending',
        'succeeded' => 'Succeeded',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
        'cancelled' => 'Cancelled',
    ];
    ?>
    <div class="wrap">
        <h1>Bookings</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Booking Number</th>
                    <th>Amount Paid</th>
                    <th>Date of Scavenger Hunt</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                <tr>
                    <form method="post">
                        <td><input type="text" name="customer_name" value="<?php echo esc_attr($booking->customer_name); ?>" /></td>
                        <td><input type="email" name="customer_email" value="<?php echo esc_attr($booking->customer_email); ?>" /></td>
                        <td><?php echo esc_html($booking->booking_code); ?></td>
                        <td>$<?php echo number_format($booking->total_price, 2); ?></td>
                        <td><?php echo ($booking->hosting_type === 'hosted' && $booking->event_date) ? date('F j, Y, g:i a', strtotime($booking->event_date)) : 'N/A'; ?></td>
                        <td>
                            <select name="payment_status">
                                <?php foreach ($status_options as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($booking->payment_status, $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="hidden" name="edit_booking_id" value="<?php echo $booking->id; ?>" />
                            <button type="submit" class="button button-primary">Save</button>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
} 
=======
} 
>>>>>>> 7de96ad7ad0c01e25fd6b5b7ca87ba80255f8500
