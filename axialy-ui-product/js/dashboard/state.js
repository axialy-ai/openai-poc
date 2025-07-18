// /js/dashboard/state.js
var DashboardStateModule = (function() {
    // Private state variables
    let feedbackRequests = [];
    let focusOrganizations = [];
    let stakeholderEmails = [];
    let analysisPackages = [];
    let focusAreas = [];
    let isInitialized = false;
    let currentPage = 1;
    let totalCount = 0;
    let limit = 10;

    return {
        getFeedbackRequests: function() {
            return feedbackRequests;
        },
        setFeedbackRequests: function(requests) {
            feedbackRequests = requests;
        },
        getFocusOrganizations: function() {
            return focusOrganizations;
        },
        setFocusOrganizations: function(orgs) {
            focusOrganizations = orgs;
        },
        getStakeholderEmails: function() {
            return stakeholderEmails;
        },
        setStakeholderEmails: function(emails) {
            stakeholderEmails = emails;
        },
        getAnalysisPackages: function() {
            return analysisPackages;
        },
        setAnalysisPackages: function(packages) {
            analysisPackages = packages;
        },
        getFocusAreas: function() {
            return focusAreas;
        },
        setFocusAreas: function(areas) {
            focusAreas = areas;
        },
        isInitialized: function() {
            return isInitialized;
        },
        setInitialized: function(value) {
            isInitialized = value;
        },
        getCurrentPage: function() {
            return currentPage;
        },
        setCurrentPage: function(page) {
            currentPage = page;
        },
        getTotalCount: function() {
            return totalCount;
        },
        setTotalCount: function(count) {
            totalCount = count;
        },
        getLimit: function() {
            return limit;
        },
        setLimit: function(lim) {
            limit = lim;
        },
        resetState: function() {
            feedbackRequests = [];
            focusOrganizations = [];
            stakeholderEmails = [];
            analysisPackages = [];
            focusAreas = [];
            isInitialized = false;
            currentPage = 1;
            totalCount = 0;
            limit = 10;
        }
    };
})();
