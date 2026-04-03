
class MainScreenContainerItem extends HTMLElement {
    connectedCallback() {
    this.innerHTML = `
    <div class="ms-item">
        <div>
            <div class="item_title"><a href="#">Канал Грибоедова</a></div>
            <div class="author"><a href="#">Владислав Савенко</a></div>
        </div>
        <div class="year">2026</div>
    </div>
    `;
    }
}
customElements.define(`main-screen-item`, MainScreenContainerItem)