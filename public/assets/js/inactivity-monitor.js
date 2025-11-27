/**
 * Inactivity Monitor
 * Shows warning after 10 seconds of inactivity with countdown based on admin setting
 */

let sessionTimeout = 30; // Countdown duration in minutes (set by admin)
let inactivityDetectionTime = 10; // Fixed: 10 seconds to detect inactivity
let inactivityTimer = null;
let countdownTimer = null;
let countdownInterval = null;
let warningShown = false;
let countdownSeconds = 0;

// Format seconds to MM:SS or HH:MM:SS
function formatCountdown(totalSeconds) {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    } else {
        return `${minutes}:${String(seconds).padStart(2, '0')}`;
    }
}

// Warning modal HTML
const createWarningModal = () => {
    const modalHTML = `
        <div class="modal fade" id="inactivityWarningModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="background: #1a1a1a; border: 2px solid #ffc107;">
                    <div class="modal-header" style="border-color: #333; background: #222;">
                        <h5 class="modal-title text-warning">
                            <i class="fas fa-clock me-2"></i>
                            Session Expiring Soon
                        </h5>
                    </div>
                    <div class="modal-body text-center py-4">
                        <div class="mb-4">
                            <div class="position-relative d-inline-block">
                                <svg width="120" height="120" style="transform: rotate(-90deg);">
                                    <circle cx="60" cy="60" r="54" fill="none" stroke="#333" stroke-width="8"/>
                                    <circle id="progress-ring" cx="60" cy="60" r="54" fill="none"
                                            stroke="#ffc107" stroke-width="8"
                                            stroke-dasharray="339.292"
                                            stroke-dashoffset="0"
                                            style="transition: stroke-dashoffset 1s linear;"/>
                                </svg>
                                <div class="position-absolute top-50 start-50 translate-middle">
                                    <h1 id="countdown-timer" class="text-warning mb-0" style="font-size: 2rem; font-weight: bold;">0:00</h1>
                                </div>
                            </div>
                        </div>
                        <h5 class="text-white mb-3">You will be automatically logged out due to inactivity</h5>
                        <p class="text-muted mb-0">Move your mouse or press any key to extend your session</p>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('inactivityWarningModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
};

// Initialize inactivity monitor
function initializeInactivityMonitor(timeoutMinutes) {
    sessionTimeout = parseInt(timeoutMinutes) || 0;

    // Clear existing timers
    clearAllTimers();

    // If timeout is 0 or negative, disable monitoring
    if (sessionTimeout <= 0) {
        console.log('🔓 Inactivity monitoring DISABLED');
        return;
    }

    console.log(`🔐 Inactivity Monitor Initialized:
    ├─ Inactivity detection: ${inactivityDetectionTime} seconds
    ├─ Countdown duration: ${sessionTimeout} minutes
    └─ Total max idle: ${inactivityDetectionTime}s + ${sessionTimeout}m`);

    // Create warning modal
    createWarningModal();

    // Set up activity listeners
    setupActivityListeners();

    // Start monitoring for inactivity
    startInactivityDetection();
}

// Clear all timers
function clearAllTimers() {
    if (inactivityTimer) {
        clearTimeout(inactivityTimer);
        inactivityTimer = null;
    }
    if (countdownTimer) {
        clearTimeout(countdownTimer);
        countdownTimer = null;
    }
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
}

// Start detecting inactivity (10 seconds)
function startInactivityDetection() {
    // Clear existing timer
    if (inactivityTimer) {
        clearTimeout(inactivityTimer);
    }

    // If monitoring is disabled, don't set new timer
    if (sessionTimeout <= 0) {
        return;
    }

    // Set 10-second inactivity detection timer
    inactivityTimer = setTimeout(() => {
        showWarningWithCountdown();
    }, inactivityDetectionTime * 1000);
}

// Show warning modal and start countdown
function showWarningWithCountdown() {
    if (warningShown) return;

    console.log(`⚠️ Warning triggered after ${inactivityDetectionTime} seconds of inactivity`);
    console.log(`⏱️ Starting countdown: ${sessionTimeout} minutes`);

    warningShown = true;
    const modal = document.getElementById('inactivityWarningModal');

    if (!modal) {
        createWarningModal();
    }

    // Show modal
    const bsModal = new bootstrap.Modal(document.getElementById('inactivityWarningModal'));
    bsModal.show();

    // Start countdown based on admin's timeout setting
    countdownSeconds = sessionTimeout * 60; // Convert minutes to seconds
    const totalSeconds = countdownSeconds;
    const countdownEl = document.getElementById('countdown-timer');
    const progressRing = document.getElementById('progress-ring');
    const circumference = 339.292; // 2 * PI * 54

    // Update display immediately
    if (countdownEl) {
        countdownEl.textContent = formatCountdown(countdownSeconds);
    }

    // Update countdown every second
    countdownInterval = setInterval(() => {
        countdownSeconds--;

        if (countdownEl) {
            countdownEl.textContent = formatCountdown(countdownSeconds);
        }

        // Update progress ring
        if (progressRing) {
            const progress = countdownSeconds / totalSeconds;
            const offset = circumference * (1 - progress);
            progressRing.style.strokeDashoffset = offset;

            // Change color when time is running out
            if (countdownSeconds <= 60) {
                progressRing.style.stroke = '#dc3545'; // Red
            } else if (countdownSeconds <= 300) {
                progressRing.style.stroke = '#fd7e14'; // Orange
            }
        }

        if (countdownSeconds <= 0) {
            clearInterval(countdownInterval);
            handleSessionExpired();
        }
    }, 1000);

    // Set logout timer (same duration as countdown)
    countdownTimer = setTimeout(() => {
        handleSessionExpired();
    }, sessionTimeout * 60 * 1000);
}

// Handle session expiration
function handleSessionExpired() {
    console.log(`🚪 Session expired - logging out`);
    clearAllTimers();
    warningShown = false;

    // Show logout message
    if (typeof showError === 'function') {
        showError('Session expired due to inactivity. Please log in again.');
    }

    // Perform logout
    setTimeout(() => {
        window.location.href = `${API_BASE}/auth/logout.php`;
    }, 1000);
}

// Reset on activity
function resetOnActivity() {
    // Hide warning modal if shown
    const modal = document.getElementById('inactivityWarningModal');
    if (modal && warningShown) {
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) {
            bsModal.hide();
        }
        warningShown = false;
        clearAllTimers();
        console.log('✓ Activity detected - session extended');
    }

    // Restart inactivity detection
    startInactivityDetection();
}

// Setup activity listeners
function setupActivityListeners() {
    // Events that indicate user activity
    const activityEvents = [
        'mousedown',
        'mousemove',
        'keypress',
        'scroll',
        'touchstart',
        'click'
    ];

    // Throttle to avoid excessive resets
    let throttleTimeout = null;
    const throttleMs = 1000; // Only reset once per second

    const handleActivity = () => {
        if (!throttleTimeout) {
            throttleTimeout = setTimeout(() => {
                resetOnActivity();
                throttleTimeout = null;
            }, throttleMs);
        }
    };

    // Add event listeners
    activityEvents.forEach(event => {
        document.addEventListener(event, handleActivity, true);
    });

    console.log('👂 Activity listeners active');
}

// Make functions globally available
window.initializeInactivityMonitor = initializeInactivityMonitor;
window.resetInactivityTimer = resetOnActivity; // Keep function name for compatibility

// Handle logout button (if user wants to logout from modal)
window.handleLogout = async function() {
    console.log('🚪 Manual logout requested');
    clearAllTimers();
    window.location.href = `${API_BASE}/auth/logout.php`;
};
