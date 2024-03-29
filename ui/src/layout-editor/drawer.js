// DRAWER Module

// Load templates
const drawerTemplate = require('../templates/drawer.hbs');
const loadingTemplate = require('../templates/loading.hbs');

/**
 * Drawer contructor
 * @param {object} container - the container to render the drawer to
 */
let Drawer = function(parent, container, data) {
    this.parent = parent;
    this.DOMObject = container;

    // Drawer open state
    this.opened = false;
    this.initialised = false;

    // Search query
    this.searchQuery = '';

    this.searchFocus = false;
};

/**
 * Update search
 */
Drawer.prototype.updateSearch = function() {
    this.searchQuery = this.DOMObject.find('#inputSearch').val();
    this.searchFocus = true;
    this.render();
};

/**
 * Toggle the drawer
 */
Drawer.prototype.toggleDrawer = function(data) {
    const app = this.parent;
    this.opened = !this.opened;
    this.DOMObject.toggleClass('drawed', this.opened);

    if(!this.initialised || $.isEmptyObject(lD.layout.drawer)) {
        this.initDrawer();
    }

    // Update timeline width and scroll event ( with a delay for the drawer animation to end)
    _.debounce(function() {
        const $regionsContainer = app.timeline.DOMObject.find('#regions-container');
        // Save regions size to guarantee that when the scroll event is called, the region don't reset to 0 ( bugfix )
        app.timeline.properties.scrollWidth = $regionsContainer.find("#regions").width();

        // Trigger scroll
        $regionsContainer.scroll();
    }, 1000)();
};

/**
 * Create the drawer in the layout object
 */
Drawer.prototype.initDrawer = function(data) {
    const app = this.parent;
    const readOnlyModeOn = (app.readOnlyMode != undefined && app.readOnlyMode === true);
    const self = this;

    // Check if the drawer is already created/added
    if(!$.isEmptyObject(lD.layout.drawer)) {
        this.initalised = true;
        return;
    }

    // If layout is published and the drawer doesn't exist, cancel request
    if(readOnlyModeOn) {
        lD.layout.drawer.isEmpty = true;
        this.render();
        return;
    }

    if(data == undefined) {
        // Create a new drawer
        const linkToAPI = urlsForApi.layout.addDrawer;
        let requestPath = linkToAPI.url;

        // Show loading template
        self.DOMObject.find('#actions-drawer-content').html(loadingTemplate());

        // replace id if necessary/exists
        requestPath = requestPath.replace(':id', lD.layout.layoutId);

        $.ajax({
            url: requestPath,
            type: linkToAPI.type
        }).done(function(res) {
            if(res.success) {
                toastr.success(res.message);

                // Create drawer in the layout object
                lD.layout.createDrawer(res.data);

                // Mark as initialised
                self.initalised = true;

                // Re-render drawer
                self.render();
            } else {
                // Login Form needed?
                if(res.login) {
                    window.location.href = window.location.href;
                    location.reload(false);
                } else {
                    toastr.error(res.message);
                }

                lD.common.hideLoadingScreen();
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            lD.common.hideLoadingScreen();

            // Output error to console
            console.error(jqXHR, textStatus, errorThrown);
        });
    }

    this.initialised = true;
};

/**
 * Render Drawer and the layout
 * @param {Object} layout - the layout object to be rendered
 */
Drawer.prototype.render = function() {
    const app = this.parent;
    const self = this;
    const readOnlyModeOn = (app.readOnlyMode != undefined && app.readOnlyMode === true);

    // Show loading template
    self.DOMObject.html(loadingTemplate());
    self.DOMObject.find('#actions-drawer-content').html(loadingTemplate());

    let widgetArray = $.map(lD.layout.drawer.widgets, 
        function(value, index) {
            // Validate drawer widget based on isValid and target region
            if(value.isValid == 0 || value.targetRegionId == undefined) {
                value.isDrawerValid = false;
            } else {
                value.isDrawerValid = true;
                
                // Set region name
                value.targetRegionName = app.getElementByTypeAndId('region', 'region_' + value.targetRegionId).name;
            }

            return [value];
        }
    );

    // Sort and filter the widgets
    widgetArray.sort(function(a, b) {
        var nameA = a.widgetName.toUpperCase();
        var nameB = b.widgetName.toUpperCase();
        
        if(nameA < nameB) {
            return -1;
        }

        if(nameA > nameB) {
            return 1;
        }

        return 0;
    });

    let widgetArrayFiltered = widgetArray.filter(function(a) {
        var query = self.searchQuery.toUpperCase();
        // Widget name
        if(a.widgetName.toUpperCase().includes(query)) {
            return true;
        }

        // Module name
        if(a.moduleName.toUpperCase().includes(query)) {
            return true;
        }
        
        return false;
    });

    // Render drawer template using layout object
    const html = drawerTemplate({
        drawer: lD.layout.drawer,
        widgets: widgetArrayFiltered,
        readOnlyModeOn: readOnlyModeOn,
        searchQuery: self.searchQuery,
        trans: drawerTrans
    });

    // Append layout html to the main div
    this.DOMObject.html(html);

    // Drawer toggle
    this.DOMObject.find('#actions-drawer-toggle').off().click(function() {
        self.toggleDrawer();
    });

    // Enable hover and select for each widget
    this.DOMObject.find('.selectable:not(.ui-draggable-dragging)').click(function(e) {
        e.stopPropagation();
        lD.selectObject($(this));
    });
    
    if(lD.readOnlyMode === false) {
        const $searchInput = this.DOMObject.find('#inputSearch');
        $searchInput.off().on('input', _.debounce(function() {
            self.updateSearch();
        }, 500));

        if(this.searchFocus) {
            // Focus with the cursor to the end of text 
            var strLength = $searchInput.val().length * 2;
            $searchInput.focus();
            $searchInput[0].setSelectionRange(strLength, strLength);
            this.searchFocus = false;
        }

        // Drawer content
        this.DOMObject.find('#actions-drawer-content').droppable({
            accept: '[drop-to="region"]',
            tolerance: 'pointer',
            drop: function(event, ui) {
                if (self.opened) {
                    lD.dropItemAdd(event.target, ui.draggable[0]);
                }
            }
        }).click(function(e) {
            if(!$.isEmptyObject(lD.toolbar.selectedCard) || !$.isEmptyObject(lD.toolbar.selectedQueue)) {
                e.stopPropagation();
                lD.selectObject($(this));
            }
        });

        this.DOMObject.find('.designer-widget').droppable({
            greedy: true,
            tolerance: 'pointer',
            accept: function(el) {
                return ($(this).hasClass('editable') && $(el).attr('drop-to') === 'widget') ||
                    ($(this).hasClass('permissionsModifiable') && $(el).attr('drop-to') === 'all' && $(el).data('subType') === 'permissions');
            },
            drop: function(event, ui) {
                if (self.opened) {
                    lD.dropItemAdd(event.target, ui.draggable[0]);
                }
            }
        });

        this.DOMObject.find('.designer-widget.editable .editProperty').click(function(e) {
            e.stopPropagation();

            const parent = $(this).parents('.designer-widget.editable:first');
            const widget = lD.getElementByTypeAndId(parent.data('type'), parent.attr('id'), parent.data('parentType'));

            widget.editPropertyForm($(this).data('property'), $(this).data('propertyType'));
        });

        this.DOMObject.find('.designer-widget').contextmenu(function(ev) {
            
            if($(ev.currentTarget).is('.editable, .deletable, .permissionsModifiable')) {
                // Open context menu
                lD.openContextMenu(ev.currentTarget, {
                    x: ev.pageX,
                    y: ev.pageY
                });
            }

            // Prevent browser menu to open
            return false;
        });
    }
};

/**
 * Save drawer dimensions
 */
Drawer.prototype.saveDimensions = function() {
    const app = this.parent;

    let newWidth = this.DOMObject.find('#drawerRegionWidth').val();
    let newHeight = this.DOMObject.find('#drawerRegionHeight').val();

    if (newWidth == '' || newHeight == '') {
        console.log('No dimensions set, skip save');
        return;
    }

    const saveData = {
        width: newWidth,
        height: newHeight
    };

    const linkToAPI = urlsForApi.layout.saveDrawer;
    let requestPath = linkToAPI.url;

    // replace id if necessary/exists
    requestPath = requestPath.replace(':id', lD.layout.drawer.regionId);

    $.ajax({
        url: requestPath,
        type: linkToAPI.type,
        data: saveData
    }).done(function(res) {
        if(res.success) {
            // Drawer dimensions saved
            // Set dimenions to the local object
            lD.layout.drawer.dimensions.width = saveData.width;
            lD.layout.drawer.dimensions.height = saveData.height;

            // Refresh the viewer to reflect the changes
            lD.renderContainer(lD.viewer, lD.selectedObject);
        } else {
            // Login Form needed?
            if(res.login) {
                window.location.href = window.location.href;
                location.reload(false);
            } else {
                toastr.error(res.message);
            }

            lD.common.hideLoadingScreen();
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        lD.common.hideLoadingScreen();

        // Output error to console
        console.error(jqXHR, textStatus, errorThrown);
    });
};

module.exports = Drawer;