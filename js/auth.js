// Authentication JavaScript - js/auth.js

class AuthManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.setupPasswordToggle();
        this.setupFormValidation();
        this.checkForMessages();
    }

    bindEvents() {
        // Form submission
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => {
                this.handleFormSubmit(e);
            });
        }

        // Real-time validation
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('blur', (e) => {
                this.validateField(e.target);
            });
            
            input.addEventListener('input', (e) => {
                this.clearFieldError(e.target);
            });
        });

        // Remember me info
        const rememberCheckbox = document.getElementById('remember');
        if (rememberCheckbox) {
            rememberCheckbox.addEventListener('change', (e) => {
                this.showRememberInfo(e.target.checked);
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });
    }

    setupPasswordToggle() {
        const passwordToggle = document.getElementById('passwordToggle');
        const passwordInput = document.getElementById('password');
        
        if (passwordToggle && passwordInput) {
            passwordToggle.addEventListener('click', () => {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                
                const icon = passwordToggle.querySelector('i');
                icon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
                
                // Accessibility
                passwordToggle.setAttribute('aria-label', 
                    isPassword ? 'Hide password' : 'Show password'
                );
            });
        }
    }

    setupFormValidation() {
        // Username/email validation
        const usernameInput = document.getElementById('username');
        if (usernameInput) {
            usernameInput.addEventListener('input', (e) => {
                this.validateUsername(e.target);
            });
        }

        // Password strength indicator (optional)
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', (e) => {
                this.validatePassword(e.target);
            });
        }
    }

    handleFormSubmit(e) {
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Validate all fields
        const isValid = this.validateForm(form);
        
        if (!isValid) {
            e.preventDefault();
            return false;
        }
        
        // Show loading state
        this.setLoadingState(submitBtn, true);
        
        // The form will submit naturally, but we can add additional logic here
        return true;
    }

    validateForm(form) {
        const inputs = form.querySelectorAll('.form-control[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    }

    validateField(field) {
        const value = field.value.trim();
        const fieldName = field.name;
        let isValid = true;
        let message = '';
        
        // Required field validation
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            message = 'This field is required';
        }
        
        // Specific field validations
        switch (fieldName) {
            case 'username':
                if (value && !this.isValidUsernameOrEmail(value)) {
                    isValid = false;
                    message = 'Please enter a valid username or email address';
                }
                break;
                
            case 'password':
                if (value && value.length < 6) {
                    isValid = false;
                    message = 'Password must be at least 6 characters long';
                }
                break;
        }
        
        // Apply validation state
        this.setFieldValidationState(field, isValid, message);
        
        return isValid;
    }

    validateUsername(field) {
        const value = field.value.trim();
        
        if (value.length === 0) {
            this.clearFieldError(field);
            return true;
        }
        
        const isValid = this.isValidUsernameOrEmail(value);
        
        if (isValid) {
            this.setFieldSuccess(field);
        } else {
            this.setFieldError(field, 'Please enter a valid username or email');
        }
        
        return isValid;
    }

    validatePassword(field) {
        const value = field.value;
        
        if (value.length === 0) {
            this.clearFieldError(field);
            return true;
        }
        
        const strength = this.calculatePasswordStrength(value);
        
        if (strength >= 2) {
            this.setFieldSuccess(field);
            return true;
        } else if (strength === 1) {
            this.setFieldWarning(field, 'Weak password');
            return true;
        } else {
            this.setFieldError(field, 'Password too weak');
            return false;
        }
    }

    isValidUsernameOrEmail(value) {
        // Check if it's a valid email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (emailRegex.test(value)) {
            return true;
        }
        
        // Check if it's a valid username (alphanumeric + underscore, 3-20 chars)
        const usernameRegex = /^[a-zA-Z0-9_]{3,20}$/;
        return usernameRegex.test(value);
    }

    calculatePasswordStrength(password) {
        let strength = 0;
        
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        return Math.min(strength, 4);
    }

    setFieldValidationState(field, isValid, message) {
        if (isValid) {
            this.setFieldSuccess(field);
        } else {
            this.setFieldError(field, message);
        }
    }

    setFieldSuccess(field) {
        field.classList.remove('error');
        field.classList.add('success');
        this.removeFieldMessage(field);
    }

    setFieldError(field, message) {
        field.classList.remove('success');
        field.classList.add('error');
        this.showFieldMessage(field, message, 'error');
    }

    setFieldWarning(field, message) {
        field.classList.remove('success', 'error');
        this.showFieldMessage(field, message, 'warning');
    }

    clearFieldError(field) {
        field.classList.remove('error', 'success');
        this.removeFieldMessage(field);
    }

    showFieldMessage(field, message, type) {
        this.removeFieldMessage(field);
        
        const messageElement = document.createElement('div');
        messageElement.className = `field-message field-message-${type}`;
        messageElement.textContent = message;
        messageElement.style.cssText = `
            font-size: 0.75rem;
            margin-top: 0.25rem;
            color: ${type === 'error' ? 'var(--danger)' : 'var(--warning)'};
            animation: slideDown 0.2s ease;
        `;
        
        field.parentNode.appendChild(messageElement);
    }

    removeFieldMessage(field) {
        const existingMessage = field.parentNode.querySelector('.field-message');
        if (existingMessage) {
            existingMessage.remove();
        }
    }

    setLoadingState(button, loading) {
        if (loading) {
            button.disabled = true;
            button.classList.add('loading');
            
            const text = button.querySelector('span');
            if (text) {
                text.textContent = 'Signing in...';
            }
        } else {
            button.disabled = false;
            button.classList.remove('loading');
            
            const text = button.querySelector('span');
            if (text) {
                text.textContent = 'Sign In';
            }
        }
    }

    showRememberInfo(checked) {
        const existingInfo = document.querySelector('.remember-info');
        if (existingInfo) {
            existingInfo.remove();
        }
        
        if (checked) {
            const info = document.createElement('div');
            info.className = 'remember-info';
            info.innerHTML = `
                <div style="
                    font-size: 0.75rem;
                    color: var(--text-muted);
                    margin-top: 0.5rem;
                    padding: 0.5rem;
                    background: var(--bg-secondary);
                    border-radius: var(--radius);
                    border-left: 3px solid var(--primary);
                ">
                    <i class="fas fa-info-circle"></i>
                    You'll stay logged in for 30 days on this device
                </div>
            `;
            
            const rememberLabel = document.querySelector('.checkbox-label');
            rememberLabel.parentNode.insertBefore(info, rememberLabel.nextSibling);
        }
    }

    checkForMessages() {
        // Check for logout success message
        const urlParams = new URLSearchParams(window.location.search);
        const message = urlParams.get('message');
        
        if (message === 'logged_out') {
            this.showAlert('You have been successfully logged out.', 'success');
            
            // Clean URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }

    showAlert(message, type = 'info') {
        const alertContainer = document.querySelector('.alert');
        
        if (alertContainer) {
            // Update existing alert
            alertContainer.className = `alert alert-${type}`;
            alertContainer.querySelector('span').textContent = message;
        } else {
            // Create new alert
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            
            const form = document.querySelector('.auth-form');
            form.parentNode.insertBefore(alert, form);
        }
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                const alert = document.querySelector('.alert');
                if (alert) {
                    alert.style.animation = 'slideUp 0.3s ease reverse';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        }
    }

    handleKeyboardShortcuts(e) {
        // Enter key on form fields
        if (e.key === 'Enter' && e.target.classList.contains('form-control')) {
            const form = e.target.closest('form');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.click();
            }
        }
    }

    // Utility methods
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Security helpers
    generateFingerprint() {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        ctx.textBaseline = 'top';
        ctx.font = '14px Arial';
        ctx.fillText('Browser fingerprint', 2, 2);
        
        return canvas.toDataURL().slice(-50);
    }

    // Local storage helpers
    saveRememberPreference(remember) {
        try {
            localStorage.setItem('auth_remember_preference', remember ? '1' : '0');
        } catch (e) {
            console.warn('Could not save remember preference');
        }
    }

    loadRememberPreference() {
        try {
            const saved = localStorage.getItem('auth_remember_preference');
            return saved === '1';
        } catch (e) {
            return false;
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new AuthManager();
});

// Add CSS animations dynamically
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes slideUp {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-10px); }
    }
`;
document.head.appendChild(style);
