/**
 * ChatBot Futurista — Portfolio ENGH
 * Robot estilo cat-bot con base holografica, animaciones neon, accesibilidad auditiva
 * VERSION CORREGIDA PARA PRODUCCION
 */

class ChatBot {
    constructor() {
        this.container       = null;
        this.chatWindow      = null;
        this.messagesContainer = null;
        this.input           = null;
        this.isOpen          = false;
        this.isTyping        = false;
        this.visitorId       = null;
        this.sessionId       = localStorage.getItem('chat_session') || this.genId();
        this.messageHistory  = [];
        this.currentLang     = localStorage.getItem('portfolio_lang') || document.documentElement.lang || 'en';
        localStorage.setItem('chat_session', this.sessionId);
        this.init();
    }

    genId() { return 'cs_' + Date.now() + '_' + Math.random().toString(36).substr(2,9); }

    init() {
        this.injectStyles();

        // Esperar visitorId con fallback
        window.addEventListener('visitorRegistered', e => { this.visitorId = e.detail.visitorId; });

        setTimeout(() => {
            if (!this.visitorId) {
                const saved = this.getCookie('portfolio_visitor');
                if (saved) { 
                    try { this.visitorId = JSON.parse(saved).visitor_id; } catch(e){} 
                }
            }
        }, 2000);

        this.buildWidget();
        this.bindEvents();
        setTimeout(() => this.showWelcome(), 4000);
        // Escuchar cambio de idioma
        document.addEventListener('langchange', (e) => {
            this.currentLang = e.detail.lang;
             if (this.messagesContainer) {
                const messages = this.messagesContainer.querySelectorAll('.chat-message');
                if (messages.length <= 1) {
                    this.messagesContainer.innerHTML = '';
                    this.messageHistory = [];
                    this.showWelcome();
                }
            }
        });
    }

    // ============================================================
    // SVG CAT-BOT CON BASE HOLOGRAFICA (CORREGIDO - coincide con imagen)
    // ============================================================
    robotSVGBubble() {
        const uid = Math.random().toString(36).substr(2,5);
        return `<svg width="70" height="98" viewBox="0 0 100 140" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;overflow:visible;filter:drop-shadow(0 0 8px rgba(56,189,248,0.4))">
            <defs>
                <radialGradient id="metalBody_${uid}" cx="35%" cy="25%" r="75%">
                    <stop offset="0%" stop-color="#FFFFFF"/>
                    <stop offset="25%" stop-color="#E8F4FF"/>
                    <stop offset="60%" stop-color="#B8D4F0"/>
                    <stop offset="100%" stop-color="#7BB3D4"/>
                </radialGradient>
                <linearGradient id="visorMask_${uid}" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stop-color="#1E3A5F"/>
                    <stop offset="50%" stop-color="#0D1F3C"/>
                    <stop offset="100%" stop-color="#050A14"/>
                </linearGradient>
                <radialGradient id="holoBase_${uid}" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" stop-color="#38BDF8" stop-opacity="0.7"/>
                    <stop offset="40%" stop-color="#1A8ACB" stop-opacity="0.4"/>
                    <stop offset="100%" stop-color="#0A1628" stop-opacity="0"/>
                </radialGradient>
                <filter id="glow_${uid}" x="-50%" y="-50%" width="200%" height="200%">
                    <feGaussianBlur stdDeviation="2" result="blur"/>
                    <feComposite in="SourceGraphic" in2="blur" operator="over"/>
                </filter>
                <filter id="glowStrong_${uid}" x="-50%" y="-50%" width="200%" height="200%">
                    <feGaussianBlur stdDeviation="3" result="blur"/>
                    <feComposite in="SourceGraphic" in2="blur" operator="over"/>
                </filter>
                <filter id="glowMega_${uid}" x="-100%" y="-100%" width="300%" height="300%">
                    <feGaussianBlur stdDeviation="5" result="blur"/>
                    <feComposite in="SourceGraphic" in2="blur" operator="over"/>
                </filter>
            </defs>

            <!-- Base Holografica -->
            <g class="robot-base">
                <ellipse cx="50" cy="130" rx="40" ry="8" fill="#38BDF8" opacity="0.3" filter="url(#glowMega_${uid})"/>
                <ellipse cx="50" cy="128" rx="36" ry="7" fill="url(#holoBase_${uid})"/>
                <ellipse cx="50" cy="128" rx="34" ry="6" fill="none" stroke="#38BDF8" stroke-width="2" opacity="0.9"/>
                <ellipse cx="50" cy="128" rx="26" ry="4.5" fill="none" stroke="#7C3AED" stroke-width="1.5" opacity="0.7"/>
                <ellipse cx="50" cy="128" rx="18" ry="3" fill="none" stroke="#10B981" stroke-width="1" opacity="0.8"/>
                <line x1="18" y1="128" x2="82" y2="128" stroke="#38BDF8" stroke-width="1" opacity="0.6"/>
                <circle cx="25" cy="124" r="1.5" fill="#38BDF8" opacity="0.8" filter="url(#glow_${uid})"/>
                <circle cx="75" cy="126" r="1.2" fill="#7C3AED" opacity="0.7" filter="url(#glow_${uid})"/>
                <circle cx="50" cy="122" r="1.8" fill="#10B981" opacity="0.6" filter="url(#glow_${uid})"/>
            </g>

            <!-- Cuerpo del Robot -->
            <g class="robot-body">
                <!-- Cuerpo principal -->
                <path d="M8 55 Q5 68 8 78 Q12 88 22 92 Q35 96 50 96 Q65 96 78 92 Q88 88 92 78 Q95 68 92 55 Q88 45 78 42 Q65 38 50 38 Q35 38 22 42 Q12 45 8 55 Z" fill="url(#metalBody_${uid})" stroke="#7BB3D4" stroke-width="1.5"/>
                <ellipse cx="35" cy="60" rx="20" ry="14" fill="#FFFFFF" opacity="0.25"/>

                <!-- Detalle cuello -->
                <path d="M30 42 Q50 36 70 42 L68 48 Q50 44 32 48 Z" fill="#0A1628" opacity="0.95"/>
                <path d="M32 45 Q50 41 68 45" stroke="#38BDF8" stroke-width="1.5" fill="none" opacity="0.7"/>

                <!-- Pecho/centro -->
                <circle cx="50" cy="72" r="8" fill="#0A1628" stroke="#38BDF8" stroke-width="1.5"/>
                <circle cx="50" cy="72" r="5" fill="#38BDF8" opacity="0.5" filter="url(#glow_${uid})"/>
                <circle cx="50" cy="72" r="2" fill="#FFFFFF" opacity="0.9"/>

                <!-- Brazos -->
                <path d="M8 58 Q-2 62 2 75 Q5 85 12 82" fill="url(#metalBody_${uid})" stroke="#7BB3D4" stroke-width="1.2"/>
                <path d="M92 58 Q102 62 98 75 Q95 85 88 82" fill="url(#metalBody_${uid})" stroke="#7BB3D4" stroke-width="1.2"/>
                <circle cx="4" cy="68" r="2.5" fill="#38BDF8" opacity="0.7" filter="url(#glow_${uid})"/>
                <circle cx="96" cy="68" r="2.5" fill="#38BDF8" opacity="0.7" filter="url(#glow_${uid})"/>

                <!-- Cabeza -->
                <path d="M15 22 Q15 2 50 2 Q85 2 85 22 Q85 38 76 45 Q65 52 50 52 Q35 52 24 45 Q15 38 15 22 Z" fill="url(#metalBody_${uid})" stroke="#7BB3D4" stroke-width="1.5"/>
                <ellipse cx="38" cy="10" rx="22" ry="10" fill="#FFFFFF" opacity="0.35"/>
                <ellipse cx="42" cy="14" rx="12" ry="5" fill="#FFFFFF" opacity="0.15"/>

                <!-- OREJAS DE GATO (nuevo - coincide con imagen) -->
                <path d="M18 15 L10 -8 L32 8 Z" fill="url(#metalBody_${uid})" stroke="#7BB3D4" stroke-width="1.2"/>
                <path d="M17 12 L12 -4 L28 6 Z" fill="#1E3A5F" opacity="0.5"/>
                <circle cx="10" cy="-8" r="3" fill="#38BDF8" filter="url(#glowStrong_${uid})"/>
                <path d="M82 15 L90 -8 L68 8 Z" fill="url(#metalBody_${uid})" stroke="#7BB3D4" stroke-width="1.2"/>
                <path d="M83 12 L88 -4 L72 6 Z" fill="#1E3A5F" opacity="0.5"/>
                <circle cx="90" cy="-8" r="3" fill="#38BDF8" filter="url(#glowStrong_${uid})"/>

                <!-- Visor oscuro -->
                <path d="M18 18 Q18 10 50 10 Q82 10 82 18 Q82 30 74 38 Q62 44 50 44 Q38 44 26 38 Q18 30 18 18 Z" fill="url(#visorMask_${uid})" stroke="#38BDF8" stroke-width="1" opacity="0.95"/>
                <path d="M20 15 Q50 12 80 15" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" opacity="0.25" fill="none"/>
                <path d="M22 17 Q50 14 78 17" stroke="#38BDF8" stroke-width="0.8" stroke-linecap="round" opacity="0.4" fill="none"/>

                <!-- Ojos con forma de sonrisa (nuevo - coincide con imagen) -->
                <path d="M28 24 Q38 16 48 24 Q48 28 38 31 Q28 28 28 24 Z" fill="#0A1628"/>
                <path d="M29 24 Q38 17 47 24 Q47 27 38 30 Q29 27 29 24 Z" fill="#38BDF8" filter="url(#glow_${uid})"/>
                <circle cx="34" cy="21" r="2" fill="#FFFFFF" opacity="0.95"/>
                <circle cx="36" cy="19" r="0.8" fill="#FFFFFF" opacity="0.8"/>
                <path d="M52 24 Q62 16 72 24 Q72 28 62 31 Q52 28 52 24 Z" fill="#0A1628"/>
                <path d="M53 24 Q62 17 71 24 Q71 27 62 30 Q53 27 53 24 Z" fill="#38BDF8" filter="url(#glow_${uid})"/>
                <circle cx="66" cy="21" r="2" fill="#FFFFFF" opacity="0.95"/>
                <circle cx="68" cy="19" r="0.8" fill="#FFFFFF" opacity="0.8"/>

                <!-- Boca sonrisa -->
                <path d="M44 38 Q50 42 56 38" stroke="#38BDF8" stroke-width="1.5" stroke-linecap="round" fill="none" opacity="0.8"/>
                <circle cx="50" cy="35" r="1.5" fill="#38BDF8" opacity="0.6"/>

                <!-- Antena central -->
                <line x1="50" y1="2" x2="50" y2="-10" stroke="#7BB3D4" stroke-width="2" stroke-linecap="round"/>
                <circle cx="50" cy="-12" r="4" fill="#38BDF8" filter="url(#glowStrong_${uid})"/>
                <circle cx="50" cy="-12" r="2" fill="#FFFFFF" opacity="0.9"/>
                <path d="M25 -2 Q50 -12 75 -2" stroke="#38BDF8" stroke-width="1.5" stroke-linecap="round" fill="none" opacity="0.5"/>
                <path d="M20 -8 Q50 -22 80 -8" stroke="#38BDF8" stroke-width="1" stroke-linecap="round" fill="none" opacity="0.3"/>
                <path d="M30 8 Q50 2 70 8" stroke="#7C3AED" stroke-width="0.8" stroke-linecap="round" fill="none" opacity="0.4"/>

                <!-- Brillo inferior -->
                <ellipse cx="50" cy="96" rx="28" ry="5" fill="#38BDF8" opacity="0.2" filter="url(#glow_${uid})"/>
            </g>
        </svg>`;
    }

    robotSVGAvatar(w, h) {
        const uid = Math.random().toString(36).substr(2,5);
        return `<svg width="${w}" height="${h}" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block">
            <defs>
                <radialGradient id="metalBodyA_${uid}" cx="40%" cy="30%" r="70%">
                    <stop offset="0%" stop-color="#FFFFFF"/>
                    <stop offset="30%" stop-color="#F0F8FF"/>
                    <stop offset="60%" stop-color="#D0E8F8"/>
                    <stop offset="100%" stop-color="#8BB8D8"/>
                </radialGradient>
                <linearGradient id="visorMaskA_${uid}" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" stop-color="#1E3A5F"/>
                    <stop offset="50%" stop-color="#0D1F3C"/>
                    <stop offset="100%" stop-color="#050A14"/>
                </linearGradient>
                <filter id="glowA_${uid}" x="-50%" y="-50%" width="200%" height="200%">
                    <feGaussianBlur stdDeviation="1.5" result="blur"/>
                    <feComposite in="SourceGraphic" in2="blur" operator="over"/>
                </filter>
            </defs>

            <!-- Cabeza -->
            <path d="M25 25 Q25 10 50 10 Q75 10 75 25 Q75 38 68 43 Q60 47 50 47 Q40 47 32 43 Q25 38 25 25 Z" fill="url(#metalBodyA_${uid})" stroke="#7BB3D4" stroke-width="1"/>
            <ellipse cx="42" cy="15" rx="15" ry="6" fill="#FFFFFF" opacity="0.25"/>

            <!-- Orejas -->
            <path d="M28 15 L23 3 L35 12 Z" fill="url(#metalBodyA_${uid})" stroke="#7BB3D4" stroke-width="0.8"/>
            <path d="M72 15 L77 3 L65 12 Z" fill="url(#metalBodyA_${uid})" stroke="#7BB3D4" stroke-width="0.8"/>
            <circle cx="23" cy="3" r="1.5" fill="#38BDF8" filter="url(#glowA_${uid})"/>
            <circle cx="77" cy="3" r="1.5" fill="#38BDF8" filter="url(#glowA_${uid})"/>

            <!-- Visor -->
            <path d="M28 20 Q28 15 50 15 Q72 15 72 20 Q72 28 66 33 Q58 36 50 36 Q42 36 34 33 Q28 28 28 20 Z" fill="url(#visorMaskA_${uid})" stroke="#38BDF8" stroke-width="0.6"/>

            <!-- Ojos sonrientes -->
            <path d="M35 24 Q40 20 45 24 Q45 26 40 27 Q35 26 35 24 Z" fill="#38BDF8" filter="url(#glowA_${uid})"/>
            <path d="M55 24 Q60 20 65 24 Q65 26 60 27 Q55 26 55 24 Z" fill="#38BDF8" filter="url(#glowA_${uid})"/>
            <circle cx="38" cy="23" r="1" fill="#FFFFFF" opacity="0.9"/>
            <circle cx="62" cy="23" r="1" fill="#FFFFFF" opacity="0.9"/>

            <!-- Boca -->
            <path d="M47 38 Q50 40 53 38" stroke="#38BDF8" stroke-width="0.8" stroke-linecap="round" fill="none" opacity="0.6"/>

            <!-- Cuello -->
            <rect x="42" y="45" width="16" height="4" rx="2" fill="#0A1628" opacity="0.8"/>
            <path d="M44 47 Q50 45 56 47" stroke="#38BDF8" stroke-width="0.5" fill="none"/>

            <!-- Cuerpo -->
            <path d="M30 50 Q28 58 30 65 Q32 72 40 74 Q50 76 60 74 Q68 72 70 65 Q72 58 70 50 Q67 45 60 43 Q50 42 40 43 Q33 45 30 50 Z" fill="url(#metalBodyA_${uid})" stroke="#7BB3D4" stroke-width="0.8"/>
            <circle cx="50" cy="58" r="4" fill="#0A1628" stroke="#38BDF8" stroke-width="0.5"/>
            <circle cx="50" cy="58" r="2" fill="#38BDF8" opacity="0.6" filter="url(#glowA_${uid})"/>

            <!-- Brazos -->
            <path d="M28 52 Q22 55 24 62 Q25 66 28 64" fill="url(#metalBodyA_${uid})" stroke="#7BB3D4" stroke-width="0.6"/>
            <path d="M72 52 Q78 55 76 62 Q75 66 72 64" fill="url(#metalBodyA_${uid})" stroke="#7BB3D4" stroke-width="0.6"/>

            <!-- Antena -->
            <line x1="50" y1="10" x2="50" y2="4" stroke="#7BB3D4" stroke-width="1"/>
            <circle cx="50" cy="3" r="2" fill="#38BDF8" filter="url(#glowA_${uid})"/>
        </svg>`;
    }

    injectStyles() {
        const css = `
        .chatbot-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9500;
            font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        #chatToggle {
            position: relative;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            background: linear-gradient(135deg, #0D1F3C 0%, #1A3A5C 50%, #0D1F3C 100%);
            box-shadow: 0 0 0 0 rgba(56,189,248,0.4),
                        0 8px 32px rgba(0,0,0,0.5),
                        inset 0 1px 0 rgba(255,255,255,0.1);
            animation: bubblePulse 3s infinite ease-in-out;
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
            overflow: visible;
        }

        @keyframes bubblePulse {
            0%,100% {
                box-shadow: 0 0 0 0 rgba(56,189,248,0.5),
                            0 8px 32px rgba(0,0,0,0.5);
            }
            50% {
                box-shadow: 0 0 0 12px rgba(56,189,248,0),
                            0 0 40px rgba(56,189,248,0.3),
                            0 8px 32px rgba(0,0,0,0.5);
            }
        }

        #chatToggle:hover {
            animation-play-state: paused;
            transform: scale(1.12) translateY(-4px);
            box-shadow: 0 0 30px rgba(56,189,248,0.6),
                        0 12px 40px rgba(0,0,0,0.6);
        }

        .robot-bubble-wrapper {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 70px;
            height: 98px;
            pointer-events: none;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .robot-bubble-wrapper svg {
            animation: robotFloat 3s ease-in-out infinite, robotSpin360 8s linear infinite;
            transform-style: preserve-3d;
        }

        @keyframes robotFloat {
            0%, 100% { transform: translateY(0) rotateY(0deg); }
            50% { transform: translateY(-6px) rotateY(180deg); }
        }

        @keyframes robotSpin360 {
            0% { transform: rotateY(0deg); }
            100% { transform: rotateY(360deg); }
        }

        #chatToggle:hover .robot-bubble-wrapper svg {
            animation-play-state: paused;
        }

        #chatToggle::before {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            border: 2px solid transparent;
            background: linear-gradient(135deg, #38BDF8, #7C3AED, #10B981, #38BDF8) border-box;
            -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: destination-out;
            mask-composite: exclude;
            animation: ringRotate 4s linear infinite;
        }

        @keyframes ringRotate {
            to { transform: rotate(360deg); }
        }

        .chat-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 22px;
            height: 22px;
            background: #FC8181;
            border-radius: 50%;
            font-size: 11px;
            font-weight: 700;
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #070B14;
            z-index: 10;
            animation: badgePulse 2s ease-in-out infinite;
        }

        @keyframes badgePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .bubble-tooltip {
            position: absolute;
            bottom: calc(100% + 12px);
            right: 0;
            background: #0F172A;
            color: #F8FAFC;
            font-size: 11px;
            font-weight: 600;
            padding: 7px 12px;
            border-radius: 8px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s, transform 0.2s;
            transform: translateY(6px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            border: 1px solid rgba(56,189,248,0.2);
            z-index: 99999;
        }
        #chatToggle:hover .bubble-tooltip {
            opacity: 1;
            transform: translateY(0);
        }

        .chat-window {
            position: absolute;
            bottom: 82px;
            right: 0;
            width: 350px;
            max-height: 520px;
            background: #0D1321;
            border: 1px solid rgba(56,189,248,0.15);
            border-radius: 18px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.7),
                        0 0 0 1px rgba(56,189,248,0.08);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            opacity: 0;
            transform: scale(0.92) translateY(12px);
            transform-origin: bottom right;
            pointer-events: none;
            transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.34,1.56,0.64,1);
        }
        .chat-window.active {
            opacity: 1;
            transform: scale(1) translateY(0);
            pointer-events: all;
        }

        .chat-header {
            padding: 14px 16px;
            background: linear-gradient(135deg, #0A1628 0%, #1A3A5C 100%);
            border-bottom: 1px solid rgba(56,189,248,0.12);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .chat-header-info { display: flex; align-items: center; gap: 10px; }
        .chat-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0D1F3C, #1A3A5C);
            border: 2px solid rgba(56,189,248,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 0 12px rgba(56,189,248,0.2);
        }
        .chat-header-text h3 {
            font-size: 14px;
            font-weight: 700;
            color: #E8F0FE;
            margin: 0;
        }
        .chat-header-text p {
            font-size: 11px;
            color: #38BDF8;
            margin: 2px 0 0;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .chat-header-text p::before {
            content: '';
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #10B981;
            animation: onlineBlink 2s ease-in-out infinite;
        }
        @keyframes onlineBlink {
            0%,100% { opacity: 1; }
            50%      { opacity: 0.4; }
        }
        .chat-close {
            background: none;
            border: none;
            color: rgba(255,255,255,0.4);
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            transition: all 0.2s;
            display: flex;
        }
        .chat-close:hover { background: rgba(255,255,255,0.08); color: #fff; }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            scroll-behavior: smooth;
        }
        .chat-messages::-webkit-scrollbar { width: 4px; }
        .chat-messages::-webkit-scrollbar-thumb {
            background: rgba(56,189,248,0.2);
            border-radius: 2px;
        }

        .chat-message { display: flex; gap: 8px; align-items: flex-end; }
        .chat-message.bot  { flex-direction: row; }
        .chat-message.user { flex-direction: row-reverse; }

        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .message-content { display: flex; flex-direction: column; gap: 4px; max-width: 78%; }

        .message-bubble {
            padding: 10px 14px;
            border-radius: 16px;
            font-size: 13px;
            line-height: 1.55;
            word-break: break-word;
        }
        .chat-message.bot .message-bubble {
            background: #131C30;
            color: #E8F0FE;
            border: 1px solid rgba(56,189,248,0.12);
            border-radius: 4px 16px 16px 16px;
        }
        .chat-message.user .message-bubble {
            background: linear-gradient(135deg, #1A4A8C, #38BDF8);
            color: #fff;
            border-radius: 16px 4px 16px 16px;
        }

        .message-time {
            font-size: 10px;
            color: #4A6080;
            font-family: 'IBM Plex Mono', monospace;
        }
        .chat-message.user .message-time { text-align: right; }

        .typing-indicator { display: flex; gap: 5px; align-items: center; padding: 4px 0; }
        .typing-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #38BDF8;
            animation: typingBounce 1s ease-in-out infinite;
        }
        .typing-dot:nth-child(2) { animation-delay: 0.15s; }
        .typing-dot:nth-child(3) { animation-delay: 0.3s; }
        @keyframes typingBounce {
            0%,100% { transform: translateY(0); opacity: 0.4; }
            50%      { transform: translateY(-6px); opacity: 1; }
        }

        .chat-suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        .suggestion-chip {
            background: rgba(56,189,248,0.1);
            border: 1px solid rgba(56,189,248,0.25);
            color: #38BDF8;
            font-size: 11px;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'IBM Plex Sans', sans-serif;
        }
        .suggestion-chip:hover {
            background: #38BDF8;
            color: #0A1628;
            transform: translateY(-1px);
        }

        .chat-input-container {
            padding: 12px;
            border-top: 1px solid rgba(56,189,248,0.1);
            display: flex;
            gap: 8px;
            align-items: flex-end;
            background: #0A1628;
            flex-shrink: 0;
        }
        .chat-input {
            flex: 1;
            background: #131C30;
            border: 1px solid rgba(56,189,248,0.15);
            border-radius: 12px;
            padding: 9px 13px;
            color: #E8F0FE;
            font-family: 'IBM Plex Sans', sans-serif;
            font-size: 13px;
            resize: none;
            outline: none;
            max-height: 100px;
            transition: border-color 0.2s;
            line-height: 1.5;
        }
        .chat-input:focus { border-color: rgba(56,189,248,0.4); }
        .chat-input::placeholder { color: #4A6080; }

        .chat-send {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #38BDF8, #1A8ACB);
            border: none;
            color: #0A1628;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.22s;
            flex-shrink: 0;
        }
        .chat-send:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 16px rgba(56,189,248,0.5);
        }
        .chat-send svg { width: 16px; height: 16px; }

        @media (max-width: 400px) {
            .chat-window { width: calc(100vw - 20px); right: -4px; }
            .chatbot-container { right: 12px; bottom: 12px; }
        }
        `;

        const el = document.createElement('style');
        el.textContent = css;
        document.head.appendChild(el);
    }

    buildWidget() {
        const html = `
        <div class="chatbot-container" id="chatbotContainer">
            <button class="chat-toggle" id="chatToggle"
                    aria-label="Open AI Assistant — click to chat"
                    aria-expanded="false">
                <div class="robot-bubble-wrapper">
                    ${this.robotSVGBubble()}
                </div>
                <span class="chat-badge" id="chatBadge" style="display:none">1</span>
                <span class="bubble-tooltip">AI Assistant — Click to chat</span>
            </button>

            <div class="chat-window" id="chatWindow">
                <div class="chat-header">
                    <div class="chat-header-info">
                        <div class="chat-avatar">
                            ${this.robotSVGAvatar(28, 28)}
                        </div>
                        <div class="chat-header-text">
                            <h3 data-en="AI Assistant" data-es="Asistente Virtual">AI Assistant</h3>
                            <p data-en="Online now" data-es="En linea ahora">Online now</p>
                        </div>
                    </div>
                    <button class="chat-close" id="chatClose" aria-label="Close chat">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.5">
                            <path d="M18 6L6 18M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="chat-messages" id="chatMessages"
                     role="log" aria-live="polite" aria-label="Chat messages"></div>

                <div class="chat-input-container">
                    <textarea class="chat-input" id="chatInput"
                              placeholder="Type your message..." rows="1"
                              aria-label="Type a message"></textarea>
                    <button class="chat-send" id="chatSend" aria-label="Send message">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>`;

        document.body.insertAdjacentHTML('beforeend', html);
        this.container        = document.getElementById('chatbotContainer');
        this.chatWindow       = document.getElementById('chatWindow');
        this.messagesContainer = document.getElementById('chatMessages');
        this.input            = document.getElementById('chatInput');
    }

    bindEvents() {
        document.getElementById('chatToggle').addEventListener('click', () => this.toggle());
        document.getElementById('chatClose').addEventListener('click', () => this.close());
        document.getElementById('chatSend').addEventListener('click', () => this.sendMessage());
        this.input.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.sendMessage(); }
        });
        this.input.addEventListener('input', () => {
            this.input.style.height = 'auto';
            this.input.style.height = this.input.scrollHeight + 'px';
        });
    }

    toggle() { this.isOpen ? this.close() : this.open(); }

    open() {
        this.chatWindow.classList.add('active');
        document.getElementById('chatToggle').setAttribute('aria-expanded','true');
        document.getElementById('chatBadge').style.display = 'none';
        this.isOpen = true;
        setTimeout(() => this.input.focus(), 300);
        this.scrollToBottom();
    }

    close() {
        this.chatWindow.classList.remove('active');
        document.getElementById('chatToggle').setAttribute('aria-expanded','false');
        this.isOpen = false;
    }

    showWelcome() {
        if (this.isOpen) return;
        const lang = this.currentLang || localStorage.getItem('portfolio_lang') || document.documentElement.lang || 'en';
        const es = lang === 'es';
        this.addMessage(
            es ? 'Hola! 👋 Soy el asistente de **Nicolas Guamialama**, Ingeniero en TI.\n\nPuedo ayudarte a conocer sus servicios, ver proyectos, agendar una reunión o responder preguntas técnicas.\n\n¿Cómo puedo ayudarte hoy?'
               : "Hello! 👋 I'm the assistant for **Nicolas Guamialama**, IT Engineer.\n\nI can help you learn about his services, view projects, schedule a meeting, or answer technical questions.\n\nHow can I help you today?",
            'bot',
            es ? ['¿Que servicios ofreces?', 'Agendar reunión', 'Ver proyectos']
               : ['What services do you offer?', 'Schedule a meeting', 'View projects']
        );
        document.getElementById('chatBadge').style.display = 'flex';
    }

    async sendMessage() {
        const message = this.input.value.trim();
        if (!message || this.isTyping) return;

        this.input.value = '';
        this.input.style.height = 'auto';
        this.addMessage(message, 'user');
        this.showTyping();

        try {
            const res = await fetch('./api/chatbot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    visitor_id: this.visitorId,
                    session_id: this.sessionId,
                    message:    message,
                    lang:       document.documentElement.lang || 'en',
                    language:   document.documentElement.lang || 'en',
                })
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);

            const result = await res.json();
            this.hideTyping();

            if (result.success) {
                const reply = result.reply || result.data;
                const text  = reply?.text || reply?.message || 'How can I help?';
                const chips = reply?.quick_replies?.map(r => r.label || r) || result.data?.suggestions || [];
                this.addMessage(text, 'bot', chips, reply?.action || result.data?.action);
                if (reply?.action || result.data?.action) {
                    this.executeAction(reply?.action || result.data?.action);
                }
            } else {
                this.addMessage('Sorry, I encountered an error. Please try again.', 'bot');
            }
        } catch (err) {
            console.error('Chatbot error:', err);
            this.hideTyping();
            this.addMessage("Sorry, I'm having trouble connecting. Try WhatsApp: +1 (575) 888-5484", 'bot');
        }
    }

    addMessage(text, sender, suggestions = null, action = null) {
        const time      = new Date().toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
        const formatted = this.format(text);
        const cleanText = formatted.replace(/<[^>]+>/g, '');

        const avatarHTML = sender === 'bot'
            ? `<div class="message-avatar" style="background:linear-gradient(135deg,#0D1F3C,#1A3A5C);border:1px solid rgba(56,189,248,0.2);box-shadow:0 0 8px rgba(56,189,248,0.1)" aria-hidden="true">
                   ${this.robotSVGAvatar(22, 22)}
               </div>`
            : `<div class="message-avatar" aria-hidden="true"
                    style="background:linear-gradient(135deg,#1A4A8C,#38BDF8);
                           font-size:14px;font-weight:700;color:#fff">
                   <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                       <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                   </svg>
               </div>`;

        const html = `
            <div class="chat-message ${sender}">
                ${avatarHTML}
                <div class="message-content">
                    <div class="message-bubble"
                         ${sender==='bot' ? `role="status" aria-live="polite" aria-label="Assistant: ${cleanText}"` : ''}>
                        ${formatted}
                    </div>
                    <div class="message-time">${time}</div>
                    ${suggestions ? this.buildSuggestions(suggestions) : ''}
                </div>
            </div>`;

        this.messagesContainer.insertAdjacentHTML('beforeend', html);
        this.messageHistory.push({ text, sender, time });

        if (suggestions) {
            setTimeout(() => {
                this.messagesContainer.querySelectorAll('.suggestion-chip').forEach(chip => {
                    if (!chip.dataset.bound) {
                        chip.dataset.bound = '1';
                        chip.addEventListener('click', () => {
                            const val = chip.dataset.suggestion || '';
                            if (val.toLowerCase().includes('calendly')) {
                                window.open('https://calendly.com/edhissonguami', '_blank');
                                return;
                            }
                            this.input.value = val;
                            this.sendMessage();
                        });
                    }
                });
            }, 100);
        }
        this.scrollToBottom();
    }

    format(text) {
        return text
            .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" style="color:#38BDF8">$1</a>')
            .replace(/\n/g, '<br>');
    }

    buildSuggestions(chips) {
        if (!chips?.length) return '';
        return `<div class="chat-suggestions">${chips.map(c => {
                const label = typeof c === 'string' ? c : (c.label || c.value || '');
                const value = typeof c === 'string' ? c : (c.value || c.label || '');
                return `<button class="suggestion-chip" data-suggestion="${value}">${label}</button>`;
            }).join('')}</div>`;
    }

    showTyping() {
        this.isTyping = true;
        const html = `
            <div class="chat-message bot typing-message">
                <div class="message-avatar" style="background:linear-gradient(135deg,#0D1F3C,#1A3A5C);border:1px solid rgba(56,189,248,0.2)" aria-hidden="true">
                    ${this.robotSVGAvatar(22, 22)}
                </div>
                <div class="message-content">
                    <div class="message-bubble">
                        <div class="typing-indicator">
                            <span class="typing-dot"></span>
                            <span class="typing-dot"></span>
                            <span class="typing-dot"></span>
                        </div>
                    </div>
                </div>
            </div>`;
        this.messagesContainer.insertAdjacentHTML('beforeend', html);
        this.scrollToBottom();
    }

    hideTyping() {
        this.isTyping = false;
        const el = this.messagesContainer.querySelector('.typing-message');
        if (el) el.remove();
    }

    executeAction(action) {
        switch (action) {
            case 'open_scheduler':
                setTimeout(() =>  {
                    
                    window.open('https://calendly.com/edhissonguami', '_blank');
                }, 800);
                break;
            case 'show_projects':
                const p = document.getElementById('cases') || document.getElementById('projects');
                if (p) { this.close(); p.scrollIntoView({ behavior:'smooth' }); }
                break;
            case 'open_contact':
                const c = document.getElementById('contact');
                if (c) { this.close(); c.scrollIntoView({ behavior:'smooth' }); }
                break;
        }
    }

    scrollToBottom() {
        setTimeout(() => { this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight; }, 80);
    }

    getCookie(name) {
        const eq = name + '=';
        return document.cookie.split(';')
            .map(c => c.trim())
            .find(c => c.startsWith(eq))
            ?.substring(eq.length) || null;
    }
}

// Inicializacion segura
document.addEventListener('DOMContentLoaded', () => { 
    if (!window.chatBot) {
        window.chatBot = new ChatBot(); 
    }
});

// Fallback si DOMContentLoaded ya paso
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    if (!window.chatBot) {
        window.chatBot = new ChatBot();
    }
}