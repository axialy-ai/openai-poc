/****************************************************************************
 * /js/refine/recover-focus-area.js
 *
 * Adapts the old "recoverFocusArea" concept to the new schema:
 *   - fetches versions from fetch_past_versions.php
 *   - user picks a version
 *   - calls process_recover_focus_area.php with that version
 *
 * We assume:
 *   - package_id
 *   - focus_area_name
 *   - currentVersion => the current focus_area_version_number
 ****************************************************************************/
window.RecoverFocusAreaModule = (function() {
    let packageIdGlobal       = 0;
    let packageNameGlobal     = '';
    let focusAreaNameGlobal   = '';
    let currentVersionGlobal  = 0;

    /**
     * Called externally to start the "Recover Versions" flow.
     * @param {string} focusAreaName
     * @param {string} packageName
     * @param {number} packageId
     * @param {number} currentVersion (the focus_area_version_number that’s current)
     */
    function init(focusAreaName, packageName, packageId, currentVersion) {
        packageIdGlobal      = packageId;
        packageNameGlobal    = packageName;
        focusAreaNameGlobal  = focusAreaName;
        currentVersionGlobal = currentVersion;

        showRecoveryOverlay();
    }

    /**
     * Build an overlay that lists all versions for the specified focus area
     */
    function showRecoveryOverlay() {
        removeOverlayIfExists('recover-versions-overlay');

        const overlay = document.createElement('div');
        overlay.id = 'recover-versions-overlay';
        overlay.className = 'overlay';

        const content = document.createElement('div');
        content.className = 'overlay-content';

        const closeBtn = document.createElement('span');
        closeBtn.className = 'close-overlay';
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', () => overlay.remove());

        const title = document.createElement('h2');
        title.textContent = 'Recover Focus Area Versions';

        const subTitle = document.createElement('p');
        subTitle.textContent = `Select a past version to restore for "${focusAreaNameGlobal}".`;

        const listContainer = document.createElement('div');
        listContainer.className = 'versions-list';

        // fetchFocusAreaVersions => from our new fetch_past_versions.php
        fetchFocusAreaVersions(packageIdGlobal, focusAreaNameGlobal)
            .then(versions => {
                if (!versions || versions.length === 0) {
                    listContainer.innerHTML = '<p>No past versions found.</p>';
                    return;
                }

                const ul = document.createElement('ul');
                ul.style.listStyle = 'none';
                ul.style.paddingLeft = '0';

                versions.forEach(v => {
                    // v.version_num, v.created_at, v.focus_area_object, v.revision_summary
                    const li = document.createElement('li');
                    li.style.marginBottom = '12px';

                    const radio = document.createElement('input');
                    radio.type  = 'radio';
                    radio.name  = 'restoreVersion';
                    radio.value = String(v.version_num);

                    // If it’s the same as currentVersionGlobal => disable
                    if (parseInt(v.version_num, 10) === currentVersionGlobal) {
                        radio.disabled = true;
                    }

                    const label = document.createElement('label');
                    label.style.marginLeft = '6px';
                    const versionNumStr = `Version ${v.version_num}`;
                    const createdAtStr  = v.created_at || '(no date)';
                    label.innerHTML = `${versionNumStr}, created at ${createdAtStr}`;

                    li.appendChild(radio);
                    li.appendChild(label);

                    if (v.revision_summary && v.revision_summary.trim() !== '') {
                        const summaryDiv = document.createElement('div');
                        summaryDiv.style.marginLeft = '24px';
                        summaryDiv.style.fontSize   = '0.9em';
                        summaryDiv.style.color      = '#444';
                        summaryDiv.innerHTML =
                          `<strong>Revision Summary:</strong><br>${escapeHTML(v.revision_summary)}`;
                        li.appendChild(summaryDiv);
                    }

                    ul.appendChild(li);
                });

                listContainer.appendChild(ul);
            })
            .catch(err => {
                console.error('[recover-focus-area.js] Error fetching versions:', err);
                listContainer.innerHTML = `<p>Error fetching past versions: ${err.message}</p>`;
            });

        const restoreBtn = document.createElement('button');
        restoreBtn.className = 'overlay-button commit-btn';
        restoreBtn.textContent = 'Restore Version';
        restoreBtn.addEventListener('click', () => {
            const chosen = listContainer.querySelector('input[name="restoreVersion"]:checked');
            if (!chosen) {
                alert('Please select a version to restore.');
                return;
            }
            const chosenVersion = parseInt(chosen.value, 10);
            overlay.remove();
            doFocusAreaRecovery(chosenVersion);
        });

        content.appendChild(closeBtn);
        content.appendChild(title);
        content.appendChild(subTitle);
        content.appendChild(listContainer);
        content.appendChild(restoreBtn);

        overlay.appendChild(content);
        document.body.appendChild(overlay);
    }

    function fetchFocusAreaVersions(packageId, focusAreaName) {
        const url = `fetch_past_versions.php?package_id=${packageId}&focus_area_name=${encodeURIComponent(focusAreaName)}`;
        return fetch(url)
            .then(r => r.ok ? r.json() : Promise.reject(new Error('Network error loading versions.')))
            .then(json => {
                if (json.status !== 'success') {
                    throw new Error(json.message || 'Failed to load versions');
                }
                return json.data;
            });
    }

    function doFocusAreaRecovery(chosenVersion) {
        // We show a spinner while recovering
        if (window.RefineUtilsModule && typeof RefineUtilsModule.showPageMaskSpinner === 'function') {
            RefineUtilsModule.showPageMaskSpinner('Recovering past version...');
        }

        const payload = {
            package_id:           packageIdGlobal,
            package_name:         packageNameGlobal,
            focus_area_name:      focusAreaNameGlobal,
            current_version_num:  currentVersionGlobal,
            recover_version_num:  chosenVersion
        };

        fetch('process_recover_focus_area.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(resp => {
            if (window.RefineUtilsModule && typeof RefineUtilsModule.hidePageMaskSpinner === 'function') {
                RefineUtilsModule.hidePageMaskSpinner();
            }
            if (resp.status === 'success') {
                alert(
                  `Focus area restored successfully from version ${chosenVersion}. ` +
                  `New version #${resp.new_version_number} created.`
                );
                if (typeof reloadRefineTabAndOpenPackage === 'function') {
                    reloadRefineTabAndOpenPackage(packageIdGlobal, focusAreaNameGlobal);
                } else {
                    window.location.reload();
                }
            } else {
                throw new Error(resp.message || 'Error recovering version.');
            }
        })
        .catch(err => {
            if (window.RefineUtilsModule && typeof RefineUtilsModule.hidePageMaskSpinner === 'function') {
                RefineUtilsModule.hidePageMaskSpinner();
            }
            console.error('[recover-focus-area.js] doFocusAreaRecovery error:', err);
            alert(`Failed to recover version: ${err.message}`);
        });
    }

    // Helper to sanitize user-supplied text
    function escapeHTML(str) {
        if (!str) return '';
        return str.replace(/[<>&"']/g, c => {
            switch (c) {
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '&': return '&amp;';
                case '"': return '&quot;';
                case "'": return '&#39;';
            }
        });
    }

    function removeOverlayIfExists(id) {
        const ex = document.getElementById(id);
        if (ex) ex.remove();
    }

    // Public API
    return {
        init
    };
})();
