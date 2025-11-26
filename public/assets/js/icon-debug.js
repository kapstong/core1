/**
 * Icon Performance Debug Tool
 * Production-ready version with console logging disabled
 * Add to your page to monitor icon loading performance (silent mode)
 */

class IconPerformanceDebugger {
    constructor(verbose = false) {
        this.verbose = false; // Disabled for production
        this.metrics = {
            fontAwesomeLoadTime: null,
            cssLoadTime: null,
            iconRenderTime: null,
            totalTime: null,
            iconCount: null
        };

        this.init();
    }

    init() {
        // Silent initialization
        this.trackCSSLoad();
        window.addEventListener('load', () => this.analyzePerformance());
        this.startContinuousMonitoring();
    }

    trackCSSLoad() {
        const startTime = performance.now();

        const checkFA = () => {
            if (document.fonts && document.fonts.status === 'loaded') {
                const endTime = performance.now();
                this.metrics.fontAwesomeLoadTime = (endTime - startTime).toFixed(2);
                return;
            }

            const faCSS = document.querySelector('link[href*="font-awesome"]');
            if (faCSS && faCSS.sheet) {
                const endTime = performance.now();
                this.metrics.fontAwesomeLoadTime = (endTime - startTime).toFixed(2);
            }
        };

        const interval = setInterval(() => {
            checkFA();
            if (this.metrics.fontAwesomeLoadTime) {
                clearInterval(interval);
            }
        }, 100);

        setTimeout(() => clearInterval(interval), 5000);
    }

    analyzePerformance() {
        const startTime = performance.now();

        this.metrics.iconCount = document.querySelectorAll('[class*="fas"], [class*="fab"], [class*="far"], [class*="fal"], [class*="fad"]').length;

        requestAnimationFrame(() => {
            const endTime = performance.now();
            this.metrics.iconRenderTime = (endTime - startTime).toFixed(2);
            this.generateReport();
        });
    }

    startContinuousMonitoring() {
        // Silent monitoring (no console output)
        setInterval(() => {
            const hiddenIcons = Array.from(
                document.querySelectorAll('i[class*="fas"], i[class*="fab"]')
            ).filter(icon => {
                const computed = window.getComputedStyle(icon);
                return computed.display === 'none' ||
                       computed.visibility === 'hidden' ||
                       computed.opacity === '0';
            });

            // Silent - no console warnings
        }, 2000);
    }

    generateReport() {
        const report = {
            'FontAwesome Load Time': `${this.metrics.fontAwesomeLoadTime}ms`,
            'Icon Render Time': `${this.metrics.iconRenderTime}ms`,
            'Total Icons': this.metrics.iconCount,
            'Navigation Timing': this.getNavigationMetrics()
        };

        // Silent - no console output
        this.assessPerformance();
    }

    getNavigationMetrics() {
        if (!performance.timing) return 'N/A';

        const timing = performance.timing;
        const navigationStart = timing.navigationStart;
        const loadEventEnd = timing.loadEventEnd;

        return `${(loadEventEnd - navigationStart).toFixed(0)}ms`;
    }

    assessPerformance() {
        // Silent assessment - no console output
        let assessment = '✅ Good';
        let details = [];

        if (this.metrics.fontAwesomeLoadTime > 1000) {
            assessment = '⚠️  Slow';
            details.push('FontAwesome taking too long to load. Consider Font Awesome Kit.');
        }

        if (this.metrics.iconRenderTime > 500) {
            assessment = '❌ Very Slow';
            details.push('Icon rendering is slow. Consider using SVG icons.');
        }

        if (this.metrics.iconCount > 100) {
            details.push(`High icon count (${this.metrics.iconCount}). Consider lazy loading.`);
        }

        // Silent - no console output
    }

    checkElement(selector) {
        const elements = document.querySelectorAll(selector);
        // Silent - no console output

        const results = [];
        elements.forEach((el, idx) => {
            const computed = window.getComputedStyle(el);
            results.push({
                visible: computed.display !== 'none',
                opacity: computed.opacity,
                hasContent: el.innerHTML.length > 0,
                classes: el.className
            });
        });
        return results;
    }

    exportMetrics() {
        return JSON.stringify(this.metrics, null, 2);
    }

    testIconVisibility() {
        const icons = document.querySelectorAll('i[class*="fas"], i[class*="fab"]');
        const stats = {
            total: icons.length,
            visible: 0,
            hidden: 0,
            offscreen: 0
        };

        icons.forEach(icon => {
            const rect = icon.getBoundingClientRect();
            const computed = window.getComputedStyle(icon);

            if (computed.display === 'none' || computed.visibility === 'hidden') {
                stats.hidden++;
            } else if (rect.bottom < 0 || rect.top > window.innerHeight) {
                stats.offscreen++;
            } else {
                stats.visible++;
            }
        });

        // Silent - no console output
        return stats;
    }
}

// Auto-initialize in silent mode for production
if (typeof window !== 'undefined') {
    window.iconDebugger = new IconPerformanceDebugger(false);
    // Silent - no console tips
}
