<?php
declare(strict_types=1);
$appRoot=getenv('PP_APP_ROOT') ?: dirname(__DIR__);
require $appRoot.'/src/core.php';
header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; script-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$mode=getenv('PP_ENV') ?: 'production';
$storage=getenv('PP_STORAGE') ?: $appRoot.'/var';
$campaigns=require $appRoot.'/config/campaigns.php';
$resolved=resolveCampaign(scalarParam($_GET,'campaign'),$campaigns);
$campaign=$resolved['campaign']; $campaignSlug=$resolved['slug'];
$action=scalarParam($_GET,'action');
$catalogue=catalogue($mode,$storage,$action==='book'); $quests=$catalogue['quests'];
$message=$resolved['error'];
if (!$message && $mode!=='local' && getenv('PP_BOOKING_VERIFIED')!=='1') {
    $message='Explore our adventures below. Online booking from this page is temporarily unavailable while we finish checking the booking connection.';
}
if ($action==='book') {
    $id=scalarParam($_GET,'quest'); $quest=null;
    foreach ($quests as $q) if ((string)$q['id']===$id) $quest=$q;
    try {
        if ($resolved['error']) throw new RuntimeException($resolved['error']);
        if (!$quest || $catalogue['stale']) throw new RuntimeException('We cannot confirm this quest’s availability right now. Please return to the islands and try again shortly.');
        eventLog($storage,'quest_selection',$campaign,$quest['id']);
        if ($mode==='local') { require $appRoot.'/src/mock-checkout.php'; exit; }
        if (getenv('PP_BOOKING_VERIFIED')!=='1') throw new RuntimeException('Booking handoff is awaiting verification. Please try again shortly. No booking or payment has been made.');
        $provider=$campaign['provider']==='rezdy'?new RezdyProvider():new PuzzlePathProvider(getenv('PP_PROMOTION_VERIFIED')==='1');
        $destination=$provider->destination($quest,$campaign);
        eventLog($storage,'checkout_handoff',$campaign,$quest['id']);
        header('Location: '.$destination,true,303); exit;
    } catch (RuntimeException $e) { http_response_code(503); $message=$e->getMessage(); }
}
if ($action==='webhook') { http_response_code(501); header('Content-Type: application/json'); echo '{"error":"Provider verification and reconciliation are not configured."}'; exit; }
if ($action==='') eventLog($storage,'landing_view',$campaign);
require $appRoot.'/src/page.php';
