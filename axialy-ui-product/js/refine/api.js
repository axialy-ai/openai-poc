/*
 * /js/refine/api.js
 *
 */

var RefineApiModule = (function() {
    /**
     * Searches analysis packages by a text query, optionally including
     * soft-deleted packages if showDeleted=true.
     */
    function fetchPackages(searchTerm, showDeleted = false) {
        const deletedParam = showDeleted ? 1 : 0;
        const url = `api/get_analysis_packages_with_metrics.php?search=${encodeURIComponent(searchTerm)}&showDeleted=${deletedParam}`;
        return fetch(url)
            .then(resp => {
                if (resp.status === 401) {
                    return resp.json().then(data => {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        }
                        throw new Error(data.message || 'Unauthorized');
                    });
                }
                if (!resp.ok) {
                    throw new Error('Error fetching packages');
                }
                return resp.json();
            });
    }

    /**
     * Loads focus-area records from fetch_analysis_package_focus_area_records.php,
     * optionally including is_deleted=1 if showDeleted=true. If no focus_area_version_number
     * is specified, the endpoint picks the highest version available.
     */
    function fetchFocusAreaRecords(packageId, showDeleted, focusAreaVersionNumber = null) {
        const dParam = showDeleted ? 1 : 0;
        let url = `fetch_analysis_package_focus_area_records.php?package_id=${packageId}&show_deleted=${dParam}`;
        if (focusAreaVersionNumber !== null) {
            url += `&focus_area_version_number=${focusAreaVersionNumber}`;
        }
        return fetch(url)
            .then(resp => resp.ok ? resp.json() : Promise.reject('Failed to fetch focus area records'));
    }

    /**
     * Loads a JSON file that may define available “refine activities.”
     */
    function fetchRefineActivities() {
        return fetch('config/refine-activities.json')
            .then(resp => resp.ok ? resp.json() : Promise.reject('Failed to load refine-activities.json'))
            .then(data => {
                if (data.refineActivities && Array.isArray(data.refineActivities)) {
                    return data.refineActivities.filter(a => a.active);
                }
                return [];
            });
    }

    /**
     * Loads stakeholder feedback for a package and a specified focus area,
     * using a new approach that references focus_areas_id and focus_area_version_id.
     */
    function fetchStakeholderFeedback(packageId, focusAreasId, focusAreaVersionId) {
        const url = `fetch_stakeholder_feedback.php?package_id=${packageId}`
                  + `&focus_areas_id=${focusAreasId}`
                  + `&focus_area_version_id=${focusAreaVersionId}`;
        return fetch(url)
            .then(resp => resp.ok ? resp.json() : Promise.reject('Failed to load stakeholder feedback'));
    }

    /**
     * Calls a server endpoint to perform a logical deletion (or other removal)
     * of focus-area data, referencing analysis_package_focus_area_records.
     */
    function deleteFocusAreaData(data) {
        return fetch('process_delete_focus_area_data.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(resp => resp.json());
    }

    return {
        fetchPackages,
        fetchFocusAreaRecords,
        fetchRefineActivities,
        fetchStakeholderFeedback,
        deleteFocusAreaData
    };
})();
