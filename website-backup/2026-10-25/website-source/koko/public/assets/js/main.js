(() => {
 const map=document.querySelector('.map'); if(!map || !('ResizeObserver' in window)) return;
 const reduced=matchMedia('(prefers-reduced-motion: reduce)');
 let prints=[],mapTop=0,revealed=0,pending=false;
 function reveal(){pending=false;revealed=Math.max(revealed,scrollY+innerHeight*1.12-mapTop);for(const p of prints) if(reduced.matches||p.y<=revealed)p.node.classList.add('revealed');}
 function redraw(){
  const mobile=matchMedia('(max-width:700px)').matches,svg=map.querySelector(mobile?'.trail-mobile':'.trail-desktop');
  const width=map.clientWidth,height=map.clientHeight,rect=map.getBoundingClientRect();mapTop=rect.top+scrollY;
  svg.setAttribute('viewBox',`0 0 ${width} ${height}`);
  const fragment=document.createDocumentFragment(),next=[],amplitude=width*(mobile?.36:.29),period=mobile?530:780;
  for(let y=15,i=0;y<height;y+=32,i++){
   const x=width/2+amplitude*Math.sin(y/period*2*Math.PI)+(i%2?11:-11);
   const angle=180-Math.atan(amplitude*2*Math.PI/period*Math.cos(y/period*2*Math.PI))*180/Math.PI+(i%2?7:-7);
   const node=document.createElementNS('http://www.w3.org/2000/svg','use');
   Object.entries({href:'assets/svg/footprints.svg#paw',x:-12.5,y:-16.25,width:25,height:32.5,opacity:.4+(i%4)*.08,transform:`translate(${x} ${y}) rotate(${angle})`,class:'journey-paw'}).forEach(([k,v])=>node.setAttribute(k,v));
   fragment.append(node);next.push({node,y});
  }
  svg.replaceChildren(fragment);prints=next;reveal();
 }
 new ResizeObserver(redraw).observe(map);
 addEventListener('scroll',()=>{if(!pending){pending=true;requestAnimationFrame(reveal);}},{passive:true});
 reduced.addEventListener('change',reveal);
})();
