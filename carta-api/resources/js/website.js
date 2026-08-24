import * as bootstrap from 'bootstrap';

const loader = document.querySelector('[data-pv-loader]');
const finishLoading = () => {
    if (!loader || loader.classList.contains('is-hidden')) return;
    loader.classList.add('is-hidden');
    window.setTimeout(() => loader.remove(), 350);
};

if (document.readyState === 'complete') {
    finishLoading();
} else {
    window.addEventListener('load', finishLoading, { once: true });
    // Nunca bloquear a página por causa de um recurso externo lento.
    window.setTimeout(finishLoading, 2500);
}

const header = document.querySelector('[data-pv-header]');
const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 12);

updateHeader();
window.addEventListener('scroll', updateHeader, { passive: true });

document.querySelectorAll('#pvMenu a').forEach((link) => {
    link.addEventListener('click', () => {
        const menu = document.getElementById('pvMenu');
        const instance = menu ? bootstrap.Offcanvas.getInstance(menu) : null;
        instance?.hide();
    });
});

const revealItems = document.querySelectorAll('.pv-reveal');
if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    revealItems.forEach((item) => observer.observe(item));
} else {
    revealItems.forEach((item) => item.classList.add('is-visible'));
}

const cookieBanner = document.querySelector('[data-pv-cookie-banner]');
const consentKey = 'prontovia_cookie_consent';
const readConsent = () => {
    try { return window.localStorage.getItem(consentKey); } catch { return null; }
};
const saveConsent = (choice) => {
    try { window.localStorage.setItem(consentKey, choice); } catch { /* A preferência continua na sessão atual. */ }
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `pv_cookie_consent=${choice}; Max-Age=31536000; Path=/; SameSite=Lax${secure}`;
    cookieBanner?.setAttribute('hidden', '');
    document.dispatchEvent(new CustomEvent('prontovia:consent', { detail: { choice } }));
};

if (cookieBanner && !readConsent()) cookieBanner.removeAttribute('hidden');
document.querySelector('[data-pv-cookie-essential]')?.addEventListener('click', () => saveConsent('essential'));
document.querySelector('[data-pv-cookie-all]')?.addEventListener('click', () => saveConsent('all'));
document.querySelector('[data-pv-cookie-settings]')?.addEventListener('click', () => {
    cookieBanner?.removeAttribute('hidden');
    cookieBanner?.querySelector('button')?.focus();
});

const assistant = document.querySelector('[data-pv-assistant]');
const assistantPanel = assistant?.querySelector('#pvAssistantPanel');
const assistantToggle = assistant?.querySelector('[data-pv-assistant-toggle]');
const assistantAnswer = assistant?.querySelector('[data-pv-answer]');
const setAssistantOpen = (open) => {
    if (!assistantPanel || !assistantToggle) return;
    assistantPanel.toggleAttribute('hidden', !open);
    assistantToggle.setAttribute('aria-expanded', String(open));
    if (open) assistantPanel.querySelector('button')?.focus();
    else assistantToggle.focus();
};

assistantToggle?.addEventListener('click', () => setAssistantOpen(assistantPanel?.hasAttribute('hidden')));
assistant?.querySelector('[data-pv-assistant-close]')?.addEventListener('click', () => setAssistantOpen(false));
assistant?.querySelectorAll('[data-pv-assistant-answer]').forEach((button) => {
    button.addEventListener('click', () => {
        if (!assistantAnswer) return;
        const answers = {
            candidate: ['Comece pela aplicação: nela pode estudar, praticar e acompanhar o seu progresso.', assistant.dataset.candidateUrl, 'Conhecer ou baixar a aplicação'],
            school: ['O ProntoVia Escolas ajuda a gerir turmas, aplicar testes e transformar resultados em acompanhamento.', assistant.dataset.schoolUrl, 'Falar sobre a minha escola'],
            support: ['Consulte as perguntas frequentes ou contacte a equipa de suporte.', assistant.dataset.supportUrl, 'Obter ajuda'],
        };
        const [copy, url, label] = answers[button.dataset.pvAssistantAnswer];
        assistantAnswer.replaceChildren();
        const paragraph = document.createElement('p');
        paragraph.textContent = copy;
        const link = document.createElement('a');
        link.href = url;
        link.className = 'pv-btn pv-btn-primary pv-btn-small';
        link.textContent = label;
        assistantAnswer.append(paragraph, link);
    });
});
