// /js/settings/state.js
var SettingsStateModule = (function() {
    // Private state variables
    let currentFocusOrg = null;
    let customOrgs = [];
    let isInitialized = false;

    return {
        getCurrentFocusOrg: function() {
            return currentFocusOrg;
        },
        setCurrentFocusOrg: function(orgId) {
            currentFocusOrg = orgId;
        },
        getCustomOrgs: function() {
            return customOrgs;
        },
        setCustomOrgs: function(orgs) {
            customOrgs = orgs;
        },
        addCustomOrg: function(org) {
            customOrgs.push(org);
        },
        isInitialized: function() {
            return isInitialized;
        },
        setInitialized: function(value) {
            isInitialized = value;
        },
        resetState: function() {
            currentFocusOrg = null;
            customOrgs = [];
            isInitialized = false;
        }
    };
})();
