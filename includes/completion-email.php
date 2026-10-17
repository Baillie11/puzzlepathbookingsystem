<?php
defined('ABSPATH') || exit;
function pp_completion_email_install() {
    global $wpdb;
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pp_dashboard_access (token_hash char(64) NOT NULL PRIMARY KEY, booking_id mediumint(9) NOT NULL, expires_at datetime NOT NULL, created_at datetime NOT NULL, KEY booking_id (booking_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pp_completion_emails (booking_id mediumint(9) NOT NULL PRIMARY KEY, status varchar(20) NOT NULL DEFAULT 'pending', attempts int NOT NULL DEFAULT 0, updated_at datetime NOT NULL, sent_at datetime NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!wp_next_scheduled('pp_completion_email_tick')) wp_schedule_event(time()+60,'pp_five_minutes','pp_completion_email_tick');
}
add_filter('cron_schedules',function($s){$s['pp_five_minutes']=['interval'=>300,'display'=>'Every five minutes'];return $s;});
add_action('admin_init','pp_completion_email_install');
function pp_completion_email_body($row,$url,$duration) {
    $badgeUrl=trim($row->medal_image_url??'');
    if($badgeUrl!=='' && function_exists('pp_badge_asset_url'))$badgeUrl=pp_badge_asset_url($badgeUrl,'email');
    if(strpos($badgeUrl,'/')===0 && strpos($badgeUrl,'//')!==0)$badgeUrl='https://puzzlepath.com.au'.$badgeUrl;
    $badgeUrl=esc_url($badgeUrl,['https','http']);
    $badge=$badgeUrl?'<img src="'.$badgeUrl.'" width="180" alt="'.esc_attr($row->title.' badge').'" style="display:block;width:180px;max-width:100%;height:auto;margin:0 auto;">':'<p style="font-size:42px;margin:0;">&#9733;</p><p>Your badge is earned. The artwork is on its way!</p>';
    return '<!doctype html><html><body style="margin:0;padding:24px 12px;background:#fff8ea;color:#25315e;font-family:Arial,sans-serif;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center"><table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;"><tr><td style="padding:14px 24px;font-weight:bold;letter-spacing:2px;color:#08796e;">PUZZLE PATH</td></tr><tr><td align="center" style="padding:32px 24px;background:#ffe374;border:2px solid #25315e;border-radius:24px;"><p style="font-size:14px;font-weight:bold;letter-spacing:2px;">CLUES CRACKED. BADGE UNLOCKED.</p><h1 style="font-size:32px;line-height:1.2;">High five, '.esc_html($row->customer_name).'!</h1><p style="font-size:18px;">You conquered <strong>'.esc_html($row->title).'</strong>. That deserves a little celebration!</p></td></tr><tr><td align="center" style="padding:30px 24px;background:#ffffff;"><p style="font-weight:bold;color:#08796e;">YOUR SHINY NEW BADGE</p>'.$badge.'<p style="font-size:18px;"><strong>'.esc_html($row->title).'</strong></p><p style="color:#586078;">Your adventure time: <strong>'.esc_html($duration).'</strong></p><p style="margin:30px 0;"><a href="'.esc_url($url).'" style="display:inline-block;background:#6745bd;color:#ffffff;padding:16px 24px;border:2px solid #25315e;border-radius:14px;text-decoration:none;font-weight:bold;">Show me my badge collection! &#8599;</a></p><p>Your badge has a home on your personal dashboard. Pop in to relive your result, peek at the leaderboard and find your next badge-shaped adventure.</p></td></tr><tr><td style="padding:22px 24px;background:#ece1ff;border-radius:0 0 24px 24px;"><p style="margin:0 0 12px;">Keep that curiosity going!<br><strong>The Puzzle Path team</strong></p><p style="font-size:12px;line-height:1.6;margin:0;">This is your private dashboard link, valid for 30 days. Keep it to yourself. If images are hidden, open your dashboard to see your badge.</p></td></tr></table></td></tr></table></body></html>';
}
function pp_send_completion_email($id) {
    global $wpdb;
    $table=$wpdb->prefix.'pp_completion_emails';
    $row=$wpdb->get_row($wpdb->prepare("SELECT b.id,b.customer_name,b.customer_email,e.title,e.medal_image_url,p.duration_ms,p.completed_at FROM {$wpdb->prefix}pp_bookings b JOIN {$wpdb->prefix}pp_events e ON e.id=b.event_id JOIN {$wpdb->prefix}pp_booking_progress p ON p.booking_id=b.id WHERE b.id=%d AND b.payment_status='paid' AND p.quest_status='completed'",$id));
    if (!$row || !is_email($row->customer_email)) return 'unavailable';
    $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (booking_id,status,updated_at) VALUES (%d,'pending',UTC_TIMESTAMP())",$id));
    // Claim once before calling mail. An uncertain send is never retried automatically.
    $claimed=$wpdb->query($wpdb->prepare("UPDATE $table SET status='sending',attempts=attempts+1,updated_at=UTC_TIMESTAMP() WHERE booking_id=%d AND status IN ('pending','failed') AND attempts<3",$id));
    if ($claimed!==1) return $wpdb->get_var($wpdb->prepare("SELECT status FROM $table WHERE booking_id=%d",$id)) ?: 'unavailable';
    $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
    $created=$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}pp_dashboard_access (token_hash,booking_id,expires_at,created_at) VALUES (%s,%d,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY),UTC_TIMESTAMP())",$hash,$id));
    $accepted=false;
    if ($created===1) {
        $url='https://app.puzzlepath.com.au/dashboard.php#access='.$token;
        $seconds=(int)floor($row->duration_ms/1000);$duration=sprintf('%d:%02d:%02d',floor($seconds/3600),floor(($seconds%3600)/60),$seconds%60);
        $body=pp_completion_email_body($row,$url,$duration);
        try { $accepted=wp_mail($row->customer_email,'Congratulations on completing '.$row->title.'!',$body,['Content-Type: text/html; charset=UTF-8']); }
        catch (Throwable $e) { error_log('PuzzlePath completion email encountered an uncertain send for booking '.(int)$id); return 'sending'; }
    }
    $status=$accepted?'sent':'failed';
    $wpdb->query($wpdb->prepare("UPDATE $table SET status=%s,updated_at=UTC_TIMESTAMP(),sent_at=IF(%s='sent',UTC_TIMESTAMP(),NULL) WHERE booking_id=%d AND status='sending'",$status,$status,$id));
    return $status;
}
add_action('pp_completion_email_tick',function(){global $wpdb;$ids=$wpdb->get_col("SELECT booking_id FROM {$wpdb->prefix}pp_completion_emails WHERE status IN ('pending','failed') AND attempts<3 ORDER BY updated_at LIMIT 10");foreach($ids as $id) pp_send_completion_email((int)$id);});
add_action('admin_init',function(){
    if(($_POST['pp_completion_action']??'')!=='resend_badge' || !current_user_can('manage_options'))return;
    $id=absint($_POST['booking_id']??0);$sentAt=sanitize_text_field(wp_unslash($_POST['sent_at']??''));
    check_admin_referer('pp_badge_email_'.$id.'_'.$sentAt);
    global $wpdb;
    // Compare the previous send timestamp so a repeated form submission cannot send twice.
    $changed=$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}pp_completion_emails SET status='pending',attempts=0,sent_at=NULL,updated_at=UTC_TIMESTAMP() WHERE booking_id=%d AND status='sent' AND sent_at=%s",$id,$sentAt));
    if($changed===1)pp_send_completion_email($id);
    wp_safe_redirect(admin_url('admin.php?page=puzzlepath-completion-emails'));exit;
});
add_action('rest_api_init',function(){register_rest_route('puzzlepath/v1','/completion-email',['methods'=>'POST','permission_callback'=>'__return_true','callback'=>function($request){
    global $wpdb;$token=$request->get_param('access_token');
    if (!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token)) return new WP_Error('invalid_access','Invalid access.',['status'=>403]);
    $id=$wpdb->get_var($wpdb->prepare("SELECT a.booking_id FROM {$wpdb->prefix}pp_dashboard_access a JOIN {$wpdb->prefix}pp_booking_progress p ON p.booking_id=a.booking_id WHERE a.token_hash=%s AND a.expires_at>UTC_TIMESTAMP() AND p.quest_status='completed'",hash('sha256',$token)));
    if (!$id) return new WP_Error('invalid_access','Invalid or expired access.',['status'=>403]);
    return rest_ensure_response(['email_status'=>pp_send_completion_email((int)$id)]);
}]);});
add_action('admin_menu',function(){add_submenu_page('puzzlepath-booking','Completion Emails','Completion Emails','manage_options','puzzlepath-completion-emails',function(){
    if(!current_user_can('manage_options'))return;
    global $wpdb;
    $rows=$wpdb->get_results("SELECT q.*,b.booking_code,e.title FROM {$wpdb->prefix}pp_completion_emails q JOIN {$wpdb->prefix}pp_bookings b ON b.id=q.booking_id JOIN {$wpdb->prefix}pp_events e ON e.id=b.event_id ORDER BY q.updated_at DESC LIMIT 50");
    echo '<div class="wrap"><h1>Quest Completion Emails</h1><p>Sent means WordPress accepted the email for sending; it does not confirm inbox delivery. Failed sends retry up to three times. Sending is retained after an uncertain send to avoid duplicates.</p><table class="widefat striped"><thead><tr><th>Booking</th><th>Quest</th><th>Status</th><th>Attempts</th><th>Accepted at (UTC)</th><th>Actions</th></tr></thead><tbody>';
    foreach($rows as $row){
        echo '<tr><td>'.esc_html($row->booking_code).'</td><td>'.esc_html($row->title).'</td><td>'.esc_html(ucfirst($row->status)).'</td><td>'.(int)$row->attempts.'</td><td>'.esc_html($row->sent_at?:'-').'</td><td>';
        if($row->status==='sent' && $row->sent_at){echo '<form method="post"><input type="hidden" name="pp_completion_action" value="resend_badge"><input type="hidden" name="booking_id" value="'.(int)$row->booking_id.'"><input type="hidden" name="sent_at" value="'.esc_attr($row->sent_at).'">';wp_nonce_field('pp_badge_email_'.$row->booking_id.'_'.$row->sent_at);echo '<button type="submit" class="button">Resend with current badge</button></form>';}
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
});});
