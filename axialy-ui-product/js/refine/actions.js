/****************************************************************************
 * /js/refine/actions.js
 *
 * The core logic that handles each "Refine Activity" action.
 * Called by RefineEventsModule.toggleRefineDropdown() -> handleActivitySelection().
 *
 * Must be loaded after "RefineStateModule" and any modules we call:
 *   - EnhanceContentModule
 *   - ContentReviewModule
 *   - CollateFeedbackModule
 *   - etc.
 *
 * No further manual merges are required; this file is fully integrated.
 ***************************************************************************/
var RefineActionsModule = (function() {
  // 1) Build a dictionary of "action name" => function
  const refineActions = {
    // 1) Enhance Content (AI) => updated to accept an optional actionContent param
    enhanceFocusAreaContent: function(
      focusAreaName,
      packageName,
      packageId,
      focusAreaVersionRow,
      actionContent
    ) {
      if (window.EnhanceContentModule && typeof window.EnhanceContentModule.initEnhancement === 'function') {
        // We assume you've updated EnhanceContentModule.initEnhancement to accept a 4th param:
        // initEnhancement(packageId, packageName, focusAreaName, defaultUserInstruction='')
        // So we pass actionContent as that 4th param:
        window.EnhanceContentModule.initEnhancement(
          packageId,
          packageName,
          focusAreaName,
          actionContent || ''
        );
      } else {
        console.error('[RefineActions] EnhanceContentModule missing or invalid.');
        alert('Enhance Content (AI) feature is currently unavailable.');
      }
    },

    // 2) Request Feedback => updated to accept an optional actionContent param
    contentReviews: function(
      focusAreaName,
      packageName,
      packageId,
      focusAreaVersionRow,
      actionContent
    ) {
      if (window.ContentReviewModule && typeof window.ContentReviewModule.init === 'function') {
        // pass actionContent as the 4th argument so it populates the personal message
        window.ContentReviewModule.init(focusAreaName, packageName, packageId, actionContent || '');
      } else {
        console.error('[RefineActions] ContentReviewModule missing or invalid.');
        alert('Request Feedback feature is currently unavailable.');
      }
    },

    // 3) Compile Feedback (AI)
    collateFeedback: function(focusAreaName, packageName, packageId, focusAreaVersionRow) {
      if (window.CollateFeedbackModule && typeof window.CollateFeedbackModule.init === 'function') {
        window.CollateFeedbackModule.init(focusAreaName, packageName, packageId, focusAreaVersionRow);
      } else {
        console.error('[RefineActions] CollateFeedbackModule missing or invalid.');
        alert('Compile Feedback (AI) feature is currently unavailable.');
      }
    },

    // 4) Remove Focus Area
    deleteFocusAreaData: function(focusAreaName, packageName, packageId, focusAreaVersionRow) {
      const confirmation = confirm(
        `Are you sure you want to delete all records for focus area "${focusAreaName}"?\n` +
        'This action will create a new version of the focus area.'
      );
      if (!confirmation) return;

      RefineUtilsModule.showPageMaskSpinner('Deleting focus area data...');

      // We now send the row ID (focusAreaVersionRow) as focus_area_version_id:
      const dataToSend = {
        focus_area_name: focusAreaName,
        package_name: packageName,
        package_id: packageId,
        focus_area_version_id: focusAreaVersionRow
      };

      RefineApiModule.deleteFocusAreaData(dataToSend)
        .then(resp => {
          RefineUtilsModule.hidePageMaskSpinner();
          if (resp.status === 'success') {
            alert('Focus area data deleted successfully.');
            if (typeof reloadRefineTabAndOpenPackage === 'function') {
              reloadRefineTabAndOpenPackage(packageId);
            }
          } else {
            alert('Failed to delete focus area data: ' + (resp.error || resp.message));
          }
        })
        .catch(err => {
          RefineUtilsModule.hidePageMaskSpinner();
          console.error('[RefineActions] Error deleting focus area data:', err);
          alert('An error occurred while deleting focus area data.');
        });
    },

    // 5) Recover Versions
    recoverFocusAreaVersions: function(focusAreaName, packageName, packageId, focusAreaVersionRow) {
      if (window.RecoverFocusAreaModule && typeof window.RecoverFocusAreaModule.init === 'function') {
        window.RecoverFocusAreaModule.init(focusAreaName, packageName, packageId, focusAreaVersionRow);
      } else {
        console.error('[RefineActions] RecoverFocusAreaModule missing or invalid.');
        alert('Recover Versions feature is currently unavailable.');
      }
    },

    // 6) Edit Content
    contentRevisions: function(focusAreaName, packageName, packageId, focusAreaVersionRow) {
      if (window.ContentRevisionModule && typeof window.ContentRevisionModule.init === 'function') {
        window.ContentRevisionModule.init(focusAreaName, packageName, packageId, focusAreaVersionRow);
      } else {
        console.error('[RefineActions] ContentRevisionModule missing or invalid.');
        alert('Edit Content (manual revisions) is currently unavailable.');
      }
    }
  };

  /**
   * This function is invoked when the user selects a refine activity
   * from the dropdown inside toggleRefineDropdown(...).
   * We also allow an optional 'actionContent' as the last argument if needed.
   */
  function handleActivitySelection(activity, focusAreaName, focusAreaVersionRow, actionContent) {
    const pkgId       = RefineStateModule.getSelectedPackageId();
    const packageName = RefineStateModule.getCurrentPackageName();

    if (!pkgId) {
      alert('No analysis package selected.');
      return;
    }

    // Look up the function
    const actionFunction = refineActions[activity.action];
    if (typeof actionFunction === 'function') {
      // We unify the approach so 5th param can be used for "enhanceFocusAreaContent" or "contentReviews"
      if (activity.action === 'enhanceFocusAreaContent' || activity.action === 'contentReviews') {
        actionFunction(focusAreaName, packageName, pkgId, focusAreaVersionRow, actionContent);
      } else {
        // For other actions we just pass the 4 main args
        actionFunction(focusAreaName, packageName, pkgId, focusAreaVersionRow);
      }
    } else {
      console.error(`[RefineActions] No matching function for action "${activity.action}".`);
      alert(`No implementation found for ${activity.label}.`);
    }
  }

  // Return our public methods
  return {
    handleActivitySelection: handleActivitySelection
  };
})();
