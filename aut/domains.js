// Domain Management JavaScript - js/domains.js

class DomainManager {
    constructor() {
        this.currentAction = 'add';
        this.editingDomainId = null;
        this.isMobile = window.innerWidth <= 768;
        this.init();
    }

    init() {
        this.bindEvents();
        this.setupFormValidation();
        this.handleResize();
    }

    bindEvents() {
        // Add domain button
        document.getElementById('addDomainBtn').addEventListener('click', () => {
            this.openAddDomainModal();
        });

        // Form submission
        document.getElementById('domainForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleFormSubmit();
        });

        // Auto-fill site name from domain
        document.getElementById('domain').addEventListener('input', (e) => {
            this.autoFillSiteName(e.target.value);
        });

        // Close modals with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeDomainModal();
                this.closeConfirmModal();
            }
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            this.handleResize();
        });

        // Form validation on input
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('blur', () => {
                this.validateField(input);
            });
            
            input.addEventListener('input', () => {
                this.clearFieldError(input);
            });
        });
    }

    setupFormValidation() {
        const domainInput = document.getElementById('domain');
        const siteNameInput = document.getElementById('siteName');

        // Domain validation
        domainInput.addEventListener('input', (e) => {
            const value = e.target.value.trim();
            if (value && !this.isValidDomain(value)) {
                this.setFieldError(domainInput, 'Please enter a valid domain name');
            } else {
                this.clearFieldError(domainInput);
            }
        });

        // Site name validation
        siteNameInput.addEventListener('input', (e) => {
            const value = e.target.value.trim();
            if (value && value.length < 3) {
                this.setFieldError(siteNameInput, 'Site name must be at least 3 characters');
            } else {
                this.clearFieldError(siteNameInput);
            }
        });
    }

    handleResize() {
        this.isMobile = window.innerWidth <= 768;
    }

    // Modal Management
    openAddDomainModal() {
        this.currentAction = 'add';
        this.editingDomainId = null;
        
        document.getElementById('modalTitle').textContent = 'Add New Domain';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i><span>Save Domain</span>';
        
        this.resetForm();
        this.showModal('domainModal');
        
        // Focus first input
        setTimeout(() => {
            document.getElementById('domain').focus();
        }, 100);
    }

    openEditDomainModal(domainData) {
        this.currentAction = 'edit';
        this.editingDomainId = domainData.id;
        
        document.getElementById('modalTitle').textContent = 'Edit Domain';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i><span>Update Domain</span>';
        
        this.populateForm(domainData);
        this.showModal('domainModal');
    }

    closeDomainModal() {
        this.hideModal('domainModal');
        this.resetForm();
        this.currentAction = 'add';
        this.editingDomainId = null;
    }

    showModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    hideModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }

    // Form Management
    resetForm() {
        const form = document.getElementById('domainForm');
        form.reset();
        
        // Clear validation states
        const inputs = form.querySelectorAll('.form-control');
        inputs.forEach(input => {
            this.clearFieldError(input);
        });
        
        document.getElementById('domainId').value = '';
    }

    populateForm(domainData) {
        document.getElementById('domainId').value = domainData.id;
        document.getElementById('domain').value = domainData.domain;
        document.getElementById('siteName').value = domainData.name;
        document.getElementById('siteDescription').value = domainData.description || '';
        document.getElementById('themeSelect').value = domainData.theme_id;
        document.getElementById('statusSelect').value = domainData.status;
    }

    autoFillSiteName(domain) {
        const siteNameInput = document.getElementById('siteName');
        
        // Only auto-fill if the site name field is empty and we're adding (not editing)
        if (this.currentAction === 'add' && siteNameInput.value.trim() === '' && domain.trim() !== '') {
            const siteName = domain
                .replace(/^www\./, '') // Remove www.
                .split('.')[0] // Get the main part before .com
                .replace(/[-_]/g, ' ') // Replace hyphens and underscores with spaces
                .replace(/\b\w/g, l => l.toUpperCase()) // Capitalize first letter of each word
                + ' Movie Database'; // Add suffix
            
            siteNameInput.value = siteName;
        }
    }

    // Form Submission
    async handleFormSubmit() {
        const submitBtn = document.getElementById('submitBtn');
        
        if (!this.validateForm()) {
            return;
        }
        
        this.setLoadingState(submitBtn, true);
        this.showLoadingOverlay();
        
        try {
            const formData = new FormData(document.getElementById('domainForm'));
            formData.append('action', this.currentAction === 'add' ? 'add_domain' : 'update_domain');
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast(result.message, 'success');
                this.closeDomainModal();
                
                if (this.currentAction === 'add') {
                    this.addDomainToGrid(result.domain);
                } else {
                    this.updateDomainInGrid(this.editingDomainId, result.domain);
                }
                
                this.updateStats();
            } else {
                this.showToast(result.message, 'error');
            }
            
        } catch (error) {
            console.error('Form submission error:', error);
            this.showToast('An error occurred. Please try again.', 'error');
        } finally {
            this.setLoadingState(submitBtn, false);
            this.hideLoadingOverlay();
        }
    }

    validateForm() {
        const form = document.getElementById('domainForm');
        const inputs = form.querySelectorAll('.form-control[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        // Additional validations
        const domainInput = document.getElementById('domain');
        if (!this.isValidDomain(domainInput.value)) {
            this.setFieldError(domainInput, 'Please enter a valid domain name');
            isValid = false;
        }
        
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
            case 'domain':
                if (value && !this.isValidDomain(value)) {
                    isValid = false;
                    message = 'Please enter a valid domain name (e.g., example.com)';
                }
                break;
                
            case 'name':
                if (value && value.length < 3) {
                    isValid = false;
                    message = 'Site name must be at least 3 characters long';
                }
                break;
        }
        
        // Apply validation state
        if (isValid) {
            this.clearFieldError(field);
        } else {
            this.setFieldError(field, message);
        }
        
        return isValid;
    }

    isValidDomain(domain) {
        const domainRegex = /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/;
        return domainRegex.test(domain);
    }

    setFieldError(field, message) {
        field.classList.add('error');
        field.classList.remove('success');
        
        // Remove existing error message
        this.clearFieldMessage(field);
        
        // Add new error message
        const errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        errorElement.innerHTML = `<i class="fas fa-exclamation-circle"></i>${message}`;
        
        field.parentNode.appendChild(errorElement);
    }

    clearFieldError(field) {
        field.classList.remove('error', 'success');
        this.clearFieldMessage(field);
    }

    clearFieldMessage(field) {
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
    }

    // Domain Operations
    async editDomain(domainId) {
        this.showLoadingOverlay();
        
        try {
            const formData = new FormData();
            formData.append('action', 'get_domain');
            formData.append('id', domainId);
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.openEditDomainModal(result.domain);
            } else {
                this.showToast(result.message, 'error');
            }
            
        } catch (error) {
            console.error('Error fetching domain:', error);
            this.showToast('Error loading domain data', 'error');
        } finally {
            this.hideLoadingOverlay();
        }
    }

    deleteDomain(domainId, domainName) {
        document.getElementById('confirmMessage').textContent = 
            `Are you sure you want to delete "${domainName}"?`;
        
        document.getElementById('confirmBtn').onclick = () => {
            this.confirmDelete(domainId);
        };
        
        this.showModal('confirmModal');
    }

    async confirmDelete(domainId) {
        this.closeConfirmModal();
        this.showLoadingOverlay();
        
        try {
            const formData = new FormData();
            formData.append('action', 'delete_domain');
            formData.append('id', domainId);
            
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast(result.message, 'success');
                this.removeDomainFromGrid(domainId);
                this.updateStats();
            } else {
                this.showToast(result.message, 'error');
            }
            
        } catch (error) {
            console.error('Error deleting domain:', error);
            this.showToast('Error deleting domain', 'error');
        } finally {
            this.hideLoadingOverlay();
        }
    }

    closeConfirmModal() {
        this.hideModal('confirmModal');
    }

    // Grid Management
    addDomainToGrid(domainData) {
        const domainsGrid = document.getElementById('domainsGrid');
        const emptyState = domainsGrid.querySelector('.empty-state');
        
        if (emptyState) {
            emptyState.remove();
        }
        
        const domainCard = this.createDomainCard(domainData);
        domainsGrid.insertAdjacentHTML('afterbegin', domainCard);
    }

    updateDomainInGrid(domainId, domainData) {
        const domainCard = document.querySelector(`[data-domain-id="${domainId}"]`);
        if (domainCard) {
            domainCard.outerHTML = this.createDomainCard(domainData);
        }
    }

    removeDomainFromGrid(domainId) {
        const domainCard = document.querySelector(`[data-domain-id="${domainId}"]`);
        if (domainCard) {
            domainCard.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => {
                domainCard.remove();
                
                // Check if grid is empty
                const domainsGrid = document.getElementById('domainsGrid');
                if (domainsGrid.children.length === 0) {
                    this.showEmptyState();
                }
            }, 300);
        }
    }

    createDomainCard(domain) {
        const statusClass = `status-${domain.status}`;
        const createdDate = new Date(domain.created_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
        
        return `
            <div class="domain-card" data-domain-id="${domain.id}">
                <div class="domain-header">
                    <div class="domain-info">
                        <h3 class="domain-name">${this.escapeHtml(domain.domain)}</h3>
                        <p class="site-name">${this.escapeHtml(domain.name)}</p>
                        ${domain.description ? `<p class="domain-description">${this.escapeHtml(domain.description)}</p>` : ''}
                    </div>
                    <div class="domain-status">
                        <span class="status-badge ${statusClass}">
                            ${domain.status.charAt(0).toUpperCase() + domain.status.slice(1)}
                        </span>
                    </div>
                </div>
                
                <div class="domain-stats">
                    <div class="stat">
                        <i class="fas fa-palette"></i>
                        <span>${domain.theme_name || 'Default'}</span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-film"></i>
                        <span>${domain.movies_count || 0} movies</span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-tv"></i>
                        <span>${domain.shows_count || 0} shows</span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-calendar"></i>
                        <span>${createdDate}</span>
                    </div>
                </div>
                
                <div class="domain-actions">
                    <a href="http://${this.escapeHtml(domain.domain)}" 
                       target="_blank" 
                       rel="noopener"
                       class="btn btn-secondary btn-sm">
                        <i class="fas fa-external-link-alt"></i>
                        <span>Visit Site</span>
                    </a>
                    <button class="btn btn-primary btn-sm" 
                            onclick="domainManager.editDomain(${domain.id})">
                        <i class="fas fa-edit"></i>
                        <span>Edit</span>
                    </button>
                    <button class="btn btn-danger btn-sm" 
                            onclick="domainManager.deleteDomain(${domain.id}, '${this.escapeHtml(domain.domain)}')">
                        <i class="fas fa-trash-alt"></i>
                        <span>Delete</span>
                    </button>
                </div>
            </div>
        `;
    }

    updateStats() {
        // This would typically refetch stats from the server
        // For now, we'll update based on current DOM state
        const domainCards = document.querySelectorAll('.domain-card');
        const totalDomains = domainCards.length;
        const activeDomains = document.querySelectorAll('.status-active').length;
        
        // Update stat numbers with animation
        this.animateStatUpdate('.stat-number', 0, totalDomains);
    }

    animateStatUpdate(selector, from, to) {
        const element = document.querySelector(selector);
        if (!element) return;
        
        const duration = 1000;
        const steps = 60;
        const increment = (to - from) / steps;
        let current = from;
        
        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= to) || (increment < 0 && current <= to)) {
                current = to;
                clearInterval(timer);
            }
            element.textContent = Math.round(current).toLocaleString();
        }, duration / steps);
    }

    // UI State Management
    setLoadingState(button, loading) {
        if (loading) {
            button.disabled = true;
            button.classList.add('loading');
        } else {
            button.disabled = false;
            button.classList.remove('loading');
        }
    }

    showLoadingOverlay() {
        document.getElementById('loadingOverlay').classList.add('show');
    }

    hideLoadingOverlay() {
        document.getElementById('loadingOverlay').classList.remove('show');
    }

    showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastMessage = toast.querySelector('.toast-message');
        const toastIcon = toast.querySelector('.toast-icon');
        
        // Update message
        toastMessage.textContent = message;
        
        // Update icon based on type
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        
        toastIcon.className = `toast-icon ${type}`;
        toastIcon.querySelector('i').className = icons[type] || icons.success;
        
        // Show toast
        toast.classList.add('show');
        
        // Auto hide after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
        }, 5000);
        
        // Allow manual close
        toast.onclick = () => {
            toast.classList.remove('show');
        };
    }

    // Utility Methods
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

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

    // Keyboard Shortcuts
    handleKeyboardShortcuts(e) {
        // Ctrl/Cmd + N to add new domain
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            this.openAddDomainModal();
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            this.closeDomainModal();
            this.closeConfirmModal();
        }
    }

    // Mobile Optimizations
    handleTouchEvents() {
        if (!this.isMobile) return;
        
        // Add touch feedback for buttons
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(button => {
            button.addEventListener('touchstart', () => {
                button.style.transform = 'scale(0.98)';
            });
            
            button.addEventListener('touchend', () => {
                setTimeout(() => {
                    button.style.transform = '';
                }, 100);
            });
        });
        
        // Improve modal scrolling on mobile
        const modals = document.querySelectorAll('.modal-content');
        modals.forEach(modal => {
            modal.addEventListener('touchmove', (e) => {
                e.stopPropagation();
            });
        });
    }

    // Data Export/Import (for future use)
    exportDomains() {
        const domains = Array.from(document.querySelectorAll('.domain-card')).map(card => {
            return {
                id: card.dataset.domainId,
                domain: card.querySelector('.domain-name').textContent,
                name: card.querySelector('.site-name').textContent,
                status: card.querySelector('.status-badge').textContent.toLowerCase()
            };
        });
        
        const dataStr = JSON.stringify(domains, null, 2);
        const dataBlob = new Blob([dataStr], {type: 'application/json'});
        
        const link = document.createElement('a');
        link.href = URL.createObjectURL(dataBlob);
        link.download = 'domains-export.json';
        link.click();
        
        this.showToast('Domains exported successfully', 'success');
    }

    // Search and Filter (for future enhancement)
    setupSearch() {
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Search domains...';
        searchInput.className = 'form-control';
        searchInput.style.maxWidth = '300px';
        
        searchInput.addEventListener('input', this.debounce((e) => {
            this.filterDomains(e.target.value);
        }, 300));
        
        // Add to header actions
        const headerActions = document.querySelector('.header-actions');
        headerActions.insertBefore(searchInput, headerActions.firstChild);
    }

    filterDomains(searchTerm) {
        const domainCards = document.querySelectorAll('.domain-card');
        const term = searchTerm.toLowerCase();
        
        domainCards.forEach(card => {
            const domain = card.querySelector('.domain-name').textContent.toLowerCase();
            const name = card.querySelector('.site-name').textContent.toLowerCase();
            
            if (domain.includes(term) || name.includes(term)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Bulk Operations (for future enhancement)
    enableBulkOperations() {
        // Add checkboxes to domain cards
        const domainCards = document.querySelectorAll('.domain-card');
        domainCards.forEach(card => {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'domain-checkbox';
            checkbox.dataset.domainId = card.dataset.domainId;
            
            const header = card.querySelector('.domain-header');
            header.insertBefore(checkbox, header.firstChild);
        });
        
        // Add bulk action bar
        const bulkActions = document.createElement('div');
        bulkActions.className = 'bulk-actions';
        bulkActions.innerHTML = `
            <div class="bulk-actions-content">
                <span class="selected-count">0 selected</span>
                <button class="btn btn-danger btn-sm" onclick="domainManager.bulkDelete()">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
                <button class="btn btn-secondary btn-sm" onclick="domainManager.clearSelection()">
                    Clear Selection
                </button>
            </div>
        `;
        
        document.querySelector('.domains-section').insertBefore(bulkActions, document.querySelector('.domains-grid'));
    }
}

// Global functions for onclick handlers (needed for inline event handlers)
function editDomain(domainId) {
    domainManager.editDomain(domainId);
}

function deleteDomain(domainId, domainName) {
    domainManager.deleteDomain(domainId, domainName);
}

function openAddDomainModal() {
    domainManager.openAddDomainModal();
}

function closeDomainModal() {
    domainManager.closeDomainModal();
}

function closeConfirmModal() {
    domainManager.closeConfirmModal();
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.domainManager = new DomainManager();
    
    // Add keyboard shortcut listener
    document.addEventListener('keydown', (e) => {
        domainManager.handleKeyboardShortcuts(e);
    });
});

// Add CSS animations for better UX
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(-100%);
        }
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .domain-card {
        animation: slideIn 0.3s ease;
    }
    
    .toast {
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .loading-overlay {
        transition: opacity 0.2s ease;
    }
    
    .modal-content {
        transition: transform 0.2s ease;
    }
    
    /* Mobile touch feedback */
    @media (hover: none) and (pointer: coarse) {
        .btn:active {
            transform: scale(0.98);
        }
        
        .domain-card:active {
            transform: scale(0.995);
        }
    }
`;
document.head.appendChild(style);

  
