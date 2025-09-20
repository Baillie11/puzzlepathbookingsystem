<?php
defined('ABSPATH') or die('No script kiddies please!');

// This file now only handles the main settings tab content.
// Menu pages are now registered in their respective files (events.php, coupons.php, stripe-integration.php)

// Register settings
add_action('admin_init', 'puzzlepath_register_settings');
function puzzlepath_register_settings() {
    register_setting('puzzlepath_settings', 'puzzlepath_email_template');
    register_setting('puzzlepath_settings', 'puzzlepath_use_default_template');
}

// Settings page content
function puzzlepath_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Handle reset to default template
    if (isset($_POST['reset_template']) && wp_verify_nonce($_POST['_wpnonce'], 'reset-template')) {
        update_option('puzzlepath_email_template', puzzlepath_get_default_html_template());
        echo '<div class="notice notice-success is-dismissible"><p>Email template has been reset to the default HTML template.</p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <!-- Reset to Default Form -->
        <div style="background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px; margin-bottom: 20px;">
            <h2>Email Template Management</h2>
            <p>Customize the HTML email template sent to customers when their booking is confirmed.</p>
            
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('reset-template'); ?>
                <input type="submit" name="reset_template" class="button" value="Reset to Professional Default Template" 
                       onclick="return confirm('This will replace your current template with the default HTML template. Continue?');" />
            </form>
            
            <p style="margin-top: 15px;"><strong>Available placeholders:</strong></p>
            <ul style="margin-left: 20px;">
                <li><code>{name}</code> - Customer name</li>
                <li><code>{event_title}</code> - Event title</li>
                <li><code>{event_date}</code> - Event date and time</li>
                <li><code>{price}</code> - Total price paid</li>
                <li><code>{booking_code}</code> - Unique booking code</li>
                <li><code>{logo_url}</code> - PuzzlePath logo URL (automatically populated)</li>
                <li><code>{app_url}</code> - Quest app URL (automatically populated)</li>
            </ul>
        </div>
        
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
                        $template = get_option('puzzlepath_email_template', puzzlepath_get_default_html_template());
                        wp_editor(
                            $template,
                            'puzzlepath_email_template',
                            array(
                                'textarea_name' => 'puzzlepath_email_template',
                                'textarea_rows' => 15,
                                'media_buttons' => true,
                                'teeny' => false,
                                'tinymce' => true,
                                'quicktags' => true
                            )
                        );
                        ?>
                        <p class="description">This template supports full HTML. The email will be sent as HTML with automatic plain-text fallback for better deliverability.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Email Template'); ?>
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

/**
 * Get default professional HTML email template
 * Used by settings page and email functions
 */
function puzzlepath_get_default_html_template() {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PuzzlePath Booking Confirmation</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background-color: #f5f7fa; color: #333333;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 0; padding: 0; background-color: #f5f7fa;">
        <tr>
            <td style="padding: 20px 0;">
                <table role="presentation" style="width: 100%; max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header with Logo -->
                    <tr>
                        <td style="padding: 40px 40px 20px; text-align: center; background-color: #3F51B5; background: linear-gradient(135deg, #3F51B5 0%, #5C6BC0 100%); border-radius: 8px 8px 0 0;">
                            <img src="{logo_url}" alt="PuzzlePath Logo" style="width: 150px; height: auto; display: block; margin: 0 auto;" />
                            <h1 style="color: #ffffff !important; font-size: 28px; font-weight: 600; margin: 20px 0 10px; line-height: 1.2; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">Booking Confirmed!</h1>
                            <p style="color: #ffffff !important; font-size: 16px; margin: 0; opacity: 0.95; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">Your adventure awaits</p>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #3F51B5; font-size: 24px; font-weight: 600; margin: 0 0 20px; line-height: 1.3;">Hello {name}! 👋</h2>
                            
                            <p style="font-size: 16px; line-height: 1.6; color: #555555; margin: 0 0 25px;">Thank you for booking your PuzzlePath adventure! We're excited to have you join us for an unforgettable treasure hunt experience.</p>
                            
                            <!-- Booking Details Card -->
                            <div style="background-color: #f8f9ff; border: 1px solid #e0e3ff; border-radius: 8px; padding: 25px; margin: 25px 0;">
                                <h3 style="color: #3F51B5; font-size: 18px; font-weight: 600; margin: 0 0 15px;">📋 Booking Details</h3>
                                
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="padding: 8px 0; font-weight: 600; color: #666666; width: 40%;">Event:</td>
                                        <td style="padding: 8px 0; color: #333333;">{event_title}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; font-weight: 600; color: #666666;">Date & Time:</td>
                                        <td style="padding: 8px 0; color: #333333;">{event_date}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; font-weight: 600; color: #666666;">Total Paid:</td>
                                        <td style="padding: 8px 0; color: #333333; font-weight: 600;">{price}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; font-weight: 600; color: #666666;">Booking Code:</td>
                                        <td style="padding: 8px 0; color: #3F51B5; font-family: 'Courier New', monospace; font-weight: 700; font-size: 16px;">{booking_code}</td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Call to Action -->
                            <div style="text-align: center; margin: 35px 0;">
                                <p style="font-size: 18px; color: #333333; margin: 0 0 20px; font-weight: 600;">Ready to start your quest?</p>
                                <a href="{app_url}" style="display: inline-block; background-color: #3F51B5; background: linear-gradient(135deg, #3F51B5 0%, #5C6BC0 100%); color: #ffffff !important; text-decoration: none; padding: 16px 32px; border-radius: 50px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 12px rgba(63, 81, 181, 0.3); border: 2px solid #3F51B5; text-shadow: 0 1px 2px rgba(0,0,0,0.1);" target="_blank">
                                    🚀 Open Your Quest
                                </a>
                                <p style="font-size: 14px; color: #888888; margin: 15px 0 0; line-height: 1.4;">Click the button above or visit:<br/><a href="{app_url}" style="color: #3F51B5; text-decoration: none;">{app_url}</a></p>
                            </div>
                            
                            <!-- Important Notes -->
                            <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 15px; margin: 25px 0;">
                                <p style="font-size: 14px; color: #856404; margin: 0; line-height: 1.5;">💡 <strong>Important:</strong> Please save your booking code <strong>{booking_code}</strong> - you'll need it to access your quest on the day of your adventure!</p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #f8f9fa; border-radius: 0 0 8px 8px; text-align: center; border-top: 1px solid #e9ecef;">
                            <p style="font-size: 14px; color: #6c757d; margin: 0 0 10px; line-height: 1.5;">Questions about your booking? Contact us at <a href="mailto:support@puzzlepath.com.au" style="color: #3F51B5; text-decoration: none;">support@puzzlepath.com.au</a></p>
                            <p style="font-size: 12px; color: #adb5bd; margin: 0; line-height: 1.4;">© 2024 PuzzlePath. All rights reserved.<br/>This email was sent regarding your booking confirmation.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}
