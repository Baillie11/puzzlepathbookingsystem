<?php
$fixture=sys_get_temp_dir().'/pp-badge-test-'.bin2hex(random_bytes(5));mkdir($fixture.'/wp-admin/includes',0777,true);mkdir($fixture.'/uploads');
file_put_contents($fixture.'/wp-admin/includes/image.php','<?php');file_put_contents($fixture.'/uploads/badge.png','original badge fixture');file_put_contents($fixture.'/outside.png','outside');define('ABSPATH',$fixture.'/');
function add_action(...$args){}function wp_upload_dir(){global $fixture;return ['basedir'=>$fixture.'/uploads','baseurl'=>'https://puzzlepath.com.au/wp-content/uploads','error'=>false];}
function wp_mkdir_p($dir){return is_dir($dir)||mkdir($dir,0777,true);}function is_wp_error($r){return $r instanceof Exception;}
class BadgeEditor {function resize($w,$h,$crop){if($w!==384||$h!==576||$crop)throw new Exception('Wrong resize geometry');return true;}function set_quality($q){}function save($path,$mime){file_put_contents($path,'small badge fixture');return ['path'=>$path];}}
function wp_get_image_editor($file){global $editCalls;$editCalls++;return new BadgeEditor;}
class BadgeDatabase {public $prefix='wp2s_';public $rows=[];function prepare($q,$hash){return $hash;}function get_row($hash){return isset($this->rows[$hash])?(object)$this->rows[$hash]:null;}function replace($table,$row,$format){$this->rows[$row['source_hash']]=$row;return 1;}}
$wpdb=new BadgeDatabase;$editCalls=0;require __DIR__.'/../includes/badge-assets.php';
function badgeCheck($ok,$msg){if(!$ok)throw new Exception($msg);}
$url='https://puzzlepath.com.au/wp-content/uploads/badge.png';$display=pp_badge_asset_url($url);badgeCheck(strpos($display,'puzzlepath-badge-cache/')!==false&&substr($display,-5)==='.webp','Optimised display copy missing');
$email=pp_badge_asset_url($url,'email');badgeCheck(substr($email,-4)==='.png'&&$editCalls===1,'Email copy or reuse failed');badgeCheck(file_get_contents($fixture.'/uploads/badge.png')==='original badge fixture','Original changed');
file_put_contents($fixture.'/uploads/badge.png','new longer original badge fixture');clearstatcache();badgeCheck(pp_badge_asset_url($url)!==$display&&$editCalls===2,'Changed artwork did not invalidate cache');
$outside='https://puzzlepath.com.au/wp-content/uploads/../outside.png';badgeCheck(pp_badge_asset_url($outside)===$outside&&$editCalls===2,'Path escaped uploads');$remote='https://example.test/wp-content/uploads/badge.png';badgeCheck(pp_badge_asset_url($remote)===$remote&&$editCalls===2,'Remote URL was fetched');
echo "PASS: aspect-preserving display/email copies, cache reuse, source invalidation, original preserved and local path confinement.\n";
