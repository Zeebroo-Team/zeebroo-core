@extends('theme::layouts.app', ['title' => 'AI Agent · '.$businessLabel, 'heading' => 'AI Agent', 'chatWorkspace' => true])

@section('content')
<div class="aibot-root aibot-root--intro"
     data-business-name="{{ e($businessLabel) }}"
     data-chat-url="{{ route('aibot.chat') }}"
     data-csrf="{{ csrf_token() }}"
     data-tts-available="{{ filter_var(config('aibot.gemini.reply_audio_enabled', false), FILTER_VALIDATE_BOOLEAN) ? '1' : '0' }}">
    <div class="aibot-main">
        <div class="aibot-thread" id="aibot-thread" tabindex="0" aria-live="polite">
            <div class="aibot-welcome" id="aibot-welcome">
                <div class="aibot-welcome-icon" aria-hidden="true">
                    <i class="fa fa-sparkles"></i>
                </div>
                <h1 class="aibot-welcome-title">How can I help today?</h1>
                <p class="aibot-welcome-sub muted">Ask about balances, bills, rentals, loans, ledger activity, or HR. Context: <strong>{{ $businessLabel }}</strong>@if(!$business)<span> — choose a business in the header for company-specific answers.</span>@endif Answers use Google Gemini tools. Bill insertion is supported with a draft + confirmation flow.</p>
                <div class="aibot-suggestions">
                    <button type="button" class="aibot-chip" data-prompt="Summarize cash position for my business">Summarize cash position</button>
                    <button type="button" class="aibot-chip" data-prompt="What HR records should I maintain?">HR compliance tips</button>
                    <button type="button" class="aibot-chip" data-prompt="Explain SociBiz Overview tiles">Explain Overview</button>
                </div>
            </div>
            <div class="aibot-messages" id="aibot-messages" hidden></div>
        </div>

        <div class="aibot-composer-wrap">
            <form class="aibot-composer" id="aibot-form" autocomplete="off">
                @csrf
                <label class="sr-only" for="aibot-input">Message</label>
                <textarea id="aibot-input" class="aibot-input" rows="1" placeholder="Message AI Agent…" maxlength="32000"></textarea>
                <button type="button" class="aibot-mic" id="aibot-mic" aria-pressed="false" aria-label="Record voice message" title="Hold or tap twice: record, then sends when you stop">
                    <i class="fa fa-microphone"></i>
                </button>
                <button type="submit" class="aibot-send" id="aibot-send" aria-label="Send message">
                    <i class="fa fa-arrow-up"></i>
                </button>
            </form>
            <p id="aibot-speak-row" class="aibot-speak-row muted" @unless(filter_var(config('aibot.gemini.reply_audio_enabled', false), FILTER_VALIDATE_BOOLEAN)) hidden @endunless>
                <label class="aibot-read-aloud-label">
                    <input type="checkbox" id="aibot-speak-check" checked>
                    Read replies aloud (Gemini)
                </label>
            </p>
            <p class="aibot-disclaimer muted">AI can make mistakes. Verify important finance or compliance answers.</p>
        </div>
    </div>

    <aside class="aibot-panel-nav" aria-label="Conversations">
        <button type="button" class="aibot-new-chat" id="aibot-new-chat">
            <i class="fa fa-plus"></i><span>New chat</span>
        </button>
        <div class="aibot-history-label muted">Recent</div>
        <ul class="aibot-history-list" id="aibot-history">
            <li><button type="button" class="aibot-history-item is-active"><i class="fa fa-comment"></i><span class="truncate">Workspace assistant</span></button></li>
        </ul>
        <div class="aibot-panel-foot muted">
            <small>{{ $businessLabel }}</small>
        </div>
    </aside>
</div>

<style>
    /* Fill space below navbar; prevents page scroll hiding the composer (thread scrolls only). */
    .aibot-root{
        align-self:stretch;
        width:100%;
        flex:1 1 0;
        display:flex;
        min-height:0;
        max-height:100%;
        overflow:hidden;
    }
    .aibot-panel-nav{
        width:260px;
        flex-shrink:0;
        border-left:1px solid var(--border);
        background:color-mix(in srgb,var(--card) 94%,transparent);
        display:flex;
        flex-direction:column;
        gap:12px;
        padding:14px;
    }
    .aibot-new-chat{
        display:flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        width:100%;
        padding:11px 12px;
        border-radius:12px;
        border:1px solid var(--border);
        background:color-mix(in srgb,var(--card) 88%,transparent);
        color:var(--text);
        font-weight:600;
        font-size:13px;
        cursor:pointer;
        transition:border-color .2s,background .2s;
    }
    .aibot-new-chat:hover{border-color:color-mix(in srgb,var(--primary) 50%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
    .aibot-history-label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;margin:4px 0 0;padding:0 4px;}
    .aibot-history-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:4px;}
    .aibot-history-item{
        width:100%;
        display:flex;
        align-items:center;
        gap:8px;
        padding:9px 10px;
        border:none;
        border-radius:10px;
        background:transparent;
        color:var(--text);
        font-size:13px;
        text-align:left;
        cursor:pointer;
        transition:background .15s;
    }
    .aibot-history-item:hover{background:color-mix(in srgb,var(--primary) 8%,transparent);}
    .aibot-history-item.is-active{background:color-mix(in srgb,var(--primary) 14%,transparent);outline:1px solid color-mix(in srgb,var(--primary) 35%,var(--border));}
    .aibot-history-item i{font-size:12px;color:var(--muted);width:1em;text-align:center;}
    .truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .aibot-panel-foot{margin-top:auto;padding-top:8px;}
    .aibot-main{display:flex;flex-direction:column;flex:1 1 0;min-width:0;min-height:0;max-height:100%;overflow:hidden;}
    .aibot-thread{
        flex:1 1 0;
        min-height:0;
        overflow-y:auto;
        overflow-x:hidden;
        -webkit-overflow-scrolling:touch;
        padding:28px 24px 24px;
        display:flex;
        flex-direction:column;
    }
    .aibot-welcome{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:24px 12px 48px;max-width:520px;margin:0 auto;width:100%;}
    .aibot-welcome-icon{
        width:52px;height:52px;border-radius:16px;
        background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 45%,transparent),color-mix(in srgb,var(--primary) 15%,transparent));
        display:grid;place-items:center;margin-bottom:16px;color:var(--primary);font-size:22px;border:1px solid color-mix(in srgb,var(--primary) 28%,var(--border));
    }
    .aibot-welcome-title{margin:0 0 8px;font-size:clamp(1.35rem,2.5vw,1.65rem);font-weight:680;letter-spacing:-.02em;}
    .aibot-welcome-sub{margin:0 0 20px;line-height:1.5;font-size:14px;}
    .aibot-suggestions{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;}
    .aibot-chip{
        padding:9px 14px;border-radius:999px;font-size:12px;font-weight:500;
        border:1px solid var(--border);background:color-mix(in srgb,var(--card) 85%,transparent);
        color:var(--text);cursor:pointer;transition:all .18s ease;
    }
    .aibot-chip:hover{border-color:color-mix(in srgb,var(--primary) 45%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);}
    .aibot-messages{display:flex;flex-direction:column;gap:14px;max-width:780px;margin:0 auto;width:100%;padding-bottom:24px;}
    .aibot-row{display:flex;gap:10px;width:100%;animation:aibot-enter .26s ease;}
    .aibot-row-user{justify-content:flex-end;}
    .aibot-row-ai{justify-content:flex-start;}
    @keyframes aibot-enter{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:none;}}
    .aibot-avatar{width:34px;height:34px;border-radius:10px;flex-shrink:0;display:grid;place-items:center;font-size:14px;background:color-mix(in srgb,var(--primary) 16%,transparent);border:1px solid color-mix(in srgb,var(--primary) 30%,var(--border));color:var(--primary);}
    .aibot-row-user .aibot-avatar{background:linear-gradient(135deg,var(--btn-bg),color-mix(in srgb,var(--btn-bg) 55%,var(--border)));border-color:transparent;color:#fff;}
    .aibot-msg{max-width:min(560px,calc(100% - 48px));padding:12px 16px;border-radius:16px;font-size:14px;line-height:1.52;white-space:pre-wrap;word-break:break-word;}
    .aibot-msg-user{
        border-bottom-right-radius:6px;background:linear-gradient(135deg,color-mix(in srgb,var(--primary) 32%,transparent),color-mix(in srgb,var(--primary) 12%,transparent));
        border:1px solid color-mix(in srgb,var(--primary) 42%,var(--border));
        color:var(--text);
    }
    .aibot-msg-ai{
        border-bottom-left-radius:6px;background:color-mix(in srgb,var(--card) 96%,#000);border:1px solid var(--border);color:var(--text);margin-left:0;
        white-space:normal;
    }
    .aibot-msg-ai p{margin:0 0 10px}
    .aibot-msg-ai p:last-child{margin-bottom:0}
    .aibot-msg-ai h1,.aibot-msg-ai h2,.aibot-msg-ai h3{margin:6px 0 8px;line-height:1.3}
    .aibot-msg-ai h1{font-size:1.05rem}
    .aibot-msg-ai h2{font-size:1rem}
    .aibot-msg-ai h3{font-size:.95rem}
    .aibot-msg-ai ul,.aibot-msg-ai ol{margin:0 0 10px 18px;padding:0}
    .aibot-msg-ai li{margin:3px 0}
    .aibot-msg-ai pre{margin:8px 0;padding:10px 12px;border-radius:10px;overflow:auto;background:color-mix(in srgb,#0b1220 82%,var(--card));color:#e5e7eb;font-size:12px;line-height:1.45}
    .aibot-msg-ai code{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:.92em}
    .aibot-msg-ai :not(pre)>code{padding:1px 5px;border-radius:6px;background:color-mix(in srgb,var(--primary) 10%,transparent);border:1px solid color-mix(in srgb,var(--primary) 22%,var(--border))}
    .aibot-msg-ai a{color:color-mix(in srgb,var(--primary) 82%,var(--text));text-decoration:none;border-bottom:1px solid color-mix(in srgb,var(--primary) 32%,transparent)}
    .aibot-msg-ai a:hover{color:var(--primary);border-bottom-color:var(--primary)}
    .aibot-composer-wrap{
        flex-shrink:0;
        position:relative;
        z-index:2;
        padding:12px 20px max(14px,env(safe-area-inset-bottom,0px));
        border-top:1px solid var(--border);
        background:color-mix(in srgb,var(--card) 96%,transparent);
        box-shadow:0 -12px 32px rgba(0,0,0,.08);
    }
    .aibot-composer{display:flex;align-items:flex-end;gap:10px;max-width:780px;margin:0 auto;background:color-mix(in srgb,var(--card) 95%,transparent);border:1px solid var(--border);border-radius:18px;padding:10px 10px 10px 16px;}
    .aibot-mic{width:40px;height:40px;border-radius:12px;border:1px solid var(--border);flex-shrink:0;background:color-mix(in srgb,var(--card) 88%,transparent);color:var(--text);cursor:pointer;display:grid;place-items:center;transition:border-color .15s,background .15s,color .15s;}
    .aibot-mic:hover{border-color:color-mix(in srgb,var(--primary) 42%,var(--border));background:color-mix(in srgb,var(--primary) 10%,transparent);color:var(--primary);}
    .aibot-mic.is-recording{border-color:#b91c1c;background:#fee2e2;color:#b91c1c;animation:aibot-rec-pulse 1.2s ease-in-out infinite;}
    @keyframes aibot-rec-pulse{0%,100%{opacity:1;}50%{opacity:.72;}}
    .aibot-speak-row{font-size:12px;margin:6px auto 0;max-width:780px;display:flex;justify-content:center;}
    .aibot-read-aloud-label{display:inline-flex;align-items:center;gap:6px;cursor:pointer;}
    .aibot-read-aloud-label input{accent-color:var(--primary);}
    .aibot-input{
        flex:1;
        resize:none;border:none;background:transparent;color:var(--text);
        font-size:15px;line-height:1.45;max-height:180px;min-height:24px;padding:8px 0;font-family:inherit;
        outline:none;
    }
    .aibot-send{
        width:40px;height:40px;border-radius:12px;border:none;flex-shrink:0;
        background:var(--btn-bg);color:#fff;cursor:pointer;display:grid;place-items:center;transition:transform .15s,background .15s;
    }
    .aibot-send:hover{background:var(--btn-hover);color:#111827;}
    .aibot-send:active{transform:scale(.96);}
    .aibot-disclaimer{text-align:center;font-size:11px;margin:8px 0 0;}
    .aibot-form--busy{opacity:.88;pointer-events:none;}
    .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
    .aibot-typing{display:inline-flex;gap:4px;align-items:center;padding:4px 0;}
    .aibot-typing span{width:6px;height:6px;border-radius:50%;background:var(--muted);animation:aibot-dot 1.2s ease-in-out infinite;}
    .aibot-typing span:nth-child(2){animation-delay:.2s;}
    .aibot-typing span:nth-child(3){animation-delay:.4s;}
    @keyframes aibot-dot{0%,80%,100%{opacity:.35;transform:scale(.88);}40%{opacity:1;transform:scale(1);}}
    @media (max-width:900px){
        .aibot-root{flex-direction:column;}
        .aibot-panel-nav{width:100%;flex-direction:row;flex-wrap:wrap;align-items:center;border-left:0;border-top:1px solid var(--border);}
        .aibot-new-chat{width:auto;flex:1;min-width:140px;}
        .aibot-history-label{display:none;}
        .aibot-history-list{flex-direction:row;flex:1;overflow-x:auto;}
        .aibot-history-item{white-space:nowrap;}
        .aibot-panel-foot{display:none;}
    }

    /* Startup intro (disabled when .aibot-root--intro is removed) */
    @media (prefers-reduced-motion: no-preference) {
        .aibot-root--intro{position:relative;}
        .aibot-root--intro::before{
            content:'';
            position:absolute;inset:0;pointer-events:none;z-index:0;
            background:
                radial-gradient(ellipse 85% 55% at 50% -8%,color-mix(in srgb,var(--primary) 22%,transparent),transparent 58%),
                radial-gradient(ellipse 70% 40% at 92% 88%,color-mix(in srgb,var(--primary) 12%,transparent),transparent 55%),
                radial-gradient(ellipse 50% 35% at 8% 75%,color-mix(in srgb,var(--btn-hover) 10%,transparent),transparent 50%);
            opacity:0;
            animation:aibot-intro-bg 1.15s ease-out forwards;
        }
        .aibot-root--intro > *{position:relative;z-index:1;}
        @keyframes aibot-intro-bg{
            from{opacity:0;filter:blur(8px);}
            35%{opacity:1;filter:blur(0);}
            to{opacity:.55;filter:blur(0);}
        }
        .aibot-root--intro .aibot-main{
            opacity:0;
            transform:translateY(18px) scale(.988);
            animation:aibot-intro-main 0.72s cubic-bezier(.22,1,.36,1) 0.06s forwards;
        }
        @keyframes aibot-intro-main{
            to{opacity:1;transform:translateY(0) scale(1);}
        }
        .aibot-root--intro .aibot-welcome-icon{
            opacity:0;
            transform:scale(.72) rotate(-12deg);
            box-shadow:0 0 0 0 color-mix(in srgb,var(--primary) 35%,transparent);
            animation:aibot-intro-icon 0.85s cubic-bezier(.34,1.56,.64,1) 0.04s forwards,aibot-intro-ring 1.8s ease-out 0.15s 1;
        }
        @keyframes aibot-intro-icon{
            to{opacity:1;transform:scale(1) rotate(0deg);}
        }
        @keyframes aibot-intro-ring{
            0%{box-shadow:0 0 0 0 color-mix(in srgb,var(--primary) 45%,transparent);}
            70%{box-shadow:0 0 0 14px transparent;}
            100%{box-shadow:0 0 0 0 transparent;}
        }
        .aibot-root--intro .aibot-welcome-title{
            opacity:0;
            transform:translateY(14px);
            animation:aibot-intro-fade-up 0.58s cubic-bezier(.22,1,.36,1) 0.18s forwards;
        }
        .aibot-root--intro .aibot-welcome-sub{
            opacity:0;
            transform:translateY(12px);
            animation:aibot-intro-fade-up 0.58s cubic-bezier(.22,1,.36,1) 0.28s forwards;
        }
        .aibot-root--intro .aibot-suggestions{
            opacity:0;
            transform:translateY(10px);
            animation:aibot-intro-fade-up 0.58s cubic-bezier(.22,1,.36,1) 0.4s forwards;
        }
        .aibot-root--intro .aibot-chip{
            opacity:0;
            transform:translateY(8px);
            animation:aibot-intro-chip 0.48s cubic-bezier(.22,1,.36,1) forwards;
        }
        .aibot-root--intro .aibot-chip:nth-child(1){animation-delay:0.48s;}
        .aibot-root--intro .aibot-chip:nth-child(2){animation-delay:0.56s;}
        .aibot-root--intro .aibot-chip:nth-child(3){animation-delay:0.64s;}
        @keyframes aibot-intro-fade-up{
            to{opacity:1;transform:translateY(0);}
        }
        @keyframes aibot-intro-chip{
            to{opacity:1;transform:translateY(0);}
        }
        .aibot-root--intro .aibot-composer-wrap{
            opacity:0;
            transform:translateY(22px);
            animation:aibot-intro-composer 0.65s cubic-bezier(.22,1,.36,1) 0.35s forwards;
        }
        @keyframes aibot-intro-composer{
            to{opacity:1;transform:translateY(0);}
        }
        .aibot-root--intro .aibot-panel-nav{
            opacity:0;
            transform:translateX(22px);
            animation:aibot-intro-panel 0.62s cubic-bezier(.22,1,.36,1) 0.22s forwards;
        }
        @keyframes aibot-intro-panel{
            from{opacity:0;transform:translateX(22px);}
            to{opacity:1;transform:translateX(0);}
        }
    }
    @media (max-width:900px) and (prefers-reduced-motion: no-preference){
        .aibot-root--intro .aibot-panel-nav{
            animation-name:aibot-intro-panel-mob;
        }
        @keyframes aibot-intro-panel-mob{
            from{opacity:0;transform:translateY(16px);}
            to{opacity:1;transform:translateY(0);}
        }
    }
</style>

<script>
(function () {
    const root = document.querySelector('.aibot-root');
    if (!root) return;
    if (root.classList.contains('aibot-root--intro')) {
        var endIntro = function () {
            root.classList.remove('aibot-root--intro');
        };
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            endIntro();
        } else {
            window.setTimeout(endIntro, 1180);
        }
    }
    const chatUrl = root.dataset.chatUrl || '';
    const csrfToken = root.dataset.csrf || '';
    const speakCheckbox = document.getElementById('aibot-speak-check');
    const welcome = document.getElementById('aibot-welcome');
    const messagesEl = document.getElementById('aibot-messages');
    const thread = document.getElementById('aibot-thread');
    const form = document.getElementById('aibot-form');
    const input = document.getElementById('aibot-input');
    const micBtn = document.getElementById('aibot-mic');
    const newChatBtn = document.getElementById('aibot-new-chat');
    let conversation = [];
    let busy = false;
    /** @type {MediaRecorder | null} */
    let voiceRecorder = null;
    /** @type {BlobPart[]} */
    let voiceChunks = [];
    let recordingVoice = false;
    let recordingMimeType = 'audio/webm';

    function escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function renderChatMarkup(text) {
        var src = String(text || '');
        var codeBlocks = [];
        src = src.replace(/```([\s\S]*?)```/g, function (_, code) {
            var idx = codeBlocks.push('<pre><code>' + escapeHtml(String(code).trim()) + '</code></pre>') - 1;
            return '@@CODEBLOCK_' + idx + '@@';
        });

        var lines = src.split(/\r?\n/);
        var out = [];
        var inUl = false;
        var inOl = false;
        var closeLists = function () {
            if (inUl) { out.push('</ul>'); inUl = false; }
            if (inOl) { out.push('</ol>'); inOl = false; }
        };

        for (var i = 0; i < lines.length; i++) {
            var raw = lines[i];
            var trimmed = raw.trim();
            if (trimmed === '') {
                closeLists();
                continue;
            }

            var ulMatch = raw.match(/^\s*[-*]\s+(.+)$/);
            if (ulMatch) {
                if (!inUl) { closeLists(); out.push('<ul>'); inUl = true; }
                out.push('<li>' + escapeHtml(ulMatch[1]) + '</li>');
                continue;
            }

            var olMatch = raw.match(/^\s*\d+\.\s+(.+)$/);
            if (olMatch) {
                if (!inOl) { closeLists(); out.push('<ol>'); inOl = true; }
                out.push('<li>' + escapeHtml(olMatch[1]) + '</li>');
                continue;
            }

            closeLists();
            var hMatch = raw.match(/^\s*(#{1,3})\s+(.+)$/);
            if (hMatch) {
                var lvl = hMatch[1].length;
                out.push('<h' + lvl + '>' + escapeHtml(hMatch[2]) + '</h' + lvl + '>');
            } else {
                out.push('<p>' + escapeHtml(raw) + '</p>');
            }
        }
        closeLists();

        var html = out.join('\n');
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/(^|[\s(])\*(.+?)\*(?=[\s).,!?:;]|$)/g, '$1<em>$2</em>');
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
        html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
        html = html.replace(/(^|[\s>])(https?:\/\/[^\s<]+)/g, '$1<a href="$2" target="_blank" rel="noopener noreferrer">$2</a>');
        html = html.replace(/@@CODEBLOCK_(\d+)@@/g, function (_, n) { return codeBlocks[Number(n)] || ''; });

        return html;
    }

    function hideWelcomeIfNeeded() {
        if (!messagesEl.children.length) return;
        welcome.hidden = true;
        messagesEl.hidden = false;
    }

    function discardActiveVoiceRecording() {
        if (!recordingVoice && !voiceRecorder && !finalizeVoiceRecording._stream) return;

        var stream = finalizeVoiceRecording._stream || null;
        finalizeVoiceRecording._stream = null;
        recordingVoice = false;

        if (voiceRecorder) {
            voiceRecorder.onstop = function () {};
            try {
                if (voiceRecorder.state === 'recording') {
                    voiceRecorder.stop();
                }
            } catch (_) {}
        }

        if (stream && stream.getTracks) {
            stream.getTracks().forEach(function (t) {
                t.stop();
            });
        }

        voiceRecorder = null;
        voiceChunks = [];
        if (micBtn) {
            micBtn.classList.remove('is-recording');
            micBtn.setAttribute('aria-pressed', 'false');
        }
    }

    function showWelcome() {
        discardActiveVoiceRecording();
        welcome.hidden = false;
        messagesEl.hidden = true;
        messagesEl.innerHTML = '';
        conversation = [];
        thread.scrollTop = 0;
        input.focus();
    }

    function appendUserBubble(text) {
        const row = document.createElement('div');
        row.className = 'aibot-row aibot-row-user';
        row.innerHTML =
            '<div class="aibot-msg aibot-msg-user">' + escapeHtml(text) + '</div>' +
            '<div class="aibot-avatar" aria-hidden="true"><i class="fa fa-user"></i></div>';
        messagesEl.appendChild(row);
        hideWelcomeIfNeeded();
        thread.scrollTop = thread.scrollHeight;
        return row;
    }

    function appendTypingRow() {
        const row = document.createElement('div');
        row.className = 'aibot-row aibot-row-ai';
        row.dataset.role = 'typing';
        row.innerHTML =
            '<div class="aibot-avatar" aria-hidden="true"><i class="fa fa-robot"></i></div>' +
            '<div class="aibot-msg aibot-msg-ai"><span class="aibot-typing" aria-hidden="true"><span></span><span></span><span></span></span></div>';
        messagesEl.appendChild(row);
        thread.scrollTop = thread.scrollHeight;
        return row;
    }

    function normalizeSpeakReplyPayload() {
        if (root.dataset.ttsAvailable !== '1' || !speakCheckbox) return {};
        return { speak_reply: !!speakCheckbox.checked };
    }

    function blobToBase64(blob) {
        return new Promise(function (resolve, reject) {
            const reader = new FileReader();
            reader.onloadend = function () {
                const raw = typeof reader.result === 'string' ? reader.result : '';
                const idx = raw.indexOf(',');
                resolve(idx !== -1 ? raw.slice(idx + 1) : raw);
            };
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }

    function chooseRecorderMimeType() {
        if (typeof MediaRecorder === 'undefined' || !MediaRecorder.isTypeSupported) {
            return '';
        }
        var list = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'];
        for (var i = 0; i < list.length; i++) {
            if (MediaRecorder.isTypeSupported(list[i])) return list[i];
        }
        return '';
    }

    function playGeminiVoiceReply(replyAudio) {
        if (!replyAudio || typeof replyAudio.data !== 'string' || !replyAudio.data) return;
        try {
            var url =
                'data:' +
                (replyAudio.mime || 'audio/wav') +
                ';base64,' +
                replyAudio.data;
            var a = new Audio(url);
            a.play().catch(function () {});
        } catch (_) {}
    }

    async function finalizeVoiceRecording() {
        var stream = finalizeVoiceRecording._stream || null;
        finalizeVoiceRecording._stream = null;
        recordingVoice = false;
        if (micBtn) {
            micBtn.classList.remove('is-recording');
            micBtn.setAttribute('aria-pressed', 'false');
        }

        try {
            if (!voiceChunks.length) return;
            var blob = new Blob(voiceChunks, { type: recordingMimeType || 'audio/webm' });
            if (blob.size < 256) return;
            var mime = blob.type || recordingMimeType || 'audio/webm';
            var normalizedMime = mime.indexOf(';') !== -1 ? mime.split(';')[0] : mime;
            var b64 = await blobToBase64(blob);
            await submitPrompt('', { audioBase64: b64, mimeType: normalizedMime });
        } catch (err) {
            window.console.error('voice encode failed', err);
        }

        voiceChunks = [];
        if (stream && stream.getTracks) {
            stream.getTracks().forEach(function (t) {
                t.stop();
            });
        }
        voiceRecorder = null;
    }

    async function toggleVoiceRecording() {
        if (
            typeof navigator.mediaDevices === 'undefined' ||
            !navigator.mediaDevices.getUserMedia ||
            typeof MediaRecorder === 'undefined'
        ) {
            var row = appendTypingRow();
            replaceTypingWithAi('Voice recording is not available in this browser.', row);

            return;
        }
        if (busy) return;

        if (!recordingVoice) {
            try {
                var stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                var mimePick = chooseRecorderMimeType();
                recordingMimeType = mimePick || 'audio/webm';
                voiceChunks = [];
                voiceRecorder =
                    mimePick !== ''
                        ? new MediaRecorder(stream, { mimeType: mimePick })
                        : new MediaRecorder(stream);

                finalizeVoiceRecording._stream = stream;
                recordingMimeType = voiceRecorder.mimeType || recordingMimeType;

                voiceRecorder.ondataavailable = function (ev) {
                    if (ev.data && ev.data.size) voiceChunks.push(ev.data);
                };
                voiceRecorder.onstop = function () {
                    void finalizeVoiceRecording();
                };

                voiceRecorder.start(100);
                recordingVoice = true;
                micBtn.classList.add('is-recording');
                micBtn.setAttribute('aria-pressed', 'true');
            } catch (_) {
                var failRow = appendTypingRow();
                replaceTypingWithAi('Microphone permission is required for voice messages.', failRow);
            }

            return;
        }

        if (voiceRecorder && voiceRecorder.state === 'recording') {
            if (typeof voiceRecorder.requestData === 'function') voiceRecorder.requestData();
            voiceRecorder.stop();
        }
    }

    function replaceTypingWithAi(text, typingRow) {
        const bubble = typingRow.querySelector('.aibot-msg-ai');
        if (bubble) bubble.innerHTML = renderChatMarkup(text);
        typingRow.removeAttribute('data-role');
        thread.scrollTop = thread.scrollHeight;
    }

    function resizeInput() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 180) + 'px';
    }

    function refreshEmptyThreadState() {
        if (!messagesEl.children.length) {
            welcome.hidden = false;
            messagesEl.hidden = true;
        }
    }

    /**
     * @param {{ audioBase64: string, mimeType: string } | undefined} voiceOpts
     */
    async function submitPrompt(text, voiceOpts) {
        const trimmed = text.trim();
        if ((!voiceOpts && !trimmed) || busy || !chatUrl) return;

        var displayUser = voiceOpts ? 'Voice message' : trimmed;

        busy = true;
        form.classList.add('aibot-form--busy');

        if (voiceOpts) {
            conversation.push({
                role: 'user',
                content: trimmed,
                audio: { base64: voiceOpts.audioBase64, mime_type: voiceOpts.mimeType },
            });
        } else {
            conversation.push({ role: 'user', content: trimmed });
            input.value = '';
            resizeInput();
        }

        var userRow = appendUserBubble(displayUser);
        var typingRow = appendTypingRow();
        var requestBody = Object.assign({ messages: conversation }, normalizeSpeakReplyPayload());

        try {
            const res = await fetch(chatUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(requestBody),
            });
            const data = await res.json().catch(function () {
                return {};
            });

            if (!res.ok) {
                const flattened =
                    data && data.errors && typeof data.errors === 'object'
                        ? Object.values(data.errors).flat().find(Boolean)
                        : null;
                const msg =
                    (data && data.message) ||
                    (data && data.error) ||
                    flattened ||
                    (res.status === 429 ? 'Too many requests. Wait a moment and try again.' : 'Request failed (' + res.status + ').');
                conversation.pop();
                typingRow.remove();
                userRow.remove();
                refreshEmptyThreadState();
                appendUserBubble(displayUser);
                const errTyping = appendTypingRow();
                replaceTypingWithAi(msg, errTyping);
                conversation.push({ role: 'user', content: displayUser });

                return;
            }

            const reply = (typeof data.reply === 'string' ? data.reply : '').trim();
            if (!reply) {
                const msg =
                    (data && data.message) ||
                    (data && data.error) ||
                    'No reply from the agent.';
                replaceTypingWithAi(msg, typingRow);
                var lastEmpty = conversation.length - 1;
                if (
                    lastEmpty >= 0 &&
                    conversation[lastEmpty].role === 'user' &&
                    conversation[lastEmpty].audio
                ) {
                    conversation[lastEmpty] = { role: 'user', content: '(Voice question)' };
                }
                return;
            }

            conversation.push({ role: 'assistant', content: reply });
            replaceTypingWithAi(reply, typingRow);
            playGeminiVoiceReply(data.reply_audio);

            var uIdx = conversation.length - 2;
            if (uIdx >= 0 && conversation[uIdx].role === 'user' && conversation[uIdx].audio) {
                conversation[uIdx] = { role: 'user', content: '(Voice question)' };
            }
        } catch (err) {
            conversation.pop();
            typingRow.remove();
            userRow.remove();
            refreshEmptyThreadState();
            appendUserBubble(displayUser);
            const errTyping = appendTypingRow();
            replaceTypingWithAi('Network error: ' + (err && err.message ? err.message : 'unknown'), errTyping);
            conversation.push({ role: 'user', content: displayUser });
        } finally {
            busy = false;
            form.classList.remove('aibot-form--busy');
            input.focus();
        }
    }

    input.addEventListener('input', resizeInput);

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitPrompt(input.value);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            submitPrompt(input.value);
        }
    });

    document.querySelectorAll('.aibot-chip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const p = btn.getAttribute('data-prompt') || '';
            if (p) submitPrompt(p);
        });
    });

    newChatBtn.addEventListener('click', showWelcome);

    if (micBtn) {
        micBtn.addEventListener('click', function () {
            void toggleVoiceRecording();
        });
    }

    resizeInput();
})();
</script>
@endsection
