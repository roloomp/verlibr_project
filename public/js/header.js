
class MyHeader extends HTMLElement {
    connectedCallback() {
        this.innerHTML = `
        <header class="header">
            <a href="/" class="header__left">
                <img class="header__logo" src="public/assets/icons/Icon_main.png" alt="Верлибр">
                <span class="header__title">Verlibr</span>
            </a>
            <nav class="header__nav" aria-label="Навигация">
                <a href="#" class="header__icon" aria-label="Популярное">
                    <img src="public/assets/icons/Icon_fire.png" alt="">
                </a>
                <a href="#" class="header__icon" aria-label="Поиск">
                    <img src="public/assets/icons/Icon_search.png" alt="">
                </a>
                <a href="#" class="header__icon" aria-label="Профиль">
                    <img src="public/assets/icons/Icon_profile.png" alt="">
                </a>
                <a href="#" class="header__icon" aria-label="Избранное">
                    <img src="public/assets/icons/Icon_favorite.png" alt="">
                </a>
            </nav>
        </header>
        `;
    }
}
customElements.define('my-header', MyHeader);


class AuthButtons extends HTMLElement {
    connectedCallback() {
        const serverError = this.dataset.error ?? '';

        this.innerHTML = `
        <div class="auth-bar">
            <button class="auth-btn auth-btn--login" id="btn-login">Вход</button>
            <button class="auth-btn auth-btn--register" id="btn-register">Регистрация</button>
        </div>

        <div class="overlay" id="overlay">
            <div class="modal" role="dialog" aria-modal="true">
                <button class="modal__close" id="btn-close" aria-label="Закрыть">✕</button>

                <!-- Форма входа -->
                <form class="form-panel" id="panel-login" method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form__title">Вход</div>
                    <div class="form__subtitle">Введите свои данные для входа в аккаунт</div>

                    <label class="form__label">Email <span class="required">*</span></label>
                    <input class="form__input" type="email" placeholder="Ваш email" name="login_email" required>

                    <div class="form__label">
                        Пароль <span class="required">*</span>
                        <button type="button" class="form__link-btn">Забыли пароль?</button>
                    </div>
                    <input class="form__input" type="password" placeholder="Ваш пароль" name="login_password" required>

                    ${serverError ? `<div class="error-message">${serverError}</div>` : ''}

                    <button class="form__btn form__btn--primary" type="submit">Войти</button>
                    <button class="form__btn form__btn--secondary" id="btn-go-register" type="button">Зарегистрироваться</button>
                </form>

                <!-- Форма регистрации -->
                <form class="form-panel" id="panel-register" method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="form__title">Создать аккаунт</div>

                    <label class="form__label">Email <span class="required">*</span></label>
                    <div class="form__hint">Будет также логином для авторизации</div>
                    <input class="form__input" type="email" placeholder="mail@example.com" name="register_email" required>

                    <label class="form__label">Отображаемое имя <span class="required">*</span></label>
                    <div class="form__hint">Ваш никнейм</div>
                    <input class="form__input" type="text" name="register_nickname" required>

                    <label class="form__label">Пароль <span class="required">*</span></label>
                    <input class="form__input" type="password" name="register_password" required>

                    <label class="form__label">Подтвердите пароль <span class="required">*</span></label>
                    <input class="form__input" type="password" name="register_verify_password" required>

                    <button class="form__btn form__btn--primary" type="submit">Создать аккаунт</button>
                    <p class="form__footer">
                        Уже есть аккаунт?
                        <button class="form__footer-btn" id="btn-go-login" type="button">Войти</button>
                    </p>
                </form>
            </div>
        </div>
        `;

        this._initModal();

        if (serverError) {
            const failedAction = document.querySelector('input[name="action"]')?.value;
            this._openModal(failedAction === 'register' ? 'register' : 'login');
        }
    }

    _initModal() {
        const overlay = this.querySelector('#overlay');
        const modal   = this.querySelector('.modal');
        const panels  = this.querySelectorAll('.form-panel');

        const openModal = (tab) => {
            if (document.activeElement) document.activeElement.blur();
            panels.forEach(p => p.classList.remove('active'));
            this.querySelector(`#panel-${tab}`).classList.add('active');
            overlay.classList.add('open');
        };

        const closeModal = () => overlay.classList.remove('open');

        this._openModal = openModal;

        this.querySelector('#btn-login').addEventListener('click', () => openModal('login'));
        this.querySelector('#btn-register').addEventListener('click', () => openModal('register'));
        this.querySelector('#btn-close').addEventListener('click', closeModal);
        this.querySelector('#btn-go-register').addEventListener('click', () => openModal('register'));
        this.querySelector('#btn-go-login').addEventListener('click', () => openModal('login'));

        overlay.addEventListener('click', (e) => {
            if (!modal.contains(e.target)) closeModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModal();
        });
    }
}
customElements.define('auth-buttons', AuthButtons);
