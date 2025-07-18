// /js/refine/state.js
var RefineStateModule = (function() {
    let selectedPackageId = null;
    let currentStakeholders = [];
    let currentPackageName = '';
    let currentVersion = null;
    let activeRefineActivities = [];

    function setSelectedPackageId(id) {
        selectedPackageId = id;
    }

    function getSelectedPackageId() {
        return selectedPackageId;
    }

    function setCurrentStakeholders(stakeholders) {
        currentStakeholders = stakeholders;
    }

    function getCurrentStakeholders() {
        return currentStakeholders;
    }

    function setCurrentPackageName(name) {
        currentPackageName = name;
    }

    function getCurrentPackageName() {
        return currentPackageName;
    }

    function setCurrentVersion(version) {
        currentVersion = version;
    }

    function getCurrentVersion() {
        return currentVersion;
    }

    function setActiveRefineActivities(activities) {
        activeRefineActivities = activities;
    }

    function getActiveRefineActivities() {
        return activeRefineActivities;
    }

    return {
        setSelectedPackageId: setSelectedPackageId,
        getSelectedPackageId: getSelectedPackageId,
        setCurrentStakeholders: setCurrentStakeholders,
        getCurrentStakeholders: getCurrentStakeholders,
        setCurrentPackageName: setCurrentPackageName,
        getCurrentPackageName: getCurrentPackageName,
        setCurrentVersion: setCurrentVersion,
        getCurrentVersion: getCurrentVersion,
        setActiveRefineActivities: setActiveRefineActivities,
        getActiveRefineActivities: getActiveRefineActivities
    };
})();
