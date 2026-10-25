const {chromium}=require('playwright');
const fs=require('fs');
const path=require('path');
const root=path.resolve('booking-refresh');
let css=fs.readFileSync(path.join(root,'puzzlepath-booking-look/assets/booking-look.css'),'utf8');
for(const [file,mime] of [['koko.jpg','image/jpeg'],['footprints.svg','image/svg+xml']]) css=css.replaceAll(`url('${file}')`,`url('data:${mime};base64,${fs.readFileSync(path.join(root,'puzzlepath-booking-look/assets',file)).toString('base64')}')`);
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 console.log('Browser launched'); const page=await browser.newPage(); console.log('Page created');
 await page.route('**/*',r=>r.request().method()==='GET'?r.continue():r.abort());
 await page.goto('https://puzzlepath.com.au/book-today/?event_id=23',{waitUntil:'domcontentloaded',timeout:60000});
 console.log('Page loaded'); await page.waitForTimeout(2500); const before=await page.locator('#puzzlepath-booking-form').innerHTML();
 await page.evaluate(()=>document.body.classList.add('pp-booking-look'));
 await page.addStyleTag({content:css});
 if(before!==await page.locator('#puzzlepath-booking-form').innerHTML()) throw Error('Form markup changed');
 const results=[];
 for(const width of [1440,768,390,320]){
  await page.setViewportSize({width,height:1000});
  console.log('Screenshot '+width); await page.screenshot({timeout:15000,path:path.join(root,'reports',`booking-${width}.png`),fullPage:true});
  const state=await page.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,form:!!document.querySelector('#booking-form'),stripe:!!document.querySelector('#card-element iframe'),hiddenSuccess:getComputedStyle(document.querySelector('#payment-success')).display, font:getComputedStyle(document.querySelector('#name')).fontFamily}));
  if(state.scroll>width+1) throw Error('Overflow '+JSON.stringify(state));
  results.push(state);
 }
 await page.selectOption('#event_id','23');
 await page.fill('#tickets','2');
 await page.locator('#tickets').dispatchEvent('change');
 await page.waitForTimeout(400);
 results.push({twoCarloadTotal:await page.locator('#total').innerText()});
 await page.locator('#name').focus();
 results.push({focusOutline:await page.locator('#name').evaluate(e=>getComputedStyle(e).outlineStyle)});
 fs.writeFileSync(path.join(root,'reports/checks.json'),JSON.stringify(results,null,2));
 // Static review copy: preserve rendered content, but disable real checkout calls.
 let html=await page.content();
 html=html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'').replace(/<iframe\b[^>]*>[\s\S]*?<\/iframe>/gi,'');
 html=html.replace('</body>','<script>document.querySelector("#booking-form").addEventListener("submit",e=>{e.preventDefault();alert("Design preview only. No booking is submitted.")});document.querySelector("#apply-coupon").addEventListener("click",()=>alert("Design preview only. Coupons remain handled by the existing live booking system."));</script></body>');
 html=html.replaceAll('Number of Carloads:', 'Number of Groups (up to 5 people per group):').replaceAll('Select 1 for one carload.', 'Select 1 for one group.');
 html=html.replace('<body ','<body data-preview="Design preview only — checkout inactive" ');
 html=html.replace('<div id="card-element"','<div aria-label="Card field placeholder in design preview" id="card-element"');
 html=html.replace('<!-- Stripe Elements will create form elements here -->','<span style="color:#52635a;font-size:14px">Card number &nbsp; &nbsp; MM / YY &nbsp; CVC</span>');
 fs.writeFileSync(path.join(root,'preview.html'),html);
 console.log(JSON.stringify(results));
 await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});

