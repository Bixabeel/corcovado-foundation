(function(){
const header = document.getElementById('siteHeader');
const nav = document.getElementById('siteNav');
const toggle = document.querySelector('.nav-toggle');
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const revealEls = document.querySelectorAll('.reveal');
const statEls = document.querySelectorAll('[data-count]');
function formatCount(el, value) {
  const prefix = el.dataset.prefix || '';
  const suffix = el.dataset.suffix || '';
  const format = el.dataset.format || 'integer';
  const display = format === 'decimal'
    ? value.toLocaleString(undefined, { maximumFractionDigits: 1 })
    : Math.round(value).toLocaleString(undefined, { maximumFractionDigits: 0 });
  el.textContent = prefix + display + suffix;
}
function setFinalCount(el) {
  const target = parseFloat(el.dataset.count || '0');
  formatCount(el, target);
}
function animateCount(el) {
  const target = parseFloat(el.dataset.count || '0');
  const start = performance.now();
  const duration = 1100;
  function tick(now) {
    const p = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - p, 3);
    formatCount(el, target * eased);
    if (p < 1) requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
}
function onScroll(){ if (window.scrollY > 10) header.classList.add('scrolled'); else header.classList.remove('scrolled'); }
function setNav(open){ if(!nav || !toggle) return; nav.classList.toggle('open', open); toggle.setAttribute('aria-expanded', String(open)); }
toggle && toggle.addEventListener('click', () => setNav(!nav.classList.contains('open')));
document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape') setNav(false); });
document.addEventListener('click', (e)=>{ if(!nav || !toggle) return; if(!nav.contains(e.target) && !toggle.contains(e.target)) setNav(false); });
window.addEventListener('scroll', onScroll, {passive:true});
onScroll();
if (reduce) {
  revealEls.forEach(el => el.classList.add('is-visible'));
  statEls.forEach(setFinalCount);
} else {
  const io = new IntersectionObserver((entries, obs)=>{
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        obs.unobserve(entry.target);
      }
    });
  }, {threshold:0.12});
  revealEls.forEach(el => io.observe(el));

  const countIo = new IntersectionObserver((entries, obs)=>{
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      animateCount(entry.target);
      obs.unobserve(entry.target);
    });
  }, {threshold:0.4});
  statEls.forEach(el => countIo.observe(el));
}
})();
