// /js/account-actions.js

/**
 * Open Feedback Form
 */
function openFeedback() {
    console.log('Open Feedback form triggered.');
    // Implement your feedback form logic here
    alert('Feedback form is under construction.');
}

/**
 * Report Issues
 */
function reportIssue() {
    console.log('Report Issues form triggered.');
    // Implement your report issue form logic here
    alert('Report Issues form is under construction.');
}

/**
 * Toggle Light/Dark Mode
 */
function toggleMode() {
    console.log('Toggle Light/Dark Mode triggered.');
    document.body.classList.toggle('dark-mode');
    // Optionally, persist the mode using localStorage
    if (document.body.classList.contains('dark-mode')) {
        localStorage.setItem('theme', 'dark');
    } else {
        localStorage.setItem('theme', 'light');
    }
}

/**
 * Logout with confirmation
 */
function logout() {
    console.log('Logout action triggered.');
    if (confirm('Are you sure you want to log out?')) {
        // Clear any local storage data
        localStorage.removeItem('theme');
        // Redirect to logout handler
        window.location.href = '/logout.php';
    }
}

/**
 * End Demo
 */
function endDemo() {
    console.log('End Demo action triggered.');
    // Redirect to end_demo.php or implement end demo logic
    window.location.href = 'end_demo.php';
}

/**
 * Initialize the theme based on localStorage
 */
function initializeTheme() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }
}

// Initialize account actions
function initializeAccountActions() {
    // Load account actions from config if needed
    fetch('/config/account-actions.json')
        .then(response => response.json())
        .then(data => {
            // Process account actions configuration
            console.log('Account actions loaded:', data);
        })
        .catch(error => console.error('Error loading account actions:', error));
}

// Call initializations on page load
document.addEventListener('DOMContentLoaded', () => {
    initializeTheme();
    initializeAccountActions();
});