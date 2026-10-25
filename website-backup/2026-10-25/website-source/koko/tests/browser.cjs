const { chromium }=require('playwright');
const AxeBuilder=require('@axe-core/playwright').default;
const assert=require('node:assert/strict'),fs=require('node:fs');
const base='http://127.0.0.1:4180/';
(async()=>{
 const browser=await chromium.launch({executablePath:'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',headless:true});
 fs.mkdirSync('koko/reports',{recursive:true});const results=[];
 try{
  for(const width of [320,375,390,430,768,1024,1440]){
   const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),errors=[];
   page.on('pageerror',e=>errors.push(e.message));
   await page.goto(base+'?campaign=dl-brochure',{waitUntil:'networkidle'});
   assert.equal(await page.locator('.quest').count(),11);
   assert.equal(await page.locator('.quest img').count(),0);
   const measure=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth,overlaps:[...document.querySelectorAll('.island')].filter(e=>{const r=e.getBoundingClientRect(),c=e.querySelector('.island-content').getBoundingClientRect();return c.bottom>r.bottom+2||c.top<r.top-2;}).map(e=>e.querySelector('h3').textContent)}));
   assert.equal(measure.overflow,false,`horizontal overflow at ${width}`);
   assert.deepEqual(measure.overlaps,[],`content outside island at ${width}`);
   const axe=await new AxeBuilder({page}).analyze();
   fs.writeFileSync(`koko/reports/axe-${width}.json`,JSON.stringify(axe.violations,null,2));
   assert.deepEqual(axe.violations.map(v=>v.id),[],`accessibility ${width}`);
   await page.screenshot({path:`koko/reports/preview-${width}.png`,fullPage:true});
   assert.deepEqual(errors,[]);
   results.push({width,quests:11,overflow:false,axeViolations:0,errors});await context.close();
  }
  const page=await browser.newPage();await page.goto(base+'?campaign=dl-brochure');
  const links=await page.locator('.quest .button').evaluateAll(es=>es.map(e=>e.href));
  for(const link of links){await page.goto(link);assert.match(await page.locator('.preview-total').innerText(),/14.96/);}
  await page.goto(base+'?action=book&quest=23&campaign=dl-brochure&percent=100&price=0');assert.match(await page.locator('.preview-total').innerText(),/14.96/);
  await page.goto(base+'?action=book&quest=23');assert.match(await page.locator('.preview-total').innerText(),/19.95/);
  assert.equal((await page.goto(base+'?action=book&quest=999')).status(),503);
  assert.equal((await page.goto(base+'?action=book&quest=23&campaign=invalid')).status(),503);
  assert.equal((await page.goto(base+'?action=webhook')).status(),501);
  await page.goto(base+'?campaign=dl-brochure');await page.locator('.journey').scrollIntoViewIfNeeded();
  await page.waitForTimeout(400);const first=await page.locator('.journey-paw.revealed').count();
  await page.locator('.final-island').scrollIntoViewIfNeeded();await page.waitForTimeout(400);const last=await page.locator('.journey-paw.revealed').count();assert.ok(last>first);
  await page.evaluate(()=>scrollTo(0,0));await page.waitForTimeout(100);assert.equal(await page.locator('.journey-paw.revealed').count(),last);
  await page.emulateMedia({reducedMotion:'reduce'});assert.equal(await page.locator('.journey-paw').evaluateAll(es=>es.every(e=>getComputedStyle(e).visibility==='visible')),true);
  const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width:390,height:844}});const p=await nojs.newPage();await p.goto(base+'?campaign=dl-brochure');assert.equal(await p.locator('.quest').count(),11);assert.ok(await p.locator('.trail-mobile use').count()>0);await p.locator('.quest .button').first().click();assert.match(await p.locator('.preview-total').innerText(),/14.96/);await nojs.close();
  fs.writeFileSync('koko/reports/browser-results.json',JSON.stringify({viewports:results,bookingLinks:11,tampering:true,noJavaScript:true,reducedMotion:true,monotonicTrail:true},null,2));console.log('PASS browser checks',results);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1});
