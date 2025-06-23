<?php
defined('ABSPATH') or die('No script kiddies please!');

// The admin menu for this page is now registered in the main plugin file.

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
            $wpdb->insert($table_name, $data);
        }
        
        // Redirect to avoid form resubmission
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