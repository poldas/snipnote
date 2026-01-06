import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'list', 'totalCount', 'completedCount', 'pendingCount', 'markdownContent'];

    connect() {
        this.todos = [];
        this.idCounter = 1;
        
        // Inicjalizacja danych
        this.parseMarkdown();
        this.render();
    }

    parseMarkdown() {
        if (!this.hasMarkdownContentTarget) return;

        // Szukamy wszystkich li wewnątrz ul
        const items = this.markdownContentTarget.querySelectorAll('ul li');
        
        if (items.length > 0) {
            this.todos = [];
            items.forEach(item => {
                const text = item.textContent.trim();
                if (text) {
                    this.todos.push({
                        id: this.idCounter++,
                        text: text,
                        completed: false
                    });
                }
            });
            
            // Ukrywamy oryginalne listy Markdown
            this.markdownContentTarget.querySelectorAll('ul').forEach(ul => {
                ul.style.display = 'none';
            });
        }
    }

    add(event) {
        if (event.type === 'keypress' && event.key !== 'Enter') return;

        const text = this.inputTarget.value.trim();
        if (!text) return;

        this.todos.push({
            id: this.idCounter++,
            text: text,
            completed: false
        });

        this.inputTarget.value = '';
        this.render();
    }

    toggle(event) {
        const id = parseInt(event.currentTarget.dataset.id);
        const todo = this.todos.find(t => t.id === id);
        
        if (todo) {
            // Bezpośrednio zmieniamy stan na podstawie checkboxa
            todo.completed = event.currentTarget.checked;
            this.render();
        }
    }

    remove(event) {
        const id = parseInt(event.currentTarget.dataset.id);
        this.todos = this.todos.filter(t => t.id !== id);
        this.render();
    }

    render() {
        // 1. Aktualizacja statystyk (musi być przed lub w trakcie renderowania)
        const total = this.todos.length;
        const completed = this.todos.filter(t => t.completed).length;
        const pending = total - completed;

        if (this.hasTotalCountTarget) this.totalCountTarget.textContent = total;
        if (this.hasCompletedCountTarget) this.completedCountTarget.textContent = completed;
        if (this.hasPendingCountTarget) this.pendingCountTarget.textContent = pending;

        // 2. Renderowanie listy
        if (total === 0) {
            this.listTarget.innerHTML = `
                <div class="empty-state">
                    <span class="emoji">🛒</span>
                    <h3>Lista jest pusta</h3>
                    <p>Dodaj pierwsze zadanie, aby zacząć!</p>
                </div>
            `;
            return;
        }

        // Czyścimy i budujemy nową listę
        this.listTarget.innerHTML = '';
        this.todos.forEach(todo => {
            const item = document.createElement('div');
            item.className = `todo-item ${todo.completed ? 'completed' : ''}`;
            item.innerHTML = `
                <div class="todo-checkbox-wrapper">
                    <input 
                        type="checkbox" 
                        class="todo-checkbox" 
                        ${todo.completed ? 'checked' : ''}
                        data-id="${todo.id}"
                    >
                </div>
                <div class="todo-text">${this.escapeHtml(todo.text)}</div>
                <button 
                    class="btn-delete" 
                    data-id="${todo.id}"
                >
                    Usuń
                </button>
            `;

            // Ręczne bindowanie eventów dla większej pewności
            item.querySelector('.todo-checkbox').addEventListener('change', (e) => this.toggle(e));
            item.querySelector('.btn-delete').addEventListener('click', (e) => this.remove(e));

            this.listTarget.appendChild(item);
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
