var _SITE_ROOT = (function() {
    var scripts = document.getElementsByTagName('script');
    for (var i = 0; i < scripts.length; i++) {
        var src = scripts[i].src;
        var m = src.match(/^(https?:\/\/[^\/]+)(\/.*?)public\/js\/header\.js/);
        if (m) return m[1] + m[2];
    }
    return '/';
})();

class MyHeader extends HTMLElement {
    connectedCallback() {
        var path = window.location.pathname;
        var page = path.split('/').pop() || 'index.php';

        function isActive(names) {
            return names.some(function(n) {
                return page === n || path.endsWith('/' + n);
            }) ? 'header__icon--active' : '';
        }

        var r = _SITE_ROOT;

        this.innerHTML =
            '<header class="header">' +
                '<a href="' + r + 'index.php" class="header__left">' +
                    '<img class="header__logo" src="' + r + 'public/assets/icons/Icon_main.png" alt="Верлибр">' +
                    '<span class="header__title">Verlibr</span>' +
                '</a>' +
                '<nav class="header__nav">' +
                    '<a href="' + r + 'popular.php" class="header__icon ' + isActive(['popular.php']) + '" title="Популярное">' +
                        '<img src="' + r + 'public/assets/icons/Icon_fire.png" alt="Популярное">' +
                    '</a>' +
                    '<a href="' + r + 'search.php" class="header__icon ' + isActive(['search.php']) + '" title="Поиск">' +
                        '<img src="' + r + 'public/assets/icons/Icon_search.png" alt="Поиск">' +
                    '</a>' +
                    '<a href="' + r + 'profile.php" class="header__icon ' + isActive(['profile.php']) + '" title="Профиль">' +
                        '<img src="' + r + 'public/assets/icons/Icon_profile.png" alt="Профиль">' +
                    '</a>' +
                    '<a href="' + r + 'favorites.php" class="header__icon ' + isActive(['favorites.php']) + '" title="Избранное">' +
                        '<img src="' + r + 'public/assets/icons/Icon_favorite.png" alt="Избранное">' +
                    '</a>' +
                '</nav>' +
            '</header>';
    }
}
customElements.define('my-header', MyHeader);


class AuthButtons extends HTMLElement {
    connectedCallback() {
        var r = _SITE_ROOT;

        this.innerHTML =
            '<div class="auth-bar">' +
                '<button class="auth-btn auth-btn--login"    id="btn-login">Вход</button>' +
                '<button class="auth-btn auth-btn--register" id="btn-register">Регистрация</button>' +
            '</div>' +

            '<div class="overlay" id="overlay">' +
                '<div class="modal" role="dialog" aria-modal="true">' +
                    '<button class="modal__close" id="btn-close" aria-label="Закрыть">✕</button>' +

                    '<div id="auth-error-msg" style="display:none;color:red;font-size:13px;margin-bottom:10px;text-align:center;"></div>' +

                    '<form class="form-panel" id="panel-login" method="POST" action="' + r + 'auth.php">' +
                        '<input type="hidden" name="action" value="login">' +
                        '<input type="hidden" name="csrf_token" id="csrf-login" value="">' +
                        '<div class="form__title">Вход</div>' +
                        '<div class="form__subtitle">Введите свои данные для входа в аккаунт</div>' +
                        '<label class="form__label">Email <span class="required">*</span></label>' +
                        '<input class="form__input" type="email" name="login_email" placeholder="Ваш email" autocomplete="email" required>' +
                        '<div class="form__label">Пароль <span class="required">*</span></div>' +
                        '<input class="form__input" type="password" name="login_password" placeholder="Ваш пароль" autocomplete="current-password" required>' +
                        '<button class="form__btn form__btn--primary" type="submit">Войти</button>' +
                        '<button class="form__btn form__btn--secondary" id="btn-go-register" type="button">Зарегистрироваться</button>' +
                    '</form>' +

                    '<form class="form-panel" id="panel-register" method="POST" action="' + r + 'auth.php">' +
                        '<input type="hidden" name="action" value="register">' +
                        '<input type="hidden" name="csrf_token" id="csrf-register" value="">' +
                        '<div class="form__title">Создать аккаунт</div>' +
                        '<label class="form__label">Email <span class="required">*</span></label>' +
                        '<div class="form__hint">Будет также логином для авторизации</div>' +
                        '<input class="form__input" type="email" name="register_email" placeholder="mail@example.com" autocomplete="email" required>' +
                        '<label class="form__label">Отображаемое имя <span class="required">*</span></label>' +
                        '<div class="form__hint">Ваш никнейм</div>' +
                        '<input class="form__input" type="text" name="register_nickname" autocomplete="username" required>' +
                        '<label class="form__label">Пароль <span class="required">*</span></label>' +
                        '<input class="form__input" type="password" name="register_password" autocomplete="new-password" required>' +
                        '<label class="form__label">Подтвердите пароль <span class="required">*</span></label>' +
                        '<input class="form__input" type="password" name="register_verify_password" autocomplete="new-password" required>' +
                        '<button class="form__btn form__btn--primary" type="submit">Создать аккаунт</button>' +
                        '<p class="form__footer">Уже есть аккаунт? ' +
                            '<button class="form__footer-btn" id="btn-go-login" type="button">Войти</button>' +
                        '</p>' +
                    '</form>' +

                '</div>' +
            '</div>';

        // Грузим CSRF
        fetch(r + 'public/api/get_csrf.php')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                var t = data.token || '';
                var el1 = document.getElementById('csrf-login');
                var el2 = document.getElementById('csrf-register');
                if (el1) el1.value = t;
                if (el2) el2.value = t;
            })
            .catch(function() {});

        // Проверяем — есть ли ошибка авторизации от сервера
        fetch(r + 'public/api/get_auth_error.php')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.error) {
                    var el = document.getElementById('auth-error-msg');
                    if (el) {
                        el.textContent = data.error;
                        el.style.display = 'block';
                    }
                    // Открываем модалку автоматически если есть ошибка
                    var tab = data.tab || 'login';
                    window.openAuthModal(tab);
                }
            })
            .catch(function() {});

        this._initModal();
    }

    _initModal() {
        var self    = this;
        var overlay = this.querySelector('#overlay');
        var modal   = this.querySelector('.modal');
        var panels  = this.querySelectorAll('.form-panel');

        function openModal(tab) {
            panels.forEach(function(p) { p.classList.remove('active'); });
            var target = self.querySelector('#panel-' + tab);
            if (target) target.classList.add('active');
            overlay.classList.add('open');
        }

        function closeModal() {
            overlay.classList.remove('open');
        }

        window.openAuthModal = function(tab) { openModal(tab || 'login'); };

        this.querySelector('#btn-login').addEventListener('click',       function() { openModal('login'); });
        this.querySelector('#btn-register').addEventListener('click',    function() { openModal('register'); });
        this.querySelector('#btn-close').addEventListener('click',       closeModal);
        this.querySelector('#btn-go-register').addEventListener('click', function() { openModal('register'); });
        this.querySelector('#btn-go-login').addEventListener('click',    function() { openModal('login'); });

        overlay.addEventListener('click', function(e) {
            if (!modal.contains(e.target)) closeModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
    }
}
customElements.define('auth-buttons', AuthButtons);
