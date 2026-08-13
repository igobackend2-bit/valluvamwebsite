/**
 * SweetAlert2 Helper Utility for E-commerce Site
 * Provides consistent, user-friendly messaging across the application
 */

// Helper function to ensure SweetAlert appears above modals
function setSwalZIndex() {
    setTimeout(() => {
        const popup = document.querySelector('.swal2-popup');
        const container = document.querySelector('.swal2-container');
        if (popup) {
            popup.style.zIndex = '99999';
        }
        if (container) {
            container.style.zIndex = '99998';
        }
    }, 10);
}

const SwalHelper = {
    /**
     * Show success message
     */
    success: function(title, message, timer = 2000) {
        return Swal.fire({
            icon: 'success',
            title: title || 'Success!',
            text: message || '',
            timer: timer,
            timerProgressBar: true,
            showConfirmButton: true,
            confirmButtonColor: '#57B846',
            confirmButtonText: 'OK',
            didOpen: setSwalZIndex
        });
    },

    /**
     * Show error message (user-friendly for e-commerce)
     */
    error: function(title, message, timer = 3000, showRetry = false, retryCallback = null) {
        const config = {
            icon: 'error',
            title: title || 'Oops! Something went wrong',
            text: message || 'Please try again later. If the problem persists, contact our support team.',
            timer: showRetry ? null : timer,
            timerProgressBar: !showRetry,
            showConfirmButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'OK',
            customClass: {
                popup: 'swal2-popup-custom'
            },
            didOpen: setSwalZIndex
        };

        if (showRetry && retryCallback) {
            config.showCancelButton = true;
            config.cancelButtonText = 'Cancel';
            config.confirmButtonText = 'Retry';
            config.confirmButtonColor = '#57B846';
        }

        return Swal.fire(config).then((result) => {
            if (showRetry && result.isConfirmed && retryCallback) {
                retryCallback();
            }
        });
    },

    /**
     * Show warning message
     */
    warning: function(title, message, timer = 2500) {
        return Swal.fire({
            icon: 'warning',
            title: title || 'Warning',
            text: message || '',
            timer: timer,
            timerProgressBar: true,
            showConfirmButton: true,
            confirmButtonColor: '#ffc107',
            confirmButtonText: 'OK',
            didOpen: setSwalZIndex
        });
    },

    /**
     * Show info message
     */
    info: function(title, message, timer = 2500) {
        return Swal.fire({
            icon: 'info',
            title: title || 'Information',
            text: message || '',
            timer: timer,
            timerProgressBar: true,
            showConfirmButton: true,
            confirmButtonColor: '#17a2b8',
            confirmButtonText: 'OK',
            didOpen: setSwalZIndex
        });
    },

    /**
     * Show confirmation dialog
     */
    confirm: function(title, message, confirmText = 'Yes', cancelText = 'No') {
        return Swal.fire({
            icon: 'question',
            title: title || 'Are you sure?',
            text: message || '',
            showCancelButton: true,
            confirmButtonColor: '#57B846',
            cancelButtonColor: '#dc3545',
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            didOpen: setSwalZIndex
        });
    },

    /**
     * Show loading message
     */
    loading: function(title = 'Please wait...') {
        Swal.fire({
            title: title,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
                setSwalZIndex();
            }
        });
    },

    /**
     * Close current SweetAlert
     */
    close: function() {
        Swal.close();
    },

    /**
     * E-commerce specific messages
     */
    ecommerce: {
        // Cart messages
        addedToCart: function() {
            return SwalHelper.success('Added to Cart!', 'The item has been added to your shopping cart.', 2000);
        },
        cartError: function(message) {
            return SwalHelper.error('Cart Error', message || 'Unable to add item to cart. Please try again.');
        },
        notLoggedIn: function() {
            // Show login modal after warning
            return SwalHelper.warning('Login Required', 'Please login to continue shopping.', 2500).then(() => {
                if ($('#popupForm').length) {
                    $('#popupForm').fadeIn();
                    toggleLogin(); // Ensure login form is shown
                }
            });
        },

        // Wishlist messages
        addedToWishlist: function() {
            return SwalHelper.success('Added to Wishlist!', 'The item has been saved to your wishlist.', 2000);
        },
        wishlistExists: function() {
            return SwalHelper.info('Already in Wishlist', 'This product is already in your wishlist.');
        },
        wishlistError: function(message) {
            return SwalHelper.error('Wishlist Error', message || 'Unable to add item to wishlist. Please try again.');
        },

        // Order messages
        orderSuccess: function(orderId) {
            return SwalHelper.success('Order Placed Successfully!', `Your order has been placed. Order ID: ${orderId}. You will receive a confirmation email shortly.`, 4000);
        },
        orderError: function(message) {
            return SwalHelper.error('Order Failed', message || 'Unable to place your order. Please check your information and try again.');
        },

        // Authentication messages
        loginSuccess: function() {
            // Hide login modal before showing success
            if ($('#popupForm').length) {
                $('#popupForm').fadeOut();
            }
            return SwalHelper.success('Welcome Back!', 'You have been successfully logged in.', 2000);
        },
        loginError: function(message, retryCallback = null) {
            // Hide login modal temporarily to show error
            const $modal = $('#popupForm');
            if ($modal.length && $modal.is(':visible')) {
                $modal.fadeOut(100);
            }
            
            return SwalHelper.error(
                'Login Failed', 
                message || 'Invalid email/username or password. Please check your credentials and try again.',
                0, // No auto-close
                true, // Show retry
                function() {
                    // Retry callback - show modal again
                    if ($modal.length) {
                        $modal.fadeIn();
                        // Focus on first input
                        setTimeout(() => {
                            $('#loginForm input[name="identifier"]').focus();
                        }, 300);
                    }
                    if (retryCallback) {
                        retryCallback();
                    }
                }
            ).then(() => {
                // If user clicks Cancel, show modal again
                if ($modal.length && !$modal.is(':visible')) {
                    $modal.fadeIn();
                }
            });
        },
        signupSuccess: function() {
            // Hide signup modal before showing success
            if ($('#popupForm').length) {
                $('#popupForm').fadeOut();
            }
            return SwalHelper.success('Account Created!', 'Your account has been created successfully. You are now logged in.', 2500);
        },
        signupError: function(message, retryCallback = null) {
            // Hide signup modal temporarily to show error
            const $modal = $('#popupForm');
            if ($modal.length && $modal.is(':visible')) {
                $modal.fadeOut(100);
            }
            
            return SwalHelper.error(
                'Signup Failed', 
                message || 'Unable to create your account. Please check your information and try again.',
                0, // No auto-close
                true, // Show retry
                function() {
                    // Retry callback - show modal again
                    if ($modal.length) {
                        $modal.fadeIn();
                        // Focus on first input
                        setTimeout(() => {
                            $('#signupForm input[name="email"]').focus();
                        }, 300);
                    }
                    if (retryCallback) {
                        retryCallback();
                    }
                }
            ).then(() => {
                // If user clicks Cancel, show modal again
                if ($modal.length && !$modal.is(':visible')) {
                    $modal.fadeIn();
                }
            });
        },

        // Validation messages
        validationError: function(field, message) {
            return SwalHelper.warning('Validation Error', message || `Please check your ${field} and try again.`);
        },

        // Network/Server errors
        networkError: function() {
            return SwalHelper.error('Connection Error', 'Unable to connect to the server. Please check your internet connection and try again.');
        },
        serverError: function() {
            return SwalHelper.error('Server Error', 'Something went wrong on our end. Our team has been notified. Please try again in a few moments.');
        }
    }
};

// Make it globally available
window.SwalHelper = SwalHelper;

