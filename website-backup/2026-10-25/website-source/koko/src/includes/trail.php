<?php
// Server-rendered fallback: alternating prints along a sinusoidal journey.
function trail(string $mode, int $height, int $width): void { ?>
<svg class="trail trail-<?= $mode ?>" viewBox="0 0 <?= $width ?> <?= $height ?>" preserveAspectRatio="none" aria-hidden="true">
<?php for ($y=12,$i=0; $y<$height; $y+=34,$i++) {
 $amplitude=$mode==='mobile'?130:350; $period=$mode==='mobile'?480:720;
 $x=$width/2+$amplitude*sin($y/$period*2*M_PI)+($i%2?12:-12);
 $dx=$amplitude*2*M_PI/$period*cos($y/$period*2*M_PI);
 $angle=180-rad2deg(atan($dx))+($i%2?7:-7);
 echo '<use href="assets/svg/footprints.svg#paw" x="-12.5" y="-16.25" width="25" height="32.5" opacity="'.(0.40+($i%4)*.08).'" transform="translate('.round($x,2).' '.$y.') rotate('.round($angle,2).')"/>';
} ?>
</svg>
<?php }
