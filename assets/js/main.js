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

(function(){
  'use strict';
  const forms = document.querySelectorAll('[data-contact-form]');
  if (!forms.length) return;
  const destination = 'info@corcovadofoundation.org';
  forms.forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const status = form.querySelector('[data-contact-status]');
      const name = form.querySelector('[name="name"]');
      const email = form.querySelector('[name="email"]');
      const phone = form.querySelector('[name="phone"]');
      const subject = form.querySelector('[name="subject"]');
      const message = form.querySelector('[name="message"]');

      if (!name || !email || !message || !name.value.trim() || !email.value.trim() || !message.value.trim() || !email.checkValidity()) {
        if (email && typeof email.reportValidity === 'function') email.reportValidity();
        if (!email || email.value.trim()) {
          if (name && !name.value.trim() && typeof name.reportValidity === 'function') name.reportValidity();
          if (message && !message.value.trim() && typeof message.reportValidity === 'function') message.reportValidity();
        }
        if (status) status.textContent = (document.documentElement.lang || '').toLowerCase().startsWith('es')
          ? 'Completa los campos obligatorios antes de continuar.'
          : 'Please complete the required fields before continuing.';
        return;
      }

      const langIsEs = (document.documentElement.lang || '').toLowerCase().startsWith('es');
      const topic = subject && subject.value ? subject.value : (langIsEs ? 'Consulta general' : 'General inquiry');
      const body = [
        langIsEs ? 'Mensaje desde el sitio web de Fundación Corcovado' : 'Message from the Corcovado Foundation website',
        `${langIsEs ? 'Nombre' : 'Name'}: ${name.value.trim()}`,
        `${langIsEs ? 'Correo' : 'Email'}: ${email.value.trim()}`,
        `${langIsEs ? 'Teléfono' : 'Phone'}: ${phone && phone.value.trim() ? phone.value.trim() : (langIsEs ? 'No indicado' : 'Not provided')}`,
        `${langIsEs ? 'Tema' : 'Subject'}: ${topic}`,
        '',
        langIsEs ? 'Mensaje:' : 'Message:',
        message.value.trim()
      ].join('\n');
      const subjectLine = `${langIsEs ? 'Contacto web' : 'Website contact'} — ${topic}`;
      window.location.href = `mailto:${destination}?subject=${encodeURIComponent(subjectLine)}&body=${encodeURIComponent(body)}`;
      if (status) status.textContent = langIsEs
        ? 'Se abrirá tu aplicación de correo para enviar el mensaje.'
        : 'Your email application will open so you can send the message.';
    });
  });
})();
