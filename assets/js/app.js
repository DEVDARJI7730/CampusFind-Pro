/**
 * CampusFind Pro - Application Core JS
 * Handles theme toggling, custom toast notifications, AJAX helpers, and form validations.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Theme (Dark / Light Mode)
    initTheme();

    // 2. Initialize AOS if loaded
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            easing: 'ease-out-cubic',
            once: true
        });
    }

    // 3. Close alerts automatically
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (typeof bootstrap !== 'undefined') {
                const bsAlert = bootstrap.Alert.getInstance(alert) || new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    });
});

/**
 * Theme Manager
 */
function initTheme() {
    const themeToggler = document.getElementById('theme-toggle');
    const currentTheme = localStorage.getItem('theme') || 'light';
    
    // Apply current theme to HTML
    document.documentElement.setAttribute('data-theme', currentTheme);
    updateThemeToggleUI(currentTheme);

    if (themeToggler) {
        themeToggler.addEventListener('click', () => {
            const activeTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = activeTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeToggleUI(newTheme);

            // Dispatch global event for charts updates if needed
            window.dispatchEvent(new Event('themeChanged'));
        });
    }
}

function updateThemeToggleUI(theme) {
    const icon = document.querySelector('#theme-toggle i');
    if (!icon) return;
    
    if (theme === 'dark') {
        icon.className = 'fa-solid fa-sun';
        icon.style.color = '#fbbf24';
    } else {
        icon.className = 'fa-solid fa-moon';
        icon.style.color = '#4f46e5';
    }
}

/**
 * Toast Notification System
 */
class Toast {
    static createContainer() {
        let container = document.querySelector('.toast-container-custom');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container-custom';
            document.body.appendChild(container);
        }
        return container;
    }

    static show(message, type = 'success') {
        const container = this.createContainer();
        const toast = document.createElement('div');
        toast.className = `toast-custom ${type} glass-panel`;
        
        let iconClass = 'fa-circle-check';
        let iconColor = 'var(--color-success)';
        
        if (type === 'danger') {
            iconClass = 'fa-circle-xmark';
            iconColor = 'var(--color-danger)';
        } else if (type === 'warning') {
            iconClass = 'fa-triangle-exclamation';
            iconColor = 'var(--color-warning)';
        } else if (type === 'info') {
            iconClass = 'fa-circle-info';
            iconColor = 'var(--color-info)';
        }

        toast.innerHTML = `
            <div class="d-flex align-items-center gap-3">
                <i class="fa-solid ${iconClass}" style="color: ${iconColor}; font-size: 1.25rem;"></i>
                <span style="font-size: 0.9rem; font-weight: 500;">${message}</span>
            </div>
            <button type="button" class="btn-close" style="font-size: 0.75rem; margin-left: 15px;" onclick="this.parentElement.remove()"></button>
        `;

        container.appendChild(toast);

        // Slide out and remove
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-20px) scale(0.9)';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 4000);
    }
}

/**
 * Secure AJAX wrapper utilizing custom configurations and CSRF token verification
 */
async function secureFetch(url, options = {}) {
    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : '';

    if (!options.headers) {
        options.headers = {};
    }

    if (options.body && !(options.body instanceof FormData)) {
        options.headers['Content-Type'] = 'application/json';
        if (csrfToken) {
            if (typeof options.body === 'string') {
                try {
                    const parsed = JSON.parse(options.body);
                    parsed.csrf_token = csrfToken;
                    options.body = JSON.stringify(parsed);
                } catch (e) {
                    // Ignore, submit as-is
                }
            }
        }
    } else if (options.body && options.body instanceof FormData) {
        if (csrfToken) {
            options.body.append('csrf_token', csrfToken);
        }
    }

    try {
        const response = await fetch(url, options);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        console.error('Fetch error:', error);
        Toast.show('Network or server error occurred.', 'danger');
        throw error;
    }
}

/**
 * Form validation helper
 */
function validateForm(formElement) {
    let isValid = true;
    const requiredInputs = formElement.querySelectorAll('[required]');

    requiredInputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.classList.add('is-invalid');
            
            // Add custom invalid feedback if not present
            let feedback = input.nextElementSibling;
            if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                feedback.innerText = 'This field is required.';
                input.parentNode.insertBefore(feedback, input.nextSibling);
            }
        } else {
            input.classList.remove('is-invalid');
        }
    });

    return isValid;
}
