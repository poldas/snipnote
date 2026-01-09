import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'list', 'totalCount', 'completedCount', 'pendingCount', 'markdownContent'];
    static values = {
        noteId: String
    };

    connect() {
        this.todos = [];
        this.idCounter = 1;
        
        this.refresh();
    }

    /**
     * Re-parses and merges todos.
     */
    refresh() {
        const remoteTodos = this.parseMarkdown();
        const localData = this.loadFromLocalStorage();
        
        if (localData) {
            this.idCounter = localData.idCounter || 1;
            this.todos = this.merge(remoteTodos, localData.todos || []);
        } else {
            this.todos = remoteTodos.map(t => ({ ...t, id: this.idCounter++ }));
        }
        
        this.render();
    }

    /**
     * Parses remote markdown content and returns objects.
     */
    parseMarkdown() {
        if (!this.hasMarkdownContentTarget) return [];

        const items = this.markdownContentTarget.querySelectorAll('ul li');
        const parsed = [];
        
        items.forEach(item => {
            const text = item.textContent.trim();
            if (text) {
                parsed.push({
                    text: text,
                    completed: false,
                    deleted: false,
                    source: 'remote'
                });
            }
        });
        
        // Hide original list
        this.markdownContentTarget.querySelectorAll('ul').forEach(ul => {
            ul.style.display = 'none';
        });

        return parsed;
    }

    /**
     * Merges remote todos from author with local user state.
     */
    merge(remote, local) {
        const merged = [];
        const localMap = new Map();
        
        // Indeksowanie lokalnych zadań po tekście
        local.forEach(t => {
            localMap.set(t.text, t);
        });

        // 1. Procesuj zadania od autora (Remote)
        remote.forEach(remoteTodo => {
            const existingLocal = localMap.get(remoteTodo.text);
            
            if (existingLocal) {
                // Zachowaj stan postępu użytkownika dla zadania autora
                merged.push({
                    ...remoteTodo,
                    id: this.idCounter++,
                    completed: existingLocal.completed,
                    deleted: existingLocal.deleted || false
                });
                localMap.delete(remoteTodo.text);
            } else {
                // Nowe zadanie dodane przez autora w edytorze
                merged.push({
                    ...remoteTodo,
                    id: this.idCounter++,
                    completed: false,
                    deleted: false
                });
            }
        });

        // 2. Dodaj zadania dodane przez użytkownika lokalnie (Source: local)
        local.forEach(localTodo => {
            if (localTodo.source === 'local') {
                merged.push({
                    ...localTodo,
                    id: this.idCounter++
                });
            }
        });

        return merged;
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
                return JSON.parse(stored);
            } catch (e) {
                console.error("Błąd podczas wczytywania listy zadań:", e);
            }
        }
        return null;
    }

    reset() {
        const message = "Czy na pewno chcesz przywrócić listę do stanu początkowego?\n\nSpowoduje to usunięcie wszystkich Twoich lokalnych zmian i przywrócenie oryginalnej treści notatki.";
        
        if (confirm(message)) {
            localStorage.removeItem(this.storageKey);
            this.idCounter = 1;
            this.todos = this.parseMarkdown().map(t => ({ ...t, id: this.idCounter++ }));
            this.render();
        }
    }

    // --- Akcje ---

    add(event) {
        if (event.type === 'keypress' && event.key !== 'Enter') return;

        const text = this.inputTarget.value.trim();
        if (!text) return;

        this.todos.unshift({
            id: this.idCounter++,
            text: text,
            completed: false,
            deleted: false,
            source: 'local'
        });

        this.inputTarget.value = '';
        this.saveToLocalStorage();
        this.render();
    }

    toggle(event) {
        const id = parseInt(event.params.id);
        const index = this.todos.findIndex(t => t.id === id);
        
        if (index !== -1) {
            const todo = this.todos[index];
            todo.completed = event.target.checked;
            
            this.todos.splice(index, 1);
            if (!todo.completed) {
                this.todos.unshift(todo);
            } else {
                this.todos.push(todo);
            }
            
            this.saveToLocalStorage();
            this.render();
        }
    }

    remove(event) {
        const id = parseInt(event.params.id);
        const index = this.todos.findIndex(t => t.id === id);
        if (index !== -1) {
            const todo = this.todos[index];
            todo.deleted = true;
            
            this.todos.splice(index, 1);
            this.todos.push(todo);
            
            this.saveToLocalStorage();
            this.render();
        }
    }

    restore(event) {
        const id = parseInt(event.params.id);
        const index = this.todos.findIndex(t => t.id === id);
        if (index !== -1) {
            const todo = this.todos[index];
            todo.deleted = false;
            todo.completed = false;
            
            this.todos.splice(index, 1);
            this.todos.unshift(todo);
            
            this.saveToLocalStorage();
            this.render();
        }
    }

    permanentDelete(event) {
        const id = parseInt(event.params.id);
        this.todos = this.todos.filter(t => t.id !== id);
        this.saveToLocalStorage();
        this.render();
    }

    // --- Renderowanie ---

    render() {
        const activeTodos = this.todos.filter(t => !t.deleted);
        const deletedTodos = this.todos.filter(t => t.deleted);
        
        const pending = activeTodos.filter(t => !t.completed);
        const completed = activeTodos.filter(t => t.completed);

        if (this.hasTotalCountTarget) this.totalCountTarget.textContent = activeTodos.length;
        if (this.hasCompletedCountTarget) this.completedCountTarget.textContent = completed.length;
        if (this.hasPendingCountTarget) this.pendingCountTarget.textContent = pending.length;

        if (this.todos.length === 0) {
            this.listTarget.innerHTML = `
                <div class="empty-state">
                    <span class="emoji">🛒</span>
                    <h3>Lista jest pusta</h3>
                    <p>Zacznij od dodania nowego zadania!</p>
                </div>
            `;
            return;
        }

        this.listTarget.innerHTML = '';

        if (pending.length > 0) {
            this.renderGroup("Do zrobienia", pending, "pending");
        }

        if (completed.length > 0) {
            this.renderGroup("Ukończone", completed, "completed");
        }

        if (deletedTodos.length > 0) {
            this.renderGroup("Usunięte", deletedTodos, "deleted");
        }
    }

    renderGroup(title, items, type) {
        const header = document.createElement('h4');
        header.className = `todo-group-title ${type}`;
        header.textContent = title;
        this.listTarget.appendChild(header);

        items.forEach(todo => {
            const item = document.createElement('div');
            item.className = `todo-item ${todo.completed ? 'completed' : ''} ${todo.deleted ? 'deleted' : ''}`;
            
            let actionsHtml = '';
            if (todo.deleted) {
                actionsHtml = `
                    <button class="btn-restore" 
                            data-action="click->public-todo#restore" 
                            data-public-todo-id-param="${todo.id}"
                            title="Przywróć zadanie">Przywróć</button>
                    <button class="btn-delete-perm" 
                            data-action="click->public-todo#permanentDelete" 
                            data-public-todo-id-param="${todo.id}"
                            title="Usuń na zawsze">×</button>
                `;
            } else {
                actionsHtml = `
                    <button class="btn-delete" 
                            data-action="click->public-todo#remove" 
                            data-public-todo-id-param="${todo.id}"
                            title="Usuń zadanie">Usuń</button>
                `;
            }

            item.innerHTML = `
                <div class="todo-checkbox-wrapper">
                    <input 
                        type="checkbox" 
                        class="todo-checkbox" 
                        ${todo.completed ? 'checked' : ''}
                        data-action="change->public-todo#toggle"
                        data-public-todo-id-param="${todo.id}"
                        ${todo.deleted ? 'disabled' : ''}
                    >
                </div>
                <div class="todo-text">${this.escapeHtml(todo.text)}</div>
                <div class="todo-actions">
                    ${actionsHtml}
                </div>
            `;

            this.listTarget.appendChild(item);
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
