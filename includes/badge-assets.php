<?php
defined('ABSPATH') || exit;
/** Small, versioned display assets; uploaded badge originals are never modified. */
function pp_badge_assets_install() {
    global $wpdb;
    return $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pp_badge_assets (source_hash char(64) NOT NULL PRIMARY KEY, source_url varchar(1000) NOT NULL, signature char(64) NOT NULL, display_url varchar(1000) NOT NULL, email_url varchar(1000) NOT NULL, updated_at datetime NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")!==false;
}
function pp_badge_asset_url($source,$kind='display') {
    global $wpdb;
    static $generated=0;
    $source=trim((string)$source);if($source==='')return '';
    $uploads=wp_upload_dir();if(!empty($uploads['error']))return $source;
    $sourcePath=rawurldecode(parse_url($source,PHP_URL_PATH)??'');
    $basePath=parse_url($uploads['baseurl'],PHP_URL_PATH)??'';
    $sourceHost=parse_url($source,PHP_URL_HOST);$baseHost=parse_url($uploads['baseurl'],PHP_URL_HOST);
    if(($sourceHost && strcasecmp($sourceHost,$baseHost)!==0)||strpos($sourcePath,$basePath.'/')!==0)return $source;
    $root=realpath($uploads['basedir']);$file=realpath($uploads['basedir'].substr($sourcePath,strlen($basePath)));
    if(!$root||!$file||strpos($file,$root.DIRECTORY_SEPARATOR)!==0||!is_file($file))return $source;
    $hash=hash('sha256',$source);$signature=hash('sha256','badge-384-v1|'.$file.'|'.filemtime($file).'|'.filesize($file));
    $cached=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}pp_badge_assets WHERE source_hash=%s",$hash));
    if($cached && hash_equals($cached->signature,$signature) && is_file($uploads['basedir'].'/puzzlepath-badge-cache/'.basename(parse_url($cached->display_url,PHP_URL_PATH)??'')) && is_file($uploads['basedir'].'/puzzlepath-badge-cache/'.basename(parse_url($cached->email_url,PHP_URL_PATH)??'')))return $kind==='email'?$cached->email_url:$cached->display_url;
    if($generated>=4)return $source;
    $generated++;
    require_once ABSPATH.'wp-admin/includes/image.php';
    $editor=wp_get_image_editor($file);if(is_wp_error($editor))return $source;
    $resized=$editor->resize(384,576,false);if(is_wp_error($resized))return $source;
    $directory=$uploads['basedir'].'/puzzlepath-badge-cache';if(!wp_mkdir_p($directory))return $source;
    if(!file_exists($directory.'/.htaccess'))file_put_contents($directory.'/.htaccess',"<IfModule mod_headers.c>\nHeader set Cache-Control \"public, max-age=31536000, immutable\"\n</IfModule>\n");
    $stem=substr($hash,0,16).'-'.substr($signature,0,16).'-384';
    $editor->set_quality(82);$display=$editor->save($directory.'/'.$stem.'.webp','image/webp');
    if(is_wp_error($display))$display=$editor->save($directory.'/'.$stem.'.jpg','image/jpeg');
    if(is_wp_error($display))return $source;
    // PNG remains widely compatible with email clients and preserves transparent artwork.
    $email=$editor->save($directory.'/'.$stem.'.png','image/png');
    if(is_wp_error($email))$email=$display;
    $base=rtrim($uploads['baseurl'],'/').'/puzzlepath-badge-cache/';
    $displayUrl=$base.basename($display['path']);$emailUrl=$base.basename($email['path']);
    $saved=$wpdb->replace($wpdb->prefix.'pp_badge_assets',['source_hash'=>$hash,'source_url'=>$source,'signature'=>$signature,'display_url'=>$displayUrl,'email_url'=>$emailUrl,'updated_at'=>gmdate('Y-m-d H:i:s')],['%s','%s','%s','%s','%s','%s']);
    if($saved===false)return $source;
    return $kind==='email'?$emailUrl:$displayUrl;
}
add_action('admin_init',function(){
    if(!current_user_can('manage_options')||!pp_badge_assets_install())return;
    global $wpdb;
    $sources=$wpdb->get_col("SELECT DISTINCT medal_image_url FROM {$wpdb->prefix}pp_events WHERE medal_image_url IS NOT NULL AND medal_image_url<>'' ORDER BY medal_image_url");
    // Cache generation runs in admin requests, never in the player's page request.
    foreach($sources as $source){try{pp_badge_asset_url($source);}catch(Throwable $e){error_log('PuzzlePath badge display copy could not be generated.');}}
});
