<?php
declare(strict_types=1);
function esc(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function scalarParam(array $input, string $key): string {
    $value = $input[$key] ?? '';
    return is_string($value) && strlen($value) <= 100 ? $value : '';
}
function resolveCampaign(string $slug, array $campaigns, ?int $now=null): array {
    $direct = ['id'=>'direct','name'=>'Adventure on your terms','source'=>'direct','partner'=>null,'policy'=>null,'percent'=>0,'provider'=>'puzzlepath'];
    if ($slug === '') return ['slug'=>'','campaign'=>$direct,'error'=>null];
    $c = $campaigns[$slug] ?? null; $now ??= time();
    if (!$c || !$c['active'] || ($c['starts'] && strtotime($c['starts']) > $now) || ($c['expires'] && strtotime($c['expires']) <= $now)) {
        return ['slug'=>'','campaign'=>$direct,'error'=>'This campaign link is unavailable or has expired. Explore the quests at standard prices, or reopen your invitation.'];
    }
    return ['slug'=>$slug,'campaign'=>$c,'error'=>null];
}
function normaliseCatalogue(array $data): array {
    if (!isset($data['hunts']) || !is_array($data['hunts'])) throw new RuntimeException('Catalogue format unavailable.');
    $quests=[];
    foreach ($data['hunts'] as $row) {
        if (!is_array($row)) continue;
        $id=filter_var($row['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id || ($row['seats_available'] ?? 0) < 1 || (isset($row['display_on_site']) && !$row['display_on_site']) || (isset($row['active']) && !$row['active']) || empty($row['title']) || !is_numeric($row['price'] ?? null) || $row['price'] < 0) continue;
        if (isset($quests[$id])) throw new RuntimeException('Duplicate catalogue ID.');
        $description=trim(strip_tags((string)($row['quest_description'] ?? '')));
        if ($description==='') $description='Follow the clues and discover '.trim((string)($row['location'] ?? 'somewhere new')).'.';
        if (strlen($description)>150) $description=substr($description,0,147).'…';
        $quests[$id]=['id'=>$id,'title'=>(string)$row['title'],'description'=>$description,'price'=>(int)round((float)$row['price']*100),'provider'=>'puzzlepath'];
    }
    return array_values($quests);
}
function fetchCatalogue(string $url): array {
    // The destination is server configuration; never a visitor-supplied URL.
    if (parse_url($url,PHP_URL_SCHEME)!=='https' || parse_url($url,PHP_URL_HOST)!=='puzzlepath.com.au' || !function_exists('curl_init')) throw new RuntimeException('Catalogue connection unavailable.');
    $curl=curl_init($url);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>5,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $body=curl_exec($curl); $status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    if ($status!==200 || !is_string($body) || strlen($body)>1000000) throw new RuntimeException('Catalogue connection unavailable.');
    return json_decode($body,true,512,JSON_THROW_ON_ERROR);
}
function catalogue(string $mode, string $storage, bool $fresh=false, ?callable $loader=null): array {
    if ($mode==='local') return ['quests'=>normaliseCatalogue(json_decode(file_get_contents(__DIR__.'/../tests/catalogue-snapshot.json'),true,512,JSON_THROW_ON_ERROR)),'stale'=>false,'snapshot'=>true];
    $cache=$storage.'/catalogue.json';
    $age=is_file($cache)?time()-filemtime($cache):PHP_INT_MAX;
    if (!$fresh && $age<300) {
        try { return ['quests'=>normaliseCatalogue(json_decode(file_get_contents($cache),true,512,JSON_THROW_ON_ERROR)),'stale'=>false,'snapshot'=>false]; } catch (Throwable $e) { /* Retry source. */ }
    }
    try {
        $data=($loader ?? 'fetchCatalogue')(getenv('PP_CATALOGUE_URL') ?: 'https://puzzlepath.com.au/wp-json/puzzlepath/v1/hunts');
        $quests=normaliseCatalogue($data);
        $temp=tempnam($storage,'catalogue-');
        if ($temp!==false) { file_put_contents($temp,json_encode($data),LOCK_EX); rename($temp,$cache); }
        return ['quests'=>$quests,'stale'=>false,'snapshot'=>false];
    } catch (Throwable $e) {
        if ($age<86400) {
            try { return ['quests'=>normaliseCatalogue(json_decode(file_get_contents($cache),true,512,JSON_THROW_ON_ERROR)),'stale'=>true,'snapshot'=>false]; } catch (Throwable $ignored) {}
        }
        return ['quests'=>[],'stale'=>true,'snapshot'=>false];
    }
}
function quote(int $cents, array $campaign): int { return intdiv($cents*(100-$campaign['percent'])+50,100); }
interface BookingProvider { public function destination(array $quest, array $campaign): string; }
final class PuzzlePathProvider implements BookingProvider {
    public function __construct(private bool $promotionVerified=false) {}
    public function destination(array $quest,array $campaign): string {
        if ($quest['id']<1) throw new RuntimeException('Please choose an available quest.');
        if ($campaign['percent'] && !$this->promotionVerified) throw new RuntimeException('We cannot confirm your promotional price yet. Please try again later; you have not been sent to a full-price checkout.');
        $params=['event_id'=>$quest['id'],'utm_source'=>$campaign['source'],'utm_medium'=>$campaign['source']==='qr'?'print':'referral','utm_campaign'=>$campaign['id']];
        // Existing js/discount-link.js contract. An empty discount clears a previously saved voucher.
        $params['discount']=$campaign['percent'] ? $campaign['coupon'] : '';
        return 'https://puzzlepath.com.au/book-today/?'.http_build_query($params);
    }
}
final class RezdyProvider implements BookingProvider {
    public function destination(array $quest,array $campaign): string { throw new RuntimeException('This booking partner is not connected yet. Please choose another invitation.'); }
}
final class ExternalReferralProvider implements BookingProvider {
    public function destination(array $quest,array $campaign): string { return (new PuzzlePathProvider(false))->destination($quest,$campaign); }
}
function eventLog(string $storage,string $event,array $campaign,?int $quest=null): void {
    if (getenv('PP_ANALYTICS')!=='1' || !empty($_SERVER['HTTP_SEC_GPC']) || ($_SERVER['HTTP_DNT']??'')==='1') return;
    if (!in_array($event,['landing_view','quest_selection','checkout_handoff'],true)) return;
    // Aggregate events only: no IP, user agent, customer data, full URL or persistent visitor ID.
    $row=['event'=>$event,'campaign'=>$campaign['id'],'source'=>$campaign['source'],'partner'=>$campaign['partner'],'quest'=>$quest,'at'=>gmdate('c')];
    file_put_contents($storage.'/events-'.gmdate('Y-m-d').'.jsonl',json_encode($row)."\n",FILE_APPEND|LOCK_EX);
}
