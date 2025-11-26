/**
 * Icon Lazy Loader
 * Defers FontAwesome icon rendering for elements not immediately visible
 * Usage: Add data-lazy-icon="true" to any container with FontAwesome icons
 */

class IconLazyLoader {
    constructor(options = {}) {
        this.options = {
            rootMargin: options.rootMargin || '50px',
            threshold: options.threshold || 0.01,
            ...options
        };
        
        this.observer = null;
        this.loadedElements = new Set();
        this.init();
    }

    init() {
        // Only initialize if IntersectionObserver is supported
        if (!('IntersectionObserver' in window)) {
            this.loadAllIcons();
            return;
        }

        this.observer = new IntersectionObserver(
            (entries) => this.handleIntersection(entries),
            {
                rootMargin: this.options.rootMargin,
                threshold: this.options.threshold
            }
        );

        this.observeElements();
    }

    observeElements() {
        // Select all elements marked for lazy loading
        const elements = document.querySelectorAll('[data-lazy-icon]');
        elements.forEach(el => {
            if (!this.loadedElements.has(el)) {
                this.observer.observe(el);
            }
        });
    }

    handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                this.loadIcons(entry.target);
                this.observer.unobserve(entry.target);
                this.loadedElements.add(entry.target);
            }
        });
    }

    loadIcons(element) {
        // Trigger FontAwesome to render icons in this element
        if (window.FontAwesome && window.FontAwesome.config) {
            window.FontAwesome.config.autoReplaceSvg = 'nest';
            if (window.FontAwesome.parse) {
                window.FontAwesome.parse(element);
            }
        }
    }

    loadAllIcons() {
        // Fallback for browsers without IntersectionObserver
        const elements = document.querySelectorAll('[data-lazy-icon]');
        elements.forEach(el => this.loadIcons(el));
    }

    // Public method to manually observe new elements added to DOM
    observe(element) {
        if (this.observer && !this.loadedElements.has(element)) {
            this.observer.observe(element);
        }
    }

    // Public method to manually load icons for an element
    load(element) {
        this.loadIcons(element);
        this.loadedElements.add(element);
    }

    // Public method to disconnect observer
    disconnect() {
        if (this.observer) {
            this.observer.disconnect();
        }
    }
}

// Initialize lazy loader when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.iconLazyLoader = new IconLazyLoader({
        rootMargin: '100px', // Start loading 100px before element enters viewport
        threshold: 0.01
    });
});

// Re-observe when AJAX/dynamic content loads
document.addEventListener('DOMContentLoaded', () => {
    // Watch for dynamically added content
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.addedNodes.length) {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        const lazyElements = node.querySelectorAll?.('[data-lazy-icon]');
                        if (lazyElements) {
                            lazyElements.forEach(el => {
                                window.iconLazyLoader?.observe(el);
                            });
                        }
                    }
                });
            }
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = IconLazyLoader;
}
