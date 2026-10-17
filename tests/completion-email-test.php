<?php
define('ABSPATH',__DIR__);
function add_action(...$a){}function add_filter(...$a){}
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL);}
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES);}
function esc_attr($v){return htmlspecialchars($v,ENT_QUOTES);}
function esc_url($v){return htmlspecialchars($v,ENT_QUOTES);}
function wp_mail($to,$subject,$body,$headers){global $mailCalls,$mailAccept;$mailCalls++;if($to!=='fixture@example.test'||strpos($body,'dashboard.php#access=')===false)throw new Exception('Wrong mail destination or missing link');return $mailAccept;}
class MailDatabase {
 public $prefix='wp2s_';public $status='pending';public $attempts=0;public $email='fixture@example.test';
 function prepare($sql,...$args){return [$sql,$args];}
 function get_row($q){return (object)['id'=>7,'customer_name'=>'Fixture','customer_email'=>$this->email,'title'=>'Fixture Quest','medal_image_url'=>'https://puzzlepath.com.au/fixture-badge.png','duration_ms'=>120000,'completed_at'=>'2026-10-17 00:00:00'];}
 function get_var($q){return $this->status;}
 function query($q){[$sql,$args]=$q;if(strpos($sql,"SET status='sending'")!==false){if(!in_array($this->status,['pending','failed'])||$this->attempts>=3)return 0;$this->status='sending';$this->attempts++;return 1;}if(strpos($sql,'SET status=%s')!==false){$this->status=$args[0];return 1;}return 1;}
}
require __DIR__.'/../includes/completion-email.php';
function mailCheck($ok,$message){if(!$ok)throw new Exception($message);}
$wpdb=new MailDatabase;$mailCalls=0;$mailAccept=true;
mailCheck(pp_send_completion_email(7)==='sent'&&$mailCalls===1,'First email failed');
mailCheck(pp_send_completion_email(7)==='sent'&&$mailCalls===1,'Duplicate email sent');
$wpdb=new MailDatabase;$mailCalls=0;$mailAccept=false;
mailCheck(pp_send_completion_email(7)==='failed','False mail result marked sent');
$mailAccept=true;mailCheck(pp_send_completion_email(7)==='sent'&&$mailCalls===2,'Failed email retry failed');
$wpdb=new MailDatabase;$wpdb->status='sending';$mailCalls=0;pp_send_completion_email(7);mailCheck($mailCalls===0,'Uncertain send duplicated');
$wpdb=new MailDatabase;$wpdb->email='invalid';$mailCalls=0;pp_send_completion_email(7);mailCheck($mailCalls===0,'Invalid recipient sent');
$badgeFixture=(object)['customer_name'=>'Andrew <test>','title'=>'Broadbeach & friends','medal_image_url'=>'https://puzzlepath.com.au/fixture-badge.png'];
$badgeBody=pp_completion_email_body($badgeFixture,'https://app.puzzlepath.com.au/dashboard.php#access=fixture','0:02:04');
mailCheck(strpos($badgeBody,'src="https://puzzlepath.com.au/fixture-badge.png"')!==false && strpos($badgeBody,'Broadbeach &amp; friends badge')!==false,'Assigned quest badge missing or unescaped');
mailCheck(strpos($badgeBody,'Andrew &lt;test&gt;')!==false,'Customer name unescaped');
$badgeFixture->medal_image_url='';mailCheck(strpos(pp_completion_email_body($badgeFixture,'https://app.puzzlepath.com.au/dashboard.php','0:02:04'),'artwork is on its way')!==false,'Missing badge fallback absent');
echo "PASS: one email, stored recipient, private link, retry after rejection, uncertain-send deduplication.\n";
