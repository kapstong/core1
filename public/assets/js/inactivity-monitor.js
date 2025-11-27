/**
 * Inactivity Monitor
 * Automatically logs out users after a period of inactivity
 */

let inactivityTimeout = 30; // Default: 30 minutes
let warningDuration = 60; // Warning duration in seconds (calculated based on timeout)
let inactivityTimer = null;
let warningTimer = null;
let countdownInterval = null;
let warningShown = false;

// Calculate appropriate warning duration based on total timeout
function calculateWarningDuration(timeoutMinutes) {
    // For timeouts less than 2 minutes, use 20% of timeout
    // For longer timeouts, use minimum of 2 minutes or 10% of timeout
    if (timeoutMinutes <= 0) {
        return 60; // Default 1 minute
    } else if (timeoutMinutes < 2) {
        return Math.floor(timeoutMinutes * 60 * 0.2); // 20% of timeout in seconds
    } else if (timeoutMinutes <= 10) {
        return 60; // 1 minute warning for 2-10 minute timeouts
    } else {
        // For timeouts > 10 minutes, use 2 minutes warning
        return 120;
    }
}

// Warning modal HTML
const createWarningModal = () => {
    const modalHTML = `
        <div class="modal fade" id="inactivityWarningModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="modal-header" style="border-color: var(--border-color);">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                            Session Timeout Warning
                        </h5>
                    </div>
                    <div class="modal-body text-center">
                        <p>Your session will expire due to inactivity.</p>
                        <div class="mb-3">
                            <h2 id="countdown-timer" class="text-warning mb-0">60</h2>
                            <small class="text-muted">seconds remaining</small>
                        </div>
                        <p class="mb-0">Click "Stay Logged In" to continue your session.</p>
                    </div>
                    <div class="modal-footer" style="border-color: var(--border-color);">
                        <button type="button" class="btn btn-secondary" onclick="handleLogout()">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout Now
                        </button>
                        <button type="button" class="btn btn-primary" onclick="resetInactivityTimer()">
                            <i class="fas fa-check me-2"></i>Stay Logged In
                        </button>
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
    inactivityTimeout = parseInt(timeoutMinutes) || 0;

    // Clear existing timers
    clearAllTimers();

    // If timeout is 0 or negative, disable monitoring
    if (inactivityTimeout <= 0) {
        console.log('Inactivity monitoring disabled');
        return;
    }

    // Calculate appropriate warning duration based on timeout
    warningDuration = calculateWarningDuration(inactivityTimeout);

    console.log(`Inactivity monitor initialized: ${inactivityTimeout} minutes timeout, ${warningDuration} seconds warning`);

    // Create warning modal
    createWarningModal();

    // Set up activity listeners
    setupActivityListeners();

    // Start the inactivity timer
    resetInactivityTimer();
}

// Clear all timers
function clearAllTimers() {
    if (inactivityTimer) {
        clearTimeout(inactivityTimer);
        inactivityTimer = null;
    }
    if (warningTimer) {
        clearTimeout(warningTimer);
        warningTimer = null;
    }
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
}

// Reset inactivity timer
function resetInactivityTimer() {
    // Hide warning modal if shown
    const modal = document.getElementById('inactivityWarningModal');
    if (modal && warningShown) {
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) {
            bsModal.hide();
        }
        warningShown = false;
    }

    // Clear existing timers
    clearAllTimers();

    // If monitoring is disabled, don't set new timers
    if (inactivityTimeout <= 0) {
        return;
    }

    // Calculate timeout in milliseconds
    const timeoutMs = inactivityTimeout * 60 * 1000;
    const warningMs = timeoutMs - (warningDuration * 1000); // Show warning based on calculated duration

    // Set warning timer
    if (warningMs > 0) {
        warningTimer = setTimeout(() => {
            showInactivityWarning();
        }, warningMs);
    } else {
        // If timeout is very short (less than warning duration), show warning immediately
        setTimeout(() => {
            showInactivityWarning();
        }, 100);
    }

    // Set logout timer
    inactivityTimer = setTimeout(() => {
        handleInactivityLogout();
    }, timeoutMs);
}

// Show inactivity warning
function showInactivityWarning() {
    warningShown = true;
    const modal = document.getElementById('inactivityWarningModal');

    if (!modal) {
        createWarningModal();
    }

    const bsModal = new bootstrap.Modal(document.getElementById('inactivityWarningModal'));
    bsModal.show();

    // Start countdown using calculated warning duration
    let secondsLeft = warningDuration;
    const countdownEl = document.getElementById('countdown-timer');

    if (countdownEl) {
        countdownEl.textContent = secondsLeft;
    }

    countdownInterval = setInterval(() => {
        secondsLeft--;
        if (countdownEl) {
            countdownEl.textContent = secondsLeft;
        }

        if (secondsLeft <= 0) {
            clearInterval(countdownInterval);
        }
    }, 1000);
}

// Handle inactivity logout
function handleInactivityLogout() {
    clearAllTimers();

    // Show logout message
    if (typeof showError === 'function') {
        showError('Session expired due to inactivity. Please log in again.');
    }

    // Perform logout
    setTimeout(() => {
        window.location.href = `${API_BASE}/auth/logout.php`;
    }, 1000);
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
                resetInactivityTimer();
                throttleTimeout = null;
            }, throttleMs);
        }
    };

    // Add event listeners
    activityEvents.forEach(event => {
        document.addEventListener(event, handleActivity, true);
    });
}

// Make functions globally available
window.initializeInactivityMonitor = initializeInactivityMonitor;
window.resetInactivityTimer = resetInactivityTimer;

// Handle logout button
window.handleLogout = async function() {
    clearAllTimers();
    window.location.href = `${API_BASE}/auth/logout.php`;
};
