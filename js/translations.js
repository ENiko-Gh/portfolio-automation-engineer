/**
 * js/translations.js — Sistema bilingüe EN/ES
 * Compatible con tu index.html: los botones .lb llaman window.setLanguage()
 */
(function () {
    'use strict';

    const T = {
        en: {
            'nav.home':'Home','nav.services':'Services','nav.cases':'Case Studies',
            'nav.stack':'Tech Stack','nav.certs':'Credentials','nav.contact':'Contact',
            'hero.eyebrow':'IT Engineer · Remote Consultant',
            'hero.status':'Available for Remote Consulting',
            'hero.cta1':'View Case Studies','hero.cta2':'Download Dossier','hero.cta3':'Hire Me →',
            'met.title':'Numbers That Matter',
            'met.sub':'Real results from delivered projects and academic excellence',
            'met.iso':'ISO 27001 Compliance','met.iso.note':'First external audit · Quesinor',
            'met.uptime':'Network Uptime','met.uptime.note':'SLA exceeded · MPLS project',
            'met.score':'Academic Score / 20','met.score.note':'Complexivo Exam · ESPE Feb 2026',
            'met.records':'Records Analyzed','met.records.note':'MIES dataset · RapidMiner + Power BI',
            'srv.title':'4 Service Pillars',
            'srv.sub':'Packaged solutions based on real delivered projects — not theoretical skills',
            'cases.title':'Case Studies','cases.sub':'Real implementations with verifiable outcomes — not demos',
            'stack.title':'Full Technology Stack',
            'stack.sub':'Every tool mastered through real project delivery — ESPE ITIN curriculum + field work',
            'certs.title':'Certifications & Education',
            'remote.title':'Global Support, Zero Geographic Limits',
            'contact.title':'Ready to Secure Your Infrastructure?',
            'contact.sub':'Available for remote consulting, technical audits and infrastructure design across US time zones. Response within 24 hours.',
            'contact.send':'Send Message','contact.sched':'Schedule Meeting','contact.wa':'💬 WhatsApp',
            'contact.ok.title':'Message Sent!','contact.ok.sub':'Thank you for reaching out. I\'ll respond within 24 hours.',
            'foot.sub':'IT Engineer · ITIN · ESPE · Remote Consultant',
            'react.like':'Like','react.dislike':'Dislike','react.wow':'Wow',
            'react.rate':'Rate:','react.share':'Share','react.comments':'comments',
            'react.show':'Show comments','react.hide':'Hide comments',
            'react.nocomments':'No comments yet. Be the first to share your thoughts!',
            'react.name_placeholder':'Your name *','react.email_placeholder':'Email (optional)',
            'react.comment_placeholder':'Share a quick thought about this project...',
            'react.err_required':'Name and comment are required.',
            'react.comment_ok':'✓ Comment submitted! It will appear after a quick review.',
        },
        es: {
            'nav.home':'Inicio','nav.services':'Servicios','nav.cases':'Casos de Estudio',
            'nav.stack':'Tecnologías','nav.certs':'Credenciales','nav.contact':'Contacto',
            'hero.eyebrow':'Ingeniero en TI · Consultor Remoto',
            'hero.status':'Disponible para Consultoría Remota',
            'hero.cta1':'Ver Casos de Estudio','hero.cta2':'Descargar Dossier','hero.cta3':'Contrátame →',
            'met.title':'Números que Importan',
            'met.sub':'Resultados reales de proyectos entregados y excelencia académica',
            'met.iso':'Cumplimiento ISO 27001','met.iso.note':'Primera auditoría externa · Quesinor',
            'met.uptime':'Uptime de Red','met.uptime.note':'SLA superado · Proyecto MPLS',
            'met.score':'Calificación Académica / 20','met.score.note':'Examen Complexivo · ESPE Feb 2026',
            'met.records':'Registros Analizados','met.records.note':'Dataset MIES · RapidMiner + Power BI',
            'srv.title':'4 Pilares de Servicios',
            'srv.sub':'Soluciones basadas en proyectos reales entregados — no habilidades teóricas',
            'cases.title':'Casos de Estudio','cases.sub':'Implementaciones reales con resultados verificables — no demos',
            'stack.title':'Stack Tecnológico Completo',
            'stack.sub':'Cada herramienta dominada a través de proyectos reales — ITIN ESPE + trabajo en campo',
            'certs.title':'Certificaciones y Educación',
            'remote.title':'Soporte Global, Sin Límites Geográficos',
            'contact.title':'¿Listo para Asegurar tu Infraestructura?',
            'contact.sub':'Disponible para consultoría remota, auditorías técnicas y diseño de infraestructura en zonas horarias de EE.UU. Respuesta en menos de 24 horas.',
            'contact.send':'Enviar Mensaje','contact.sched':'Agendar Reunión','contact.wa':'💬 WhatsApp',
            'contact.ok.title':'¡Mensaje Enviado!','contact.ok.sub':'Gracias por contactarme. Responderé en menos de 24 horas.',
            'foot.sub':'Ingeniero en TI · ITIN · ESPE · Consultor Remoto',
            'react.like':'Me gusta','react.dislike':'No me gusta','react.wow':'¡Wow!',
            'react.rate':'Calificar:','react.share':'Compartir','react.comments':'comentarios',
            'react.show':'Ver comentarios','react.hide':'Ocultar comentarios',
            'react.nocomments':'Sin comentarios aún. ¡Sé el primero en opinar!',
            'react.name_placeholder':'Tu nombre *','react.email_placeholder':'Email (opcional)',
            'react.comment_placeholder':'Comparte una opinión rápida sobre este proyecto...',
            'react.err_required':'El nombre y el comentario son requeridos.',
            'react.comment_ok':'✓ ¡Comentario enviado! Aparecerá después de una revisión rápida.',
        }
    };

    let lang = localStorage.getItem('portfolio_lang') || 'en';

    function t(key) {
        return (T[lang] || T.en)[key] || T.en[key] || key;
    }

    function apply(l) {
        lang = l;
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const v = t(el.dataset.i18n);
            if (v && v !== el.dataset.i18n) el.textContent = v;
        });
        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const v = t(el.dataset.i18nPlaceholder);
            if (v && v !== el.dataset.i18nPlaceholder) el.placeholder = v;
        });
        document.querySelectorAll('[data-i18n-aria]').forEach(el => {
            const v = t(el.dataset.i18nAria);
            if (v && v !== el.dataset.i18nAria) el.setAttribute('aria-label', v);
        });
        document.documentElement.lang = l;
        document.querySelectorAll('.lb').forEach(b => b.classList.toggle('on', b.dataset.lang === l));
        localStorage.setItem('portfolio_lang', l);
        document.dispatchEvent(new CustomEvent('langchange', { detail: { lang: l } }));
    }

    window.setLanguage = apply;
    window.t = t;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => apply(lang));
    } else {
        apply(lang);
    }
})();
