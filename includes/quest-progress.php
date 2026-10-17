<?php
defined('ABSPATH') || exit;

function puzzlepath_progress_table_exists($name) {
    global $wpdb;
    return (bool)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $name));
}
function puzzlepath_progress_install() {
    if (!current_user_can('manage_options') || get_option('puzzlepath_booking_progress_v1')) return;
    global $wpdb;
    $table = $wpdb->prefix . 'pp_booking_progress';
    $collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (booking_id mediumint(9) NOT NULL, quest_status varchar(20) NOT NULL DEFAULT 'not_started', started_at datetime NULL, completed_at datetime NULL, duration_ms bigint NULL, source varchar(32) NOT NULL DEFAULT 'app', note text NULL, updated_at datetime NOT NULL, PRIMARY KEY (booking_id), KEY quest_status (quest_status)) ENGINE=InnoDB $collate";
    $created = $wpdb->query($sql);
    $redemptions = $wpdb->prefix . 'pp_redemptions';
    $redeemed = $wpdb->query("CREATE TABLE IF NOT EXISTS $redemptions (id INT AUTO_INCREMENT PRIMARY KEY, booking_code VARCHAR(50) UNIQUE NOT NULL, redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    if ($created !== false && $redeemed !== false) update_option('puzzlepath_booking_progress_v1', 1);
}
add_action('admin_init','puzzlepath_progress_install');
function puzzlepath_booking_progress($id) {
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pp_booking_progress WHERE booking_id=%d",$id));
    return $row ?: (object)['quest_status'=>'not_started','started_at'=>null,'completed_at'=>null,'duration_ms'=>null,'source'=>'','note'=>''];
}
function puzzlepath_progress_datetime($date) {
    return $date ? wp_date('j M Y, g:i a',strtotime($date.' UTC')) : 'Not recorded';
}
function puzzlepath_progress_duration($ms) {
    if ($ms === null) return 'Not recorded';
    $seconds = (int)round($ms / 1000);
    return sprintf('%d:%02d:%02d',floor($seconds/3600),floor($seconds/60)%60,$seconds%60);
}
function puzzlepath_progress_badge($id, $details=false) {
    $p = puzzlepath_booking_progress($id);
    $labels=['not_started'=>'Not started','in_progress'=>'In progress','completed'=>'Completed'];
    $colors=['not_started'=>'#646970','in_progress'=>'#996800','completed'=>'#00802b'];
    $help=['not_started'=>'No quest start has been recorded. Older or offline activity may not have been saved.','in_progress'=>'A quest start has been recorded; completion has not been confirmed.','completed'=>'Quest completion has been recorded. Open progress details for its date and source.'];
    $status=$p->quest_status;
    $html='<span tabindex="0" title="'.esc_attr($help[$status]??'Unknown progress').'" style="display:inline-block;padding:3px 8px;border-radius:3px;color:#fff;background:'.esc_attr($colors[$status]??'#646970').'">'.esc_html($labels[$status]??$status).'</span>';
    if ($p->completed_at) $html.='<br><small>'.esc_html(puzzlepath_progress_datetime($p->completed_at)).'</small>';
    if ($details) {
        $sources=['app'=>'Quest app','legacy_tracking'=>'Imported app tracking record','admin'=>'Manually recorded by an administrator'];
        $html.='<p>Started: '.esc_html(puzzlepath_progress_datetime($p->started_at)).'<br>Time taken: '.esc_html(puzzlepath_progress_duration($p->duration_ms)).'<br>Source: '.esc_html($sources[$p->source]??'No recorded activity').'</p>';
        if ($p->note) $html.='<p>Note: '.esc_html($p->note).'</p>';
    }
    $html.='<br><a href="'.esc_url(admin_url('admin.php?page=puzzlepath-quest-progress&booking_id='.(int)$id)).'">Progress details</a>';
    return $html;
}
function puzzlepath_import_legacy_progress() {
    global $wpdb;
    if (!puzzlepath_progress_table_exists('pp_quest_tracking')) return ['imported'=>0,'skipped'=>0];
    $table=$wpdb->prefix.'pp_booking_progress'; $bookings=$wpdb->prefix.'pp_bookings';
    $rows=$wpdb->get_results("SELECT b.id AS booking_id, b.booking_code, t.id AS tracking_id, UNIX_TIMESTAMP(t.start_time) AS started, UNIX_TIMESTAMP(t.end_time) AS finished, t.total_time_ms FROM $bookings b JOIN pp_quest_tracking t ON CONVERT(t.booking_code USING utf8mb4) COLLATE utf8mb4_unicode_ci=CONVERT(b.booking_code USING utf8mb4) COLLATE utf8mb4_unicode_ci AND t.hunt_id=b.event_id LEFT JOIN $table p ON p.booking_id=b.id WHERE b.payment_status='paid' AND p.booking_id IS NULL ORDER BY b.id, t.end_time DESC, t.id DESC LIMIT 200");
    if ($wpdb->last_error) throw new RuntimeException('Could not read historical tracking records.');
    $seen=[]; $imported=0; $skipped=0;
    foreach ($rows as $row) {
        if (isset($seen[$row->booking_id])) continue;
        $seen[$row->booking_id]=true;
        $start=(int)$row->started; $end=(int)$row->finished;
        if (!$start || $start>time()+300 || ($end && ($end<$start || $end>time()+300 || (int)$row->total_time_ms<1 || abs(($end-$start)*1000-(int)$row->total_time_ms)>10000))) { $skipped++; continue; }
        $data=['booking_id'=>(int)$row->booking_id,'quest_status'=>$end?'completed':'in_progress','started_at'=>gmdate('Y-m-d H:i:s',$start),'completed_at'=>$end?gmdate('Y-m-d H:i:s',$end):null,'duration_ms'=>$end?(int)$row->total_time_ms:null,'source'=>'legacy_tracking','note'=>'Imported from app tracking record #'.(int)$row->tracking_id,'updated_at'=>gmdate('Y-m-d H:i:s')];
        $redemptions=$wpdb->prefix.'pp_redemptions';
        foreach ([$table,$redemptions] as $name) {
            $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$name));
            if (strtoupper((string)$engine)!=='INNODB') throw new RuntimeException('Historical import requires transactional tables.');
        }
        if ($wpdb->query('START TRANSACTION')===false) throw new RuntimeException('Could not begin historical import.');
        try {
        $result=$wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (booking_id,quest_status,started_at,completed_at,duration_ms,source,note,updated_at) VALUES (%d,%s,%s,NULLIF(%s,''),NULLIF(%s,''),%s,%s,%s)",$data['booking_id'],$data['quest_status'],$data['started_at'],$data['completed_at']??'',$data['duration_ms']===null?'':(string)$data['duration_ms'],$data['source'],$data['note'],$data['updated_at']));
            if ($result===false) throw new RuntimeException('Could not import historical progress.');
            if ($result && $end && $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $redemptions (booking_code) VALUES (%s)",$row->booking_code))===false) throw new RuntimeException('Could not mark imported completion as used.');
            if ($wpdb->query('COMMIT')===false) throw new RuntimeException('Could not confirm historical import.');
        } catch (Throwable $e) { $wpdb->query('ROLLBACK'); throw $e; }
        if ($result) { $imported++; PuzzlePath_Audit_Logger::log_booking_action($data['booking_id'],'quest_progress_imported',null,$data,$data['note']); }
    }
    return ['imported'=>$imported,'skipped'=>$skipped];
}
function puzzlepath_mark_quest_completed($id,$note) {
    global $wpdb;
    if (trim($note)==='') throw new RuntimeException('Please enter a reason for recording completion manually.');
    $table=$wpdb->prefix.'pp_booking_progress'; $bookings=$wpdb->prefix.'pp_bookings'; $redemptions=$wpdb->prefix.'pp_redemptions';
    if ($wpdb->query("CREATE TABLE IF NOT EXISTS $redemptions (id INT AUTO_INCREMENT PRIMARY KEY, booking_code VARCHAR(50) UNIQUE NOT NULL, redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB")===false) throw new RuntimeException('Could not initialise booking redemption storage.');
    foreach ([$table,$bookings,$redemptions] as $name) {
        $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$name));
        if (strtoupper((string)$engine)!=='INNODB') throw new RuntimeException('Completion requires transactional tables.');
    }
    if ($wpdb->query('START TRANSACTION')===false) throw new RuntimeException('Could not start completion update.');
    try {
        $booking=$wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings WHERE id=%d FOR UPDATE",$id));
        if (!$booking || $booking->payment_status!=='paid') throw new RuntimeException('Only a Paid booking can be marked Completed.');
        $old=puzzlepath_booking_progress($id);
        if ($old->quest_status==='completed') { $wpdb->query('ROLLBACK'); return; }
        $now=gmdate('Y-m-d H:i:s');
        $result=$wpdb->query($wpdb->prepare("INSERT INTO $table (booking_id,quest_status,completed_at,source,note,updated_at) VALUES (%d,'completed',%s,'admin',%s,%s) ON DUPLICATE KEY UPDATE quest_status='completed',completed_at=VALUES(completed_at),duration_ms=NULL,source='admin',note=VALUES(note),updated_at=VALUES(updated_at)",$id,$now,$note,$now));
        if ($result===false || $wpdb->query($wpdb->prepare("INSERT IGNORE INTO $redemptions (booking_code) VALUES (%s)",$booking->booking_code))===false || $wpdb->query('COMMIT')===false) throw new RuntimeException('Could not save completion.');
    } catch (Throwable $e) { $wpdb->query('ROLLBACK'); throw $e; }
    PuzzlePath_Audit_Logger::log_booking_action($id,'quest_completed_manually',(array)$old,(array)puzzlepath_booking_progress($id),$note);
}
function puzzlepath_quest_progress_page() {
    if (!current_user_can('manage_options')) wp_die('Insufficient permissions.');
    global $wpdb;
    echo '<div class="wrap"><h1>Quest Progress</h1><p>Quest progress is separate from payment status. Completion dates use your WordPress timezone. An absent start record does not prove that an older booking was never used.</p>';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_admin_referer('puzzlepath_quest_progress');
        try {
            if (($_POST['progress_action']??'')==='import') { $r=puzzlepath_import_legacy_progress(); echo '<div class="notice notice-success"><p>'.esc_html($r['imported'].' app tracking records imported; '.$r['skipped'].' need review.').'</p></div>'; }
            elseif (($_POST['progress_action']??'')==='complete') { puzzlepath_mark_quest_completed(absint($_POST['booking_id']??0),sanitize_textarea_field(wp_unslash($_POST['note']??''))); echo '<div class="notice notice-success"><p>Quest completion recorded. No payment or email action was performed.</p></div>'; }
        } catch (Throwable $e) { echo '<div class="notice notice-error"><p>'.esc_html($e->getMessage()).'</p></div>'; }
    }
    $table=$wpdb->prefix.'pp_booking_progress';
    $counts=$wpdb->get_results("SELECT quest_status,COUNT(*) AS total FROM $table GROUP BY quest_status",OBJECT_K);
    echo '<p><strong>Recorded completions: '.esc_html($counts['completed']->total??0).'</strong> &nbsp; Recorded starts without completion: '.esc_html($counts['in_progress']->total??0).'</p>';
    if (puzzlepath_progress_table_exists('pp_quest_tracking')) {
        $legacy=$wpdb->get_var('SELECT COUNT(*) FROM pp_quest_tracking WHERE end_time IS NOT NULL');
        echo '<p>App tracking records with a finish time: '.esc_html($legacy).'. Historical imports match both booking code and quest ID and require consistent timestamps. Used-booking records alone are not treated as proof of completion.</p><form method="post">';wp_nonce_field('puzzlepath_quest_progress');echo '<button class="button" name="progress_action" value="import">Import recorded app progress (up to 200 records)</button></form>';
    } else echo '<p>No historical app tracking table was found in the booking database.</p>';
    $id=absint($_GET['booking_id']??$_POST['booking_id']??0);
    if ($id) {
        $b=$wpdb->get_row($wpdb->prepare("SELECT id,booking_code,payment_status FROM {$wpdb->prefix}pp_bookings WHERE id=%d",$id));
        if ($b) {
            echo '<h2>Booking '.esc_html($b->booking_code).'</h2>'.puzzlepath_progress_badge($id,true);
            if ($b->payment_status==='paid' && puzzlepath_booking_progress($id)->quest_status!=='completed') {
                echo '<h3>Record completion manually</h3><p>Use this only when you have confirmed the quest was completed. This also marks the booking used so it cannot be replayed.</p><form method="post">';wp_nonce_field('puzzlepath_quest_progress');echo '<input type="hidden" name="booking_id" value="'.esc_attr($id).'"><label for="pp-progress-note">Reason / evidence</label><br><textarea id="pp-progress-note" name="note" rows="3" class="large-text" required></textarea><p><button class="button button-primary" name="progress_action" value="complete">Mark quest Completed</button></p></form>';
            }
        }
    }
    echo '</div>';
}
add_action('admin_menu',function(){ add_submenu_page('puzzlepath-booking','Quest Progress','Quest Progress','manage_options','puzzlepath-quest-progress','puzzlepath_quest_progress_page'); });
