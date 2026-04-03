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
customElements.define(`my-header`, MyHeader)

class RegistrationAndLoginButton extends HTMLElement {
    connectedCallback() {
    this.innerHTML = `
    <div class="auth-button">
        <button onclick="openModal('login')" class="auth-btn left-btn">Вход</button>
        <button onclick="openModal('register')" class="auth-btn right-btn">Регистрация</button>
    </div>
    `;
    }
}
customElements.define(`auth-buttons`, RegistrationAndLoginButton)