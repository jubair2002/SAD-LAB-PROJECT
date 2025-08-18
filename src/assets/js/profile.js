// Full Screen Profile JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Setup full screen handling
    setupFullScreenHandling();
    
    // Initialize tab functionality
    initializeTabs();
    
    // Setup form validation
    setupFormValidation();
    
    // Initialize file upload
    initializeFileUpload();
    
    // Setup animations
    setupAnimations();
    
    console.log('Profile page loaded and initialized');
});

// ===== FULL SCREEN HANDLING =====
function setupFullScreenHandling() {
    // Prevent zoom on mobile
    document.addEventListener('touchstart', function(event) {
        if (event.touches.length > 1) {
            event.preventDefault();
        }
    });

    let lastTouchEnd = 0;
    document.addEventListener('touchend', function(event) {
        const now = (new Date()).getTime();
        if (now - lastTouchEnd <= 300) {
            event.preventDefault();
        }
        lastTouchEnd = now;
    }, false);

    // Adjust viewport height for full screen
    adjustViewportHeight();

    // Handle orientation change
    window.addEventListener('orientationchange', function() {
        setTimeout(adjustViewportHeight, 100);
    });

    // Handle resize
    window.addEventListener('resize', adjustViewportHeight);
}

function adjustViewportHeight() {
    const vh = window.innerHeight;
    document.body.style.height = vh + 'px';
    const dashboard = document.querySelector('.profile-dashboard');
    if (dashboard) {
        dashboard.style.height = vh + 'px';
    }
}

// ===== TAB FUNCTIONALITY =====
function initializeTabs() {
    const menuItems = document.querySelectorAll('.menu-item');
    const tabContents = document.querySelectorAll('.tab-content');
    
    menuItems.forEach(item => {
        item.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all menu items
            menuItems.forEach(menu => menu.classList.remove('active'));
            
            // Add active class to clicked item
            this.classList.add('active');
            
            // Hide all tab contents
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Show target tab content
            const targetContent = document.getElementById(targetTab);
            if (targetContent) {
                targetContent.classList.add('active');
            }
            
            // Update content header
            updateContentHeader(targetTab);
            
            // Add visual feedback
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    });
}

function updateContentHeader(tabName) {
    const contentTitle = document.querySelector('.content-title');
    const contentSubtitle = document.querySelector('.content-subtitle');
    
    const tabInfo = {
        'profile-info': {
            title: 'Profile Information',
            subtitle: 'Manage your personal information and profile settings'
        },
        'security': {
            title: 'Security Settings',
            subtitle: 'Update your password and security preferences'
        }
    };
    
    if (tabInfo[tabName]) {
        contentTitle.textContent = tabInfo[tabName].title;
        contentSubtitle.textContent = tabInfo[tabName].subtitle;
    }
}

// ===== FORM VALIDATION =====
function setupFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Allow the form to submit by default - this is the key fix
            let isValid = validateForm(this);
            
            // If validation fails, prevent submission
            if (!isValid) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                // Don't disable the button to ensure form submission
                // submitBtn.disabled = true;
            }
            
            // Form is valid, allow it to submit normally
            return true;
        });
    });
    
    // Real-time validation
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            // Clear validation styling on input
            this.style.borderColor = '';
            this.style.boxShadow = '';
            
            // Remove error message if exists
            const errorMsg = this.parentNode.querySelector('.error-message');
            if (errorMsg) {
                errorMsg.remove();
            }
        });
    });
}

function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        // Skip file inputs if they're not required for this submission
        if (field.type === 'file' && !form.querySelector('[name="upload_picture"]')) {
            return;
        }
        
        if (!validateField(field)) {
            isValid = false;
        }
    });
    
    // Additional validation for password change form
    if (form.querySelector('[name="change_password"]')) {
        const newPassword = form.querySelector('[name="new_password"]');
        if (newPassword && newPassword.value.length < 6) {
            showFieldError(newPassword, 'Password must be at least 6 characters long');
            isValid = false;
        }
    }
    
    // Email validation
    if (form.querySelector('[name="email"]')) {
        const email = form.querySelector('[name="email"]');
        if (email && email.value.trim() !== '' && !isValidEmail(email.value)) {
            showFieldError(email, 'Please enter a valid email address');
            isValid = false;
        }
    }
    
    return isValid;
}

function validateField(field) {
    // Skip validation for hidden fields
    if (field.type === 'hidden') {
        return true;
    }
    
    // Required field validation
    if (field.hasAttribute('required') && !field.value.trim()) {
        showFieldError(field, 'This field is required');
        return false;
    }
    
    // Email validation
    if (field.type === 'email' && field.value.trim() !== '' && !isValidEmail(field.value)) {
        showFieldError(field, 'Please enter a valid email address');
        return false;
    }
    
    // Clear any existing error
    clearFieldError(field);
    return true;
}

function showFieldError(field, message) {
    field.style.borderColor = 'var(--danger-color)';
    field.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
    
    // Show error message
    let errorMsg = field.parentNode.querySelector('.error-message');
    if (!errorMsg) {
        errorMsg = document.createElement('div');
        errorMsg.className = 'error-message';
        errorMsg.style.cssText = `
            color: var(--danger-color);
            font-size: 12px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        `;
        field.parentNode.appendChild(errorMsg);
    }
    errorMsg.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
}

function clearFieldError(field) {
    field.style.borderColor = '';
    field.style.boxShadow = '';
    
    const errorMsg = field.parentNode.querySelector('.error-message');
    if (errorMsg) {
        errorMsg.remove();
    }
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// ===== FILE UPLOAD =====
function initializeFileUpload() {
    const fileInput = document.getElementById('picture-upload');
    const fileUploadArea = document.querySelector('.file-upload-area');
    
    if (fileInput && fileUploadArea) {
        // Click to upload
        fileUploadArea.addEventListener('click', function() {
            fileInput.click();
        });
        
        // Drag and drop
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        fileUploadArea.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });
        
        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelection(files[0]);
            }
        });
        
        // File selection change
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleFileSelection(this.files[0]);
            }
        });
    }
}

function handleFileSelection(file) {
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!allowedTypes.includes(file.type)) {
        showMessage('Please select a valid image file (JPG, PNG, GIF)', 'error');
        return;
    }
    
    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
        showMessage('File size must be less than 5MB', 'error');
        return;
    }
    
    // Show preview
   const reader = new FileReader();
    reader.onload = function(e) {
        const profileImg = document.querySelector('.profile-img');
        if (profileImg) {
            profileImg.src = e.target.result;
            profileImg.style.transform = 'scale(1.05)';
            setTimeout(() => {
                profileImg.style.transform = 'scale(1)';
            }, 300);
            
            // Immediately submit the form
            document.getElementById('picture-form').submit();
        }
    };
    reader.readAsDataURL(file);
}

// ===== ANIMATIONS =====
function setupAnimations() {
    // Animate elements on page load
    const animateElements = document.querySelectorAll('.form-section, .profile-sidebar, .profile-content');
    animateElements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            element.style.transition = 'all 0.6s ease';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

// ===== UTILITY FUNCTIONS =====
function showMessage(message, type = 'info') {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.toast-message');
    existingMessages.forEach(msg => msg.remove());
    
    // Create message element
    const messageDiv = document.createElement('div');
    messageDiv.className = 'toast-message';
    messageDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        ${message}
    `;
    
    // Style based on type
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        font-size: 14px;
        z-index: 10000;
        background-color: ${colors[type] || colors.info};
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        animation: slideInToast 0.3s ease;
        max-width: 350px;
        word-wrap: break-word;
        display: flex;
        align-items: center;
        gap: 10px;
    `;
    
    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInToast {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutToast {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    
    if (!document.querySelector('#toast-styles')) {
        style.id = 'toast-styles';
        document.head.appendChild(style);
    }
    
    // Add to page
    document.body.appendChild(messageDiv);
    
    // Remove after 4 seconds
    setTimeout(() => {
        messageDiv.style.animation = 'slideOutToast 0.3s ease';
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 300);
    }, 4000);
}

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    });
});

// Add slide out animation
const slideOutStyle = document.createElement('style');
slideOutStyle.textContent = `
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(slideOutStyle);