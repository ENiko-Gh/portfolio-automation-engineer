/**
 * js/reactions.js
 * Handles all reactions per project:
 * - Like / Dislike / Wow buttons
 * - Star rating (1-5)
 * - Share (copy link)
 * - Comments feed (load + post + like)
 * Connects to: api/reactions.php
 */

(function () {
    'use strict';

    const API = './api/reactions.php';

    // ── INIT ALL REACTION SECTIONS ────────────────────────────
    function initAll() {
        document.querySelectorAll('.reactions-section').forEach(section => {
            const projectId = section.dataset.projectId;
            if (!projectId) return;

            initReactionButtons(section, projectId);
            initStarRating(section, projectId);
            initShareButton(section, projectId);
            initCommentToggle(section, projectId);
            initQuickCommentForm(section, projectId);

            // Load counts from server
            loadReactionCounts(section, projectId);
        });
    }

    // ── LOAD COUNTS ───────────────────────────────────────────
    async function loadReactionCounts(section, projectId) {
        try {
            const res  = await fetch(`${API}?project_id=${projectId}&action=counts`);
            const data = await res.json();
            if (!data.success) return;

            // Update reaction counts
            const types = ['like', 'dislike', 'wow'];
            types.forEach(type => {
                const countEl = section.querySelector(`.${type}-count`);
                if (countEl) countEl.textContent = data.counts?.[type] ?? 0;
            });

            // Update comment count
            const ccEl = section.querySelector('.proj-comment-count');
            if (ccEl) ccEl.textContent = data.comment_count ?? 0;

            // Update avg star rating
            if (data.avg_rating) {
                updateAvgDisplay(section, data.avg_rating, data.rating_count);
            }

            // Restore user's previous reactions (from localStorage)
            restoreUserReactions(section, projectId, data);

        } catch (e) {
            console.debug('Could not load reactions:', e.message);
        }
    }

    // ── REACTION BUTTONS (like/dislike/wow) ───────────────────
    function initReactionButtons(section, projectId) {
        const btns = section.querySelectorAll('.react-btn[data-type]');

        btns.forEach(btn => {
            btn.addEventListener('click', async () => {
                if (btn.classList.contains('loading')) return;

                const type    = btn.dataset.type;
                const isActive = btn.classList.contains('active');

                btn.classList.add('loading');

                try {
                    const res  = await fetch(API, {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body:    JSON.stringify({
                            action:     'react',
                            project_id: parseInt(projectId),
                            type:       type,
                        }),
                    });
                    const data = await res.json();

                    if (data.success) {
                        // Update all counts for this project
                        const types = ['like', 'dislike', 'wow'];
                        types.forEach(t => {
                            const countEl = section.querySelector(`.${t}-count`);
                            if (countEl) countEl.textContent = data.counts?.[t] ?? 0;

                            const tBtn = section.querySelector(`.${t}-btn`);
                            if (tBtn) {
                                tBtn.classList.toggle('active', data.user_reactions?.[t] ?? false);
                                tBtn.setAttribute('aria-pressed',
                                    data.user_reactions?.[t] ? 'true' : 'false');
                            }
                        });

                        // Save to localStorage
                        saveUserReaction(projectId, type, data.user_reactions?.[type]);
                    }
                } catch (e) {
                    console.debug('React failed:', e.message);
                } finally {
                    btn.classList.remove('loading');
                }
            });
        });
    }

    // ── STAR RATING ───────────────────────────────────────────
    function initStarRating(section, projectId) {
        const stars = section.querySelectorAll('.star');
        if (!stars.length) return;

        // Restore saved rating
        const saved = localStorage.getItem(`rating_${projectId}`);
        if (saved) {
            highlightStars(stars, parseInt(saved));
            stars.forEach(s => s.classList.toggle('selected', parseInt(s.dataset.star) <= parseInt(saved)));
        }

        // Hover effects
        stars.forEach(star => {
            star.addEventListener('mouseenter', () => {
                highlightStars(stars, parseInt(star.dataset.star));
            });
            star.addEventListener('mouseleave', () => {
                const current = parseInt(localStorage.getItem(`rating_${projectId}`) || 0);
                highlightStars(stars, current);
            });
        });

        // Click to rate
        stars.forEach(star => {
            star.addEventListener('click', async () => {
                const rating = parseInt(star.dataset.star);
                stars.forEach(s => s.classList.toggle('selected', parseInt(s.dataset.star) <= rating));
                localStorage.setItem(`rating_${projectId}`, rating);

                try {
                    const res  = await fetch(API, {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body:    JSON.stringify({
                            action:     'rate',
                            project_id: parseInt(projectId),
                            rating:     rating,
                        }),
                    });
                    const data = await res.json();
                    if (data.success) {
                        updateAvgDisplay(section, data.avg_rating, data.rating_count);
                    }
                } catch (e) {
                    console.debug('Rating failed:', e.message);
                }
            });
        });
    }

    function highlightStars(stars, upTo) {
        stars.forEach(s => {
            const n = parseInt(s.dataset.star);
            s.classList.toggle('hovered', n <= upTo);
        });
    }

    function updateAvgDisplay(section, avg, count) {
        const avgVal = section.querySelector('.avg-val');
        const avgTotal = section.querySelector('.avg-total');
        if (avgVal)   avgVal.textContent   = avg ? parseFloat(avg).toFixed(1) : '–';
        if (avgTotal) avgTotal.textContent = count ? `(${count})` : '';
    }

    // ── SHARE BUTTON ─────────────────────────────────────────
    function initShareButton(section, projectId) {
        const btn = section.querySelector('.share-btn');
        if (!btn) return;

        btn.addEventListener('click', async () => {
            const url = `${window.location.origin}${window.location.pathname}#reactions-${projectId}`;
            try {
                await navigator.clipboard.writeText(url);
                btn.classList.add('copied');
                const lbl = btn.querySelector('.react-label');
                const orig = lbl?.textContent;
                if (lbl) lbl.textContent = 'Copied!';
                setTimeout(() => {
                    btn.classList.remove('copied');
                    if (lbl && orig) lbl.textContent = orig;
                }, 2000);
            } catch (e) {
                // Fallback
                prompt('Copy this link:', url);
            }
        });
    }

    // ── COMMENT TOGGLE ─────────────────────────────────────────
    function initCommentToggle(section, projectId) {
        const toggleBtn = section.querySelector('.toggle-comments-btn');
        const area      = section.querySelector('.proj-comments-area');
        const lbl       = toggleBtn?.querySelector('.toggle-label');
        if (!toggleBtn || !area) return;

        let loaded = false;

        toggleBtn.addEventListener('click', () => {
            const expanded = toggleBtn.getAttribute('aria-expanded') === 'true';
            toggleBtn.setAttribute('aria-expanded', !expanded);

            if (!expanded) {
                area.removeAttribute('hidden');
                if (lbl) lbl.setAttribute('data-i18n', 'react.hide');
                if (lbl) lbl.textContent = window.t?.('react.hide') || 'Hide comments';
                if (!loaded) {
                    loadProjectComments(section, projectId);
                    loaded = true;
                }
            } else {
                area.setAttribute('hidden', '');
                if (lbl) lbl.setAttribute('data-i18n', 'react.show');
                if (lbl) lbl.textContent = window.t?.('react.show') || 'Show comments';
            }
        });
    }

    // ── LOAD PROJECT COMMENTS ─────────────────────────────────
    async function loadProjectComments(section, projectId) {
        const feed    = section.querySelector('.proj-comments-feed');
        const noMsg   = section.querySelector('.no-comments-msg');
        const loading = section.querySelector('.comments-loading-msg');

        if (!feed) return;

        if (loading) loading.style.display = 'block';
        if (noMsg)   noMsg.style.display   = 'none';

        try {
            const res  = await fetch(`${API}?action=comments&project_id=${projectId}&per_page=20`);
            const data = await res.json();

            if (loading) loading.style.display = 'none';

            if (!data.success || !data.comments?.length) {
                if (noMsg) noMsg.style.display = 'block';
                return;
            }

            // Remove existing cards (not the loading/empty messages)
            feed.querySelectorAll('.proj-comment-card').forEach(c => c.remove());

            data.comments.forEach(c => {
                const card = buildCommentCard(c);
                feed.insertBefore(card, feed.firstChild);
            });

            // Update count
            const ccEl = section.querySelector('.proj-comment-count');
            if (ccEl) ccEl.textContent = data.total ?? data.comments.length;

        } catch (e) {
            if (loading) loading.style.display = 'none';
            if (noMsg)   noMsg.style.display   = 'block';
            console.debug('Load comments failed:', e.message);
        }
    }

    // ── BUILD COMMENT CARD ────────────────────────────────────
    function buildCommentCard(c) {
        const div      = document.createElement('div');
        div.className  = 'proj-comment-card';
        div.dataset.id = c.id;

        const avatar  = (c.full_name || '?').charAt(0).toUpperCase();
        const stars   = c.rating ? '★'.repeat(c.rating) : '';
        const dateStr = formatDate(c.created_at);
        const likes   = c.like_count ?? 0;

        div.innerHTML = `
            <div class="pcc-avatar">${escHtml(avatar)}</div>
            <div class="pcc-body">
                <div class="pcc-header">
                    <span class="pcc-name">${escHtml(c.full_name || 'Anonymous')}</span>
                    <span class="pcc-date">${dateStr}</span>
                    ${stars ? `<span class="pcc-stars">${stars}</span>` : ''}
                </div>
                <p class="pcc-text">${escHtml(c.comment_text)}</p>
                <div class="pcc-actions">
                    <button class="pcc-like-btn" data-comment-id="${c.id}" aria-label="Like comment">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                        </svg>
                        <span class="pcc-like-count">${likes}</span>
                    </button>
                </div>
            </div>`;

        // Comment like handler
        const likeBtn = div.querySelector('.pcc-like-btn');
        likeBtn.addEventListener('click', () => toggleCommentLike(likeBtn, c.id));

        // Restore liked state
        if (localStorage.getItem(`clk_${c.id}`)) {
            likeBtn.classList.add('active');
        }

        return div;
    }

    async function toggleCommentLike(btn, commentId) {
        try {
            const res  = await fetch(API, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({
                    action:     'like_comment',
                    comment_id: commentId,
                }),
            });
            const data = await res.json();
            if (data.success) {
                btn.classList.toggle('active', data.liked);
                const countEl = btn.querySelector('.pcc-like-count');
                if (countEl) countEl.textContent = data.count;
                if (data.liked) {
                    localStorage.setItem(`clk_${commentId}`, '1');
                } else {
                    localStorage.removeItem(`clk_${commentId}`);
                }
            }
        } catch (e) {
            console.debug('Comment like failed:', e.message);
        }
    }

    // ── QUICK COMMENT FORM ────────────────────────────────────
    function initQuickCommentForm(section, projectId) {
        const form     = section.querySelector('.quick-comment-form');
        const feedback = section.querySelector('.qcf-feedback');
        const submitBtn = form?.querySelector('.qcf-submit');
        const nameInput = form?.querySelector('[name="full_name"]');

        if (!form) return;

        // Update avatar initial as user types name
        if (nameInput) {
            nameInput.addEventListener('input', () => {
                const avatar = section.querySelector('.qcf-avatar');
                if (avatar) {
                    const v = nameInput.value.trim();
                    avatar.textContent = v ? v.charAt(0).toUpperCase() : '?';
                }
            });
        }

        form.addEventListener('submit', async e => {
            e.preventDefault();

            const name = form.querySelector('[name="full_name"]')?.value.trim();
            const email = form.querySelector('[name="email"]')?.value.trim();
            const text  = form.querySelector('[name="comment_text"]')?.value.trim();

            if (!name || !text) {
                showFormFeedback(feedback, 'err',
                    window.t?.('react.err_required') || 'Name and comment are required.');
                return;
            }

            if (submitBtn) submitBtn.disabled = true;

            try {
                const res  = await fetch(API, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({
                        action:       'comment',
                        project_id:   parseInt(projectId),
                        full_name:    name,
                        email:        email,
                        comment_text: text,
                        section:      `project_${projectId}`,
                    }),
                });
                const data = await res.json();

                if (data.success) {
                    form.reset();
                    const avatar = section.querySelector('.qcf-avatar');
                    if (avatar) avatar.textContent = '?';
                    showFormFeedback(feedback, 'ok',
                        window.t?.('react.comment_ok') ||
                        '✓ Comment submitted! It will appear after a quick review.');

                    // If instantly approved, prepend it
                    if (data.approved && data.comment) {
                        const feed = section.querySelector('.proj-comments-feed');
                        const noMsg = section.querySelector('.no-comments-msg');
                        if (noMsg) noMsg.style.display = 'none';
                        if (feed) {
                            const card = buildCommentCard(data.comment);
                            feed.insertBefore(card, feed.firstChild);
                        }
                        // Update comment count
                        const ccEl = section.querySelector('.proj-comment-count');
                        if (ccEl) ccEl.textContent = parseInt(ccEl.textContent || 0) + 1;
                    }
                } else {
                    showFormFeedback(feedback, 'err', data.message || 'Submission failed.');
                }
            } catch (e) {
                showFormFeedback(feedback, 'err', 'Network error. Please try again.');
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    }

    // ── HELPERS ───────────────────────────────────────────────
    function showFormFeedback(el, type, msg) {
        if (!el) return;
        el.textContent = msg;
        el.className   = `qcf-feedback ${type}`;
        setTimeout(() => { el.className = 'qcf-feedback'; el.textContent = ''; }, 6000);
    }

    function saveUserReaction(projectId, type, active) {
        const key  = `reactions_${projectId}`;
        const data = JSON.parse(localStorage.getItem(key) || '{}');
        data[type] = active;
        localStorage.setItem(key, JSON.stringify(data));
    }

    function restoreUserReactions(section, projectId, serverData) {
        const saved = JSON.parse(localStorage.getItem(`reactions_${projectId}`) || '{}');
        const types = ['like', 'dislike', 'wow'];
        types.forEach(type => {
            const isActive = serverData?.user_reactions?.[type] ?? saved[type] ?? false;
            const btn = section.querySelector(`.${type}-btn`);
            if (btn) {
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            }
        });
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        const now = new Date();
        const diff = now - d;
        const mins = Math.floor(diff / 60000);
        if (mins < 1)  return 'just now';
        if (mins < 60) return `${mins}m ago`;
        const hrs = Math.floor(mins / 60);
        if (hrs < 24)  return `${hrs}h ago`;
        const days = Math.floor(hrs / 24);
        if (days < 7)  return `${days}d ago`;
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    // ── AUTO-INIT ─────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

})();
