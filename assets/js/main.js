// Form validation and enhancement
document.addEventListener('DOMContentLoaded', function() {
    // Add loading state to forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner"></span> Loading...';
            }
        });
    });
    
    // Input validation
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9+-\s]/g, '');
        });
    });
    
    // Email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const email = this.value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                this.style.borderColor = 'var(--danger-color)';
                showError(this, 'Please enter a valid email address');
            } else {
                this.style.borderColor = 'var(--success-color)';
                hideError(this);
            }
        });
    });
    
    // Password strength meter
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        input.addEventListener('input', function() {
            if (this.id === 'password' || this.name === 'password') {
                checkPasswordStrength(this.value);
            }
        });
    });
});

function checkPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    
    const meter = document.getElementById('password-strength');
    if (meter) {
        let strengthText = '';
        let strengthColor = '';
        switch(strength) {
            case 0:
            case 1:
                strengthText = 'Very Weak';
                strengthColor = 'var(--danger-color)';
                break;
            case 2:
                strengthText = 'Weak';
                strengthColor = 'var(--warning-color)';
                break;
            case 3:
                strengthText = 'Medium';
                strengthColor = 'var(--info-color)';
                break;
            case 4:
                strengthText = 'Strong';
                strengthColor = 'var(--success-color)';
                break;
            case 5:
                strengthText = 'Very Strong';
                strengthColor = 'var(--success-color)';
                break;
        }
        meter.innerHTML = strengthText;
        meter.style.color = strengthColor;
    }
}

function showError(input, message) {
    let error = input.parentElement.querySelector('.error-message');
    if (!error) {
        error = document.createElement('div');
        error.className = 'error-message';
        error.style.color = 'var(--danger-color)';
        error.style.fontSize = '0.875rem';
        error.style.marginTop = '0.25rem';
        input.parentElement.appendChild(error);
    }
    error.innerHTML = message;
}

function hideError(input) {
    const error = input.parentElement.querySelector('.error-message');
    if (error) {
        error.remove();
    }
}

// Toast notification system
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div style="background: white; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; box-shadow: var(--shadow-lg);">
            ${message}
        </div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Date picker enhancement
const dateInputs = document.querySelectorAll('input[type="date"]');
dateInputs.forEach(input => {
    input.setAttribute('min', new Date().toISOString().split('T')[0]);
});

// Real-time search functionality
function searchTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toUpperCase();
    const table = document.querySelector('.data-table');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        let match = false;
        const cells = rows[i].getElementsByTagName('td');
        for (let j = 0; j < cells.length; j++) {
            if (cells[j]) {
                const textValue = cells[j].textContent || cells[j].innerText;
                if (textValue.toUpperCase().indexOf(filter) > -1) {
                    match = true;
                    break;
                }
            }
        }
        rows[i].style.display = match ? '' : 'none';
    }
}

// Export to CSV
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const rowData = [];
        const cols = row.querySelectorAll('td, th');
        cols.forEach(col => {
            rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
        });
        csv.push(rowData.join(','));
    });
    
    const csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
    const downloadLink = document.createElement('a');
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.click();
}