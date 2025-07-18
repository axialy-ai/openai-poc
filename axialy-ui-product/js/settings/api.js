// /js/settings/api.js
var SettingsAPIModule = (function() {
    async function fetchCustomOrganizations() {
        const response = await fetch('/get_custom_organizations.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        });
        if (!response.ok) {
            throw new Error(`Network response was not ok (${response.status})`);
        }
        const data = await response.json();
        if (data.status === 'success') {
            return data.organizations || [];
        } else {
            throw new Error(data.message || 'Failed to fetch organizations');
        }
    }

    async function fetchCurrentFocusOrganization() {
        const response = await fetch('/get_focus_organization.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        });
        if (!response.ok) {
            throw new Error(`Network response was not ok (${response.status})`);
        }
        const data = await response.json();
        if (data.status === 'success') {
            return data.focus_org_id;
        } else {
            throw new Error(data.message || 'Failed to fetch focus organization');
        }
    }

    async function updateFocusOrganization(orgId) {
        const response = await fetch('/update_focus_organization.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ focus_org_id: orgId })
        });
        if (!response.ok) {
            throw new Error(`Network response was not ok (${response.status})`);
        }
        const data = await response.json();
        if (data.status !== 'success') {
            throw new Error(data.message || 'Failed to update focus organization');
        }
    }

    async function createCustomOrganization(formData) {
        const response = await fetch('/create_custom_organization.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        if (!response.ok) {
            throw new Error(`Network response was not ok (${response.status})`);
        }
        const data = await response.json();
        if (data.status === 'success') {
            return data.organization;
        } else {
            throw new Error(data.message || 'Failed to create organization');
        }
    }

    async function updateCustomOrganization(orgId, formData) {
        // Must ensure organization_id is in the formData
        formData.append('organization_id', orgId);

        const response = await fetch('/update_custom_organization.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        if (!response.ok) {
            throw new Error(`Network response was not ok (${response.status})`);
        }
        const data = await response.json();
        if (data.status === 'success') {
            return data.organization;
        } else {
            throw new Error(data.message || 'Failed to update organization');
        }
    }

    return {
        fetchCustomOrganizations,
        fetchCurrentFocusOrganization,
        updateFocusOrganization,
        createCustomOrganization,
        updateCustomOrganization
    };
})();
