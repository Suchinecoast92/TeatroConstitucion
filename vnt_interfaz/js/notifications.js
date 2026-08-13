// Sistema de notificaciones personalizadas
class NotificationSystem {
    constructor() {
        this.container = null;
        this.queue = [];
        this.maxVisible = 5;
        this.init();
    }

    init() {
        // Crear contenedor si no existe
        if (!document.querySelector('.toast-container')) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        } else {
            this.container = document.querySelector('.toast-container');
        }
    }

    show(message, type = 'info', duration = 4000, options = {}) {
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;

        const icons = {
            success: 'bi-check-circle-fill',
            error: 'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill'
        };

        const titles = {
            success: options.title || 'Éxito',
            error: options.title || 'Error',
            warning: options.title || 'Advertencia',
            info: options.title || 'Información'
        };

        // Permitir HTML en el mensaje si se especifica
        const messageContent = options.allowHtml 
            ? message 
            : this.escapeHtml(message);

        toast.innerHTML = `
            <i class="bi ${icons[type]} toast-icon"></i>
            <div class="toast-content">
                <div class="toast-title">${titles[type]}</div>
                <div class="toast-message">${messageContent}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="bi bi-x"></i>
            </button>
        `;

        // Agregar barra de progreso si hay duración
        if (duration > 0) {
            toast.style.setProperty('--progress-duration', `${duration}ms`);
        }

        // Limitar cantidad de notificaciones visibles
        const visibleToasts = this.container.querySelectorAll('.toast-notification:not(.toast-hiding)');
        if (visibleToasts.length >= this.maxVisible) {
            visibleToasts[0].classList.add('toast-hiding');
            setTimeout(() => visibleToasts[0].remove(), 300);
        }

        this.container.appendChild(toast);

        // Auto-remover después de la duración especificada
        if (duration > 0) {
            const timeoutId = setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.add('toast-hiding');
                    setTimeout(() => {
                        if (toast.parentElement) {
                            toast.remove();
                        }
                    }, 300);
                }
            }, duration);

            // Permitir pausar al hacer hover
            if (options.pauseOnHover !== false) {
                let remainingTime = duration;
                let startTime = Date.now();
                
                toast.addEventListener('mouseenter', () => {
                    clearTimeout(timeoutId);
                    remainingTime -= (Date.now() - startTime);
                    toast.style.animationPlayState = 'paused';
                });
                
                toast.addEventListener('mouseleave', () => {
                    startTime = Date.now();
                    toast.style.animationPlayState = 'running';
                    setTimeout(() => {
                        if (toast.parentElement) {
                            toast.classList.add('toast-hiding');
                            setTimeout(() => {
                                if (toast.parentElement) {
                                    toast.remove();
                                }
                            }, 300);
                        }
                    }, remainingTime);
                });
            }
        }

        // Callback cuando se cierra
        if (options.onClose) {
            toast.addEventListener('remove', options.onClose);
        }

        return toast;
    }

    success(message, duration = 4000, options = {}) {
        return this.show(message, 'success', duration, options);
    }

    error(message, duration = 5000, options = {}) {
        return this.show(message, 'error', duration, options);
    }

    warning(message, duration = 4000, options = {}) {
        return this.show(message, 'warning', duration, options);
    }

    info(message, duration = 4000, options = {}) {
        return this.show(message, 'info', duration, options);
    }

    // Método para escapar HTML y prevenir XSS
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Limpiar todas las notificaciones
    clearAll() {
        const toasts = this.container.querySelectorAll('.toast-notification');
        toasts.forEach(toast => {
            toast.classList.add('toast-hiding');
            setTimeout(() => toast.remove(), 300);
        });
    }
}

// Crear instancia global
const notify = new NotificationSystem();

// Sobrescribir alert para usar notificaciones (opcional)
window.alertOriginal = window.alert;
window.alert = function(message) {
    notify.info(message);
};
