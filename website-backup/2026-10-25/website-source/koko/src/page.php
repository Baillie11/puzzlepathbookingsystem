<?php require __DIR__.'/includes/island.php'; require __DIR__.'/includes/trail.php'; ?>
<!doctype html>
<html lang="en-AU">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Follow Koko through a little world of self-guided Gold Coast adventures. Choose your self-guided Puzzle Path adventure."><title>Koko’s Island Adventure · Puzzle Path</title><link rel="stylesheet" href="assets/css/style.css"><link rel="stylesheet" href="assets/css/production.css"><script defer src="assets/js/main.js"></script></head>
<body>
<a class="skip-link" href="#adventures">Skip to adventures</a>
<header class="header">
 <a class="header-brand" href="#" aria-label="Puzzle Path home"><div class="brand-art"><strong class="brand-wordmark">Puzzle<br>Path</strong></div><span>Out here,<br><em>curiosity leads the way.</em></span></a>
 <svg class="header-paws" viewBox="0 0 200 80" aria-hidden="true"><use href="assets/svg/footprints.svg#paw" width="20" height="26" transform="translate(15 48) rotate(65)"/><use href="assets/svg/footprints.svg#paw" width="20" height="26" transform="translate(52 35) rotate(80)"/><use href="assets/svg/footprints.svg#paw" width="20" height="26" transform="translate(90 48) rotate(98)"/><use href="assets/svg/footprints.svg#paw" width="20" height="26" transform="translate(130 36) rotate(75)"/><use href="assets/svg/footprints.svg#paw" width="20" height="26" transform="translate(170 28) rotate(55)"/></svg>
 <nav aria-label="Main navigation"><a class="trail-sign" href="#adventures">Find an adventure <span aria-hidden="true">→</span></a><a class="trail-sign" href="#how-it-works">How to play <span aria-hidden="true">↗</span></a><a class="nav-offer" href="#pricing"><small>A little treat for your team</small><strong><?= $campaign['percent'] ? '25% off' : 'Let’s explore' ?></strong><span>Let’s explore! <span aria-hidden="true">↗</span></span></a></nav>
</header>
<main>
<section class="hero" aria-labelledby="hero-heading">
 <div class="hero-copy"><span class="eyebrow">A little curiosity. A whole lot of discovery.</span><h1 id="hero-heading">Your next<br>adventure<br><em>starts here.</em></h1><p>Discover South East Queensland, one clue at a time. Follow Koko and find your next self-guided Puzzle Path adventure.</p><a class="button hero-button" href="#adventures">Explore Adventures <span aria-hidden="true">↓</span></a><div class="hero-detail">Your people. Your pace. Your next great story.</div></div>
 <div class="hero-koko"><span class="koko-greeting">“Come on,<br>let’s get curious!”</span><img src="assets/images/koko-480.jpg" srcset="assets/images/koko-240.jpg 240w, assets/images/koko-480.jpg 480w, assets/images/koko-720.jpg 720w" sizes="(max-width: 700px) 240px, 360px" width="1024" height="1536" alt="Koko, our koala guide, holding her map and magnifying glass"><span class="koko-caption">Meet Koko, your adventure guide</span></div>
</section>
<section class="journey" id="adventures" aria-labelledby="journey-heading">
 <div class="journey-intro"><span class="eyebrow">Follow the little muddy footprints</span><h2 id="journey-heading">Where will your curiosity take you?</h2><p>Pick an island. Find your adventure.</p><?php if ($mode === 'local'): ?><small class="preview-label">Local preview · <?= count($quests) ?> quests captured on 22 October 2026 · checkout is simulated.</small><?php endif ?>
 <?php if ($message): ?><p class="status-message" role="alert"><?= esc($message) ?></p><?php endif ?>
 <?php if ($catalogue['stale']): ?><p class="status-message">We’re refreshing quest availability. Please try booking again shortly.</p><?php endif ?></div>
 <div class="map">
 <?php trail('desktop',1900,1200); trail('mobile',3900,390); ?>
 <?php
 $items=[];
 foreach ($quests as $i=>$quest) {
   $items[]=['item'=>$quest,'type'=>'quest','shape'=>[1,3,4,6,7][$i%5]];
   if ($i===0) $items[]=['item'=>['title'=>'One price. Up to five adventurers!','label'=>$campaign['percent']?'Your little invitation · 25% off':'Your team. Your adventure.','description'=>'Up to five participants per group. Share the discoveries at your own pace.'],'type'=>'pricing','shape'=>2,'anchor'=>'pricing'];
   if ($i===2) $items[]=['item'=>['title'=>'How does Puzzle Path work?','label'=>'As easy as one, two, clue','description'=>'Choose your adventure, follow the clues and explore at your own pace!'],'type'=>'information','shape'=>5,'anchor'=>'how-it-works'];
 }
 if (!$quests) echo '<p class="status-message">Our adventures are temporarily unavailable. Please come back shortly.</p>';
 $offset=0; $row=0;
 while ($offset<count($items)) {
   $count=$row%2===0?2:3;
   echo '<div class="island-group '.($count===2?'group-two':'group-three').'">';
   foreach(array_slice($items,$offset,$count) as $entry) {
     if(isset($entry['anchor'])) echo '<div id="'.esc($entry['anchor']).'">';
     island($entry['item'],$entry['shape'],$entry['type']);
     if(isset($entry['anchor'])) echo '</div>';
   }
   echo '</div>'; $offset+=$count; $row++;
   if($row===2): ?>
 <div class="koko-stop"><img src="assets/images/koko-480.jpg" srcset="assets/images/koko-240.jpg 240w, assets/images/koko-480.jpg 480w, assets/images/koko-720.jpg 720w" sizes="(max-width: 700px) 100px, 130px" width="1024" height="1536" alt="Koko with her adventure map" loading="lazy"><div><span class="eyebrow">A little tip from Koko</span><p>“Bring your curiosity, your favourite people and your sense of adventure!”</p></div></div>
 <?php endif;
 }
 ?>
 <div class="island-group group-one"><?php island(['title'=>'Adventure on your terms','label'=>'Make a day of it','description'=>'No tour guide, no fixed schedule. Just you, your team and an adventure waiting to happen!'],8,'information'); ?></div>
 </div>
 <section class="final-island" aria-labelledby="final-heading"><svg viewBox="0 0 500 410" aria-hidden="true"><use href="assets/svg/islands.svg#island-9"/></svg><div class="final-copy"><span class="eyebrow">The best discoveries are shared</span><h2 id="final-heading">Ready for your<br>next adventure?</h2><p>Grab your team, follow the clues and make some unforgettable memories!</p><a href="#adventures" class="button">Let’s Go Adventuring <span aria-hidden="true">↗</span></a></div><img src="assets/images/koko-480.jpg" srcset="assets/images/koko-240.jpg 240w, assets/images/koko-480.jpg 480w, assets/images/koko-720.jpg 720w" sizes="(max-width: 700px) 150px, 210px" width="1024" height="1536" alt="Koko is ready for the next adventure" loading="lazy"></section>
</section>
<div id="booking-note" class="booking-note" tabindex="-1"><?php if ($mode==='local'): ?><strong>Local review · no real bookings or payments.</strong>The brochure offer is simulated until the existing booking system’s voucher is verified.<?php else: ?>Prices and availability are confirmed by Puzzle Path checkout.<?php endif ?></div>
</main>
<footer><span>PUZZLE PATH <span aria-hidden="true">/</span> Small clues. Great discoveries.</span><a href="#">Back to the beginning ↑</a><small><a href="privacy.html">Privacy and campaign attribution</a></small></footer>

</body></html>


