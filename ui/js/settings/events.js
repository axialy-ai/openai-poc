// /js/settings/events.js
var SettingsEventsModule = (function() {
    /**
     * Sets up all event listeners for the Settings tab.
     * @param {Object} elements - Cached DOM elements.
     */
    function setupEventListeners(elements) {
        // Handle focus organization change
        elements.focusOrgSelect.addEventListener('change', async (e) => {
            const selectedValue = e.target.value;
            const newFocusOrg = selectedValue === 'default' ? 'default' : selectedValue;
            try {
                // Could show overlay too if it's a longer operation:
                // SettingsUIModule.showLoadingOverlay();
                await SettingsAPIModule.updateFocusOrganization(newFocusOrg);
                SettingsStateModule.setCurrentFocusOrg(newFocusOrg);
                SettingsUIModule.showToast('Focus Organization updated successfully.', 'success');
            } catch (error) {
                SettingsUIModule.showToast(error.message, 'danger');
                // revert selection to previous
                e.target.value = SettingsStateModule.getCurrentFocusOrg() || 'default';
            } finally {
                // SettingsUIModule.hideLoadingOverlay();
            }
        });

        // Handle create organization form submission
        elements.createOrgForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            SettingsUIModule.showLoadingOverlay();  // Show spinner
            const formData = new FormData(e.target);
            try {
                const newOrg = await SettingsAPIModule.createCustomOrganization(formData);
                SettingsStateModule.addCustomOrg(newOrg);

                SettingsUIModule.renderOrganizationsList(SettingsStateModule.getCustomOrgs());
                SettingsUIModule.updateFocusOrgDropdown(SettingsStateModule.getCustomOrgs());
                SettingsUIModule.showToast('Organization created successfully.', 'success');
                e.target.reset();
            } catch (error) {
                SettingsUIModule.showToast(error.message, 'danger');
            } finally {
                // Hide spinner no matter success or fail
                SettingsUIModule.hideLoadingOverlay();
            }
        });

        // Clicking the "Edit" button for an org
        elements.orgsList.addEventListener('click', async (e) => {
            if (e.target.classList.contains('edit-org-btn')) {
                const orgId = e.target.getAttribute('data-org-id');
                const org = SettingsStateModule.getCustomOrgs().find(o => String(o.id) === String(orgId));
                if (!org) return;

                const card = e.target.closest('.org-card');
                const formContainer = card.querySelector('.edit-org-form-container');
                if (formContainer.style.display === 'none') {
                    formContainer.innerHTML = SettingsUIModule.getEditOrgFormHTML(org);
                    formContainer.style.display = 'block';
                } else {
                    formContainer.style.display = 'none';
                }
            }
        });

        // Submitting the inline edit form
        elements.orgsList.addEventListener('submit', async (e) => {
            if (e.target.classList.contains('edit-org-form')) {
                e.preventDefault();
                SettingsUIModule.showLoadingOverlay();
                const form = e.target;
                const card = form.closest('.org-card');
                const orgId = card.getAttribute('data-org-id');

                try {
                    const formData = new FormData(form);
                    const updatedOrg = await SettingsAPIModule.updateCustomOrganization(orgId, formData);

                    // Update local state
                    let orgs = SettingsStateModule.getCustomOrgs();
                    const index = orgs.findIndex(o => String(o.id) === String(orgId));
                    if (index >= 0) {
                        orgs[index] = updatedOrg;
                    }

                    // Re-render
                    SettingsUIModule.renderOrganizationsList(orgs);
                    SettingsUIModule.showToast('Organization updated successfully.', 'success');
                } catch (error) {
                    SettingsUIModule.showToast(error.message, 'danger');
                } finally {
                    SettingsUIModule.hideLoadingOverlay();
                }
            }
        });

        // Cancel button in the inline edit form
        elements.orgsList.addEventListener('click', (e) => {
            if (e.target.classList.contains('cancel-edit-btn')) {
                const formContainer = e.target.closest('.edit-org-form-container');
                formContainer.style.display = 'none';
            }
        });
    }

    return {
        setupEventListeners: setupEventListeners
    };
})();
