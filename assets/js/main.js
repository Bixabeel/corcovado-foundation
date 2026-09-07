(function(){
const header = document.getElementById('siteHeader');
const nav = document.getElementById('siteNav');
const toggle = document.querySelector('.nav-toggle');
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const revealEls = document.querySelectorAll('.reveal');
const statEls = document.querySelectorAll('[data-count]');
function onScroll(){ if (window.scrollY > 10) header.classList.add('scrolled'); else header.classList.remove('scrolled'); }
function setNav(open){ nav.classList.toggle('open', open); toggle.setAttribute('aria-expanded', String(open)); }
toggle && toggle.addEventListener('click', () => setNav(!nav.classList.contains('open')));
document.addEventListener('keydown', (e)=>{ if(e.key === 'Escape') setNav(false); });
document.addEventListener('click', (e)=>{ if(!nav.contains(e.target) && !toggle.contains(e.target)) setNav(false); });
window.addEventListener('scroll', onScroll, {passive:true});
onScroll();
if (reduce) {
revealEls.forEach(el => el.classList.add('is-visible'));
return;
}
const io = new IntersectionObserver((entries, obs)=>{
entries.forEach(entry => {
if (entry.isIntersecting) { entry.target.classList.add('is-visible'); obs.unobserve(entry.target); }
});
}, {threshold:0.12});
revealEls.forEach(el => io.observe(el));
const countIo = new IntersectionObserver((entries, obs)=>{
entries.forEach(entry => {
if (!entry.isIntersecting) return;
const el = entry.target;
const target = parseFloat(el.dataset.count || '0');
const start = performance.now();
const duration = 1100;
function tick(now){
const p = Math.min((now - start) / duration, 1);
const v = Math.floor(target * (1 - Math.pow(1 - p, 3)));
el.textContent = Number.isInteger(target) ? v.toLocaleString() : String(v);
if (p < 1) requestAnimationFrame(tick);
}
requestAnimationFrame(tick);
obs.unobserve(el);
});
}, {threshold:0.4});
statEls.forEach(el => countIo.observe(el));
})();

(function(){
const volunteerForm = document.querySelector('[data-volunteer-form]');
if (volunteerForm) {
const copyBtn = volunteerForm.querySelector('[data-copy-volunteer]');
const submitBtn = volunteerForm.querySelector('[data-volunteer-submit]');
const volunteerEmail = 'info@corcovadofoundation.org';
function buildMessage() {
const get = name => volunteerForm.querySelector(`[name="${name}"]`);
const name = get('full_name')?.value?.trim() || 'Not provided';
const email = get('email')?.value?.trim() || 'Not provided';
const phone = get('phone')?.value?.trim() || 'Not provided';
const country = get('country')?.value?.trim() || 'Not provided';
const program = get('program')?.value || 'Not provided';
const dates = get('dates')?.value?.trim() || 'Not provided';
const experience = get('experience')?.value?.trim() || 'Not provided';
const availability = get('availability')?.value || 'Not provided';
const message = get('message')?.value?.trim() || 'No additional message.';
return {
subject: `Volunteer application — ${program}`,
body: [
'Volunteer application from Corcovado Foundation website',
`Full name: ${name}`,
`Email: ${email}`,
`Phone: ${phone}`,
`Country: ${country}`,
`Program of interest: ${program}`,
`Preferred dates: ${dates}`,
`Experience: ${experience}`,
`Availability: ${availability}`,
'',
'Message:',
message
].join('\n')
};
}
function openMailClient() {
const { subject, body } = buildMessage();
const mailto = `mailto:${volunteerEmail}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
window.location.href = mailto;
}
if (submitBtn) {
submitBtn.addEventListener('click', (e) => {
e.preventDefault();
openMailClient();
});
}
if (copyBtn) {
copyBtn.addEventListener('click', async () => {
const { subject, body } = buildMessage();
const text = `${subject}\n\n${body}`;
try {
await navigator.clipboard.writeText(text);
const copiedLabel = copyBtn.dataset.copiedLabel || 'Copied';
const label = copyBtn.dataset.copyLabel || 'Copy application';
copyBtn.textContent = copiedLabel;
setTimeout(() => { copyBtn.textContent = label; }, 1600);
} catch {
window.prompt('Copy your application', text);
}
});
}
}
})();