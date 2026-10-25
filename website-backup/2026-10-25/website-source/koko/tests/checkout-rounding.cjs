const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(process.argv[2],'utf8');
const start=source.indexOf('    function calculateAndDisplayTotal()');
const end=source.indexOf('    // When an event is selected',start);
assert.ok(start>=0&&end>start);
for(const [quantity,discount,expected] of [[1,25,1496],[2,25,2993],[3,25,4489],[5,25,7481],[1,0,1995],[2,0,3990]]){
 const output={};const context={basePrice:19.95,ticketCount:quantity,discountPercent:discount,couponApplied:discount>0,window:{},$:id=>({show(){},hide(){},text(value){output[id]=value;},html(){}})};
 vm.runInNewContext(source.slice(start,end)+'calculateAndDisplayTotal();',context);
 assert.equal(output['#total'],(expected/100).toFixed(2));
 assert.equal(context.window.puzzlepathCurrentTotal,expected/100);
 if(discount)assert.equal(Math.round(Number(output['#subtotal'])*100)-Math.round(Number(output['#discount'])*100),expected);
 console.log('PASS',quantity,'groups',discount+'%',output['#total']);
}
