<?php
// Run with: php tests/quest-image-upload-test.php
$source = file_get_contents(__DIR__ . '/../puzzlepath-booking.php');
$start = strpos($source, 'function puzzlepath_handle_quest_image_upload(');
$end = strpos($source, '/**', $start);
eval(substr($source, $start, $end - $start));

function wp_check_filetype_and_ext($path, $name, $mimes) {
    return pathinfo($name, PATHINFO_EXTENSION) === 'webp'
        ? ['ext' => 'webp', 'type' => 'image/webp']
        : ['ext' => false, 'type' => false];
}
function wp_get_image_mime($path) { return $GLOBALS['detected_mime']; }
function wp_upload_dir() { throw new RuntimeException('validated'); }
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}

$path = tempnam(sys_get_temp_dir(), 'quest-image-');
try {
    $GLOBALS['detected_mime'] = 'image/webp';
    file_put_contents($path, str_repeat('x', 2 * 1024 * 1024));
    $file = ['error' => UPLOAD_ERR_OK, 'size' => filesize($path), 'tmp_name' => $path, 'name' => 'quest.webp', 'type' => 'image/webp'];
    try {
        puzzlepath_handle_quest_image_upload($file);
        throw new RuntimeException('Expected valid file to reach upload stage');
    } catch (RuntimeException $e) {
        check($e->getMessage() === 'validated', 'Exactly 2 MB should be accepted');
    }
    file_put_contents($path, 'x', FILE_APPEND);
    clearstatcache(true, $path);
    $result = puzzlepath_handle_quest_image_upload($file);
    check(!$result['success'] && strpos($result['error'], '2 MB') !== false, 'Actual size over limit must be rejected even with smaller reported size');
    file_put_contents($path, 'image');
    clearstatcache(true, $path);
    $file['size'] = 5;
    $GLOBALS['detected_mime'] = 'text/plain';
    check(!puzzlepath_handle_quest_image_upload($file)['success'], 'Disguised image must be rejected');
    $GLOBALS['detected_mime'] = 'image/webp';
    $file['name'] = 'quest.svg';
    check(!puzzlepath_handle_quest_image_upload($file)['success'], 'Unsupported extension must be rejected');
    $file['error'] = UPLOAD_ERR_INI_SIZE;
    check(!puzzlepath_handle_quest_image_upload($file)['success'], 'PHP upload errors must be rejected');
    echo "Quest image upload checks passed.\n";
} finally {
    unlink($path);
}
