<?php
declare(strict_types=1);
// Trusted configuration: query parameters never set a discount or partner identity.
return [
 'dl-brochure' => ['id'=>'brochure_dl_2026','name'=>'Your little invitation','source'=>'qr','partner'=>null,'policy'=>'brochure25','percent'=>25,'active'=>true,'starts'=>null,'expires'=>null,'provider'=>'puzzlepath','coupon'=>'BROCHURE25'],
 'facebook-october' => ['id'=>'facebook_october_2026','name'=>'Find your next adventure','source'=>'facebook','partner'=>null,'policy'=>null,'percent'=>0,'active'=>true,'starts'=>null,'expires'=>null,'provider'=>'puzzlepath'],
 'partner-goldcoast' => ['id'=>'partner_goldcoast','name'=>'Discover together','source'=>'partner','partner'=>'goldcoast','policy'=>null,'percent'=>0,'active'=>true,'starts'=>null,'expires'=>null,'provider'=>'puzzlepath'],
 'rezdy-referral' => ['id'=>'rezdy_referral','name'=>'An invitation to explore','source'=>'rezdy','partner'=>'rezdy-preview','policy'=>null,'percent'=>0,'active'=>false,'starts'=>null,'expires'=>null,'provider'=>'rezdy'],
];
