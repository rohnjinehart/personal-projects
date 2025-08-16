// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Password toggle visibility functionality
    const togglePassword = document.querySelector('.toggle-password');
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const toggleText = this;
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleText.textContent = '👁️ Hide';
            } else {
                passwordField.type = 'password';
                toggleText.textContent = '👁️ Show';
            }
        });
    }

    // Password strength checker
    const passwordField = document.getElementById('password');
    if (passwordField) {
        passwordField.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('password-strength-bar');
            
            let strength = 0;
            if (password.length >= 8) strength += 25;
            if (password.length >= 12) strength += 25;
            if (/[A-Z]/.test(password)) strength += 15;
            if (/[0-9]/.test(password)) strength += 15;
            if (/[^A-Za-z0-9]/.test(password)) strength += 20;
            
            strengthBar.style.width = `${strength}%`;
            
            if (strength < 50) {
                strengthBar.style.backgroundColor = '#dc3545';
            } else if (strength < 75) {
                strengthBar.style.backgroundColor = '#fd7e14';
            } else {
                strengthBar.style.backgroundColor = '#28a745';
            }
            
            document.getElementById('req-length').classList.toggle('met', password.length >= 8);
            document.getElementById('req-uppercase').classList.toggle('met', /[A-Z]/.test(password));
            document.getElementById('req-number').classList.toggle('met', /[0-9]/.test(password));
            document.getElementById('req-special').classList.toggle('met', /[^A-Za-z0-9]/.test(password));
        });
    }

    // Form submission handler with reCAPTCHA v3 validation
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Disable button during submission
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Registering...';
            
            // Execute reCAPTCHA v3
            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.ready(function() {
                    grecaptcha.execute('6LfZsAUrAAAAAElwLEx1VOwt3xo3diCV4hiqn55_', {action: 'submit'}).then(function(token) {
                        handleFormSubmission(token);
                    });
                });
            } else {
                // Fallback if reCAPTCHA fails to load
                handleFormSubmission('');
            }
        });
    }
});

function handleFormSubmission(recaptchaToken) {
    // Add token to form
    document.getElementById('g-recaptcha-response').value = recaptchaToken;
    
    // Validate password length
    const password = document.getElementById('password').value;
    if (password.length < 8) {
        showMessage('Password must be at least 12 characters long', 'error');
        enableSubmitButton();
        return;
    }
    
    // Collect form data
    const formData = {
        username: document.getElementById('username').value,
        email: document.getElementById('email').value,
        password: password,
        full_name: document.getElementById('full_name').value,
        phone_number: document.getElementById('phone_number').value,
        role: document.getElementById('role').value,
        'g-recaptcha-response': recaptchaToken,
        register: true
    };

    // Send data to server
    fetch('userAuth.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(formData)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            let successMessage = data.message;
            
            // Handle email sending status
            if (data.email_sent !== undefined) {
                if (data.email_sent) {
                    successMessage += " A welcome email has been sent to your address.";
                } else {
                    successMessage += " (Note: Welcome email could not be sent)";
                }
            }
            
            showMessage(successMessage, 'success');
            
            // Redirect after delay
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 3000);
        } else {
            showMessage(data.message || 'Registration failed. Please try again.', 'error');
            enableSubmitButton();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred during registration. Please try again later.', 'error');
        enableSubmitButton();
    });
}

function showMessage(message, type) {
    const messageEl = document.getElementById('message');
    messageEl.textContent = message;
    messageEl.className = type;
    messageEl.style.display = 'block';
    
    // Auto-hide success messages after delay
    if (type === 'success') {
        setTimeout(() => {
            messageEl.style.display = 'none';
        }, 5000);
    }
}

function enableSubmitButton() {
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Register';
    }
}