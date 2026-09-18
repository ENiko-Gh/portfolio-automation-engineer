/**
 * Sistema de Comentarios y Likes
 */

class CommentsSystem {
    constructor() {
        this.visitorId = null;
        this.currentProjectId = null;
        
        this.init();
    }
    
    init() {
        // Obtener visitor ID
        window.addEventListener('visitorRegistered', (e) => {
            this.visitorId = e.detail.visitorId;
        });
        
        // O de cookie
        const savedVisitor = this.getCookie('portfolio_visitor');
        if (savedVisitor) {
            const data = JSON.parse(savedVisitor);
            this.visitorId = data.visitor_id;
        }
        
        // Inicializar comentarios en cada proyecto
        this.initializeProjectComments();
        
        // Inicializar likes
        this.initializeLikes();
    }
    
    initializeProjectComments() {
        const projectCards = document.querySelectorAll('.project-card');
        
        projectCards.forEach((card, index) => {
            const projectId = `project-${index + 1}`;
            
            // Añadir sección de comentarios
            const commentsHTML = `
                <div class="comments-section" id="comments-${projectId}">
                    <div class="comments-header">
                        <h3 data-en="Comments & Feedback" data-es="Comentarios y Opiniones">Comments & Feedback</h3>
                        <span class="comments-count" data-count="0">0 comments</span>
                    </div>
                    
                    <div class="comment-form" id="commentForm-${projectId}">
                        <div class="rating-input" id="rating-${projectId}">
                            <span class="rating-star" data-rating="1">★</span>
                            <span class="rating-star" data-rating="2">★</span>
                            <span class="rating-star" data-rating="3">★</span>
                            <span class="rating-star" data-rating="4">★</span>
                            <span class="rating-star" data-rating="5">★</span>
                        </div>
                        
                        <textarea 
                            class="comment-textarea" 
                            id="commentText-${projectId}"
                            placeholder="Share your thoughts about this project..."
                            rows="3"
                        ></textarea>
                        
                        <button class="btn btn-primary" onclick="commentsSystem.submitComment('${projectId}')">
                            <span data-en="Submit Comment" data-es="Enviar Comentario">Submit Comment</span>
                        </button>
                    </div>
                    
                    <div class="comments-list" id="commentsList-${projectId}">
                        <!-- Comments will load here -->
                    </div>
                </div>
            `;
            
            card.querySelector('.project-content').insertAdjacentHTML('beforeend', commentsHTML);
            
            // Inicializar rating stars
            this.initializeRating(projectId);
            
            // Cargar comentarios existentes
            this.loadComments(projectId);
        });
    }
    
    initializeRating(projectId) {
        const stars = document.querySelectorAll(`#rating-${projectId} .rating-star`);
        let selectedRating = 0;
        
        stars.forEach(star => {
            star.addEventListener('click', () => {
                selectedRating = parseInt(star.dataset.rating);
                
                stars.forEach((s, index) => {
                    if (index < selectedRating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
            
            star.addEventListener('mouseenter', () => {
                const rating = parseInt(star.dataset.rating);
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.style.color = '#ffc107';
                    } else {
                        s.style.color = '';
                    }
                });
            });
        });
        
        const ratingContainer = document.getElementById(`rating-${projectId}`);
        ratingContainer.addEventListener('mouseleave', () => {
            stars.forEach((s, index) => {
                if (index < selectedRating) {
                    s.style.color = '#ffc107';
                } else {
                    s.style.color = '';
                }
            });
        });
    }
    
    async submitComment(projectId) {
        if (!this.visitorId) {
            alert('Please complete the welcome form first.');
            return;
        }
        
        const textarea = document.getElementById(`commentText-${projectId}`);
        const commentText = textarea.value.trim();
        
        if (!commentText) {
            alert('Please write a comment.');
            return;
        }
        
        // Obtener rating
        const activeStars = document.querySelectorAll(`#rating-${projectId} .rating-star.active`);
        const rating = activeStars.length;
        
        try {
            const response = await fetch('./api/comments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    visitor_id: this.visitorId,
                    project_id: projectId,
                    comment_text: commentText,
                    rating: rating > 0 ? rating : null
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Limpiar formulario
                textarea.value = '';
                document.querySelectorAll(`#rating-${projectId} .rating-star`).forEach(s => {
                    s.classList.remove('active');
                });
                
                // Mostrar mensaje de éxito
                this.showNotification('Thank you! Your comment will be visible after approval.', 'success');
            } else {
                this.showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('Error submitting comment:', error);
            this.showNotification('An error occurred. Please try again.', 'error');
        }
    }
    
    async loadComments(projectId) {
        try {
            const response = await fetch(`./api/comments.php?project_id=${projectId}&limit=10`);
            const result = await response.json();
            
            if (result.success) {
                const commentsList = document.getElementById(`commentsList-${projectId}`);
                const commentsCount = document.querySelector(`#comments-${projectId} .comments-count`);
                
                // Actualizar contador
                commentsCount.textContent = `${result.data.count} comment${result.data.count !== 1 ? 's' : ''}`;
                commentsCount.dataset.count = result.data.count;
                
                // Mostrar rating promedio si existe
                if (result.data.average_rating) {
                    const avgRatingHTML = `
                        <div style="margin-bottom: 20px; padding: 15px; background: var(--bg-primary); border-radius: 8px;">
                            <strong>Average Rating:</strong> 
                            <span style="color: #ffc107; font-size: 18px;">
                                ${'★'.repeat(Math.round(result.data.average_rating))}${'☆'.repeat(5 - Math.round(result.data.average_rating))}
                            </span>
                            <span style="color: var(--text-secondary);"> (${result.data.average_rating}/5)</span>
                        </div>
                    `;
                    commentsList.insertAdjacentHTML('beforebegin', avgRatingHTML);
                }
                
                // Renderizar comentarios
                if (result.data.comments.length > 0) {
                    commentsList.innerHTML = result.data.comments.map(comment => this.renderComment(comment)).join('');
                } else {
                    commentsList.innerHTML = '<p style="text-align: center; color: var(--text-muted);">No comments yet. Be the first to comment!</p>';
                }
            }
        } catch (error) {
            console.error('Error loading comments:', error);
        }
    }
    
    renderComment(comment) {
        const date = new Date(comment.created_at);
        const timeAgo = this.getTimeAgo(date);
        
        const ratingHTML = comment.rating ? `
            <div class="comment-rating">
                ${'<span class="star">★</span>'.repeat(comment.rating)}
            </div>
        ` : '';
        
        return `
            <div class="comment-card">
                <div class="comment-header">
                    <div class="comment-author">
                        <div class="comment-name">${this.escapeHtml(comment.full_name)}</div>
                        <div class="comment-meta">
                            ${comment.company ? `${this.escapeHtml(comment.company)} • ` : ''}
                            ${timeAgo}
                        </div>
                    </div>
                    ${ratingHTML}
                </div>
                <div class="comment-text">${this.escapeHtml(comment.comment_text)}</div>
            </div>
        `;
    }
    
    initializeLikes() {
        const projectCards = document.querySelectorAll('.project-card');
        
        projectCards.forEach((card, index) => {
            const projectId = `project-${index + 1}`;
            
            // Añadir botón de like después del título
            const likeButtonHTML = `
                <button class="project-like-button" id="like-${projectId}" onclick="commentsSystem.toggleLike('${projectId}')">
                    <span class="like-icon">❤️</span>
                    <span class="like-count">0</span>
                    <span data-en="Likes" data-es="Me gusta">Likes</span>
                </button>
            `;
            
            const projectHeader = card.querySelector('.project-header');
            projectHeader.insertAdjacentHTML('beforeend', likeButtonHTML);
            
            // Cargar estado de likes
            this.loadLikes(projectId);
        });
    }
    
    async loadLikes(projectId) {
        try {
            const url = `./api/likes.php?project_id=${projectId}${this.visitorId ? `&visitor_id=${this.visitorId}` : ''}`;
            const response = await fetch(url);
            const result = await response.json();
            
            if (result.success) {
                const likeButton = document.getElementById(`like-${projectId}`);
                const likeCount = likeButton.querySelector('.like-count');
                
                likeCount.textContent = result.data.total_likes;
                
                if (result.data.user_liked) {
                    likeButton.classList.add('liked');
                }
            }
        } catch (error) {
            console.error('Error loading likes:', error);
        }
    }
    
    async toggleLike(projectId) {
        if (!this.visitorId) {
            alert('Please complete the welcome form first.');
            return;
        }
        
        try {
            const response = await fetch('./api/likes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    visitor_id: this.visitorId,
                    project_id: projectId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                const likeButton = document.getElementById(`like-${projectId}`);
                const likeCount = likeButton.querySelector('.like-count');
                
                likeCount.textContent = result.data.total_likes;
                
                if (result.data.liked) {
                    likeButton.classList.add('liked');
                } else {
                    likeButton.classList.remove('liked');
                }
            }
        } catch (error) {
            console.error('Error toggling like:', error);
        }
    }
    
    // Utility functions
    getTimeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + ' years ago';
        
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + ' months ago';
        
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + ' days ago';
        
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + ' hours ago';
        
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + ' minutes ago';
        
        return 'Just now';
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    showNotification(message, type = 'info') {
        // Crear notificación toast
        const notification = document.createElement('div');
        notification.className = `toast-notification ${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            background: ${type === 'success' ? 'var(--success)' : 'var(--error)'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10001;
            animation: slideInRight 0.3s ease;
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
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
    window.commentsSystem = new CommentsSystem();
});

// Animaciones CSS adicionales
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);