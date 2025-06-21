<?php
defined('ABSPATH') or die('No script kiddies please!');

// Add menu page for settings
add_action('admin_menu', 'puzzlepath_add_admin_menu');
function puzzlepath_add_admin_menu() {
    add_menu_page(
        'PuzzlePath Booking',
        'PuzzlePath',
        'manage_options',
        'puzzlepath-booking',
        'puzzlepath_settings_page',
        'dashicons-calendar-alt'
    );
}

// Register settings
add_action('admin_init', 'puzzlepath_register_settings');
function puzzlepath_register_settings() {
    register_setting('puzzlepath_settings', 'puzzlepath_email_template');
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
            <table class="form-table">
                <tr>
                    <th scope="row">Email Template</th>
                    <td>
                        <?php
                        wp_editor(
                            get_option('puzzlepath_email_template', 'Dear {name},\n\nThank you for your booking!\n\nBooking Details:\nEvent: {event_title}\nDate: {event_date}\nPrice: {price}\n\nRegards,\nPuzzlePath Team'),
                            'puzzlepath_email_template',
                            array(
                                'textarea_name' => 'puzzlepath_email_template',
                                'textarea_rows' => 10,
                                'media_buttons' => false
                            )
                        );
                        ?>
                        <p class="description">Available placeholders: {name}, {event_title}, {event_date}, {price}</p>
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