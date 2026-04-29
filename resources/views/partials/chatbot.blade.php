{{-- ══════════════════════════════════════════ --}}
{{--  VILLA ELENA — AI Chatbot Floating Bubble  --}}
{{-- ══════════════════════════════════════════ --}}

<style>
    :root {
        --navy: #0d1b2a;
        --gold: #c9a84c;
        --gold-light: #e8c97a;
    }

    /* Bubble Button */
    #chat-bubble {
        position: fixed;
        bottom: 28px; right: 28px;
        width: 58px; height: 58px;
        border-radius: 50%;
        background: var(--navy);
        color: var(--gold);
        border: 2px solid var(--gold);
        font-size: 24px;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        z-index: 9999;
        box-shadow: 0 4px 20px rgba(0,0,0,0.25);
        transition: transform .2s;
    }
    #chat-bubble:hover { transform: scale(1.08); }

    /* Chat Window */
    #chat-window {
        position: fixed;
        bottom: 100px; right: 28px;
        width: 360px;
        height: 500px;
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 8px 40px rgba(0,0,0,0.18);
        display: none;
        flex-direction: column;
        z-index: 9998;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    #chat-window.open { display: flex; }

    /* Header */
    .chat-header {
        background: var(--navy);
        padding: 16px 20px;
        display: flex; align-items: center; gap: 12px;
    }
    .chat-avatar {
        width: 38px; height: 38px; border-radius: 50%;
        background: var(--gold);
        color: var(--navy);
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; font-weight: 700; flex-shrink: 0;
    }
    .chat-header-info .name {
        color: #fff; font-size: 14px; font-weight: 600;
    }
    .chat-header-info .status {
        color: var(--gold-light); font-size: 11px;
    }
    .chat-close {
        margin-left: auto; background: none; border: none;
        color: rgba(255,255,255,0.5); font-size: 20px;
        cursor: pointer; line-height: 1;
    }
    .chat-close:hover { color: #fff; }

    /* Messages */
    .chat-messages {
        flex: 1; overflow-y: auto;
        padding: 16px; background: #f8f9fb;
        display: flex; flex-direction: column; gap: 10px;
    }

    .msg { display: flex; gap: 8px; max-width: 85%; }
    .msg.user { align-self: flex-end; flex-direction: row-reverse; }
    .msg.bot  { align-self: flex-start; }

    .msg-bubble {
        padding: 10px 14px;
        border-radius: 14px;
        font-size: 13.5px;
        line-height: 1.55;
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
        width: 28px; height: 28px; border-radius: 50%;
        background: var(--gold); color: var(--navy);
        font-size: 13px; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; margin-top: 2px;
    }

    /* Typing indicator */
    .typing .msg-bubble {
        display: flex; gap: 4px; align-items: center;
        padding: 12px 16px;
    }
    .dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: #94a3b8;
        animation: bounce 1.2s infinite;
    }
    .dot:nth-child(2) { animation-delay: .2s; }
    .dot:nth-child(3) { animation-delay: .4s; }
    @keyframes bounce {
        0%, 80%, 100% { transform: translateY(0); }
        40% { transform: translateY(-6px); }
    }

    /* Input Area */
    .chat-input-area {
        padding: 12px 16px;
        border-top: 1px solid #e2e8f0;
        display: flex; gap: 8px; align-items: center;
        background: #fff;
    }
    .chat-input {
        flex: 1; border: 1px solid #e2e8f0;
        border-radius: 10px; padding: 9px 14px;
        font-size: 13.5px; outline: none;
        font-family: inherit; resize: none;
        max-height: 80px; line-height: 1.4;
        color: #1a2f45;
    }
    .chat-input:focus { border-color: var(--gold); }
    .chat-send {
        width: 38px; height: 38px;
        background: var(--navy); color: var(--gold);
        border: none; border-radius: 10px;
        font-size: 16px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: opacity .2s; flex-shrink: 0;
    }
    .chat-send:hover { opacity: 0.85; }
    .chat-send:disabled { opacity: 0.4; cursor: not-allowed; }
</style>

{{-- Bubble Button --}}
<button id="chat-bubble" title="Chat with Elena">
    <i class="bi bi-stars"></i>
</button>

{{-- Chat Window --}}
<div id="chat-window">
    <div class="chat-header">
        <div class="chat-avatar">E</div>
        <div class="chat-header-info">
            <div class="name">Elena</div>
            <div class="status">Villa Elena AI Assistant</div>
        </div>
        <button class="chat-close" id="chat-close">&times;</button>
    </div>

    <div class="chat-messages" id="chat-messages">
        {{-- Welcome message --}}
        <div class="msg bot">
            <div class="msg-avatar">E</div>
            <div class="msg-bubble">
                Mabuhay! 👋 I'm <strong>Elena</strong>, your Villa Elena assistant. How can I help you today?
            </div>
        </div>
    </div>

    <div class="chat-input-area">
        <textarea class="chat-input" id="chat-input" placeholder="Type your message..." rows="1"></textarea>
        <button class="chat-send" id="chat-send">
            <i class="bi bi-send-fill"></i>
        </button>
    </div>
</div>

<script>
    const bubble   = document.getElementById('chat-bubble');
    const window_  = document.getElementById('chat-window');
    const closeBtn = document.getElementById('chat-close');
    const input    = document.getElementById('chat-input');
    const sendBtn  = document.getElementById('chat-send');
    const messages = document.getElementById('chat-messages');

    let history = [];
    let isLoading = false;

    // Toggle chat window
    bubble.addEventListener('click', () => {
        window_.classList.toggle('open');
        if (window_.classList.contains('open')) input.focus();
    });

    closeBtn.addEventListener('click', () => window_.classList.remove('open'));

    // Auto-resize textarea
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = input.scrollHeight + 'px';
    });

    // Send on Enter (Shift+Enter for new line)
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    sendBtn.addEventListener('click', sendMessage);

    function addMessage(role, text) {
        const isUser = role === 'user';
        const div = document.createElement('div');
        div.className = `msg ${isUser ? 'user' : 'bot'}`;
        div.innerHTML = `
            ${!isUser ? '<div class="msg-avatar">E</div>' : ''}
            <div class="msg-bubble">${text}</div>
        `;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    function showTyping() {
        const div = document.createElement('div');
        div.className = 'msg bot typing';
        div.id = 'typing-indicator';
        div.innerHTML = `
            <div class="msg-avatar">E</div>
            <div class="msg-bubble">
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>
        `;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    function removeTyping() {
        const t = document.getElementById('typing-indicator');
        if (t) t.remove();
    }

    async function sendMessage() {
        const text = input.value.trim();
        if (!text || isLoading) return;

        // Show user message
        addMessage('user', text);
        history.push({ role: 'user', content: text });

        input.value = '';
        input.style.height = 'auto';
        isLoading = true;
        sendBtn.disabled = true;
        showTyping();

        try {
            const res = await fetch('{{ route("chatbot.reply") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({ message: text, history: history.slice(-6) }),
            });

            const data = await res.json();
            const reply = data.reply || 'Sorry, I could not process your request.';

            removeTyping();
            addMessage('bot', reply);
            history.push({ role: 'assistant', content: reply });

        } catch (err) {
            removeTyping();
            addMessage('bot', 'Sorry, something went wrong. Please try again.');
        }

        isLoading = false;
        sendBtn.disabled = false;
        input.focus();
    }
</script>