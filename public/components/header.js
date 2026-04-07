class MyHeader extends HTMLElement {
    connectedCallback() {
        this.innerHTML = `
        <header class="header">
            <a href="#" class="left_path_header">
                <div class="logo-img">
                    <img src="public/source/Icon_main.png">
                </div>
                <div class="title">
                    <p>Верлибр</p>
                </div>
            </a>
            <nav class="right_path_header">
                <a href="#" class="header_button">
                    <img src="public/source/Icon_search.png">
                </a>
                <a href="#" class="header_button">
                    <img src="public/source/Icon_fire.png">
                </a>
                <a href="#" class="header_button">
                    <img src="public/source/Icon_favorite.png">
                </a>
                <a href="#" class="header_button">
                    <img src="public/source/Icon_profile.png">
                </a>
            </nav>
        </header>
        `;
    }
}
customElements.define('my-header', MyHeader);

class RegistrationAndLoginButton extends HTMLElement {
    connectedCallback() {
        this.innerHTML = `
        <div class="auth-button">
            <button class="auth-btn left-btn" id="btn-login">Вход</button>
            <button class="auth-btn right-btn" id="btn-register">Регистрация</button>
        </div>

        <div class="overlay" id="overlay">
            <div class="modal">
                <button class="close-btn" id="btn-close">✕</button>

                <form class="form-panel" id="panel-login" action="" method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="modal-tabs-login-text1">Вход</div>
                    <div class="modal-tabs-login-text2">Введите свои данные для входа в аккаунт</div>
                    <div class="modal-tabs-text-preinput">Email <span style="color: #F00;">*</span></div>
                    <input class="modal-tabs-input" type="email" placeholder="Ваш email" name="login_email">
                    <div class="modal-tabs-text-preinput">
                        Пароль <span style="color: #F00;">*</span>
                        <button class="modal-tabs-login-text-preinput-forgot-pass">Забыли пароль?</button>
                    </div>
                    <input class="modal-tabs-input" type="password" placeholder="Ваш пароль" name="login_password">
                    <?php if (!empty($error)): ?>
                    <div class="error-message" name="error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <button class="modal-tabs-login-button-log" type="submit">Войти</button>
                    <button class="modal-tabs-login-button-reg" id="btn-go-register" type="button">Зарегестрироваться</button>
                </form>

                <form class="form-panel" id="panel-register" action="" method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="modal-tabs-login-text1">Создать аккаунт</div>
                    <div>
                        <div class="modal-tabs-text-preinput">Email <span style="color: #F00;">*</span></div>
                        <div class="modal-tabs-text-preinput2">Будет также логином для авторизации</div>
                    </div>
                    <input class="modal-tabs-input" type="email" placeholder="mail@example.com" name="register_email">
                    <div>
                        <div class="modal-tabs-text-preinput">Отображаемое имя <span style="color: #F00;">*</span></div>
                        <div class="modal-tabs-text-preinput2">Ваш никнейм</div>
                    </div>
                    <input class="modal-tabs-input" type="text" name="register_nickname">
                    <div class="modal-tabs-text-preinput">Пароль <span style="color: #F00;">*</span></div>
                    <input class="modal-tabs-input" type="password" name="register_password">
                    <div class="modal-tabs-text-preinput">Подтвердите пароль <span style="color: #F00;">*</span></div>
                    <input class="modal-tabs-input" type="password" name="register_verify_password">
                    <button class="modal-tabs-login-button-log" type="submit">Создать аккаунт</button>
                    <div class="modal-tabs-u-have-acc">
                        Уже есть аккаунт? <button class="modal-blue-button-login" id="btn-go-login" type="button">Войти</button>
                    </div>
                </form>
            </div>
        </div>
        `;

        const overlay = this.querySelector('#overlay');
        const modal = this.querySelector('.modal');
        const panels = this.querySelectorAll('.form-panel');

        const openModal = (tab) => {
            if (document.activeElement)
                document.activeElement.blur();
                panels.forEach(p => p.classList.remove('active'));
                this.querySelector('#panel-' + tab).classList.add('active');
                overlay.classList.add('open');
        };

        const closeModal = () => overlay.classList.remove('open');

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
customElements.define('auth-buttons', RegistrationAndLoginButton);