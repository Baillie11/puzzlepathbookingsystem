<?php
defined('ABSPATH') || exit;
add_action('admin_init',function(){global $wpdb;$wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pp_repeat_hunter_offers (email_hash char(64) NOT NULL PRIMARY KEY, source_booking_id mediumint(9) NOT NULL, first_login_at datetime NOT NULL, expires_at datetime NOT NULL, coupon_code varchar(50) NOT NULL, UNIQUE KEY coupon_code (coupon_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");});
function pp_rh_is_code($code){return is_string($code)&&strpos($code,'RH-')===0;}
function pp_rh_offer($code){
    if(!is_string($code)||!preg_match('/^RH-[a-f0-9]{32}$/D',$code))return null;
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT o.*,b.customer_name,b.customer_email,b.payment_status,p.quest_status FROM {$wpdb->prefix}pp_repeat_hunter_offers o JOIN {$wpdb->prefix}pp_bookings b ON b.id=o.source_booking_id JOIN {$wpdb->prefix}pp_booking_progress p ON p.booking_id=b.id JOIN {$wpdb->prefix}pp_coupons c ON BINARY c.code=BINARY o.coupon_code AND c.discount_percent=25 AND (c.max_uses=0 OR c.times_used<c.max_uses) AND (c.expires_at IS NULL OR c.expires_at>NOW()) WHERE o.coupon_code=%s",$code));
}
function pp_rh_validate($offer,$email=null,$now=null){
    if(!$offer||$offer->payment_status!=='paid'||$offer->quest_status!=='completed')return new WP_Error('repeat_hunter_invalid','Choose a quest from your badge collection to use the Repeat Hunter Discount.',['status'=>403]);
    if(strtotime($offer->expires_at.' UTC')<=($now??time()))return new WP_Error('repeat_hunter_expired','Your 30-day Repeat Hunter offer has ended. Refresh the booking page to see the current price.',['status'=>400]);
    if($email!==null && !hash_equals($offer->email_hash,hash('sha256',strtolower(trim($email)))))return new WP_Error('repeat_hunter_email','Use the same email as your completed quest to claim the Repeat Hunter Discount.',['status'=>400]);
    return true;
}
function pp_rh_cents($price,$quantity){$subtotal=(int)round((float)$price*100)*(int)$quantity;$total=(int)round($subtotal*0.75);return ['subtotal_cents'=>$subtotal,'discount_cents'=>$subtotal-$total,'total_cents'=>$total];}
function pp_rh_total($price,$quantity){return pp_rh_cents($price,$quantity)['total_cents']/100;}
function pp_rh_event($id,$quantity){
    global $wpdb;$event=$wpdb->get_row($wpdb->prepare("SELECT price,seats,display_on_site FROM {$wpdb->prefix}pp_events WHERE id=%d",$id));
    if(!$event||!$event->display_on_site||$quantity<1||$quantity>(int)$event->seats)return new WP_Error('invalid_event','Choose an available quest and number of groups.',['status'=>400]);
    return $event;
}
function pp_rh_label($code){return pp_rh_is_code($code)?'Repeat Hunter Discount':$code;}
add_action('template_redirect',function(){
    if(isset($_GET['repeat_hunter'])){
        $code=sanitize_text_field(wp_unslash($_GET['repeat_hunter']));$offer=pp_rh_offer($code);
        $valid=pp_rh_validate($offer);
        if(!is_wp_error($valid))setcookie('PPRepeatHunter',$code,['expires'=>strtotime($offer->expires_at.' UTC'),'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
        else setcookie('PPRepeatHunter','',['expires'=>time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
        nocache_headers();header('Referrer-Policy: no-referrer');
        $target=add_query_arg('event_id',absint($_GET['event_id']??0),home_url('/book-today/'));
        // Keep activation on this checkout URL; the cookie alone must never activate later bookings.
        if(!is_wp_error($valid))$target=add_query_arg('repeat_offer',$code,$target);
        if(is_wp_error($valid)&&$code!=='none')$target=add_query_arg('repeat_offer','expired',$target);
        wp_safe_redirect($target,303);exit;
    }
    if(!empty($_COOKIE['PPRepeatHunter']))nocache_headers();
},1);
function pp_rh_coupon_guard(){
    $code=sanitize_text_field(wp_unslash($_POST['coupon_code']??''));if(!pp_rh_is_code($code))return;
    check_ajax_referer('puzzlepath_coupon_nonce','nonce');
    if(!hash_equals($code,(string)($_COOKIE['PPRepeatHunter']??'')))wp_send_json_error(['message'=>'Open your badge collection and choose a quest to activate this offer.']);
    $valid=pp_rh_validate(pp_rh_offer($code));
    if(is_wp_error($valid))wp_send_json_error(['message'=>$valid->get_error_message()]);
    wp_send_json_success(['discount_percent'=>25,'code'=>$code,'discount_label'=>'Repeat Hunter Discount']);
}
add_action('wp_ajax_apply_coupon','pp_rh_coupon_guard',0);add_action('wp_ajax_nopriv_apply_coupon','pp_rh_coupon_guard',0);
add_filter('rest_request_before_callbacks',function($response,$handler,$request){
    if($request->get_route()!=='/puzzlepath/v1/payment/create-intent')return $response;
    $params=$request->get_json_params();$code=$params['coupon_code']??'';
    if(!pp_rh_is_code($code) && $code!=='')return $response;
    $cookie=(string)($_COOKIE['PPRepeatHunter']??'');
    if(!pp_rh_is_code($code))return $response;
    if(!hash_equals($code,$cookie))return new WP_Error('repeat_hunter_link','Choose a quest from your badge collection to activate this offer.',['status'=>403]);
    $valid=pp_rh_validate(pp_rh_offer($code),(string)($params['email']??''));
    if(is_wp_error($valid))return $valid;
    $event=pp_rh_event((int)($params['event_id']??0),(int)($params['tickets']??0));
    return is_wp_error($event)?$event:$response;
},10,3);
add_action('rest_api_init',function(){register_rest_route('puzzlepath/v1','/repeat-hunter-quote',['methods'=>'POST','permission_callback'=>'__return_true','callback'=>function($request){
    $params=$request->get_json_params();$code=(string)($params['coupon_code']??'');
    if(!pp_rh_is_code($code)||!hash_equals($code,(string)($_COOKIE['PPRepeatHunter']??'')))return new WP_Error('repeat_hunter_link','Choose a quest from your Dashboard or Leaderboards to activate this offer.',['status'=>403]);
    $offer=pp_rh_offer($code);
    $email=trim((string)($params['email']??''));$valid=pp_rh_validate($offer,$email!==''?$email:null);
    if(is_wp_error($valid))return $valid;
    $quantity=(int)($params['tickets']??0);$event=pp_rh_event((int)($params['event_id']??0),$quantity);
    if(is_wp_error($event))return $event;
    return rest_ensure_response(array_merge(pp_rh_cents($event->price,$quantity),['label'=>'Repeat Hunter Discount','discount_percent'=>25,'expires_at'=>$offer->expires_at]));
}]);});
add_action('wp_enqueue_scripts',function(){
    if(!wp_script_is('puzzlepath-booking-form','enqueued'))return;
    $code=sanitize_text_field(wp_unslash($_GET['repeat_offer']??''));
    if(!pp_rh_is_code($code)||!hash_equals($code,(string)($_COOKIE['PPRepeatHunter']??'')))return;
    nocache_headers();header('Referrer-Policy: no-referrer');
    $offer=pp_rh_offer($code);
    if(!$offer||is_wp_error(pp_rh_validate($offer)))return;
    $config=wp_json_encode(['code'=>$offer->coupon_code,'customer'=>['name'=>(string)$offer->customer_name,'email'=>(string)$offer->customer_email],'expires'=>wp_date('j M Y, g:i a',strtotime($offer->expires_at.' UTC')).' AEST']);
    $script= <<<'JS'
jQuery(function($){
    const offer=window.ppRepeatHunter;
    if(!offer||!$('#booking-form').length)return;
    // Fill empty fields only; preserve browser autofill and anything the customer has typed.
    for(const [selector,value] of [['#name',offer.customer?.name],['#email',offer.customer?.email]]){
        const field=$(selector);if(value && !String(field.val()||'').trim())field.val(value);
    }
    $('#name').attr('autocomplete','name');$('#email').attr('autocomplete','email');
    let applying=false,verified=false,quoteSequence=0;
    const banner=$('<div id="repeat-hunter-banner" role="status" style="padding:20px;margin:16px 0;background:#fff1a6;border:2px solid #6745bd;border-radius:16px;color:#25315e;"></div>');
    banner.append($('<strong>').text('Your next badge comes with 25% off!'));
    banner.append($('<p>').text('Repeat Hunter Discount — automatically applied. Book by '+offer.expires+'. Use the same email as your completed quest.'));
    const ordinaryBooking=new URL(window.location.href);ordinaryBooking.searchParams.delete('repeat_offer');ordinaryBooking.searchParams.set('repeat_hunter','none');
    banner.append($('<a style="font-size:12px;text-decoration:underline">').attr('href',ordinaryBooking.toString()).text('Using a different email or coupon? Book without this offer.'));
    $('#booking-form').prepend(banner);
    // Event choices name the quest; the verified price card shows what will be charged.
    $('#event_id option').each(function(){this.textContent=this.textContent.replace(/ - \$[0-9,.]+.*$/,'');});
    $('#carload-pricing').text('Up to 5 people per quest. Select 1 for one group. Your 25% saving is included in the price below.');
    const priceCard=$('<div id="repeat-hunter-price" aria-live="polite" style="padding:22px;margin:18px 0;background:#ddf3e5;border:3px solid #08796e;border-radius:18px;color:#25315e;box-shadow:3px 4px 0 #08796e"><strong style="font-size:16px">YOUR REPEAT HUNTER PRICE</strong><p class="price-checking">Checking your discounted total…</p></div>');
    $('#event_id').closest('.form-group').after(priceCard);
    $('#total').parent().css({'font-size':'24px','color':'#086f66'});

    $('#coupon_code').attr('type','hidden');$('label[for="coupon_code"],#apply-coupon').hide();
    const message=$('<p id="repeat-hunter-check" style="margin-bottom:0"></p>');banner.append(message);
    const block=()=>{$('#submit-payment').prop('disabled',applying||!verified);};
    function quote(){
        if(!$('#event_id').val())return;
        const sequence=++quoteSequence;verified=false;applying=true;block();
        message.text('Checking your Repeat Hunter price…');priceCard.attr('aria-busy','true').find('.price-amount').text('Checking…');
        fetch(puzzlepath_data.rest_url+'repeat-hunter-quote',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({coupon_code:offer.code,event_id:$('#event_id').val(),tickets:$('#tickets').val(),email:$('#email').val()||''})})
        .then(async response=>{const data=await response.json();if(!response.ok)throw Error(data.message||'Unable to check your discount.');return data;})
        .then(data=>{if(sequence!==quoteSequence)return;$('#subtotal').text((data.subtotal_cents/100).toFixed(2));$('#discount-line').html('Repeat Hunter Discount (25%): -$<span id="discount">'+(data.discount_cents/100).toFixed(2)+'</span>').show();$('#total').text((data.total_cents/100).toFixed(2));window.puzzlepathCurrentTotal=data.total_cents/100;
            priceCard.empty().attr('aria-busy','false').append($('<strong style="font-size:14px;letter-spacing:.05em">').text('YOU PAY — 25% OFF INCLUDED'))
              .append($('<div class="price-amount" style="font-size:42px;line-height:1.3;font-weight:900;color:#086f66;margin:8px 0">').text('$'+(data.total_cents/100).toFixed(2)+' AUD'))
              .append($('<div>').append($('<span>').text('Regular total '),$('<s>').text('$'+(data.subtotal_cents/100).toFixed(2)+' AUD')))
              .append($('<strong style="display:block;margin-top:8px;color:#6745bd">').text('You save $'+(data.discount_cents/100).toFixed(2)+' with your Repeat Hunter Discount!'));
            const totalElement=document.getElementById('total');if(totalElement?.parentNode?.firstChild?.nodeType===3)totalElement.parentNode.firstChild.textContent='You pay: $';
            verified=true;message.text('25% off is ready — no code needed.');$('#coupon-message').text('Repeat Hunter Discount applied.');})
        .catch(error=>{if(sequence===quoteSequence){verified=false;message.text(error.message);priceCard.attr('aria-busy','false').empty().append($('<strong>').text('Your price needs checking'),$('<p>').text(error.message));}})
        .finally(()=>{if(sequence===quoteSequence){applying=false;block();}});
    }
    function apply(){verified=false;applying=true;block();$('#coupon_code').val(offer.code);$('#apply-coupon').trigger('click');}
    $(document).ajaxSuccess(function(event,xhr,settings,data){
        if(typeof settings.data!=='string'||!settings.data.includes('action=apply_coupon')||$('#coupon_code').val()!==offer.code)return;
        if(data&&data.success)quote();else{applying=false;verified=false;message.text(data?.data?.message||'Please reopen your badge collection to check this offer.');block();}
    });
    $(document).ajaxError(function(event,xhr,settings){if(typeof settings.data==='string'&&settings.data.includes('action=apply_coupon')){applying=false;verified=false;message.text('We could not confirm your discount. Refresh to try again.');block();}});
    $('#event_id').on('change.ppRepeat',apply);$('#tickets').on('input.ppRepeat change.ppRepeat',quote);$('#email').on('input.ppRepeat change.ppRepeat',quote);
    const guard=function(event){if(applying||!verified){event.preventDefault();event.stopImmediatePropagation();message.text('Please wait until your Repeat Hunter price is confirmed.');}};
    document.getElementById('booking-form').addEventListener('submit',guard,true);document.getElementById('submit-payment')?.addEventListener('click',guard,true);
    if($('#event_id').val())$('#event_id').trigger('change');
});
JS;
    wp_add_inline_script('puzzlepath-booking-form','window.ppRepeatHunter='.$config.';'.$script,'after');
},30);
