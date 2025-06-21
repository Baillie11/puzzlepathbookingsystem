<?php
defined('ABSPATH') or die('No script kiddies please!');

// Add submenu page for coupons
add_action('admin_menu', 'puzzlepath_add_coupons_menu');
function puzzlepath_add_coupons_menu() {
    add_submenu_page(
        'puzzlepath-booking',
        'Coupons',
        'Coupons',
        'manage_options',
        'puzzlepath-coupons',
        'puzzlepath_coupons_page'
    );
}

// Coupons page content
function puzzlepath_coupons_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'pp_coupons';

    // Handle form submission
    if (isset($_POST['submit_coupon'])) {
        $code = sanitize_text_field($_POST['code']);
        $discount_percent = intval($_POST['discount_percent']);
        $max_uses = intval($_POST['max_uses']);
        $expires_at = !empty($_POST['expires_at']) ? sanitize_text_field($_POST['expires_at']) : null;

        $wpdb->insert(
            $table_name,
            [
                'code' => $code,
                'discount_percent' => $discount_percent,
                'max_uses' => $max_uses,
                'expires_at' => $expires_at
            ],
            ['%s', '%d', '%d', $expires_at ? '%s' : null]
        );

        echo '<div class="updated"><p>Coupon added successfully!</p></div>';
    }

    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $wpdb->delete($table_name, ['id' => $id], ['%d']);
        echo '<div class="updated"><p>Coupon deleted successfully!</p></div>';
    }

    // Get all coupons
    $coupons = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    ?>
    <div class="wrap">
        <h1>Coupons</h1>
        
        <h2>Add New Coupon</h2>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="code">Coupon Code</label></th>
                    <td><input type="text" name="code" id="code" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="discount_percent">Discount Percentage</label></th>
                    <td><input type="number" name="discount_percent" id="discount_percent" min="1" max="100" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="max_uses">Maximum Uses</label></th>
                    <td>
                        <input type="number" name="max_uses" id="max_uses" min="0" required>
                        <p class="description">Set to 0 for unlimited uses</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="expires_at">Expiry Date</label></th>
                    <td>
                        <input type="datetime-local" name="expires_at" id="expires_at">
                        <p class="description">Leave empty for no expiry</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Add Coupon', 'primary', 'submit_coupon'); ?>
        </form>

        <h2>Existing Coupons</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Discount</th>
                    <th>Uses</th>
                    <th>Max Uses</th>
                    <th>Expires</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($coupons as $coupon): ?>
                    <tr>
                        <td><?php echo esc_html($coupon->code); ?></td>
                        <td><?php echo esc_html($coupon->discount_percent); ?>%</td>
                        <td><?php echo esc_html($coupon->times_used); ?></td>
                        <td><?php echo $coupon->max_uses ? esc_html($coupon->max_uses) : 'Unlimited'; ?></td>
                        <td>
                            <?php
                            if ($coupon->expires_at) {
                                echo date('F j, Y g:i a', strtotime($coupon->expires_at));
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </td>
                        <td><?php echo date('F j, Y', strtotime($coupon->created_at)); ?></td>
                        <td>
                            <a href="<?php echo add_query_arg(['action' => 'delete', 'id' => $coupon->id]); ?>" 
                               onclick="return confirm('Are you sure you want to delete this coupon?');"
                               class="button button-small">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
} 