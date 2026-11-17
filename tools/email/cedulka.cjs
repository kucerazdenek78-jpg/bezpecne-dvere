const { chromium } = require('/opt/npm-tools/node_modules/playwright');
(async()=>{const b=await chromium.launch();const p=await b.newPage({viewport:{width:1240,height:1754},deviceScaleFactor:1});
await p.goto('file://'+__dirname+'/cedulka-sousede.html');await p.waitForTimeout(900);
const box=await p.evaluate(()=>['datum','cas'].map(i=>{const r=document.getElementById(i).getBoundingClientRect();return [i,Math.round(r.left),Math.round(r.top),Math.round(r.width),Math.round(r.height)]}));
console.log(JSON.stringify(box),'přetečení dole:',await p.evaluate(()=>{const a=document.querySelector('.ad').getBoundingClientRect(),c=document.querySelector('.pod').getBoundingClientRect();return Math.round(a.top-c.bottom)}));
await p.screenshot({path:__dirname+'/../../userFiles/email/cedulka-sousede.jpg',type:'jpeg',quality:82});await b.close();})();
