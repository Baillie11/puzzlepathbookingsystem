<?php
defined('ABSPATH') or die('No script kiddies please!');

// This check ensures that the composer autoloader is present.
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>PuzzlePath Booking: The Stripe PHP library is not installed. Please run "composer install" in the plugin directory or install the plugin from the .zip file.</p></div>';
    });
    return;
}
require_once __DIR__ . '/../vendor/autoload.php';

class PuzzlePath_Stripe_Integration {

    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register settings fields
        add_action('admin_init', array($this, 'register_stripe_settings'));
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
        
        // Note: The admin_menu action to add the settings page is now in the main plugin file.
        // The callback points to 'stripe_settings_page_content'
    }

    /**
     * Register the settings fields for the Stripe settings page.
     */
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

        // New endpoint to fetch booking code by payment intent
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

        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp2s_pp_events WHERE id = %d", $event_id));

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
            // Generate a unique booking code first
            $booking_code = $this->generate_unique_booking_code();
            // Create a pending booking first
            $wpdb->insert("{$wpdb->prefix}pp_bookings", [
                'event_id' => $event_id,
                'customer_name' => sanitize_text_field($params['name']),
                'customer_email' => sanitize_email($params['email']),
                'tickets' => $tickets,
                'total_price' => $total_price,
                'coupon_id' => $coupon_id,
                'payment_status' => 'pending',
                'booking_code' => $booking_code,
            ]);
            $booking_id = $wpdb->insert_id;

            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $total_price * 100, // Amount in cents
                'currency' => 'aud',
                'metadata' => [
                    'booking_id' => $booking_id,
                    'event_id' => $event_id,
                    'tickets' => $tickets,
                ],
            ]);

            // Update booking with payment intent ID
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
        $bookings_table = 'wp2s_pp_bookings';
        $events_table = 'wp2s_pp_events';
        $coupons_table = 'wp2s_pp_coupons';

        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings_table WHERE stripe_payment_intent_id = %s", $payment_intent_id));

        if ($booking && $booking->payment_status === 'pending') {
            // No need to generate a new code, just update status
            $wpdb->update($bookings_table, 
                [
                    'payment_status' => 'paid'
                ], 
                ['id' => $booking->id]
            );

            // Decrement seat count
            $wpdb->query($wpdb->prepare("UPDATE $events_table SET seats = seats - %d WHERE id = %d", $booking->tickets, $booking->event_id));

            // Increment coupon usage
            if ($booking->coupon_id) {
                $wpdb->query($wpdb->prepare("UPDATE $coupons_table SET times_used = times_used + 1 WHERE id = %d", $booking->coupon_id));
            }

            // Send confirmation email
            $this->send_confirmation_email($booking, $booking->booking_code);

            return $booking->booking_code;
        }
        return null;
    }

    private function send_confirmation_email($booking, $booking_code) {
        $to = $booking->customer_email;
        $subject = 'Your PuzzlePath Booking Confirmation';
        $event_title = '';
        $event_date = '';
        global $wpdb;
        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp2s_pp_events WHERE id = %d", $booking->event_id));
        if ($event) {
            $event_title = $event->title;
            $event_date = $event->event_date;
        }
        $message = "Dear {$booking->customer_name},\n\nThank you for your booking!\n\nBooking Details:\nEvent: {$event_title}\nDate: {$event_date}\nPrice: ".$booking->total_price."\nBooking Code: {$booking_code}\n\nRegards,\nPuzzlePath Team";
        wp_mail($to, $subject, $message);
    }

    /**
     * Generates a unique booking code.
     */
    private function generate_unique_booking_code() {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'pp_bookings';
        
        do {
            $code = 'PP-' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
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
        if ($booking->payment_status === 'paid' && $booking->booking_code) {
            return new WP_REST_Response(['status' => 'paid', 'booking_code' => $booking->booking_code], 200);
        }
        return new WP_REST_Response(['status' => $booking->payment_status], 200);
    }

    /**
     * Display the Stripe settings page content.
     * This function is called by the add_submenu_page in the main plugin file.
     */
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
                            <p class="description">Enable test mode for development. Use test keys and test card numbers.</p>
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
                            <p class="description">Get this from your Stripe webhook settings. Ensures payment notifications are genuinely from Stripe.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>

            <h2>Webhook Setup</h2>
            <p>For Stripe to notify your site about payment status, you must set up a webhook in your Stripe Dashboard.</p>
            <p>1. Go to your <a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripe Webhooks settings</a>.</p>
            <p>2. Click "Add an endpoint".</p>
            <p>3. Enter the following URL for the endpoint:</p>
            <p><code><?php echo home_url('/wp-json/puzzlepath/v1/stripe-webhook'); ?></code></p>
            <p>4. Click "Select events" and choose the following event:</p>
            <p><code>charge.succeeded</code></p>
            <p>5. Click "Add endpoint".</p>
            <p>6. After creating the endpoint, find the "Signing secret" and paste it into the "Webhook Signing Secret" field above.</p>
        </div>
        <?php
    }

    // ... other methods for payment intent, webhook handling etc.
}

// Initialize the class
PuzzlePath_Stripe_Integration::get_instance();

