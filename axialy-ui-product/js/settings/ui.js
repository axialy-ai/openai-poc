// /js/settings/ui.js
var SettingsUIModule = (function() {
    /**
     * Renders the list of custom organizations in a Bootstrap "card" style layout.
     * @param {Array} orgs - Array of organization objects.
     */
    function renderOrganizationsList(orgs) {
        const orgsList = document.getElementById('orgs-list');
        if (!orgsList) return;

        if (!orgs || orgs.length === 0) {
            orgsList.innerHTML = '<p>No custom organizations created yet.</p>';
            return;
        }

        orgsList.innerHTML = orgs.map((org) => {
            // Build logo
            let logoHTML = `<div class="org-logo-placeholder">No Logo</div>`;
            if (org.logo_path) {
                const safeFilename = SettingsUtilsModule.sanitizeHTML(org.logo_path);
                const serveUrl = '/serve_logo.php?file=' + encodeURIComponent(safeFilename);
                logoHTML = `<img src="${serveUrl}" alt="Organization Logo" class="org-logo mb-2" />`;
            }

            // Safely render fields
            const orgName = SettingsUtilsModule.sanitizeHTML(org.custom_organization_name) || '';
            const contact = SettingsUtilsModule.sanitizeHTML(org.point_of_contact) || '';
            const email   = SettingsUtilsModule.sanitizeHTML(org.email) || '';
            const phone   = SettingsUtilsModule.sanitizeHTML(org.phone) || '';
            const website = SettingsUtilsModule.sanitizeHTML(org.website) || '';
            const notes   = SettingsUtilsModule.sanitizeHTML(org.organization_notes) || '';

            return `
                <div class="card mb-4 org-card" data-org-id="${org.id}">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <div class="me-3">
                                ${logoHTML}
                            </div>
                            <div>
                                <h5 class="card-title mb-2">${orgName}</h5>
                                <ul class="list-unstyled mb-2">
                                    <li><strong>Contact:</strong> ${contact}</li>
                                    <li><strong>Email:</strong> ${email}</li>
                                    <li><strong>Phone:</strong> ${phone}</li>
                                    <li><strong>Website:</strong> ${website}</li>
                                    <li><strong>Notes:</strong> ${notes}</li>
                                </ul>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="btn btn-sm btn-primary edit-org-btn mt-2"
                            data-org-id="${org.id}"
                        >
                            Edit
                        </button>

                        <!-- The inline edit form container: hidden by default -->
                        <div class="edit-org-form-container mt-3" style="display: none;"></div>
                    </div>
                </div>
            `;
        }).join('');
    }

    /**
     * Returns the HTML for an inline edit form with 2-column alignment.
     * @param {Object} org - The organization object to edit.
     */
    function getEditOrgFormHTML(org) {
        // We can produce unique IDs if we want label→input linking
        const baseId = `org-${org.id}`;

        return `
        <form class="edit-org-form p-3 border rounded" enctype="multipart/form-data">
          <div class="row mb-3">
            <label for="${baseId}-name" class="col-sm-3 col-form-label">Organization Name</label>
            <div class="col-sm-9">
              <input
                type="text"
                class="form-control"
                id="${baseId}-name"
                name="org-name"
                value="${SettingsUtilsModule.sanitizeHTML(org.custom_organization_name) || ''}"
              />
            </div>
          </div>

          <div class="row mb-3">
            <label for="${baseId}-contact" class="col-sm-3 col-form-label">Point of Contact</label>
            <div class="col-sm-9">
              <input
                type="text"
                class="form-control"
                id="${baseId}-contact"
                name="point-of-contact"
                value="${SettingsUtilsModule.sanitizeHTML(org.point_of_contact) || ''}"
              />
            </div>
          </div>

          <div class="row mb-3">
            <label for="${baseId}-email" class="col-sm-3 col-form-label">Email</label>
            <div class="col-sm-9">
              <input
                type="email"
                class="form-control"
                id="${baseId}-email"
                name="org-email"
                value="${SettingsUtilsModule.sanitizeHTML(org.email) || ''}"
              />
            </div>
          </div>

          <div class="row mb-3">
            <label for="${baseId}-phone" class="col-sm-3 col-form-label">Phone</label>
            <div class="col-sm-9">
              <input
                type="text"
                class="form-control"
                id="${baseId}-phone"
                name="org-phone"
                value="${SettingsUtilsModule.sanitizeHTML(org.phone) || ''}"
              />
            </div>
          </div>

          <div class="row mb-3">
            <label for="${baseId}-website" class="col-sm-3 col-form-label">Website</label>
            <div class="col-sm-9">
              <input
                type="url"
                class="form-control"
                id="${baseId}-website"
                name="org-website"
                value="${SettingsUtilsModule.sanitizeHTML(org.website) || ''}"
              />
            </div>
          </div>

          <div class="row mb-3">
            <label for="${baseId}-notes" class="col-sm-3 col-form-label">Notes</label>
            <div class="col-sm-9">
              <textarea
                class="form-control"
                id="${baseId}-notes"
                name="org-notes"
                rows="3"
              >${SettingsUtilsModule.sanitizeHTML(org.organization_notes) || ''}</textarea>
            </div>
          </div>

          <div class="row mb-3">
            <label class="col-sm-3 col-form-label">Update Logo (optional)</label>
            <div class="col-sm-9">
              <input
                type="file"
                class="form-control"
                name="org-logo"
                accept=".png,.jpg,.jpeg"
              />
            </div>
          </div>

          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-success me-2">Save Changes</button>
            <button type="button" class="btn btn-secondary cancel-edit-btn">Cancel</button>
          </div>
        </form>
        `;
    }

    /**
     * Updates the Focus Organization dropdown with current organizations.
     * @param {Array} orgs - array of organization objects
     */
    function updateFocusOrgDropdown(orgs) {
        const focusOrgSelect = document.getElementById('focus-organization');
        if (!focusOrgSelect) return;

        focusOrgSelect.innerHTML = `
            <option value="default">Default (All Organizations)</option>
            ${
                orgs.map(org => `
                  <option value="${SettingsUtilsModule.sanitizeHTML(String(org.id))}">
                    ${SettingsUtilsModule.sanitizeHTML(org.custom_organization_name)}
                  </option>
                `).join('')
            }
        `;
    }

    /**
     * Displays a toast notification.
     * @param {string} message
     * @param {string} [type='success'] - 'success', 'danger', etc.
     */
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('settings-toast-container');
        if (!toastContainer) return;

        const toastElement = SettingsUtilsModule.createToast(message, type);
        toastContainer.appendChild(toastElement);

        const bsToast = new bootstrap.Toast(toastElement);
        bsToast.show();

        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }


    /**
     * Shows the page mask (spinner overlay).
     */
    function showLoadingOverlay() {
      const overlay = document.getElementById('loading-overlay');
      if (overlay) {
        overlay.style.display = 'flex'; // or 'block'
      }
    }

    /**
     * Hides the page mask (spinner overlay).
     */
    function hideLoadingOverlay() {
      const overlay = document.getElementById('loading-overlay');
      if (overlay) {
        overlay.style.display = 'none';
      }
    }


    return {
        renderOrganizationsList,
        getEditOrgFormHTML,
        updateFocusOrgDropdown,
        showToast,
        showLoadingOverlay,
        hideLoadingOverlay
    };
})();
