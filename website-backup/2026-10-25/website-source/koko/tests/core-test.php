<?php
require __DIR__.'/../src/core.php';
require __DIR__.'/../src/conversions.php';
$count=0;
function check(bool $ok,string $label):void { global $count; if(!$ok)throw new RuntimeException($label);$count++;echo "PASS $label\n"; }
$campaigns=require __DIR__.'/../config/campaigns.php';
$brochure=resolveCampaign('dl-brochure',$campaigns)['campaign'];
$direct=resolveCampaign('',$campaigns)['campaign'];
check(quote(1995,$brochure)===1496,'brochure 25% integer rounding');
check(quote(1995,$direct)===1995,'direct has no automatic discount');
check(resolveCampaign('evil',$campaigns)['campaign']['percent']===0,'unknown campaign cannot grant discount');
$expired=$campaigns;$expired['dl-brochure']['expires']='2020-01-01T00:00:00Z';
check(resolveCampaign('dl-brochure',$expired)['error']!==null,'expired campaign rejected');
$future=$campaigns;$future['dl-brochure']['starts']='2099-01-01T00:00:00Z';
check(resolveCampaign('dl-brochure',$future)['error']!==null,'future campaign rejected');
check(resolveCampaign('facebook-october',$campaigns)['campaign']['source']==='facebook','Facebook attribution');
check(resolveCampaign('partner-goldcoast',$campaigns)['campaign']['partner']==='goldcoast','partner trusted configuration');
$rezdy=$campaigns;$rezdy['rezdy-referral']['active']=true;
check(resolveCampaign('rezdy-referral',$rezdy)['campaign']['percent']===0,'mock Rezdy referral gives no discount');
check(scalarParam(['quest'=>['23']],'quest')==='','array input rejected');
$data=json_decode(file_get_contents(__DIR__.'/catalogue-snapshot.json'),true);
$quests=normaliseCatalogue($data);
check(count($quests)===11 && count(array_unique(array_column($quests,'id')))===11,'all eleven genuine quests appear exactly once');
$data['hunts'][0]['display_on_site']=false;$data['hunts'][1]['seats_available']=0;$data['hunts'][2]['active']=false;
check(count(normaliseCatalogue($data))===8,'unpublished sold-out archived excluded');
check($quests[0]['description']!=='','missing description fallback');
$duplicated=$data;$duplicated['hunts'][]=$data['hunts'][3];
try{normaliseCatalogue($duplicated);check(false,'duplicate rejected');}catch(RuntimeException $e){check(true,'duplicate rejected');}
try{(new PuzzlePathProvider())->destination($quests[0],$brochure);check(false,'unverified promo blocked');}catch(RuntimeException $e){check(true,'unverified promo blocked');}
$url=(new PuzzlePathProvider(true))->destination($quests[0],$brochure);parse_str(parse_url($url,PHP_URL_QUERY),$params);
check($params['event_id']===$quests[0]['id'].'' && $params['discount']==='BROCHURE25','supported booking URL selects quest and coupon');
check(parse_url($url,PHP_URL_HOST)==='puzzlepath.com.au' && parse_url($url,PHP_URL_SCHEME)==='https','fixed trusted HTTPS destination');
check(!isset($params['percent']) && !isset($params['price']),'checkout price never supplied by URL');
parse_str(parse_url((new PuzzlePathProvider())->destination($quests[0],$direct),PHP_URL_QUERY),$params);
check($params['discount']==='','direct clears saved voucher without stacking');
try{(new RezdyProvider())->destination($quests[0],$brochure);check(false,'Rezdy disabled');}catch(RuntimeException $e){check(true,'Rezdy disabled');}
check(esc('<script>')==='&lt;script&gt;','output escaped');
$db=new PDO('sqlite::memory:');$ledger=new ConversionLedger($db);
$verifier=new class implements BookingNotificationVerifier {
 public function verify(string $body,array $headers):array {
  if(!hash_equals(hash_hmac('sha256',$body,'test-secret'),$headers['signature']??''))throw new RuntimeException('Invalid signature');
  return json_decode($body,true);
 }
};
$body=json_encode(['provider'=>'mock','reference'=>'test-1','status'=>'payment_confirmed','campaign'=>'brochure_dl_2026','amount_cents'=>1496]);
try{$ledger->accept($verifier,$body,[]);check(false,'unauthenticated notification rejected');}catch(RuntimeException $e){check(true,'unauthenticated notification rejected');}
$headers=['signature'=>hash_hmac('sha256',$body,'test-secret')];
check($ledger->accept($verifier,$body,$headers),'verified mock payment recorded');
check(!$ledger->accept($verifier,$body,$headers),'duplicate conversion ignored');
$bad=str_replace('payment_confirmed','checkout_handoff',$body);
try{$ledger->accept($verifier,$bad,['signature'=>hash_hmac('sha256',$bad,'test-secret')]);check(false,'click is not conversion');}catch(RuntimeException $e){check(true,'click is not conversion');}
echo "$count checks passed. Mock verifier is test-only.\n";
