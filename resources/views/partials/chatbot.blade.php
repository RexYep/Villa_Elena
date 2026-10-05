{{-- ══════════════════════════════════════════════════ --}}
{{--  VILLA ELENA — AI Booking Assistant Chatbot       --}}
{{--  SAVE AS: resources/views/portal/partials/chatbot.blade.php --}}
{{-- ══════════════════════════════════════════════════ --}}

<style>
    :root {
        --navy: #2c2416;
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
        /* Never taller than the room above the launcher (100px offset plus a
           20px gap at the top). A flat 560px put the header and its close
           button off-screen on any viewport shorter than 680px: a landscape
           phone, or a 1366x768 laptop. A definite height, not max-height, so
           the scrolling message list inside still resolves. */
        height: min(560px, calc(100vh - 120px));
        height: min(560px, calc(100dvh - 120px));
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 12px 48px rgba(0, 0, 0, 0.18);
        display: none;
        flex-direction: column;
        z-index: 9998;
        overflow: hidden;
        border: 1px solid var(--border);
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
        font-size: 12px;
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

    /* A 44px box around the "×": the glyph alone was an 11x20px target.
       The negative margin keeps it where it was in the header. */
    .chat-close {
        margin: -10px -12px -10px auto;
        width: 44px;
        height: 44px;
        flex: none;
        display: flex;
        align-items: center;
        justify-content: center;
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
        border-bottom: 1px solid var(--border);
        flex-shrink: 0;
    }

    .qr-btn {
        background: #fff;
        border: 1px solid var(--border);
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
        background: var(--border);
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
        border: 1px solid var(--border);
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
        background: var(--muted);
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
        border: 1px solid var(--border);
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
        background: var(--sand);
    }

    .prop-card-img-placeholder {
        width: 100%;
        height: 80px;
        background: linear-gradient(135deg, var(--stone), #4a3d2a);
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
        color: var(--stone);
        margin-bottom: 2px;
    }

    .prop-card-meta {
        font-size: 12px;
        color: var(--muted);
        margin-bottom: 6px;
    }

    .prop-card-price {
        font-size: 13px;
        color: var(--stone);
        margin-bottom: 8px;
    }

    .prop-card-price strong {
        color: #c9a84c;
        font-size: 15px;
    }

    .prop-card-was {
        color: var(--muted);
        font-size: 12px;
        margin-right: 3px;
    }

    .prop-card-slot {
        color: var(--muted);
        font-size: 12px;
    }

    .prop-card-promo {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #dcfce7;
        color: #15803d;
        font-size: 12px;
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
        background: var(--sand);
        border-radius: 10px;
        padding: 2px 8px;
        font-size: 12px;
        color: var(--muted);
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
        border-top: 1px solid var(--border);
        display: flex;
        gap: 8px;
        align-items: flex-end;
        background: #fff;
        flex-shrink: 0;
    }

    .chat-input {
        flex: 1;
        border: 1.5px solid var(--border-strong);
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
        /* Still a floating card, anchored to both edges with the launcher
           below it. Kept a DEFINITE height rather than auto + max-height so
           the flex column inside (scrolling message list) still resolves:
           everything above the launcher (92px) less a 12px gap at the top. */
        #chat-window {
            left: 12px;
            right: 12px;
            width: auto;
            bottom: 92px;
            height: min(560px, calc(100vh - 104px));
            height: min(560px, calc(100dvh - 104px));
        }

        #chat-bubble {
            bottom: 20px;
            right: 20px;
        }

        /* On a phone the launcher floats over the right end of whatever
           field is under it. While the guest is typing in a page field it
           steps aside; the chat's own input is excluded so the launcher
           stays while the chat is in use. */
        body:has(:is(input, select, textarea):focus:not(#chat-input)) #chat-bubble {
            opacity: 0;
            pointer-events: none;
        }

        /* What made the card cramped was not its size but what it spent
           the size on. A shorter header, and the four suggestions on one
           row that scrolls sideways instead of two or three wrapped rows,
           give that height back to the conversation. */
        .chat-header {
            padding: 12px 16px;
            gap: 10px;
        }

        /* On a 320px phone "AI Booking Assistant · Online" wrapped to a
           second line and made the header 20px taller. */
        .chat-avatar {
            width: 36px;
            height: 36px;
            font-size: 16px;
        }

        .chat-header-info .status {
            white-space: nowrap;
        }

        .quick-replies {
            flex-wrap: nowrap;
            overflow-x: auto;
            padding: 10px 14px;
            scrollbar-width: none;
        }

        .quick-replies::-webkit-scrollbar {
            display: none;
        }

        .qr-btn {
            flex: none;
        }

        .chat-messages {
            padding: 14px;
            overscroll-behavior: contain;
        }

        .chat-input-area {
            padding: 10px 12px;
        }
    }

    /* ── Keyboard up, or a phone on its side ──────────────────────────
       `html.chat-kb` is set by syncChatViewport() below while the chat is
       open and the on-screen keyboard is showing. There is then about
       300px of screen left, and the card cannot also spend it on the
       launcher's 92px and a row of suggestions: the card takes the
       visible area (still inset, still a card), the launcher hides, and
       the suggestions come back when the keyboard goes away.

       It is positioned from the TOP with the visualViewport numbers
       because Android and iOS lay the keyboard over a fixed element
       instead of resizing it: `bottom` would put the input underneath. */
    html.chat-kb #chat-window {
        top: calc(var(--chat-vv-top, 0px) + 8px);
        bottom: auto;
        height: calc(var(--chat-vv-h, 100dvh) - 16px);
    }

    html.chat-kb #chat-bubble,
    html.chat-kb .quick-replies {
        display: none;
    }

    /* The same squeeze without a keyboard: a phone held sideways, or an
       old browser that shrinks the page for the keyboard instead. */
    @media (max-height: 520px) {
        #chat-window {
            bottom: 12px;
            height: calc(100vh - 24px);
            height: calc(100dvh - 24px);
        }

        html.chat-open #chat-bubble,
        html.chat-open .quick-replies {
            display: none;
        }

        .chat-header {
            padding: 10px 16px;
        }
    }

    /* A modal or the confirm dialog owns the screen while it is open. The
       launcher's z-index is above Bootstrap's modal, so it sat on the
       corner of the booking-policy modal. */
    body.modal-open #chat-bubble,
    body:has(dialog[open]) #chat-bubble {
        display: none;
    }

    /* iOS Safari zooms the page when a focused field's text is under 16px. */
    @media (pointer: coarse) {
        .qr-btn {
            padding: 11px 14px;
            font-size: 13px;
        }

        .chat-input {
            font-size: 16px;
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
        <button class="qr-btn" onclick="quickSend('Is the villa available?')">
            <i class="bi bi-house-door me-1"></i>
            Check availability
        </button>

        <button class="qr-btn" onclick="quickSend('Book a villa for 2 people this weekend')">
            <i class="bi bi-calendar-event me-1"></i>
            Book this weekend
        </button>

        <button class="qr-btn" onclick="quickSend('What is the check-in time?')">
            <i class="bi bi-clock me-1"></i>
            Check-in time
        </button>

        <button class="qr-btn" onclick="quickSend('How much is the deposit?')">
            <i class="bi bi-credit-card me-1"></i>
            Deposit info
        </button>
    </div>

    <div class="chat-messages" id="chat-messages">
        <div class="msg bot">
            <div class="msg-avatar">E</div>
            <div class="msg-bubble">
                Mabuhay! 👋 I'm <strong>Elena</strong>, your Villa Elena booking assistant!<br><br>
                I can help you <strong>check if the villa is available</strong>, check prices, and book your stay. Just tell
                me your dates and how many guests!
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
    // `chat-open` on <html> lets the short-screen layout hide the launcher
    // while the chat is showing.
    function setChatOpen(open) {
        chatWin.classList.toggle('open', open);
        document.documentElement.classList.toggle('chat-open', open);
        syncChatViewport();

        if (!open) return;
        document.getElementById('chat-notif-dot').style.display = 'none';
        messages.scrollTop = messages.scrollHeight;

        // Focusing the input on a touch device raises the keyboard before the
        // guest has read the greeting or seen the suggestions, and it covers
        // half the chat. A mouse-and-keyboard guest still gets the focus.
        if (!window.matchMedia('(pointer: coarse)').matches) {
            setTimeout(() => input.focus(), 100);
        }
    }

    // The on-screen keyboard covers a fixed element on Android and iOS; it
    // does not resize it. visualViewport is the part of the screen actually
    // visible. While the keyboard is up (the visible part is well short of
    // the window) `chat-kb` switches the card to fill that part, so the
    // input stays above the keyboard. 150px is more than any browser
    // toolbar and less than any keyboard.
    function syncChatViewport() {
        const vv = window.visualViewport;
        const html = document.documentElement;
        const keyboardUp = !!vv && chatWin.classList.contains('open') &&
            window.innerHeight - vv.height > 150;

        html.classList.toggle('chat-kb', keyboardUp);

        if (!keyboardUp) {
            html.style.removeProperty('--chat-vv-h');
            html.style.removeProperty('--chat-vv-top');
            return;
        }

        html.style.setProperty('--chat-vv-h', vv.height + 'px');
        html.style.setProperty('--chat-vv-top', vv.offsetTop + 'px');
        messages.scrollTop = messages.scrollHeight;
    }

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', syncChatViewport);
        window.visualViewport.addEventListener('scroll', syncChatViewport);
    }

    bubble.addEventListener('click', () => setChatOpen(!chatWin.classList.contains('open')));
    closeBtn.addEventListener('click', () => setChatOpen(false));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && chatWin.classList.contains('open')) setChatOpen(false);
    });

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

    // Everything rendered below goes through innerHTML, and two of the three
    // sources are not ours to trust:
    //
    //   * `text` for a bot turn is the model's reply. A guest can ask the
    //     model to emit markup. That is self-XSS — only the person typing can
    //     influence their own reply — but it is still script execution on our
    //     origin, in a session that may be a signed-in customer's.
    //   * the card fields (name, promo label, amenities) are admin-set, so a
    //     value entered once in the admin panel would run in every guest's
    //     browser.
    //
    // Same escaper as admin/partials/realtime.blade.php: let the browser do
    // the encoding rather than maintain an entity list here.
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    // escapeHtml() IS NOT ENOUGH INSIDE AN ATTRIBUTE. Serialising a text node
    // escapes & < > and nothing else — measured against a real DOM:
    //
    //     '"quoted"'  ->  '"quoted"'        (unchanged)
    //     "it's"      ->  "it's"            (unchanged)
    //     'a<b>c'     ->  'a&lt;b&gt;c'
    //
    // So a value carrying a double quote closes src="…" or href="…" and the
    // next thing it writes is a new attribute — onerror=, for instance. Three
    // card fields sit in attribute position, so they get the quotes too.
    function escapeAttr(str) {
        return escapeHtml(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
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
        <div class="msg-bubble">${escapeHtml(text).replace(/\n/g, '<br>')}</div>
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
                    `<div class="prop-card-promo"><i class="bi bi-tag-fill"></i> ${escapeHtml(p.promo)} — applied automatically</div>` :
                    '';

                const imgHtml = p.image ?
                    `<img src="${escapeAttr(p.image)}" class="prop-card-img" alt="${escapeAttr(p.name)}" onerror="this.parentElement.innerHTML='<div class=\\'prop-card-img-placeholder\\'><i class=\\'bi bi-house-door\\'></i></div>'">` :
                    `<div class="prop-card-img-placeholder"><i class="bi bi-house-door"></i></div>`;

                const amenitiesHtml = p.amenities.length > 0 ?
                    `<div class="prop-card-amenities">${p.amenities.map(a => `<span class="amenity-tag">${escapeHtml(a)}</span>`).join('')}</div>` :
                    '';

                cardsDiv.innerHTML += `
                <div class="prop-card">
                    ${imgHtml}
                    <div class="prop-card-body">
                        <div class="prop-card-name">${escapeHtml(p.name)}</div>
                        <div class="prop-card-meta">${escapeHtml(p.type)} · Up to ${escapeHtml(p.capacity)} guests</div>
                        <div class="prop-card-price">${priceLabel} <span class="prop-card-slot">${escapeHtml(p.slot_label)}</span></div>
                        ${promoHtml}
                        ${amenitiesHtml}
                        <a href="${escapeAttr(p.book_url)}" class="prop-card-book" target="_blank">
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

        // Snapshot BEFORE adding this message: the server appends the
        // current message to the prompt itself, so including it here sent
        // it to the AI twice and wasted tokens on every turn.
        const priorHistory = history.slice(-8);

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
                    // Without this Laravel answers errors with an HTML page,
                    // res.json() throws, and every failure looks identical.
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({
                    message: text,
                    history: priorHistory
                }),
            });

            // 419: the page outlived its session (left open for hours), so
            // the CSRF token baked into it is dead. Retrying can never work
            // until the page is reloaded — say so instead of "try again".
            if (res.status === 419) {
                throw Object.assign(new Error('Your session has expired. Please refresh the page to keep chatting. 🔄'), { friendly: true });
            }

            const data = await res.json();

            // 429 carries its own `reply` explaining how long to wait.
            if (!res.ok && !data.reply) {
                throw new Error();
            }
            const reply = data.reply || 'Sorry, I could not process your request.';
            const cards = data.property_cards || [];

            removeTyping();
            addMessage('bot', reply, cards);

            // `ok === false` means the server is showing an apology, not an
            // answer from Elena. Keep it out of the history we send back, or
            // the next prompt is primed with the villa apologising to itself.
            if (data.ok !== false) {
                history.push({
                    role: 'assistant',
                    content: reply
                });
            }

            // Show notif dot if chat is closed
            if (!chatWin.classList.contains('open')) {
                document.getElementById('chat-notif-dot').style.display = 'block';
            }

        } catch (err) {
            removeTyping();
            addMessage('bot', err.friendly ? err.message : 'Sorry, something went wrong. Please try again. 🙏');
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

