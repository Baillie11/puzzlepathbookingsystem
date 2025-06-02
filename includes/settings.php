<?php
// Add settings page to the menu
add_action('admin_menu', 'puzzlepath_add_settings_page');
function puzzlepath_add_settings_page() {
    add_submenu_page(
        'puzzlepath-events',
        'Settings',
        'Settings',
        'manage_options',
        'puzzlepath-settings',
        'puzzlepath_render_settings_page'
    );
}

// Register settings
add_action('admin_init', 'puzzlepath_register_settings');
function puzzlepath_register_settings() {
    register_setting('puzzlepath_settings', 'puzzlepath_stripe_publishable_key');
    register_setting('puzzlepath_settings', 'puzzlepath_stripe_secret_key');
    register_setting('puzzlepath_settings', 'puzzlepath_confirmation_page_id');
}

// Render settings page
function puzzlepath_render_settings_page() {
    // Create payment and confirmation pages if they don't exist
    $payment_page_id = get_option('puzzlepath_payment_page_id');
    if (!$payment_page_id) {
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

    $confirmation_page_id = get_option('puzzlepath_confirmation_page_id');
    if (!$confirmation_page_id) {
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
    ?>
    <div class="wrap">
        <h1>PuzzlePath Booking Settings</h1>
        
        <form method="post" action="options.php">
            <?php settings_fields('puzzlepath_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="puzzlepath_stripe_publishable_key">Stripe Publishable Key</label>
                    </th>
                    <td>
                        <input type="text" id="puzzlepath_stripe_publishable_key" 
                               name="puzzlepath_stripe_publishable_key" 
                               value="<?php echo esc_attr(get_option('puzzlepath_stripe_publishable_key')); ?>" 
                               class="regular-text">
                        <p class="description">Your Stripe publishable key (starts with 'pk_')</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="puzzlepath_stripe_secret_key">Stripe Secret Key</label>
                    </th>
                    <td>
                        <input type="password" id="puzzlepath_stripe_secret_key" 
                               name="puzzlepath_stripe_secret_key" 
                               value="<?php echo esc_attr(get_option('puzzlepath_stripe_secret_key')); ?>" 
                               class="regular-text">
                        <p class="description">Your Stripe secret key (starts with 'sk_')</p>
                    </td>
                </tr>
            </table>

            <h2>Page Settings</h2>
            <p>The following pages have been created automatically:</p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Payment Page</th>
                    <td>
                        <a href="<?php echo get_permalink($payment_page_id); ?>" target="_blank">
                            <?php echo get_the_title($payment_page_id); ?>
                        </a>
                        <p class="description">This page handles the payment process.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Confirmation Page</th>
                    <td>
                        <a href="<?php echo get_permalink($confirmation_page_id); ?>" target="_blank">
                            <?php echo get_the_title($confirmation_page_id); ?>
                        </a>
                        <p class="description">This page shows the booking confirmation after successful payment.</p>
                    </td>
                </tr>
            </table>

            <h2>Test Your Configuration</h2>
            <p>Use these test card numbers to verify your Stripe integration:</p>
            <table class="widefat" style="max-width: 600px; margin-top: 10px;">
                <thead>
                    <tr>
                        <th>Card Number</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>4242 4242 4242 4242</code></td>
                        <td>Successful payment</td>
                    </tr>
                    <tr>
                        <td><code>4000 0000 0000 0002</code></td>
                        <td>Declined payment</td>
                    </tr>
                </tbody>
            </table>
            <p class="description">
                For test cards, use any future expiration date and any three-digit CVC.
            </p>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
} 