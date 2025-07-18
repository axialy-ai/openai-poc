//  Start File: app.axialy.ai/js/ribbon-handler.js

// /js/ribbon-handler.js

// ribbon-handler.js

// This script now exclusively handles static ribbons.
// All dynamic template ribbon functionalities have been removed to maintain separation of concerns.

// Example: Handling a static "Notifications" ribbon

document.addEventListener('DOMContentLoaded', function() {
    const notificationsRibbon = document.querySelector('.notifications-ribbon');
    if (notificationsRibbon) {
        const toggleIcon = notificationsRibbon.querySelector('.toggle-icon');
        const contentContainer = notificationsRibbon.nextElementSibling;

        toggleIcon.addEventListener('click', () => {
            const computedStyle = window.getComputedStyle(contentContainer);
            if (computedStyle.display === 'none') {
                contentContainer.style.display = 'block';
                toggleIcon.innerHTML = '&#9660;';  // Down-pointing arrow (expanded state)
            } else {
                contentContainer.style.display = 'none';
                toggleIcon.innerHTML = '&#9654;';  // Right-pointing arrow (collapsed state)
            }
        });
    }

    // Add handlers for other static ribbons similarly
});

// Example Function: Displaying a static ribbon with predefined content
function displayNotifications() {
    const ribbonsContainer = document.getElementById('ribbons-container');

    const ribbonHeader = document.createElement('div');
    ribbonHeader.className = 'ribbon notifications-ribbon';

    const toggleButton = document.createElement('span');
    toggleButton.className = 'toggle-icon';
    toggleButton.innerHTML = '&#9654;';  // Right-pointing arrow

    const title = document.createElement('span');
    title.className = 'ribbon-title';
    title.textContent = 'Notifications';

    const titleContainer = document.createElement('div');
    titleContainer.className = 'ribbon-header-content';
    titleContainer.appendChild(toggleButton);
    titleContainer.appendChild(title);

    ribbonHeader.appendChild(titleContainer);

    const ribbonContainer = document.createElement('div');
    ribbonContainer.className = 'ribbon-container';
    ribbonContainer.style.display = 'none';  // Initially collapsed

    // Add static content to the notifications ribbon
    const notificationsContent = document.createElement('div');
    notificationsContent.innerHTML = `
        <ul>
            <li>You have 3 new messages.</li>
            <li>Your report was generated successfully.</li>
            <li>System maintenance scheduled for tonight.</li>
        </ul>
    `;
    ribbonContainer.appendChild(notificationsContent);

    // Toggle functionality
    toggleButton.addEventListener('click', function() {
        const computedStyle = window.getComputedStyle(ribbonContainer);
        if (computedStyle.display === 'none') {
            ribbonContainer.style.display = 'block';
            toggleButton.innerHTML = '&#9660;';  // Down-pointing arrow
        } else {
            ribbonContainer.style.display = 'none';
            toggleButton.innerHTML = '&#9654;';  // Right-pointing arrow
        }
    });

    // Append the header and container to the ribbons container
    ribbonsContainer.appendChild(ribbonHeader);
    ribbonsContainer.appendChild(ribbonContainer);
}

// Initialize static ribbons on page load
document.addEventListener('DOMContentLoaded', function() {
    // Example: Initialize Notifications Ribbon
    displayNotifications();

    // Initialize other static ribbons as needed
});
