import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'list', 'totalCount', 'completedCount', 'pendingCount', 'markdownContent'];
    static values = {
        noteId: String
    };

    connect() {
        this.todos = [];
        this.idCounter = 1;
        
        // 1. Najpierw parsujemy Markdown, żeby mieć bazę (jeśli localstorage będzie puste)
        this.parseMarkdown();
        
        // 2. Próbujemy wczytać stan z pamięci lokalnej
        this.loadFromLocalStorage();
        
        // 3. Wyświetlamy efekt końcowy
        this.render();
    }

    parseMarkdown() {
        if (!this.hasMarkdownContentTarget) return;

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
            
            this.markdownContentTarget.querySelectorAll('ul').forEach(ul => {
                ul.style.display = 'none';
            });
        }
    }

    // --- Persystencja ---

    get storageKey() {
        return `snipnote_todo_v1_${this.noteIdValue}`;
    }

    saveToLocalStorage() {
        const data = {
            todos: this.todos,
            idCounter: this.idCounter
        };
        localStorage.setItem(this.storageKey, JSON.stringify(data));
    }

    loadFromLocalStorage() {
        const stored = localStorage.getItem(this.storageKey);
        if (stored) {
            try {
                const data = JSON.parse(stored);
                // Jeśli mamy dane w storage, nadpisujemy to co przyszło z Markdowna
                this.todos = data.todos || [];
                this.idCounter = data.idCounter || 1;
            } catch (e) {
                console.error("Błąd podczas wczytywania listy zadań:", e);
            }
        }
    }

    reset() {
        const message = "Czy na pewno chcesz przywrócić listę do stanu początkowego?\n\nSpowoduje to usunięcie wszystkich Twoich lokalnych zmian (dodanych zadań, skreśleń) i przywrócenie oryginalnej treści notatki.";
        
        if (confirm(message)) {
            localStorage.removeItem(this.storageKey);
            this.todos = [];
            this.idCounter = 1;
            this.parseMarkdown(); // Ponowne parsowanie oryginału
            this.render();
        }
    }

    // --- Akcje ---

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
        this.saveToLocalStorage();
        this.render();
    }

    toggle(event) {
        const id = parseInt(event.currentTarget.dataset.id);
        const todo = this.todos.find(t => t.id === id);
        
        if (todo) {
            todo.completed = event.currentTarget.checked;
            this.saveToLocalStorage();
            this.render();
        }
    }

    remove(event) {
        const id = parseInt(event.currentTarget.dataset.id);
        this.todos = this.todos.filter(t => t.id !== id);
        this.saveToLocalStorage();
        this.render();
    }

    render() {
        // Statystyki
        const total = this.todos.length;
        const completed = this.todos.filter(t => t.completed).length;
        const pending = total - completed;

        if (this.hasTotalCountTarget) this.totalCountTarget.textContent = total;
        if (this.hasCompletedCountTarget) this.completedCountTarget.textContent = completed;
        if (this.hasPendingCountTarget) this.pendingCountTarget.textContent = pending;

        // Lista
        if (total === 0) {
            this.listTarget.innerHTML = `
                <div class="empty-state">
                    <span class="emoji">🛒</span>
                    <h3>Lista jest pusta</h3>
                    <p>Wszystkie zadania wykonane lub usunięte!</p>
                </div>
            `;
            return;
        }

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
                    title="Usuń zadanie"
                >
                    Usuń
                </button>
            `;

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
