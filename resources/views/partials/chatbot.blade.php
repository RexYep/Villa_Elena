{{-- ══════════════════════════════════════════════════ --}}
{{--  VILLA ELENA — AI Booking Assistant Chatbot       --}}
{{--  SAVE AS: resources/views/portal/partials/chatbot.blade.php --}}
{{-- ══════════════════════════════════════════════════ --}}

<style>
    :root {
        --navy: #0d1b2a;
        --gold: #c9a84c;
        --gold-light: #e8c97a;
        --gold-dim: rgba(201, 168, 76, 0.12);
    }

    /* ── Bubble ── */
    #chat-bubble {
        position: fixed;
        bottom: 28px;
        right: 28px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--navy);
        color: var(--gold);
        border: 2px solid var(--gold);
        font-size: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 9999;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.25);
        transition: transform .2s;
    }

    #chat-bubble:hover {
        transform: scale(1.08);
    }

    #chat-bubble .notif-dot {
        position: absolute;
        top: 0;
        right: 0;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #ef4444;
        border: 2px solid #fff;
        display: none;
    }

    /* ── Window ── */
    #chat-window {
        position: fixed;
        bottom: 100px;
        right: 28px;
        width: 380px;
        height: 560px;
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 12px 48px rgba(0, 0, 0, 0.18);
        display: none;
        flex-direction: column;
        z-index: 9998;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        animation: chatSlideUp .3s cubic-bezier(.34, 1.56, .64, 1);
    }

    #chat-window.open {
        display: flex;
    }

    @keyframes chatSlideUp {
        from {
            opacity: 0;
            transform: translateY(20px) scale(.97);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    /* ── Header ── */
    .chat-header {
        background: var(--navy);
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
    }

    .chat-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--gold);
        color: var(--navy);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .chat-header-info .name {
        color: #fff;
        font-size: 14px;
        font-weight: 600;
    }

    .chat-header-info .status {
        color: var(--gold-light);
        font-size: 11px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .chat-header-info .status::before {
        content: '';
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #4ade80;
        display: inline-block;
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1
        }

        50% {
            opacity: .4
        }
    }

    .chat-close {
        margin-left: auto;
        background: none;
        border: none;
        color: rgba(255, 255, 255, .5);
        font-size: 20px;
        cursor: pointer;
        line-height: 1;
    }

    .chat-close:hover {
        color: #fff;
    }

    /* ── Quick Replies ── */
    .quick-replies {
        padding: 10px 14px 4px;
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        background: #f8f9fb;
        border-bottom: 1px solid #e2e8f0;
        flex-shrink: 0;
    }

    .qr-btn {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 5px 12px;
        font-size: 12px;
        color: var(--navy);
        cursor: pointer;
        transition: all .2s;
        font-family: inherit;
        white-space: nowrap;
    }

    .qr-btn:hover {
        background: var(--navy);
        color: var(--gold);
        border-color: var(--navy);
    }

    /* ── Messages ── */
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        background: #f8f9fb;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .chat-messages::-webkit-scrollbar {
        width: 4px;
    }

    .chat-messages::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 4px;
    }

    .msg {
        display: flex;
        gap: 8px;
        max-width: 88%;
    }

    .msg.user {
        align-self: flex-end;
        flex-direction: row-reverse;
    }

    .msg.bot {
        align-self: flex-start;
    }

    .msg-bubble {
        padding: 10px 14px;
        border-radius: 14px;
        font-size: 13.5px;
        line-height: 1.6;
    }

    .msg.bot .msg-bubble {
        background: #fff;
        color: #1a2f45;
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 4px;
    }

    .msg.user .msg-bubble {
        background: var(--navy);
        color: #fff;
        border-bottom-right-radius: 4px;
    }

    .msg-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--gold);
        color: var(--navy);
        font-size: 13px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 2px;
    }

    /* ── Typing ── */
    .typing .msg-bubble {
        display: flex;
        gap: 4px;
        align-items: center;
        padding: 12px 16px;
    }

    .dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #94a3b8;
        animation: bounce 1.2s infinite;
    }

    .dot:nth-child(2) {
        animation-delay: .2s;
    }

    .dot:nth-child(3) {
        animation-delay: .4s;
    }

    @keyframes bounce {

        0%,
        80%,
        100% {
            transform: translateY(0);
        }

        40% {
            transform: translateY(-6px);
        }
    }

    /* ── Property Cards ── */
    .property-cards {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 8px;
        width: 100%;
    }

    .prop-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        transition: box-shadow .2s;
    }

    .prop-card:hover {
        box-shadow: 0 4px 16px rgba(0, 0, 0, .1);
    }

    .prop-card-img {
        width: 100%;
        height: 100px;
        object-fit: cover;
        background: #f1f5f9;
    }

    .prop-card-img-placeholder {
        width: 100%;
        height: 80px;
        background: linear-gradient(135deg, #0d1b2a, #1a2f45);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gold);
        font-size: 24px;
    }

    .prop-card-body {
        padding: 10px 12px;
    }

    .prop-card-name {
        font-size: 13px;
        font-weight: 700;
        color: #0d1b2a;
        margin-bottom: 2px;
    }

    .prop-card-meta {
        font-size: 11px;
        color: #6b7a8d;
        margin-bottom: 6px;
    }

    .prop-card-price {
        font-size: 13px;
        color: #0d1b2a;
        margin-bottom: 8px;
    }

    .prop-card-price strong {
        color: #c9a84c;
        font-size: 15px;
    }

    .prop-card-was {
        color: #94a3b8;
        font-size: 12px;
        margin-right: 3px;
    }

    .prop-card-slot {
        color: #6b7a8d;
        font-size: 11px;
    }

    .prop-card-promo {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #dcfce7;
        color: #15803d;
        font-size: 10.5px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 999px;
        margin-bottom: 8px;
    }

    .prop-card-amenities {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        margin-bottom: 8px;
    }

    .amenity-tag {
        background: #f1f5f9;
        border-radius: 10px;
        padding: 2px 8px;
        font-size: 10px;
        color: #475569;
    }

    .prop-card-book {
        display: block;
        width: 100%;
        text-align: center;
        background: var(--navy);
        color: var(--gold);
        border: none;
        border-radius: 8px;
        padding: 8px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        transition: all .2s;
        font-family: inherit;
        letter-spacing: .3px;
    }

    .prop-card-book:hover {
        background: var(--gold);
        color: var(--navy);
    }

    /* ── Input ── */
    .chat-input-area {
        padding: 12px 14px;
        border-top: 1px solid #e2e8f0;
        display: flex;
        gap: 8px;
        align-items: flex-end;
        background: #fff;
        flex-shrink: 0;
    }

    .chat-input {
        flex: 1;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        padding: 9px 14px;
        font-size: 13.5px;
        outline: none;
        font-family: inherit;
        resize: none;
        max-height: 80px;
        line-height: 1.4;
        color: #1a2f45;
        transition: border-color .2s;
    }

    .chat-input:focus {
        border-color: var(--gold);
    }

    .chat-send {
        width: 40px;
        height: 40px;
        background: var(--navy);
        color: var(--gold);
        border: none;
        border-radius: 12px;
        font-size: 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all .2s;
        flex-shrink: 0;
    }

    .chat-send:hover {
        background: var(--gold);
        color: var(--navy);
    }

    .chat-send:disabled {
        opacity: .4;
        cursor: not-allowed;
    }

    /* ── Small screens ────────────────────────────────────────────────
       #chat-window above is a fixed 380px panel pinned 28px from the
       right edge, so it needs 408px of viewport just to sit on screen.
       Narrower than that and it is laid out partly off the LEFT edge
       (measured left: -42px at a 375px viewport) with its content simply
       cut off — no scrollbar appears to hint at it either, because a
       position:fixed box overflowing leftwards never creates one.
       Anchor it to both edges instead and let the width fall out of that.

       Kept at the end of the block on purpose: this re-declares width /
       left / right / height, which the base #chat-window rule also sets
       at the same specificity, so source order is what decides the
       winner. Placed above the base rule it would silently lose. */
    @media (max-width: 480px) {
        #chat-window {
            left: 12px;
            right: 12px;
            width: auto;
            bottom: 92px;
            /* A flat 560px does not fit a 667px-tall phone once the
               bottom offset and the launcher below it are accounted for.
               Kept a DEFINITE height rather than auto + max-height so the
               flex column inside (scrolling message list) still resolves. */
            height: min(560px, calc(100vh - 140px));
        }

        #chat-bubble {
            bottom: 20px;
            right: 20px;
        }
    }
</style>

{{-- Bubble --}}
<button id="chat-bubble" title="Chat with Elena">
    <i class="bi bi-stars"></i>
    <span class="notif-dot" id="chat-notif-dot"></span>
</button>

{{-- Chat Window --}}
<div id="chat-window">
    <div class="chat-header">
        <div class="chat-avatar">E</div>
        <div class="chat-header-info">
            <div class="name">Elena</div>
            <div class="status">AI Booking Assistant · Online</div>
        </div>
        <button class="chat-close" id="chat-close">&times;</button>
    </div>

    {{-- Quick Reply Suggestions --}}
    <div class="quick-replies" id="quick-replies">
        <button class="qr-btn" onclick="quickSend('What properties are available?')">🏡 Available properties</button>
        <button class="qr-btn" onclick="quickSend('Book a villa for 2 people this weekend')">📅 Book this
            weekend</button>
        <button class="qr-btn" onclick="quickSend('What is the check-in time?')">⏰ Check-in time</button>
        <button class="qr-btn" onclick="quickSend('How much is the deposit?')">💳 Deposit info</button>
    </div>

    <div class="chat-messages" id="chat-messages">
        <div class="msg bot">
            <div class="msg-avatar">E</div>
            <div class="msg-bubble">
                Mabuhay! 👋 I'm <strong>Elena</strong>, your Villa Elena booking assistant!<br><br>
                I can help you <strong>find available properties</strong>, check prices, and book your stay. Just tell
                me your dates and how many guests! 🏝️
            </div>
        </div>
    </div>

    <div class="chat-input-area">
        <textarea class="chat-input" id="chat-input" rows="1"></textarea>
        <button class="chat-send" id="chat-send">
            <i class="bi bi-send-fill"></i>
        </button>
    </div>
</div>

<script>
    const bubble = document.getElementById('chat-bubble');
    const chatWin = document.getElementById('chat-window');
    const closeBtn = document.getElementById('chat-close');
    const input = document.getElementById('chat-input');
    const sendBtn = document.getElementById('chat-send');
    const messages = document.getElementById('chat-messages');
    const qrPanel = document.getElementById('quick-replies');

    let history = [];
    let isLoading = false;

    // ── Toggle ─────────────────────────────────────────────────────────
    bubble.addEventListener('click', () => {
        chatWin.classList.toggle('open');
        document.getElementById('chat-notif-dot').style.display = 'none';
        if (chatWin.classList.contains('open')) {
            setTimeout(() => input.focus(), 100);
        }
    });
    closeBtn.addEventListener('click', () => chatWin.classList.remove('open'));

    // ── Input resize ───────────────────────────────────────────────────
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 80) + 'px';
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    sendBtn.addEventListener('click', sendMessage);

    // ── Quick send ─────────────────────────────────────────────────────
    function quickSend(text) {
        input.value = text;
        qrPanel.style.display = 'none';
        sendMessage();
    }

    // ── Add message ────────────────────────────────────────────────────
    function addMessage(role, text, cards = []) {
        const isUser = role === 'user';
        const wrapper = document.createElement('div');
        wrapper.style.cssText = 'display:flex; flex-direction:column; align-items:' + (isUser ? 'flex-end' :
            'flex-start') + '; gap:6px; width:100%;';

        const div = document.createElement('div');
        div.className = `msg ${isUser ? 'user' : 'bot'}`;
        div.innerHTML = `
        ${!isUser ? '<div class="msg-avatar">E</div>' : ''}
        <div class="msg-bubble">${text.replace(/\n/g, '<br>')}</div>
    `;
        wrapper.appendChild(div);

        // Property cards
        if (cards && cards.length > 0) {
            const cardsDiv = document.createElement('div');
            cardsDiv.className = 'property-cards';
            cardsDiv.style.maxWidth = '320px';

            cards.forEach(p => {
                // Ang card ay dating bumabasa ng `p.nights`/`p.total`/
                // `p.per_night` — mga field na hindi na ipinapadala ng
                // controller mula nang lumipat sa fixed-slot na package
                // model, kaya "₱NaN/night" ang aktwal na lumalabas.
                // Ngayon: `p.price` ang sisingilin, at kung may promo,
                // tinatawid ang `p.base_price` sa tabi nito.
                const priceLabel = p.discount > 0 ?
                    `<s class="prop-card-was">₱${formatPrice(p.base_price)}</s> <strong>₱${formatPrice(p.price)}</strong>` :
                    `<strong>₱${formatPrice(p.price)}</strong>`;

                const promoHtml = p.promo ?
                    `<div class="prop-card-promo"><i class="bi bi-tag-fill"></i> ${p.promo} — applied automatically</div>` :
                    '';

                const imgHtml = p.image ?
                    `<img src="${p.image}" class="prop-card-img" alt="${p.name}" onerror="this.parentElement.innerHTML='<div class=\\'prop-card-img-placeholder\\'><i class=\\'bi bi-house-door\\'></i></div>'">` :
                    `<div class="prop-card-img-placeholder"><i class="bi bi-house-door"></i></div>`;

                const amenitiesHtml = p.amenities.length > 0 ?
                    `<div class="prop-card-amenities">${p.amenities.map(a => `<span class="amenity-tag">${a}</span>`).join('')}</div>` :
                    '';

                cardsDiv.innerHTML += `
                <div class="prop-card">
                    ${imgHtml}
                    <div class="prop-card-body">
                        <div class="prop-card-name">${p.name}</div>
                        <div class="prop-card-meta">${p.type} · Up to ${p.capacity} guests</div>
                        <div class="prop-card-price">${priceLabel} <span class="prop-card-slot">${p.slot_label}</span></div>
                        ${promoHtml}
                        ${amenitiesHtml}
                        <a href="${p.book_url}" class="prop-card-book" target="_blank">
                            <i class="bi bi-calendar-check me-1"></i> Book Now
                        </a>
                    </div>
                </div>
            `;
            });
            wrapper.appendChild(cardsDiv);
        }

        messages.appendChild(wrapper);
        messages.scrollTop = messages.scrollHeight;
    }

    // ── Typing indicator ───────────────────────────────────────────────
    function showTyping() {
        const div = document.createElement('div');
        div.className = 'msg bot typing';
        div.id = 'typing-indicator';
        div.innerHTML =
            `<div class="msg-avatar">E</div><div class="msg-bubble"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>`;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    function removeTyping() {
        const t = document.getElementById('typing-indicator');
        if (t) t.remove();
    }

    // ── Send ───────────────────────────────────────────────────────────
    async function sendMessage() {
        const text = input.value.trim();
        if (!text || isLoading) return;

        qrPanel.style.display = 'none';
        addMessage('user', text);
        history.push({
            role: 'user',
            content: text
        });

        input.value = '';
        input.style.height = 'auto';
        isLoading = true;
        sendBtn.disabled = true;
        showTyping();

        try {
            const res = await fetch('{{ route('chatbot.reply') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({
                    message: text,
                    history: history.slice(-8)
                }),
            });

            const data = await res.json();
            const reply = data.reply || 'Sorry, I could not process your request.';
            const cards = data.property_cards || [];

            removeTyping();
            addMessage('bot', reply, cards);
            history.push({
                role: 'assistant',
                content: reply
            });

            // Show notif dot if chat is closed
            if (!chatWin.classList.contains('open')) {
                document.getElementById('chat-notif-dot').style.display = 'block';
            }

        } catch (err) {
            removeTyping();
            addMessage('bot', 'Sorry, something went wrong. Please try again. 🙏');
        }

        isLoading = false;
        sendBtn.disabled = false;
        input.focus();
    }

    function formatPrice(n) {
        return parseFloat(n).toLocaleString('en-PH', {
            minimumFractionDigits: 2
        });
    }
</script>
