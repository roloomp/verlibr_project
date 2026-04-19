    // Этот блок заменяет весь <script> в конце poem.php (кроме src header.js)

    function requireLogin() {
        if (typeof window.openAuthModal === 'function') {
            window.openAuthModal('login');
        }
    }

    document.getElementById('login-to-rate')?.addEventListener('click', (e) => {
        e.preventDefault();
        requireLogin();
    });

    // Слайдеры
    const sliders = document.querySelectorAll('.slider');
    function updateTotal() {
        let sum = 0;
        sliders.forEach(s => { sum += parseInt(s.value); });
        const maxTotal = sliders.length * 18;
        document.getElementById('total-score').innerHTML = sum + '<sup>/' + maxTotal + '</sup>';
    }
    sliders.forEach(s => {
        const outId = s.dataset.out;
        const out = document.getElementById(outId);
        if (out) out.textContent = s.value;
        s.addEventListener('input', () => {
            if (out) out.textContent = s.value;
            updateTotal();
        });
    });
    updateTotal();

    // Счётчик символов рецензии
    const textarea = document.getElementById('review-text-area');
    const charCount = document.getElementById('char-count');
    if (textarea) {
        textarea.addEventListener('input', () => {
            charCount.textContent = textarea.value.length;
        });
    }

    document.getElementById('clear-draft')?.addEventListener('click', () => {
        if (textarea) { textarea.value = ''; charCount.textContent = 0; }
        const titleInput = document.querySelector('input[name="review_title"]');
        if (titleInput) titleInput.value = '';
    });

    // Табы оценки
    document.querySelectorAll('.rate-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.rate-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const isReview = tab.dataset.tab === 'review';
            document.getElementById('review-fields').style.display = isReview ? 'block' : 'none';
        });
    });

    // Получаем CSRF токен один раз для fetch-запросов
    let csrfToken = '';
    fetch('public/api/get_csrf.php')
        .then(r => r.json())
        .then(data => { csrfToken = data.token || ''; })
        .catch(() => {});

    // Лайк стихотворения
    document.getElementById('btn-like')?.addEventListener('click', async function() {
        if (this.dataset.loggedIn !== '1') { requireLogin(); return; }
        const poemId = this.dataset.poemId;
        try {
            const fd = new FormData();
            fd.append('poem_id', poemId);
            const res = await fetch('public/api/toggle_like.php', { method: 'POST', body: fd });
            const data = await res.json();
            const liked = data.action === 'added';
            this.innerHTML = (liked ? '♥' : '♡') + ' <span id="like-count">' + data.count + '</span>';
            this.classList.toggle('active', liked);
        } catch(e) { console.error('like error', e); }
    });

    // Избранное — теперь с CSRF через заголовок
    document.getElementById('btn-fav')?.addEventListener('click', async function() {
        if (this.dataset.loggedIn !== '1') { requireLogin(); return; }
        const poemId = this.dataset.poemId;
        try {
            const fd = new FormData();
            fd.append('poem_id', poemId);
            const res = await fetch('public/api/toggle_favorite.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken },
                body: fd
            });
            const data = await res.json();
            if (data.error) { console.error('fav error', data.error); return; }
            const faved = data.action === 'added';
            this.textContent = faved ? '★' : '☆';
            this.classList.toggle('active', faved);
        } catch(e) { console.error('fav error', e); }
    });

    // Лайки рецензий
    document.querySelectorAll('.review-like').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (this.dataset.loggedIn !== '1') { requireLogin(); return; }
            const reviewId = this.dataset.reviewId;
            try {
                const fd = new FormData();
                fd.append('review_id', reviewId);
                const res = await fetch('public/api/toggle_review_like.php', { method: 'POST', body: fd });
                const data = await res.json();
                const liked = data.action === 'added';
                this.innerHTML = (liked ? '♥' : '♡') + ' <span>' + data.count + '</span>';
                this.classList.toggle('active', liked);
            } catch(e) { console.error('review like error', e); }
        });
    });
