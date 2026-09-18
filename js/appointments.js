/**
 * Sistema de Agendamiento de Citas
 */

class AppointmentScheduler {
    constructor() {
        this.visitorId = null;
        this.modal = null;
        this.selectedDate = null;
        this.selectedTime = null;
        this.selectedType = null;
        
        this.init();
    }
    
    init() {
        // Obtener visitor ID
        window.addEventListener('visitorRegistered', (e) => {
            this.visitorId = e.detail.visitorId;
        });
        
        const savedVisitor = this.getCookie('portfolio_visitor');
        if (savedVisitor) {
            const data = JSON.parse(savedVisitor);
            this.visitorId = data.visitor_id;
        }
        
        this.createSchedulerModal();
        this.attachEventListeners();
    }
    
    createSchedulerModal() {
        const modalHTML = `
            <div class="scheduler-modal" id="schedulerModal">
                <div class="modal-content scheduler-content">
                    <div class="modal-header">
                        <h2 data-en="Schedule a Consultation" data-es="Agendar Consulta">Schedule a Consultation</h2>
                        <button class="modal-close" id="schedulerClose">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="scheduler-body">
                        <!-- Step 1: Select Type -->
                        <div class="scheduler-step active" id="step1">
                            <h3 data-en="What type of meeting?" data-es="¿Qué tipo de reunión?">What type of meeting?</h3>
                            <div class="meeting-types">
                                <div class="meeting-type-card" data-type="consultation">
                                    <div class="type-icon">💼</div>
                                    <h4 data-en="General Consultation" data-es="Consulta General">General Consultation</h4>
                                    <p data-en="Discuss your project needs" data-es="Discutir necesidades del proyecto">Discuss your project needs</p>
                                </div>
                                <div class="meeting-type-card" data-type="project_discussion">
                                    <div class="type-icon">📊</div>
                                    <h4 data-en="Project Discussion" data-es="Discusión de Proyecto">Project Discussion</h4>
                                    <p data-en="Review project details and scope" data-es="Revisar detalles y alcance">Review project details and scope</p>
                                </div>
                                <div class="meeting-type-card" data-type="technical_audit">
                                    <div class="type-icon">🔍</div>
                                    <h4 data-en="Technical Audit" data-es="Auditoría Técnica">Technical Audit</h4>
                                    <p data-en="Security and infrastructure assessment" data-es="Evaluación de seguridad">Security and infrastructure assessment</p>
                                </div>
                                <div class="meeting-type-card" data-type="other">
                                    <div class="type-icon">💡</div>
                                    <h4 data-en="Other" data-es="Otro">Other</h4>
                                    <p data-en="Custom topic discussion" data-es="Tema personalizado">Custom topic discussion</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 2: Select Date -->
                        <div class="scheduler-step" id="step2">
                            <button class="step-back" onclick="appointmentScheduler.previousStep()">
                                ← <span data-en="Back" data-es="Atrás">Back</span>
                            </button>
                            <h3 data-en="Select a date" data-es="Selecciona una fecha">Select a date</h3>
                            <div class="calendar-container" id="calendarContainer">
                                <!-- Calendar will be generated here -->
                            </div>
                        </div>
                        
                        <!-- Step 3: Select Time -->
                        <div class="scheduler-step" id="step3">
                            <button class="step-back" onclick="appointmentScheduler.previousStep()">
                                ← <span data-en="Back" data-es="Atrás">Back</span>
                            </button>
                            <h3 data-en="Select a time" data-es="Selecciona una hora">Select a time</h3>
                            <div class="timezone-selector">
                                <label for="timezoneSelect">
                                    <span data-en="Timezone:" data-es="Zona horaria:">Timezone:</span>
                                </label>
                                <select id="timezoneSelect" class="form-select">
                                    <option value="America/New_York">Eastern Time (ET)</option>
                                    <option value="America/Chicago">Central Time (CT)</option>
                                    <option value="America/Denver">Mountain Time (MT)</option>
                                    <option value="America/Los_Angeles">Pacific Time (PT)</option>
                                </select>
                            </div>
                            <div class="time-slots" id="timeSlots">
                                <!-- Time slots will be loaded here -->
                            </div>
                        </div>
                        
                        <!-- Step 4: Confirm -->
                        <div class="scheduler-step" id="step4">
                            <button class="step-back" onclick="appointmentScheduler.previousStep()">
                                ← <span data-en="Back" data-es="Atrás">Back</span>
                            </button>
                            <h3 data-en="Confirm your appointment" data-es="Confirma tu cita">Confirm your appointment</h3>
                            
                            <div class="appointment-summary">
                                <div class="summary-item">
                                    <span class="summary-label" data-en="Type:" data-es="Tipo:">Type:</span>
                                    <span class="summary-value" id="summaryType"></span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label" data-en="Date:" data-es="Fecha:">Date:</span>
                                    <span class="summary-value" id="summaryDate"></span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label" data-en="Time:" data-es="Hora:">Time:</span>
                                    <span class="summary-value" id="summaryTime"></span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="appointmentDescription">
                                    <span data-en="Additional details (optional):" data-es="Detalles adicionales (opcional):">Additional details (optional):</span>
                                </label>
                                <textarea 
                                    id="appointmentDescription" 
                                    class="form-textarea"
                                    rows="4"
                                    placeholder="Tell me more about what you'd like to discuss..."
                                ></textarea>
                            </div>
                            
                            <button class="btn btn-primary btn-large" onclick="appointmentScheduler.confirmAppointment()">
                                <span data-en="Confirm Appointment" data-es="Confirmar Cita">Confirm Appointment</span>
                            </button>
                        </div>
                        
                        <!-- Success Step -->
                        <div class="scheduler-step" id="stepSuccess">
                            <div class="success-animation">
                                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2">
                                    <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <h3 data-en="Appointment Scheduled!" data-es="¡Cita Agendada!">Appointment Scheduled!</h3>
                            <p data-en="You'll receive a confirmation email shortly. I'll also send you a reminder 24 hours before our meeting." data-es="Recibirás un email de confirmación en breve. También te enviaré un recordatorio 24 horas antes.">
                                You'll receive a confirmation email shortly. I'll also send you a reminder 24 hours before our meeting.
                            </p>
                            <button class="btn btn-secondary" onclick="appointmentScheduler.close()">
                                <span data-en="Close" data-es="Cerrar">Close</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        this.modal = document.getElementById('schedulerModal');
    }
    
    attachEventListeners() {
        // Close button
        const closeBtn = document.getElementById('schedulerClose');
        closeBtn.addEventListener('click', () => this.close());
        
        // Meeting type cards
        const typeCards = document.querySelectorAll('.meeting-type-card');
        typeCards.forEach(card => {
            card.addEventListener('click', () => {
                this.selectedType = card.dataset.type;
                this.nextStep();
            });
        });
        
        // Close on background click
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.close();
            }
        });
    }
    
    open() {
        if (!this.visitorId) {
            alert('Please complete the welcome form first.');
            return;
        }
        
        this.modal.classList.add('active');
        this.resetSteps();
        this.generateCalendar();
    }
    
    close() {
        this.modal.classList.remove('active');
        this.resetSteps();
    }
    
    nextStep() {
        const currentStep = this.modal.querySelector('.scheduler-step.active');
        const currentStepNum = parseInt(currentStep.id.replace('step', ''));
        const nextStepNum = currentStepNum + 1;
        
        currentStep.classList.remove('active');
        document.getElementById(`step${nextStepNum}`).classList.add('active');
        
        // Load data for next step
        if (nextStepNum === 3) {
            this.loadTimeSlots();
        } else if (nextStepNum === 4) {
            this.updateSummary();
        }
    }
    
    previousStep() {
        const currentStep = this.modal.querySelector('.scheduler-step.active');
        const currentStepNum = parseInt(currentStep.id.replace('step', ''));
        const prevStepNum = currentStepNum - 1;
        
        currentStep.classList.remove('active');
        document.getElementById(`step${prevStepNum}`).classList.add('active');
    }
    
    resetSteps() {
        this.modal.querySelectorAll('.scheduler-step').forEach(step => {
            step.classList.remove('active');
        });
        document.getElementById('step1').classList.add('active');
        
        this.selectedDate = null;
        this.selectedTime = null;
        this.selectedType = null;
    }
    
    generateCalendar() {
        const container = document.getElementById('calendarContainer');
        const today = new Date();
        const currentMonth = today.getMonth();
        const currentYear = today.getFullYear();
        
        const calendar = this.createCalendarHTML(currentYear, currentMonth);
        container.innerHTML = calendar;
        
        // Attach click handlers to dates
        const dateCells = container.querySelectorAll('.calendar-date:not(.disabled)');
        dateCells.forEach(cell => {
            cell.addEventListener('click', () => {
                // Remove previous selection
                container.querySelectorAll('.calendar-date').forEach(c => c.classList.remove('selected'));
                cell.classList.add('selected');
                
                this.selectedDate = cell.dataset.date;
                
                // Auto-advance to next step after 500ms
                setTimeout(() => this.nextStep(), 500);
            });
        });
    }
    
    createCalendarHTML(year, month) {
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
        const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        let html = `
            <div class="calendar">
                <div class="calendar-header">
                    <h4>${monthNames[month]} ${year}</h4>
                </div>
                <div class="calendar-grid">
                    ${daysOfWeek.map(day => `<div class="calendar-day-name">${day}</div>`).join('')}
        `;
        
        // Empty cells before first day
        for (let i = 0; i < firstDay.getDay(); i++) {
            html += '<div class="calendar-date disabled"></div>';
        }
        
        // Date cells
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const date = new Date(year, month, day);
            const dateString = this.formatDate(date);
            const isWeekend = date.getDay() === 0 || date.getDay() === 6;
            const isPast = date < today;
            const isDisabled = isPast || isWeekend;
            
            html += `
                <div class="calendar-date ${isDisabled ? 'disabled' : ''}" data-date="${dateString}">
                    ${day}
                </div>
            `;
        }
        
        html += `
                </div>
                <p class="calendar-note" style="margin-top: 15px; font-size: 12px; color: var(--text-muted);">
                    <span data-en="Weekends are not available" data-es="Fines de semana no disponibles">Weekends are not available</span>
                </p>
            </div>
        `;
        
        return html;
    }
    
    async loadTimeSlots() {
        if (!this.selectedDate) return;
        
        const container = document.getElementById('timeSlots');
        container.innerHTML = '<div class="loading">Loading available times...</div>';
        
        try {
            const response = await fetch(`./api/appointments.php?available_slots=true&date=${this.selectedDate}`);
            const result = await response.json();
            
            if (result.success) {
                const slots = result.data.slots;
                
                if (slots.length === 0) {
                    container.innerHTML = '<p>No available times for this date.</p>';
                    return;
                }
                
                container.innerHTML = `
                    <div class="time-slots-grid">
                        ${slots.map(slot => `
                            <button 
                                class="time-slot ${!slot.available ? 'disabled' : ''}" 
                                data-time="${slot.time}"
                                ${!slot.available ? 'disabled' : ''}
                                onclick="appointmentScheduler.selectTime('${slot.time}')"
                            >
                                ${this.formatTime(slot.time)}
                            </button>
                        `).join('')}
                    </div>
                `;
            } else {
                container.innerHTML = '<p>Error loading time slots.</p>';
            }
        } catch (error) {
            console.error('Error loading time slots:', error);
            container.innerHTML = '<p>Error loading time slots. Please try again.</p>';
        }
    }
    
    selectTime(time) {
        this.selectedTime = time;
        
        // Visual feedback
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('selected');
        });
        event.target.classList.add('selected');
        
        // Auto-advance after 500ms
        setTimeout(() => this.nextStep(), 500);
    }
    
    updateSummary() {
        const typeMap = {
            'consultation': 'General Consultation',
            'project_discussion': 'Project Discussion',
            'technical_audit': 'Technical Audit',
            'other': 'Other'
        };
        
        document.getElementById('summaryType').textContent = typeMap[this.selectedType];
        document.getElementById('summaryDate').textContent = this.formatDateLong(new Date(this.selectedDate));
        document.getElementById('summaryTime').textContent = this.formatTime(this.selectedTime);
    }
    
    async confirmAppointment() {
        const description = document.getElementById('appointmentDescription').value;
        const timezone = document.getElementById('timezoneSelect').value;
        
        const data = {
            visitor_id: this.visitorId,
            appointment_date: this.selectedDate,
            appointment_time: this.selectedTime,
            timezone: timezone,
            meeting_type: this.selectedType,
            description: description
        };
        
        try {
            const response = await fetch('./api/appointments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success step
                this.modal.querySelectorAll('.scheduler-step').forEach(step => {
                    step.classList.remove('active');
                });
                document.getElementById('stepSuccess').classList.add('active');
            } else {
                alert(result.message);
            }
        } catch (error) {
            console.error('Error confirming appointment:', error);
            alert('An error occurred. Please try again.');
        }
    }
    
    // Utility functions
    formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    formatDateLong(date) {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }
    
    formatTime(time) {
        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes} ${ampm}`;
    }
    
    getCookie(name) {
        const nameEQ = `${name}=`;
        const cookies = document.cookie.split(';');
        for (let i = 0; i < cookies.length; i++) {
            let c = cookies[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }
}

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    window.appointmentScheduler = new AppointmentScheduler();
});