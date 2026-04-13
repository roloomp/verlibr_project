async function fetchJson(url) {
    const response = await fetch(url);
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
}

function renderEmpty(container, text) {
    container.innerHTML = `<p class="empty-msg">${text}</p>`;
}


class PoemItem extends HTMLElement {
    connectedCallback() {
        const title  = this.getAttribute('title')  ?? '';
        const author = this.getAttribute('author') ?? '';
        const year   = this.getAttribute('year')   ?? '';
        this.innerHTML = `
        <div class="ms-item">
            <div>
                <div class="item-title"><a href="#">${title}</a></div>
                <div class="item-author"><a href="#">${author}</a></div>
            </div>
            <div class="item-year">${year}</div>
        </div>
        `;
    }
}
customElements.define('poem-item', PoemItem);


class AuthorItem extends HTMLElement {
    connectedCallback() {
        const name  = this.getAttribute('name')  ?? 'Автор';
        const count = this.getAttribute('count') ?? '';
        this.innerHTML = `
        <div class="ms-author-item">
            <div class="author-name"><a href="#">${name}</a></div>
            ${count ? `<div class="author-count">${count} стихов</div>` : ''}
        </div>
        `;
    }
}
customElements.define('author-item', AuthorItem);


async function loadPoems(containerId, type) {
    const container = document.getElementById(containerId);
    if (!container) return;

    try {
        const poems = await fetchJson(`public/api/get_poems.php?type=${type}`);
        container.innerHTML = '';

        if (poems.length === 0) {
            renderEmpty(container, 'Пусто');
            return;
        }

        poems.forEach((poem, index) => {
            const item = document.createElement('poem-item');
            item.setAttribute('title',  poem.title  ?? '');
            item.setAttribute('author', poem.author ?? '');
            item.setAttribute('year',   poem.year   ?? '');
            container.appendChild(item);
            if (index < poems.length - 1) {
                container.appendChild(document.createElement('hr'));
            }
        });
    } catch (err) {
        renderEmpty(container, 'Ошибка загрузки');
    }
}

async function loadAuthors() {
    const container = document.querySelector('.authors-list');
    if (!container) return;

    try {
        const authors = await fetchJson('public/api/get_authors.php');
        container.innerHTML = '';

        authors.forEach((author, index) => {
            const item = document.createElement('author-item');
            item.setAttribute('name',  author.name);
            item.setAttribute('count', author.poem_count);
            container.appendChild(item);
            if (index < authors.length - 1) {
                container.appendChild(document.createElement('hr'));
            }
        });
    } catch {
        renderEmpty(container, 'Ошибка загрузки');
    }
}


document.addEventListener('DOMContentLoaded', () => {
    loadPoems('poems-day',    'daily');
    loadPoems('poems-editor', 'editors');
    loadAuthors();
});
