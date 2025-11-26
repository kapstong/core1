/**
 * Toast Notification System
 * Modern, animated toast notifications with glassmorphism design
 */

class ToastNotification {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        // Create toast container if it doesn't exist
        if (!document.querySelector('.toast-container')) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        } else {
            this.container = document.querySelector('.toast-container');
        }
    }

    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} type - Type of toast: 'success', 'error', 'warning', 'info'
     * @param {number} duration - Duration in milliseconds (default: 3000)
     * @param {string} title - Optional title
     */
    show(message, type = 'info', duration = 3000, title = null) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        // Icon based on type
        const icons = {
            success: '<i class="fas fa-check-circle"></i>',
            error: '<i class="fas fa-times-circle"></i>',
            warning: '<i class="fas fa-exclamation-triangle"></i>',
            info: '<i class="fas fa-info-circle"></i>'
        };

        // Default titles if none provided
        if (!title) {
            const titles = {
                success: 'Success',
                error: 'Error',
                warning: 'Warning',
                info: 'Information'
            };
            title = titles[type];
        }

        toast.innerHTML = `
            <div class="toast-icon">${icons[type]}</div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" aria-label="Close">&times;</button>
        `;

        // Add to container
        this.container.appendChild(toast);

        // Close button functionality
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => {
            this.remove(toast);
        });

        // Auto remove after duration
        setTimeout(() => {
            if (toast.parentElement) {
                this.remove(toast);
            }
        }, duration);

        return toast;
    }

    /**
     * Remove a toast with animation
     */
    remove(toast) {
        toast.style.animation = 'fadeOut 0.3s ease forwards';
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 300);
    }

    // Convenience methods
    success(message, title = null, duration = 3000) {
        return this.show(message, 'success', duration, title);
    }

    error(message, title = null, duration = 4000) {
        return this.show(message, 'error', duration, title);
    }

    warning(message, title = null, duration = 3500) {
        return this.show(message, 'warning', duration, title);
    }

    info(message, title = null, duration = 3000) {
        return this.show(message, 'info', duration, title);
    }

    /**
     * Show a loading toast that persists until dismissed
     */
    loading(message, title = 'Loading') {
        const toast = document.createElement('div');
        toast.className = 'toast info';
        toast.style.animation = 'slideInRight 0.3s ease forwards';

        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-spinner fa-spin"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
        `;

        this.container.appendChild(toast);
        return toast;
    }

    /**
     * Update a loading toast to success/error when done
     */
    updateLoading(toast, type, message, title = null) {
        if (!toast || !toast.parentElement) return;

        const icons = {
            success: '<i class="fas fa-check-circle"></i>',
            error: '<i class="fas fa-times-circle"></i>'
        };

        const titles = {
            success: title || 'Complete',
            error: title || 'Failed'
        };

        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-icon">${icons[type]}</div>
            <div class="toast-content">
                <div class="toast-title">${titles[type]}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" aria-label="Close">&times;</button>
        `;

        // Add close functionality
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => {
            this.remove(toast);
        });

        // Auto remove
        setTimeout(() => {
            if (toast.parentElement) {
                this.remove(toast);
            }
        }, type === 'success' ? 3000 : 4000);
    }

    /**
     * Clear all toasts
     */
    clearAll() {
        const toasts = this.container.querySelectorAll('.toast');
        toasts.forEach(toast => this.remove(toast));
    }
}

// Create global toast instance
const Toast = new ToastNotification();

// Also expose on window for backward compatibility
if (typeof window !== 'undefined') {
    window.Toast = Toast;
}
