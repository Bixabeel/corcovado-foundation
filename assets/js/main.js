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
const donationFlow = document.querySelector('[data-donation-flow]');
if (donationFlow) {
  const amountButtons = Array.from(donationFlow.querySelectorAll('[data-amount]'));
  const continueBtn = donationFlow.querySelector('[data-donation-continue]');
  const copyBtn = donationFlow.querySelector('[data-copy-donation]');
  const amountOut = donationFlow.querySelector('[data-selected-amount]');
  const routeOut = donationFlow.querySelector('[data-selected-route]');
  const customAmountInput = donationFlow.querySelector('[name="donation_amount_custom"]');
  const summaryAmountOut = donationFlow.querySelector('[data-selected-amount]');
  const summaryRouteOut = donationFlow.querySelector('[data-selected-route]');
  const summaryFreqOut = document.getElementById('summary_frequency');
  const summaryProjectOut = document.getElementById('summary_project');

  // A8: SOLO AMIGOS DE COSTA RICA
  const routes = {
    amigos: 'https://www.amigosofcostarica.org/affiliates/fundacion-corcovado'
  };

  function selectedAmount() {
    const active = amountButtons.find(btn => btn.classList.contains('is-active'));
    if (active && active.dataset.amount === 'custom') {
      return customAmountInput && customAmountInput.value.trim() ? customAmountInput.value.trim().replace(/^[^0-9.]+/, '') : 'custom';
    }
    return active ? active.dataset.amount : '50';
  }

  function selectedRouteLabel() {
    return 'Amigos of Costa Rica';
  }

  function updateSummary() {
    const amount = selectedAmount();
    const routeLabel = selectedRouteLabel();
    const frequency = 'one-time';
    const project = 'General Fund';

    const amountLabel = amount === 'custom'
      ? (customAmountInput && customAmountInput.value.trim() ? `$${customAmountInput.value.trim().replace(/^[^0-9.]+/, '')}` : '$custom')
      : `$${amount}`;

    if (summaryAmountOut) summaryAmountOut.textContent = amountLabel;
    if (summaryRouteOut) summaryRouteOut.textContent = routeLabel;
    if (summaryFreqOut) summaryFreqOut.textContent = 'One-time';
    if (summaryProjectOut) summaryProjectOut.textContent = project;
  }

  amountButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      amountButtons.forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      if (btn.dataset.amount === 'custom' && customAmountInput) customAmountInput.focus();
      updateSummary();
    });
  });

  if (customAmountInput) {
    customAmountInput.addEventListener('input', updateSummary);
  }

  if (continueBtn) {
    continueBtn.addEventListener('click', () => {
      const baseUrl = routes.amigos;
      const currentAmount = selectedAmount();
      const amount = encodeURIComponent(currentAmount === 'custom' ? (customAmountInput?.value.trim() || 'custom') : currentAmount);
      const project = encodeURIComponent('General Fund');
      const frequency = encodeURIComponent('one-time');
      const source = encodeURIComponent('corcovadofoundation.org');
      const finalUrl = `${baseUrl}${baseUrl.includes('?') ? '&' : '?'}utm_source=${source}&utm_medium=website&utm_campaign=donation-flow&amount=${amount}&project=${project}&frequency=${frequency}`;
      window.open(finalUrl, '_blank', 'noopener,noreferrer');
    });
  }

  if (copyBtn) {
    copyBtn.addEventListener('click', async () => {
      const amount = selectedAmount() === 'custom' ? (customAmountInput?.value.trim() || 'custom') : selectedAmount();
      const route = selectedRouteLabel();
      const project = 'General Fund';
      const frequency = 'one-time';
      const text = [
        'Corcovado Foundation donation brief',
        `Amount: ${amount.startsWith('$') ? amount : '$' + amount}`,
        `Frequency: ${frequency}`,
        `Project: ${project}`,
        `Preferred route: ${route}`
      ].join('\n');
      try {
        await navigator.clipboard.writeText(text);
        const copiedLabel = copyBtn.dataset.copiedLabel || 'Copied';
        const label = copyBtn.dataset.copyLabel || 'Copy donation brief';
        copyBtn.textContent = copiedLabel;
        setTimeout(() => { copyBtn.textContent = label; }, 1600);
      } catch {
        window.prompt('Copy this donation brief', text);
      }
    });
  }

  updateSummary();
}

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