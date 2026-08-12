$(document).ready(function () {
    setTimeout(function () {
        if (userStatus !== 1) {
            $('#popupForm').fadeIn(); // Show login/signup form
        }
    }, 10000);
});

// Login AJAX
$('#loginForm').on('submit', function (e) {
    e.preventDefault();
    $.ajax({
        url: 'assets/db_query/login/login_query.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                SwalHelper.ecommerce.loginSuccess().then(() => {
                    sessionStorage.setItem('user_logged_in', true);
                    location.reload();
                });
            } else {
                // Store form data for retry
                const formData = $('#loginForm').serialize();
                SwalHelper.ecommerce.loginError(res.message || 'Invalid email/username or password. Please check your credentials and try again.', function() {
                    // Retry callback - form will be ready for user to try again
                    $('#loginForm input[name="identifier"]').focus();
                });
            }
        },
        error: function() {
            // Store form data for retry
            const formData = $('#loginForm').serialize();
            SwalHelper.ecommerce.loginError('Network Error', 'Unable to connect to the server. Please check your internet connection and try again.', function() {
                // Retry callback
                $('#loginForm input[name="identifier"]').focus();
            });
        }
    });
});

// Signup AJAX with client-side validation
$('#signupForm').on('submit', function (e) {
    e.preventDefault();
    
    // Get form values - use more specific selectors within signup form
    var $form = $(this);
    var email = $form.find('input[name="email"]').val();
    var username = $form.find('input[name="username"]').val();
    var password = $form.find('input[name="password"]').val();
    var phone = $form.find('input[name="phone"]').val();
    
    // Trim values
    if (email) email = email.trim();
    if (username) username = username.trim();
    if (password) password = password.trim();
    if (phone) phone = phone.trim();
    
    // Client-side validation
    if (!email || email === '') {
        SwalHelper.ecommerce.validationError('Email', 'Email address is required to create your account.');
        $form.find('input[name="email"]').focus();
        return;
    }
    
    // Basic email validation
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        SwalHelper.ecommerce.validationError('Email', 'Please enter a valid email address (e.g., yourname@example.com).');
        $form.find('input[name="email"]').focus();
        return;
    }
    
    if (!username || username === '') {
        SwalHelper.ecommerce.validationError('Username', 'Username is required. Choose a unique username for your account.');
        $form.find('input[name="username"]').focus();
        return;
    }
    
    if (username.length < 3) {
        SwalHelper.ecommerce.validationError('Username', 'Username must be at least 3 characters long.');
        $form.find('input[name="username"]').focus();
        return;
    }
    
    if (!password || password === '') {
        SwalHelper.ecommerce.validationError('Password', 'Password is required to secure your account.');
        $form.find('input[name="password"]').focus();
        return;
    }
    
    if (password.length < 6) {
        SwalHelper.ecommerce.validationError('Password', 'Password must be at least 6 characters long for security.');
        $form.find('input[name="password"]').focus();
        return;
    }
    
    if (!phone || phone === '') {
        SwalHelper.ecommerce.validationError('Phone', 'Phone number is required for order updates and delivery.');
        $form.find('input[name="phone"]').focus();
        return;
    }
    
    // Basic phone validation (10-15 digits)
    var phoneRegex = /^[0-9]{10,15}$/;
    if (!phoneRegex.test(phone)) {
        SwalHelper.ecommerce.validationError('Phone', 'Phone number must be 10-15 digits (e.g., 9876543210).');
        $form.find('input[name="phone"]').focus();
        return;
    }
    
    // Submit to server
    $.ajax({
        url: 'assets/db_query/login/sign_query.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function (res) {
            if (res.status === 'success') {
                SwalHelper.ecommerce.signupSuccess().then(() => {
                    sessionStorage.setItem('user_logged_in', true);
                    location.reload();
                });
            } else {
                SwalHelper.ecommerce.signupError(res.message, function() {
                    // Retry callback - form will be ready for user to try again
                    $('#signupForm input[name="email"]').focus();
                });
            }
        },
        error: function() {
            SwalHelper.ecommerce.signupError('Network Error', 'Unable to connect to the server. Please check your internet connection and try again.', function() {
                // Retry callback
                $('#signupForm input[name="email"]').focus();
            });
        }
    });
});


// Toggle to Login
function toggleLogin() {
    $('#login-form').show();
    $('#signup-form').hide();

    $('#login-toggle').css({
        'background-color': '#57B846',
        'color': '#fff'
    });

    $('#signup-toggle').css({
        'background-color': '#fff',
        'color': '#222'
    });
}

// Toggle to Signup
function toggleSignup() {
    $('#login-form').hide();
    $('#signup-form').show();

    $('#signup-toggle').css({
        'background-color': '#57B846',
        'color': '#fff'
    });

    $('#login-toggle').css({
        'background-color': '#fff',
        'color': '#222'
    });
}

// Close modal
function closeForm() {
    $('#popupForm').fadeOut();
}

// Retry login (used in forgotten account)
function retryLogin() {
    toggleLogin();
}
// Check login status using sessionStorage
function isLoggedIn() {
    return sessionStorage.getItem('user_logged_in') === 'true';
}
document.getElementById('userIcon').addEventListener('click', function (e) {

    // If NOT logged in → open login popup
    if (userStatus !== 1) {
        e.preventDefault();   // stop dropdown
        openForm();           // show popup
    }

    // If logged in → Bootstrap dropdown works normally
});

function openForm() {
    document.getElementById("popupForm").style.display = "block";
}

function closeForm() {
    document.getElementById("popupForm").style.display = "none";
}
