<?php
defined('ABSPATH') or die('No script kiddies please!');

// Add submenu page for events
add_action('admin_menu', 'puzzlepath_add_events_menu');
function puzzlepath_add_events_menu() {
    add_submenu_page(
        'puzzlepath-booking',
        'Events',
        'Events',
        'manage_options',
        'puzzlepath-events',
        'puzzlepath_events_page'
    );
}

// Events page content
function puzzlepath_events_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'pp_events';

    // Handle form submission
    if (isset($_POST['submit_event'])) {
        $title = sanitize_text_field($_POST['title']);
        $hosting_type = sanitize_text_field($_POST['hosting_type']);
        $event_date = ($hosting_type === 'hosted' && !empty($_POST['event_date'])) ? sanitize_text_field($_POST['event_date']) : null;
        $location = sanitize_text_field($_POST['location']);
        $price = floatval($_POST['price']);
        $seats = intval($_POST['seats']);

        $wpdb->insert(
            $table_name,
            [
                'title' => $title,
                'hosting_type' => $hosting_type,
                'event_date' => $event_date,
                'location' => $location,
                'price' => $price,
                'seats' => $seats
            ],
            ['%s', '%s', $event_date ? '%s' : null, '%s', '%f', '%d']
        );

        echo '<div class="updated"><p>Event added successfully!</p></div>';
    }

    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $wpdb->delete($table_name, ['id' => $id], ['%d']);
        echo '<div class="updated"><p>Event deleted successfully!</p></div>';
    }

    // Handle update submission
    if (isset($_POST['update_event'])) {
        $id = intval($_POST['event_id']);
        $title = sanitize_text_field($_POST['title']);
        $hosting_type = sanitize_text_field($_POST['hosting_type']);
        $event_date = ($hosting_type === 'hosted' && !empty($_POST['event_date'])) ? sanitize_text_field($_POST['event_date']) : null;
        $location = sanitize_text_field($_POST['location']);
        $price = floatval($_POST['price']);
        $seats = intval($_POST['seats']);

        $wpdb->update(
            $table_name,
            [
                'title' => $title,
                'hosting_type' => $hosting_type,
                'event_date' => $event_date,
                'location' => $location,
                'price' => $price,
                'seats' => $seats
            ],
            ['id' => $id],
            ['%s', '%s', $event_date ? '%s' : null, '%s', '%f', '%d'],
            ['%d']
        );

        echo '<div class="updated"><p>Event updated successfully!</p></div>';
    }

    // Get event to edit, if any
    $event_to_edit = null;
    if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $event_to_edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }

    // Get all events for the list
    $events = $wpdb->get_results("SELECT * FROM $table_name ORDER BY event_date ASC, title ASC");
    
    // Determine form values
    $form_action = $event_to_edit ? 'update_event' : 'submit_event';
    $form_title = $event_to_edit ? 'Edit Event' : 'Add New Event';
    $button_text = $event_to_edit ? 'Update Event' : 'Add Event';
    ?>
    <div class="wrap">
        <h1>Events</h1>
        
        <h2><?php echo $form_title; ?></h2>
        <form method="post" action="">
            <input type="hidden" name="event_id" value="<?php echo $event_to_edit ? esc_attr($event_to_edit->id) : ''; ?>">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="title">Title</label></th>
                    <td><input type="text" name="title" id="title" class="regular-text" value="<?php echo $event_to_edit ? esc_attr($event_to_edit->title) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hosting_type">Hosting Type</label></th>
                    <td>
                        <select name="hosting_type" id="hosting_type" required>
                            <option value="hosted" <?php selected($event_to_edit ? $event_to_edit->hosting_type : '', 'hosted'); ?>>Hosted</option>
                            <option value="self_hosted" <?php selected($event_to_edit ? $event_to_edit->hosting_type : '', 'self_hosted'); ?>>Self Hosted (App)</option>
                        </select>
                        <p class="description">Hosted events have a specific date and time. Self Hosted events are played via the app at any time.</p>
                    </td>
                </tr>
                <tr id="date_row" style="display: none;">
                    <th scope="row"><label for="event_date">Date & Time</label></th>
                    <td><input type="datetime-local" name="event_date" id="event_date" value="<?php echo $event_to_edit && $event_to_edit->event_date ? esc_attr(date('Y-m-d\TH:i', strtotime($event_to_edit->event_date))) : ''; ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="location">Location</label></th>
                    <td><input type="text" name="location" id="location" class="regular-text" value="<?php echo $event_to_edit ? esc_attr($event_to_edit->location) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="price">Price</label></th>
                    <td><input type="number" name="price" id="price" step="0.01" min="0" value="<?php echo $event_to_edit ? esc_attr($event_to_edit->price) : ''; ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="seats">Available Seats</label></th>
                    <td><input type="number" name="seats" id="seats" min="1" value="<?php echo $event_to_edit ? esc_attr($event_to_edit->seats) : ''; ?>" required></td>
                </tr>
            </table>
            <?php submit_button($button_text, 'primary', $form_action); ?>
        </form>

        <h2>Existing Events</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Hosting Type</th>
                    <th>Date & Time</th>
                    <th>Location</th>
                    <th>Price</th>
                    <th>Available Seats</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?php echo esc_html($event->title); ?></td>
                        <td><?php echo $event->hosting_type === 'hosted' ? 'Hosted' : 'Self Hosted (App)'; ?></td>
                        <td>
                            <?php 
                            if ($event->hosting_type === 'hosted' && $event->event_date) {
                                echo date('F j, Y g:i a', strtotime($event->event_date));
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($event->location); ?></td>
                        <td>$<?php echo number_format($event->price, 2); ?></td>
                        <td><?php echo esc_html($event->seats); ?></td>
                        <td>
                            <a href="<?php echo add_query_arg(['action' => 'edit', 'id' => $event->id]); ?>" class="button button-small">Edit</a>
                            <a href="<?php echo add_query_arg(['action' => 'delete', 'id' => $event->id]); ?>" 
                               onclick="return confirm('Are you sure you want to delete this event?');"
                               class="button button-small">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const hostingType = document.getElementById('hosting_type');
        const dateRow = document.getElementById('date_row');
        const eventDate = document.getElementById('event_date');
        
        function toggleDateField() {
            if (hostingType.value === 'hosted') {
                dateRow.style.display = 'table-row';
                eventDate.required = true;
            } else {
                dateRow.style.display = 'none';
                eventDate.required = false;
                eventDate.value = '';
            }
        }
        
        hostingType.addEventListener('change', toggleDateField);
        toggleDateField(); // Run on page load
    });
    </script>
    <?php
} 