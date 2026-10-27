/* ════ PAW-ADOPT AI ASSISTANT — LOGIN CONNECTED + GEMINI READY ════ */
(function () {
    'use strict';

    const FAQ = [
        { q: ['adopt', 'adoption process', 'how to adopt', 'apply'],
          a: '🐾 Adoption is easy!\n1. Create an Adopter account\n2. Browse available pets\n3. Click a pet and submit an adoption application\n4. The veterinary/shelter reviews it\n5. Once approved, arrange the meet-up and take your friend home!' },
        { q: ['register', 'sign up', 'create account', 'new user'],
          a: 'Click “Create Account” on the login panel. Review the privacy notice first, then complete the Adopter or Veterinary registration form.' },
        { q: ['login', 'sign in', 'log in'],
          a: 'Select your role (Adopter, Veterinary, or Admin), enter your username/email and password, then press Sign In.' },
        { q: ['forgot', 'password', 'reset'],
          a: 'Use the Reset Password option, enter your email, and Paw-Adopt will send your reset link.' },
        { q: ['role', 'roles', 'adopter', 'veterinary', 'shelter', 'admin'],
          a: 'Paw-Adopt has 3 roles:\n🐶 Adopter — browse and apply for pets\n🏠 Veterinary/Shelter — manage pet listings and applications\n🛡️ Admin — manage the whole system' },
        { q: ['browse', 'search', 'find pet', 'pets', 'dog', 'cat'],
          a: 'After signing in as an Adopter, go to your dashboard to browse available pets and save favorites.' },
        { q: ['favorite', 'save', 'heart'],
          a: 'Tap the heart icon on any pet listing to save it to your favorites.' },
        { q: ['chat', 'message', 'contact', 'talk'],
          a: 'Adopters can chat directly with the Veterinary Clinic through the built-in messaging feature once logged in.' },
        { q: ['application', 'status', 'track'],
          a: 'You can track your adoption applications from your Adopter dashboard — pending, approved, or rejected.' },
        { q: ['terms', 'conditions'],
          a: 'You can open the Terms & Conditions popup from the login page links.' },
        { q: ['privacy', 'privacy policy', 'data'],
          a: 'The Privacy Policy popup appears before account creation and can also be opened from the login page links.' },
        { q: ['about', 'what is', 'paw adopt', 'pawadopt'],
          a: 'Paw-Adopt connects rescued pets with responsible adopters and veterinary partners.' },
        { q: ['hi', 'hello', 'hey'],
          a: 'Hello! I’m PawBot 🐾 Ask me about adoption, login, registration, support, or Paw-Adopt features.' },
        { q: ['thank', 'thanks'],
          a: 'You’re welcome! 🐾' }
    ];

    const FALLBACK = 'I’m not sure about that yet. Try asking about adoption, registration, login, privacy, terms, or applications.';
    const QUICK = ['How do I adopt?', 'Create account', 'Forgot password', 'User roles', 'Track application'];

    const launcher = document.getElementById('paLauncher');
    const win = document.getElementById('paWindow');
    const msgs = document.getElementById('paMessages');
    const quick = document.getElementById('paQuick');
    const form = document.getElementById('paForm');
    const input = document.getElementById('paInput');
    const closeBtn = document.getElementById('paClose');
    const sendBtn = document.getElementById('paSendBtn');

    if (!launcher || !win || !msgs || !quick || !form || !input || !closeBtn) {
        console.warn('PawBot elements not found.');
        return;
    }

    const dot = launcher.querySelector('.pa-notif-dot');
    const endpoint =
        window.PAWBOT_ENDPOINT ||
        document.body?.dataset?.pawbotEndpoint ||
        form.getAttribute('action') ||
        window.location.pathname + window.location.search;

    let greeted = false;
    let busy = false;
    let usedRemote = false;

    function addMsg(text, who) {
        const d = document.createElement('div');
        d.className = 'pa-msg ' + who;
        d.textContent = text;
        msgs.appendChild(d);
        msgs.scrollTop = msgs.scrollHeight;
        return d;
    }

    function answer(query) {
        const norm = String(query || '').toLowerCase().replace(/[^\w\s]/g, '');
        const words = norm.split(/\s+/).filter(Boolean);
        let best = null;
        let bestScore = 0;

        for (const item of FAQ) {
            let score = 0;
            for (const key of item.q) {
                if (norm.includes(key)) score += key.length * 2;
                for (const w of key.split(/\s+/)) {
                    if (words.includes(w)) score += w.length;
                }
            }
            if (score > bestScore) {
                bestScore = score;
                best = item;
            }
        }

        return bestScore >= 3 ? best.a : FALLBACK;
    }

    function setBusy(state) {
        busy = !!state;
        input.disabled = busy;
        if (sendBtn) sendBtn.disabled = busy;
        quick.querySelectorAll('button').forEach((btn) => {
            btn.disabled = busy;
        });
    }

    function openRegisterFlow() {
        if (typeof window.openPrivacyGate === 'function') {
            window.openPrivacyGate();
            return;
        }

        const registerLink = document.getElementById('goRegister');
        if (registerLink) {
            registerLink.click();
        }
    }

    function openTerms() {
        if (typeof window.showTerms === 'function') window.showTerms();
    }

    function openContact() {
        if (typeof window.showContact === 'function') window.showContact();
    }

    function runLocalAction(text) {
        const q = String(text || '').toLowerCase();
        if (q.includes('create account') || q.includes('register') || q.includes('sign up')) {
            openRegisterFlow();
        } else if (q.includes('terms')) {
            openTerms();
        } else if (q.includes('contact') || q.includes('support')) {
            openContact();
        }
    }

    async function askGemini(message) {
        const typing = addMsg('PawBot is thinking…', 'bot typing');
        setBusy(true);

        try {
            const body = new URLSearchParams();
            body.set('action', 'pawbot_chat');
            body.set('message', message);

            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            });

            const raw = await res.text();
            typing.remove();

            let data;
            try {
                data = JSON.parse(raw);
            } catch (error) {
                throw new Error('Invalid JSON response');
            }

            if (!res.ok || !data.ok) {
                throw new Error(data?.error || data?.message || 'Gemini is unavailable right now.');
            }

            usedRemote = true;
            addMsg(data.reply, 'bot');
        } catch (error) {
            typing.remove();
            addMsg(answer(message), 'bot');
            if (!usedRemote) {
                addMsg('Gemini is not available yet, so I answered using PawBot’s built-in help responses.', 'error');
            }
        } finally {
            runLocalAction(message);
            setBusy(false);
            input.focus();
        }
    }

    function toggleBot(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        win.classList.toggle('open');
        if (dot) dot.style.display = 'none';

        if (win.classList.contains('open') && !greeted) {
            greeted = true;
            addMsg('Hi! I’m PawBot 🐾 Ask me about adoption, registration, support, or Paw-Adopt features.', 'bot');
        }

        if (win.classList.contains('open')) input.focus();
        return false;
    }

    launcher.addEventListener('click', toggleBot);
    launcher.addEventListener('touchstart', toggleBot, { passive: false });

    closeBtn.addEventListener('click', function (event) {
        event.preventDefault();
        win.classList.remove('open');
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const text = input.value.trim();
        if (!text || busy) return;

        addMsg(text, 'user');
        input.value = '';
        askGemini(text);
    });

    QUICK.forEach(function (label) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'pa-chip';
        b.textContent = label;
        b.addEventListener('click', function () {
            if (busy) return;
            addMsg(label, 'user');
            askGemini(label);
        });
        quick.appendChild(b);
    });
})();
