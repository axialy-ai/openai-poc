// /js/focus-area-version.js
var FocusAreaVersionModule = (function() {
    /**
     * Initializes focus-area version tracking if needed.
     */
    function initialize() {
        // Any optional initialization code goes here.
    }

    /**
     * Creates a new version for a given focus area.
     * @param {number} focusAreaId - The ID of the analysis_package_focus_areas record.
     * @param {number} currentFocusAreaVersion - The current version number for this focus area.
     */
    function createNewVersion(focusAreaId, currentFocusAreaVersion) {
        const newVersion = currentFocusAreaVersion + 1;
        fetch('store_analysis_package_focus_area_version.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                analysis_package_focus_areas_id: focusAreaId,
                focus_area_version_number: newVersion
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Update the UI to reflect the new focus-area version
                updateVersionUI(newVersion);
            } else {
                alert(data.message || 'Error creating a new focus-area version.');
            }
        })
        .catch(error => {
            console.error('Error creating new focus-area version:', error);
        });
    }

    /**
     * Updates the UI to show the current focus-area version.
     * @param {number} focusAreaVersion - The new focus-area version number.
     */
    function updateVersionUI(focusAreaVersion) {
        const versionDisplay = document.getElementById('focus-area-version-display');
        if (versionDisplay) {
            versionDisplay.textContent = focusAreaVersion;
        }
    }

    return {
        initialize: initialize,
        createNewVersion: createNewVersion,
        updateVersionUI: updateVersionUI
    };
})();
