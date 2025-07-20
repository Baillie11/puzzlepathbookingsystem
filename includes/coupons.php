<<<<<<< HEAD
﻿<?php
=======
<?php
>>>>>>> 7de96ad7ad0c01e25fd6b5b7ca87ba80255f8500
defined('ABSPATH') or die('No script kiddies please!');

// The admin menu for this page is now registered in the main plugin file.

/**
 * Display the main page for managing coupons.
 */
function puzzlepath_coupons_page() {
<<<<<<< HEAD
    // Start output buffering to prevent headers already sent errors
    if (!ob_get_level()) {
        ob_start();
    }
    
=======
>>>>>>> 7de96ad7ad0c01e25fd6b5b7ca87ba80255f8500
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

<<<<<<< HEAD
        echo '<script type="text/javascript">window.location.href = "' . admin_url('admin.php?page=puzzlepath-coupons&message=1') . '";</script>';
=======
        wp_redirect(admin_url('admin.php?page=puzzlepath-coupons&message=1'));
>>>>>>> 7de96ad7ad0c01e25fd6b5b7ca87ba80255f8500
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
<<<<<<< HEAD
                    echo '<td>' . esc_html($coupon->times_used) . ' / ' . ($coupon->max_uses > 0 ? esc_html($coupon->max_uses) : 'âˆž') . '</td>';
=======
                    echo '<td>' . esc_html($coupon->times_used) . ' / ' . ($coupon->max_uses > 0 ? esc_html($coupon->max_uses) : '∞') . '</td>';
>>>>>>> 7de96ad7ad0c01e25fd6b5b7ca87ba80255f8500
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
<<<<<<< HEAD
} 
=======
} 
>>>>>>> 7de96ad7ad0c01e25fd6b5b7ca87ba80255f8500
