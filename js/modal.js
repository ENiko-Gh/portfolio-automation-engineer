/**
 * Modal de Bienvenida para Captura de Visitantes
 * CORREGIDO:
 * 1. result.data.visitor_id → result.visitor_id (fix Server error)
 * 2. Phone formatter acepta números internacionales (+593, +1, etc.)
 * 3. Ecuador agregado al select de países
 * 4. createAnonymousVisitor usa action:'skip' compatible con visitors.php
 */

class VisitorModal {
    constructor() {
        this.modal = null;
        this.form = null;
        this.visitorId = null;
        this.sessionId = null;
        
        this.config = {
            showDelay: 2000,
            cookieName: 'portfolio_visitor',
            cookieExpiry: 30
        };
        
        this.init();
    }
    
    init() {
        const savedVisitor = this.getCookie(this.config.cookieName);
        
        if (savedVisitor) {
            try {
                const visitorData = JSON.parse(savedVisitor);
                this.visitorId = visitorData.visitor_id;
                this.sessionId = visitorData.session_id;
                this.registerReturningVisit();
                // Notificar al chatbot
                window.dispatchEvent(new CustomEvent('visitorRegistered', {
                    detail: { visitorId: this.visitorId, sessionId: this.sessionId }
                }));
            } catch(e) {
                console.log('Cookie parse error:', e);
            }
        } else {
            setTimeout(() => this.show(), this.config.showDelay);
        }
        
        this.createModal();
        this.attachEventListeners();
    }
    
    createModal() {
        const modalHTML = `
            <div class="visitor-modal" id="visitorModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 data-en="Welcome! 👋" data-es="¡Bienvenido! 👋">Welcome! 👋</h2>
                        <p data-en="I'd love to know more about you" data-es="Me gustaría saber más sobre ti">
                            I'd love to know more about you
                        </p>
                    </div>
                    
                    <form class="modal-form" id="visitorForm">
                        <div class="modal-body">
                            <div class="form-group">
                                <label for="fullName">
                                    <span data-en="Full Name" data-es="Nombre Completo">Full Name</span>
                                    <span class="required">*</span>
                                </label>
                                <input 
                                    type="text" 
                                    id="fullName" 
                                    name="full_name" 
                                    class="form-input" 
                                    required
                                    placeholder="John Smith"
                                >
                            </div>
                            
                            <div class="form-group">
                                <label for="email">
                                    <span data-en="Email Address" data-es="Correo Electrónico">Email Address</span>
                                    <span class="required">*</span>
                                </label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    class="form-input" 
                                    required
                                    placeholder="john@company.com"
                                >
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phone">
                                        <span data-en="Phone" data-es="Teléfono">Phone</span>
                                    </label>
                                    <!-- FIX 2: maxlength=20, acepta formato internacional -->
                                    <input 
                                        type="tel" 
                                        id="phone" 
                                        name="phone" 
                                        class="form-input"
                                        maxlength="20"
                                        placeholder="+593 99 349 1420"
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label for="country">
                                        <span data-en="Country" data-es="País">Country</span>
                                    </label>
                                    <!-- FIX 3: Ecuador agregado -->
                                    <select id="country" name="country" class="form-select">
                                        <option value="">Select...</option>
                                        <option value="Ecuador">Ecuador</option>
                                        <option value="US">United States</option>
                                        <option value="CA">Canada</option>
                                        <option value="MX">Mexico</option>
                                        <option value="CO">Colombia</option>
                                        <option value="PE">Peru</option>
                                        <option value="AR">Argentina</option>
                                        <option value="CL">Chile</option>
                                        <option value="BR">Brazil</option>
                                        <option value="VE">Venezuela</option>
                                        <option value="UK">United Kingdom</option>
                                        <option value="DE">Germany</option>
                                        <option value="FR">France</option>
                                        <option value="ES">Spain</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="company">
                                        <span data-en="Company" data-es="Empresa">Company</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="company" 
                                        name="company" 
                                        class="form-input"
                                        placeholder="Company Name"
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label for="jobTitle">
                                        <span data-en="Job Title" data-es="Cargo">Job Title</span>
                                    </label>
                                    <input 
                                        type="text" 
                                        id="jobTitle" 
                                        name="job_title" 
                                        class="form-input"
                                        placeholder="Your Role"
                                    >
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="interest">
                                    <span data-en="What brings you here?" data-es="¿Qué te trae aquí?">What brings you here?</span>
                                </label>
                                <select id="interest" name="interest" class="form-select">
                                    <option value="">Select an option...</option>
                                    <option value="hire">Looking to hire</option>
                                    <option value="collaboration">Collaboration opportunity</option>
                                    <option value="services">Interested in services</option>
                                    <option value="learning">Learning/Research</option>
                                    <option value="networking">Networking</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn-modal btn-modal-secondary" id="skipBtn">
                                <span data-en="Skip" data-es="Omitir">Skip</span>
                            </button>
                            <button type="submit" class="btn-modal btn-modal-primary" id="submitBtn">
                                <span data-en="Continue" data-es="Continuar">Continue</span>
                            </button>
                        </div>
                    </form>
                    
                    <div class="modal-loader" id="modalLoader">
                        <div class="spinner"></div>
                        <p data-en="Processing..." data-es="Procesando...">Processing...</p>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('visitorModal');
        this.form = document.getElementById('visitorForm');
    }
    
    attachEventListeners() {
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSubmit();
        });
        
        const skipBtn = document.getElementById('skipBtn');
        skipBtn.addEventListener('click', () => {
            this.hide();
            this.createAnonymousVisitor();
        });
        
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.hide();
                this.createAnonymousVisitor();
            }
        });
        
        // FIX 2: Phone formatter — acepta internacional, NO fuerza formato US
        const phoneInput = document.getElementById('phone');
        phoneInput.addEventListener('input', (e) => {
            // Solo permitir: dígitos, +, espacios, guiones, paréntesis
            e.target.value = e.target.value.replace(/[^\d\+\s\-\(\)]/g, '');
        });
    }
    
    async handleSubmit() {
        const formData = new FormData(this.form);
        const data = {
            full_name: formData.get('full_name'),
            email:     formData.get('email'),
            phone:     formData.get('phone'),
            country:   formData.get('country'),
            company:   formData.get('company'),
            job_title: formData.get('job_title'),
            interest:  formData.get('interest'),
            language:  document.documentElement.lang || 'en',
            session_id: this.sessionId || this.generateSessionId()
        };
        
        if (!data.full_name || !data.email) {
            this.showError('Please fill in all required fields');
            return;
        }
        
        this.showLoader();
        
        try {
            const response = await fetch('./api/visitors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // FIX 1: visitors.php devuelve result.visitor_id (no result.data.visitor_id)
                this.visitorId = result.visitor_id || (result.data && result.data.visitor_id);
                this.sessionId = result.session_id || (result.data && result.data.session_id);
                
                this.setCookie(this.config.cookieName, JSON.stringify({
                    visitor_id: this.visitorId,
                    session_id: this.sessionId,
                    name:  data.full_name,
                    email: data.email
                }), this.config.cookieExpiry);
                
                // Notificar al chatbot
                window.dispatchEvent(new CustomEvent('visitorRegistered', {
                    detail: { visitorId: this.visitorId, sessionId: this.sessionId }
                }));
                
                this.hideLoader();
                this.showSuccess(result.message || '¡Welcome ' + data.full_name + '!');
                setTimeout(() => this.hide(), 1500);
            } else {
                this.hideLoader();
                this.showError(result.message || 'Error registering. Please try again.');
            }
        } catch (error) {
            console.error('Error submitting visitor form:', error);
            this.hideLoader();
            this.showError('An error occurred. Please try again.');
        }
    }
    
    async createAnonymousVisitor() {
        // FIX 4: usar action:'skip' compatible con visitors.php
        const sessionId = this.sessionId || this.generateSessionId();
        
        try {
            const response = await fetch('./api/visitors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action:     'skip',
                    session_id: sessionId,
                    language:   document.documentElement.lang || 'en'
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // FIX 1: leer visitor_id directo (no dentro de result.data)
                this.visitorId = result.visitor_id || (result.data && result.data.visitor_id);
                this.sessionId = result.session_id || sessionId;
                
                this.setCookie(this.config.cookieName, JSON.stringify({
                    visitor_id: this.visitorId,
                    session_id: this.sessionId,
                    anonymous:  true
                }), 1);
                
                // Notificar al chatbot
                window.dispatchEvent(new CustomEvent('visitorRegistered', {
                    detail: { visitorId: this.visitorId, sessionId: this.sessionId }
                }));
            }
        } catch (error) {
            console.error('Error creating anonymous visitor:', error);
        }
    }
    
    async registerReturningVisit() {
        try {
            await fetch('./api/visitors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    visitor_id: this.visitorId,
                    session_id: this.sessionId,
                    returning:  true,
                    language:   document.documentElement.lang || 'en'
                })
            });
        } catch (error) {
            console.error('Error registering returning visit:', error);
        }
    }
    
    generateSessionId() {
        const id = 'vs_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        this.sessionId = id;
        localStorage.setItem('chat_session', id);
        return id;
    }
    
    show() {
        this.modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    hide() {
        this.modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    showLoader() {
        this.form.style.display = 'none';
        document.getElementById('modalLoader').classList.add('active');
    }
    
    hideLoader() {
        this.form.style.display = 'block';
        document.getElementById('modalLoader').classList.remove('active');
    }
    
    showSuccess(message) {
        const modalBody = this.modal.querySelector('.modal-content');
        modalBody.innerHTML = `
            <div style="padding:40px 20px;text-align:center;color:var(--success,#48BB78)">
                <svg width="52" height="52" viewBox="0 0 24 24" fill="none" style="margin-bottom:14px">
                    <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z"
                          stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <p style="font-size:18px;font-weight:600;margin:0;color:#E8F0FE">${message}</p>
                <p style="font-size:13px;color:#7A90B0;margin-top:8px">Taking you to the portfolio...</p>
            </div>`;
    }
    
    showError(message) {
        // Mostrar en lugar de alert para mejor UX
        const existing = this.modal.querySelector('.form-error-msg');
        if (existing) existing.remove();
        
        const err = document.createElement('div');
        err.className = 'form-error-msg';
        err.style.cssText = 'background:rgba(252,129,129,0.12);border:1px solid rgba(252,129,129,0.4);' +
            'color:#FC8181;padding:10px 14px;border-radius:8px;font-size:13px;' +
            'margin:0 0 12px;text-align:center';
        err.textContent = message;
        
        const footer = this.modal.querySelector('.modal-footer');
        footer.parentNode.insertBefore(err, footer);
        
        setTimeout(() => { if (err.parentNode) err.remove(); }, 4000);
    }
    
    setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/`;
    }
    
    getCookie(name) {
        const nameEQ = `${name}=`;
        return document.cookie.split(';')
            .map(c => c.trim())
            .find(c => c.startsWith(nameEQ))
            ?.substring(nameEQ.length) || null;
    }
    
    getVisitorId()  { return this.visitorId; }
    getSessionId()  { return this.sessionId; }
}

document.addEventListener('DOMContentLoaded', () => {
    window.visitorModal = new VisitorModal();
});