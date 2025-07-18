// /js/modules/ui-utils-module.js
var UIUtilsModule = (function() {
    function updatePageTitle(tabName) {
        console.log("UIUtilsModule.updatePageTitle: setting document title =>", tabName); // ADDED LOG

        var viewsMenu = document.getElementById('views-menu');
        if (viewsMenu) {
            // Find and select the option that matches the tabName
            var options = viewsMenu.options;
            for (var i = 0; i < options.length; i++) {
                if (options[i].text.trim().toLowerCase() === tabName.trim().toLowerCase()) {
                    viewsMenu.selectedIndex = i;
                    console.log("UIUtilsModule.updatePageTitle: synchronized dropdown =>", tabName); // ADDED LOG
                    break;
                }
            }
        }

        // Update the document title for browser/tab title
        var appName = 'AxiaBA';
        document.title = appName + ' - ' + tabName;
        console.log("UIUtilsModule.updatePageTitle: final doc title =>", document.title); // ADDED LOG
    }

    function applyBackgroundSettings(element) {
        console.log("UIUtilsModule.applyBackgroundSettings: element =>", element); // ADDED LOG
        var backgroundImage = element.getAttribute('data-background-image');
        var backgroundOpacity = element.getAttribute('data-background-opacity');
        if (backgroundImage && backgroundOpacity !== null) {
            var pageContainer = document.querySelector('.page-container');
            if (pageContainer) {
                var absolutePath = backgroundImage.startsWith('/') ? backgroundImage : '/' + backgroundImage;
                pageContainer.style.setProperty('--panel-background-image', 'url(\'' + absolutePath + '\')');
                pageContainer.style.setProperty('--panel-background-opacity', backgroundOpacity);
                console.log("UIUtilsModule.applyBackgroundSettings: background set =>", absolutePath, "opacity =>", backgroundOpacity); // ADDED LOG
            } else {
                console.warn('UIUtilsModule.applyBackgroundSettings: Page container not found.');
            }
        } else {
            console.warn('UIUtilsModule.applyBackgroundSettings: Incomplete background settings for element.');
        }
    }

    function adjustOverviewPanel() {
        console.log("UIUtilsModule.adjustOverviewPanel: adjusting overview panel height..."); // ADDED LOG

        var overviewPanel = document.querySelector('.overview-panel');
        var header = document.querySelector('.page-header');
        var footer = document.querySelector('.page-footer');

        if (overviewPanel && header && footer) {
            var headerHeight = header.offsetHeight;
            var footerHeight = footer.offsetHeight;
            var newHeight = window.innerHeight - headerHeight - footerHeight - 40;

            overviewPanel.style.height = newHeight + 'px';
            console.log("UIUtilsModule.adjustOverviewPanel: new overviewPanel height =>", newHeight); // ADDED LOG
        }
    }

    return {
        updatePageTitle: updatePageTitle,
        applyBackgroundSettings: applyBackgroundSettings,
        adjustOverviewPanel: adjustOverviewPanel
    };
})();
