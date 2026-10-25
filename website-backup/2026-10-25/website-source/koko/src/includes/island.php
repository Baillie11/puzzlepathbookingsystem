<?php
function island(array $item, int $shape, string $type='quest'): void { global $campaign, $campaignSlug, $quests; ?>
 <article class="island <?= esc($type) ?> shape-<?= $shape ?>">
  <svg class="island-art" viewBox="0 0 500 410" aria-hidden="true"><use href="assets/svg/islands.svg#island-<?= $shape ?>"/></svg>
  <div class="island-content">
   <?php if ($type !== 'quest'): ?><span class="eyebrow"><?= esc($item['label'] ?? 'A little good to know') ?></span><?php endif ?>
   <h3><?= esc($item['title']) ?></h3>
   <?php if ($type === 'pricing' && $quests): $prices=array_column($quests,'price'); $base=min($prices); ?><div class="price"><?php if ($campaign['percent']): ?><s>$<?= number_format($base/100,2) ?></s><?php endif ?> <strong>$<?= number_format(quote($base,$campaign)/100,2) ?></strong><span><?= count(array_unique($prices))>1?'from · ':'' ?>per group · AUD</span></div><?php endif ?>
   <p><?= esc($item['description']) ?></p>
   <?php if ($type === 'quest'): ?><a class="button" href="?<?= esc(http_build_query(['action'=>'book','quest'=>$item['id'],'campaign'=>$campaignSlug])) ?>" aria-label="Book <?= esc($item['title']) ?>">Book Now <span aria-hidden="true">↗</span></a><?php endif ?>
  </div>
 </article>
<?php }
