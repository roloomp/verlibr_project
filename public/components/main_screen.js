
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


class MainScreenContainerItem extends HTMLElement {

connectedCallback(){
    const title = this.getAttribute("title")
    const author = this.getAttribute("author")
    const year = this.getAttribute("year")
    this.innerHTML = `
    <div class="ms-item">
        <div>
            <div class="item_title"><a href="#">${title}</a></div>
            <div class="author"><a href="#">${author}</a></div>
        </div>
        <div class="year">${year}</div>
    </div>
    `
    }
}

customElements.define("main-screen-item", MainScreenContainerItem)


fetch("/public/api/get_poems.php")
.then(r => r.json())
.then(data => {
    const container = document.getElementById("poems-day")
    data.forEach(poem => {
        const item = document.createElement("main-screen-item")
        item.setAttribute("title", poem.title)
        item.setAttribute("author", poem.author)
        item.setAttribute("year", poem.year)
        container.appendChild(item)
        const hr = document.createElement("hr")
        container.appendChild(hr)
    })
})




