// TIMELINE Module

// Load templates
const timelineTemplate = require('../templates/timeline.hbs');

const regionPreviewContainerDimensions = {
    width: 60,
    height: 48
};

const widgetHighlightAnimation = {
    delayTime: 1000,
    deltaTime: 1000
};

const timeLineLabelMap = [
     {
        maxTime: 20,
        step: 1,
        delta: 1
    },
    {
        maxTime: 30,
        step: 1,
        delta: 2
    },
    {
        maxTime: 60,
        step: 1,
        delta: 2
    },
    {
        maxTime: 120,
        step: 2,
        delta: 4
    },
    {
        maxTime: 240,
        step: 5,
        delta: 4
    },
    {
        maxTime: 600,
        step: 10,
        delta: 4
    },
    {
        maxTime: 1200,
        step: 10,
        delta: 4
    },
    {
        maxTime: 3600,
        step: 60,
        delta: 4
    },
    {
        maxTime: 10000,
        step: 300,
        delta: 4
    }
];

/**
 * Timeline contructor
 * @param {object} container - the container to render the timeline to
 */
let Timeline = function(parent, container) {
    this.parent = parent;
    this.DOMObject = container;

    // Boolean to represent if a sort is happening
    this.beingSorted = false;

    // Properties to be used for the template
    this.properties = {
        zoom: -1, // Zoom by default is -1 so that can be calculated based on the widgets of the regions
        timelineMaxZoom: 30000,
        startingZoom: -1,
        minTime: 0,
        maxTime: lD.layout.duration,
        deltaTime: lD.layout.duration,
        deltaTimeFormatted: lD.common.timeFormat(lD.layout.duration),
        zoomInDisable: '',
        zoomOutDisable: '',
        zoomFindWidgetDisabled: '',
        scrollPosition: 0, // scroll position
        scrollVerticalPosition: 0, // scroll vertical position
        scrollWidth: 0, // To fix the double scroll reseting to 0 bug
        widgetContentMinimumVisibleWidth: 25, // Minimum with in px to show widget's content
        widgetMinimumVisibleDuration: 5, // Minimum % value so that the region details are shown
        widgetMinimumDurationOnStart: 15 // % of the shortest widget to be used to calculate the default zoom 
    };

    this.highlightOnLoad = {};

    this.scrollOnLoad = {};

    this.timeruler = {};
};

/**
 * Change timeline zoom
 * @param {number} zoom - the change to be applied to zoom ( -1:zoomOut, 0: default, 1: zoomIn )
 */
Timeline.prototype.changeZoom = function(zoom) {
    
    // Reset to starting zoom
    if(zoom === 0) {
        this.properties.scrollPosition = 0;
        this.properties.zoom = this.properties.startingZoom;
        return;
    }

    let zoomVariation = 10;

    if(this.properties.zoom >= 10000) {
        zoomVariation = this.properties.zoom * 0.1;
    } else if(this.properties.zoom >= 5000) {
        zoomVariation = 1000;
    } else if(this.properties.zoom >= 1000) {
        zoomVariation = 200;
    } else if(this.properties.zoom >= 500) {
        zoomVariation = 100;
    } else if(this.properties.zoom >= 200) {
        zoomVariation = 50;
    }

    // Calculate new zoom value
    let newZoom = Math.round(this.properties.zoom + (zoomVariation * zoom));
    
    // Reset zoom enable flags
    this.properties.zoomOutDisable = this.properties.zoomInDisable = '';

    // If zoom out is 100% or less disable button limit it to 100%
    if( newZoom <= 100 ){
        newZoom = 100;
        this.properties.zoomOutDisable = 'disabled';
        
        // Set scroll position to 0
        this.properties.scrollPosition = 0;
    }

    // Set the zoom and calculate the max time for the ruler
    this.properties.zoom = newZoom;
};

/**
 * Calculate time values/labels based on zoom and position of the scroll view
 */
Timeline.prototype.calculateTimeValues = function() {
    this.properties.deltaTime = Math.round(10 * (lD.layout.duration / (this.properties.zoom / 100))) / 10;
    this.properties.deltaTimeFormatted = lD.common.timeFormat(this.properties.deltaTime);
    this.properties.minTime = Math.round(10 * (this.properties.scrollPosition * lD.layout.duration)) / 10;
    this.properties.maxTime = this.properties.minTime + this.properties.deltaTime;
};

/**
 * If zoom is not defined, calculate default value based on widget lenght
 * @param {object} regions - Layout regions
 */
Timeline.prototype.calculateStartingZoom = function(regions) {
    
    // Find the smallest widget ( by duration )
    let shorterWidgetDuration = -1;
    for(let region in regions) {
        for(let widget in regions[region].widgets) {
            if(regions[region].widgets[widget].getTotalDuration() < shorterWidgetDuration || shorterWidgetDuration === -1) {
                shorterWidgetDuration = regions[region].widgets[widget].getTotalDuration();
            }
        }
    }

    // Calculate zoom and limit its minimum to 100%
    this.properties.zoom = Math.floor(this.properties.widgetMinimumDurationOnStart / (shorterWidgetDuration / lD.layout.duration));
    
    if(this.properties.zoom <= 100 ) {
        this.properties.zoom = this.properties.startingZoom = 100;
        this.properties.zoomOutDisable = 'disabled';
    } else {
        this.properties.zoomOutDisable = '';
    }

    this.properties.startingZoom = this.properties.zoom;
};

/**
 * Check regions and choose display type ( detailed/zoom-to-see-details) 
 * @param {object} regions - Layout regions
 */
Timeline.prototype.checkRegionsVisibility = function(regions) {

    const visibleDuration = lD.layout.duration * (100 / this.properties.zoom); //this.properties.maxTime - this.properties.minTime;
    
    for(let region in regions) {
        // Reset the region visibility flag
        regions[region].hideDetails = false;

        for(let widget in regions[region].widgets) {

            // Calculate the ratio of the widget compared to the region length
            const widthRatio = regions[region].widgets[widget].getTotalDuration() / visibleDuration;

            // Mark region as hidden if the widget is too small to be displayed
            if(widthRatio < (this.properties.widgetMinimumVisibleDuration/100)) {
                regions[region].hideDetails = true;
                break;
            }
        }
    }
};

/**
 * Calculate a mini preview for each region
 * @param {object} regions - Layout regions
 */
Timeline.prototype.calculateRegionPreview = function(regions) {

    // Calculate scale
    const layoutRatio = lD.layout.width / lD.layout.height;
    const containerRatio = regionPreviewContainerDimensions.width / regionPreviewContainerDimensions.height;
    const previewScale = (layoutRatio > containerRatio) ? (regionPreviewContainerDimensions.width / lD.layout.width) : (regionPreviewContainerDimensions.height / lD.layout.height);

    // Scale timeline mini preview container
    const timelinePreviewContainer = {
        width: lD.layout.width * previewScale,
        height: lD.layout.height * previewScale,
        top: regionPreviewContainerDimensions.height / 2 - (lD.layout.height * previewScale) / 2,
        left: regionPreviewContainerDimensions.width / 2 - (lD.layout.width * previewScale) / 2
    };

    // Scale regions
    for(var index in regions) {
        if(regions.hasOwnProperty(index)) {
            let region = regions[index];

            region.timelinePreview = {
                width: parseFloat(region.dimensions.width) * previewScale,
                height: parseFloat(region.dimensions.height) * previewScale,
                top: parseFloat(region.dimensions.top) * previewScale,
                left: parseFloat(region.dimensions.left) * previewScale
            };

            region.timelinePreviewContainer = timelinePreviewContainer;
        }
    }
};

/**
 * Calculate a mini preview for each region
 */
Timeline.prototype.calculateTimeruler = function() {
    let steps = [];
    let totalElements = 0;
    let labelStep = null;
    let labelDelta = null;
    let selectiveRender = false;
    let selectiveRenderStart = 0;
    let selectiveRenderEnd = 0;
    const selectiveRenderOffset = 10; // 10 elements of offset

    // don't show timeline for bigger zooms
    if(this.properties.zoom > this.properties.timelineMaxZoom) {
        this.timeruler = null;
        return null;
    }

    // Calculate step and delta times
    for(let index = 0;index < timeLineLabelMap.length; index++) {
        const element = timeLineLabelMap[index];

        if(this.properties.deltaTime <= element.maxTime) {
            labelStep = element.step;
            labelDelta = element.delta;
            break;
        }
    }

    // If the value is too big, calculate grid based on percentage
    if (labelStep == null || labelDelta == null) {
        labelStep = this.properties.deltaTime / 20;
        labelStep = Math.ceil(labelStep);
        labelDelta = 4;
    }

    // If the number of elements is too high, use selective rendering
    if((lD.layout.duration / labelStep) > 100) {
        selectiveRender = true;
    }

    totalElements = Math.floor(lD.layout.duration / labelStep);
    selectiveRenderStart = (this.properties.minTime - selectiveRenderOffset * labelStep);
    selectiveRenderEnd = (this.properties.maxTime + selectiveRenderOffset * labelStep);
    selectiveRenderStart = (selectiveRenderStart < 0) ? 0 : selectiveRenderStart;
    selectiveRenderEnd = (selectiveRenderEnd > lD.layout.duration) ? lD.layout.duration : selectiveRenderEnd;

    // Calculate the visible steps
    let addElementCount = -1;
    for(let idx = selectiveRenderStart;idx < selectiveRenderEnd;idx = idx + labelStep) {
        addElementCount++;
        
        steps.push({
            labelled: (addElementCount % labelDelta == 0),
            label: lD.common.timeFormat(idx)
        });
    }

    const elementWidth = 100 / totalElements;
    const startElementsNumber = Math.floor(selectiveRenderStart / labelStep);
    const endElementsNumber = totalElements - startElementsNumber + labelStep;

    // Save timeruler object
    this.timeruler = {
        startMargin: elementWidth * startElementsNumber,
        endMargin: elementWidth * endElementsNumber,
        gap: elementWidth,
        steps: steps
    };
};

/**
 * Create widget replicas
 * @param {object} regions - Layout regions
 */
Timeline.prototype.createGhostWidgetsDynamically = function(regions) {

    for(let region in regions) {
        
        let currentRegion = regions[region];

        // if the regions isn't marked for looping, or if does not contain any widget, skip to the next one
        if(!currentRegion.loop || $.isEmptyObject(currentRegion.widgets)) {
            continue;
        }

        let widgetsTotalDuration = 0;
        let ghostWidgetsObject = [];

        // Clear region previous ghosts
        currentRegion.ghostWidgetsObject = [];

        // calculate widgets total duration
        for(let widget in currentRegion.widgets) {
            widgetsTotalDuration += currentRegion.widgets[widget].getTotalDuration();
        }

        // starting and ending time to check/draw ghosts in
        //      get the ghosts drawing starting time, depending on the minimum visualization time and if the widgets are shown on screen after it or not
        const ghostsStartTime = (widgetsTotalDuration > this.properties.minTime) ? widgetsTotalDuration : this.properties.minTime;
        const ghostsEndTime = (this.properties.maxTime > lD.layout.duration) ? lD.layout.duration : this.properties.maxTime;
        
        // distance from the beggining of ghosts and the end of the widgets
        let paddingLeft = 0;

        // if the widgets are shown until the end visualization ( or after ), don't draw any ghosts
        if(widgetsTotalDuration > ghostsEndTime){
            continue;
        }

        // start the auxiliar time just after the widgets
        let auxTime = widgetsTotalDuration;

        // go through auxiliar time, advancing with each widget's time
        while(auxTime < ghostsEndTime) {

            // repeat widget playlist to advance time and create the ghost widgets
            for(let widget in currentRegion.widgets) {

                // if the next widget shows on the time span, add it to the array
                if(auxTime + currentRegion.widgets[widget].getTotalDuration() > ghostsStartTime) {
                    // clone widget to create a ghost
                    let ghost = currentRegion.widgets[widget].createClone();

                    // if the ghost goes after the layout ending, crop it
                    if(auxTime + ghost.duration > lD.layout.duration) {
                        const cropDuration = ghost.duration - ((auxTime + ghost.duration) - lD.layout.duration);
                        ghost.duration = cropDuration;
                    }

                    // Add ghost to the array
                    ghostWidgetsObject.push(ghost);
                } else {                
                    paddingLeft += currentRegion.widgets[widget].getTotalDuration();
                }

                // Advance auxiliar time with the widget duration
                auxTime += currentRegion.widgets[widget].getTotalDuration();

                // if the time has passed the end ghost time, break out from the widget loop
                if(auxTime >= ghostsEndTime){
                    break;
                }
            }
        }

        // flag to see if there's padding
        currentRegion.ghostWidgetsHavePadding = (paddingLeft > 0);
    
        // Calulate padding in percentage ( related to the duration )
        currentRegion.ghostWidgetsPadding = (paddingLeft / lD.layout.duration) * 100;

        // add ghost object array to the region
        currentRegion.ghostWidgetsObject = ghostWidgetsObject;
    }
};

/**
 * Reset zoom to be recalculated on next render
 */
Timeline.prototype.resetZoom = function() {
    this.properties.zoom = -1;
};

/**
 * Move a widget in a region
 * @param {string} regionId - The target region
 * @param {string} widgetId - The widget to be moved
 * @param {string} moveType - "topLeft"; "left"; "right"; "topRight";
 */
Timeline.prototype.moveWidgetInRegion = function(regionId, widgetId, moveType) {

    let getElement = this.DOMObject.find('#' + regionId + ' #' + widgetId);

    switch(moveType) {
        case 'oneRight':
            getElement.insertAfter(getElement.next('.designer-widget:not(.designer-widget-ghost)'));
            break;

        case 'oneLeft':
            getElement.insertBefore(getElement.prev('.designer-widget:not(.designer-widget-ghost)'));
            break;

        case 'topRight':
            getElement.insertAfter(getElement.nextAll('.designer-widget:not(.designer-widget-ghost)').last());
            break;

        case 'topLeft':
            getElement.prependTo(getElement.parent());
            break;

        default:
            console.warn('Change type not known');        
            return;
    }

    // Save new order
    lD.common.showLoadingScreen();

    // Get playlist
    const region = this.DOMObject.find('#' + regionId);
    const playlist = lD.getElementByTypeAndId($(region).data('type'), $(region).attr('id')).playlists;

    // Add sort class
    $(region).addClass('to-sort');

    lD.layout.savePlaylistOrder(playlist, $(region).find('.designer-widget:not(.designer-widget-ghost)')).then((res) => { // Success

        lD.common.hideLoadingScreen();

        // Behavior if successful            
        toastr.success(res.message);
        lD.reloadData(lD.layout);
    }).catch((error) => { // Fail/error

        // Remove sort class
        $(region).removeClass('to-sort');

        lD.common.hideLoadingScreen();

        // Show error returned or custom message to the user
        // Show error returned or custom message to the user
        let errorMessage = '';

        if(typeof error == 'string') {
            errorMessage = error;
        } else {
            errorMessage = error.errorThrown;
        }

        toastr.error(errorMessagesTrans.saveOrderFailed.replace('%error%', errorMessage));
    });
};

/**
 * Render Timeline and the layout
 * @param {Object} layout - the layout object to be rendered
 */
Timeline.prototype.render = function(layout) {

    const app = this.parent;
    const self = this;
    const readOnlyModeOn = (app.readOnlyMode != undefined && app.readOnlyMode === true);

    // If starting zoom is not defined, calculate its value based on minimum widget duration
    if(this.properties.zoom === -1) {
        this.calculateStartingZoom(layout.regions);
    }

    // Reset being sorted flag
    this.beingSorted = false;

    // Calulate time values based on scroll position
    this.calculateTimeValues();
    
    // Calculate region preview
    this.calculateRegionPreview(layout.regions);

    // Calculate time ruler
    this.calculateTimeruler();

    // Check widget repetition and create ghosts
    this.createGhostWidgetsDynamically(layout.regions);

    // Check if we can find widget
    this.properties.zoomFindWidgetDisabled = (app.selectedObject.type != 'widget') ? 'disabled' : '';

    // Render timeline template using layout object
    const html = timelineTemplate({
        layout: layout, 
        properties: this.properties,
        readOnlyModeOn: readOnlyModeOn,
        trans: timelineTrans,
        timeruler: this.timeruler
    });

    // Append layout html to the main div
    this.DOMObject.html(html);

    // Load region container
    const regionsContainer = this.DOMObject.find('#regions-container');

    // Save regions size to guarantee that when the scroll event is called, the region don't reset to 0 ( bugfix )
    this.properties.scrollWidth = regionsContainer.find("#regions").width();

    // Maintain the previous scroll position
    regionsContainer.scrollLeft(this.properties.scrollPosition * regionsContainer.find("#regions").width());
    regionsContainer.scrollTop(this.properties.scrollVerticalPosition);

    // Enable hover and select for each layout/region
    this.DOMObject.find('.selectable:not(.ui-draggable-dragging)').click(function(e) {
        e.stopPropagation();
        lD.selectObject($(this));
    });

    this.DOMObject.find('.designer-region-overlay-step').click(function(e) {
        e.stopPropagation();

        const $parentRegion = $(this).parents('.designer-region');
        const position = parseInt($(this).data('position')) + 1;

        lD.selectObject($parentRegion, false, { positionToAdd: position});
    });

    // Button actions
    this.DOMObject.find('#findSelectedBtn').click(function() {
        if(lD.selectedObject.type == 'widget') {
            self.highlightOnLoad = lD.selectedObject;
            self.scrollOnLoad = lD.selectedObject;
            self.render(lD.layout);
        }
    });

    this.DOMObject.find('#zoomInBtn').click(function() {
        self.changeZoom(1);
        self.render(layout);
    });

    this.DOMObject.find('#zoomOutBtn').click(function() {
        self.changeZoom(-1);
        self.render(layout);
    });

    this.DOMObject.find('#zoomReset').click(function() {
        self.changeZoom(0);
        self.render(layout);
    });

    this.DOMObject.find('.open-playlist-editor').click(function() {
        const playlistId = $(this).parents('.designer-region-info').data('playlistId');
        const regionId = $(this).parents('.designer-region-info').data('region');
        const region = lD.getElementByTypeAndId('region', regionId);
        
        lD.openPlaylistEditor(playlistId, region);
    });

    // Select region to edit
    this.DOMObject.find('.edit-region, .region-preview').click(function() {
        const regionId = $(this).parents('.designer-region-info').data('region');
        lD.toggleNavigatorEditing(true);
        lD.selectObject(self.DOMObject.find('#' + regionId), true);
    });

    this.DOMObject.find('.designer-region').droppable({
        accept: function(el) {
            return ($(this).hasClass('editable') && $(el).attr('drop-to') === 'region') ||
                ($(this).hasClass('permissionsModifiable') && $(el).attr('drop-to') === 'all' && $(el).data('subType') === 'permissions');
        },
        drop: function(event, ui) {
            lD.dropItemAdd(event.target, ui.draggable[0]);
        }
    });

    this.DOMObject.find('.designer-region-overlay-step').droppable({
        greedy: true,
        accept: function(el) {
            const $parentRegion = $(this).parents('.designer-region');

            return ($parentRegion.hasClass('editable') && $(el).attr('drop-to') === 'region') ||
                ($parentRegion.hasClass('permissionsModifiable') && $(el).attr('drop-to') === 'all' && $(el).data('subType') === 'permissions');
        },
        drop: function(event, ui) {
            const $parentRegion = $(event.target).parents('.designer-region');
            const position = parseInt($(event.target).data('position')) + 1;

            lD.dropItemAdd($parentRegion, ui.draggable[0], { positionToAdd: position});
        }
    });

    this.DOMObject.find('.designer-widget').droppable({
        greedy: true,
        accept: function(el) {
            const allowDropToWidget =
                $(this).hasClass("editable") &&
                $(el).attr("drop-to") === "widget";

            const allowAttachedAudioEdit =
                $(el).attr("data-sub-type") != "audio" ||
                ($(el).attr("data-sub-type") === "audio" &&
                    $(this).data("widgetType") != "subplaylist");

            const allowModifyPermissions =
                $(this).hasClass("permissionsModifiable") &&
                $(el).attr("drop-to") === "all" &&
                $(el).data("subType") === "permissions";

            return (
                (allowDropToWidget && allowAttachedAudioEdit) ||
                allowModifyPermissions
            );
        },
        drop: function(event, ui) {
            lD.dropItemAdd(event.target, ui.draggable[0]);
        }
    }).each(function() {

        let $widget = $(this);

        if($widget.outerWidth() < self.properties.widgetContentMinimumVisibleWidth) {
            $widget.addClass('hideContent').tooltip({
                title: timelineTrans.hiddenContentInWidget
            });
        }

        // Highlight marked widgets
        if($widget.data('widgetRegion') != undefined) {
            let widgetObj = lD.getElementByTypeAndId('widget', $widget.attr('id'), $widget.data('widgetRegion'));
            
            if(widgetObj.id == self.highlightOnLoad.id) {
                self.highlightWidget(widgetObj);
            }
        }
    });

    this.DOMObject.find('.designer-widget.editable .editProperty').click(function(e) {
        e.stopPropagation();

        const $element = $(this);
        const $parent = $element.parents('.designer-widget.editable:first');
        const widget = lD.getElementByTypeAndId($parent.data('type'), $parent.attr('id'), $parent.data('widgetRegion'));
        const property = $element.data('property');
        const propertyType = $(this).data('propertyType');

        if(property == 'Action') {
            lD.propertiesPanel.openTabOnRender = 'a[href="#actionTab"]';
            lD.selectObject($parent, true);
        } else {
            widget.editPropertyForm(property, propertyType);
        }
    });
    
    
    if(lD.readOnlyMode === false) {

        this.DOMObject.find('#regions .designer-region.editable').sortable({
            items: '.designer-widget:not(.designer-widget-ghost)',
            placeholder: 'designer-widget-sortable-highlight',
            opacity: '.6',
            axis: 'x', // Restrict movement to X axis
            helper: 'clone',
            start: function(event, ui) {

                // Set sorted flag as true
                self.beingSorted = true;

                // Hide the trash container
                lD.topbar.DOMObject.find('#trashContainer').removeClass('active');

                // Get element width and timeline zoom/scale
                let zoom = self.DOMObject.find('#regions').data('zoom') / 100;
                let elementWidth = $(ui.item).width();

                // set helper new width
                $(ui.helper).width(elementWidth * zoom);
            },
            stop: function() {

                // Reset being sorted flag
                self.beingSorted = false;

                lD.common.showLoadingScreen();

                // Add sort class
                $(this).addClass('to-sort');

                // Get playlist
                const playlist = lD.getElementByTypeAndId($(this).data('type'), $(this).attr('id')).playlists;

                lD.layout.savePlaylistOrder(playlist, $(this).find('.designer-widget:not(.designer-widget-ghost)')).then((res) => { // Success

                    lD.common.hideLoadingScreen();

                    // Behavior if successful            
                    toastr.success(res.message);
                    lD.reloadData(lD.layout);
                }).catch((error) => { // Fail/error

                    // Remove sort class
                    $(this).removeClass('to-sort');
                    
                    lD.common.hideLoadingScreen();

                    // Show error returned or custom message to the user
                    // Show error returned or custom message to the user
                    let errorMessage = '';

                    if(typeof error == 'string') {
                        errorMessage = error;
                    } else {
                        errorMessage = error.errorThrown;
                    }

                    toastr.error(errorMessagesTrans.saveOrderFailed.replace('%error%', errorMessage));
                });
            }
        });

        this.DOMObject.find('.designer-region, .designer-widget:not(.designer-widget-ghost)').contextmenu(function(ev) {
            
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
    
    // When scroll is called ( by scrollbar or .scrollLeft() method calling ), use debounce and process the behaviour
    regionsContainer.scroll(_.debounce(function() {

        // If regions are still not rendered, leave method
        if(self.properties.scrollWidth != $(this).find("#regions").width() || self.beingSorted == true) {
            return;
        }

        // Save vertical scroll position
        self.properties.scrollVerticalPosition = $(this).scrollTop();

        // Get new scroll position ( percentage )
        const newScrollPosition = $(this).scrollLeft() / $(this).find("#regions").width();

        // Render only if the scroll position has been updated ( avoiding looping when calloing .scrollLeft())
        if(self.properties.scrollPosition != newScrollPosition) {
            // Update cached scroll position
            self.properties.scrollPosition = newScrollPosition;

            // Render layout
            self.render(layout);
        }
    }, 500));

    // Scroll to widget on load
    if(!$.isEmptyObject(this.scrollOnLoad)) {
        this.scrollToWidget(this.scrollOnLoad);
    }
};

/**
 * Scroll to widget
 * @param {Object} targetWidget - the target widget object
 */
Timeline.prototype.scrollToWidget = function(targetWidget) {
    // Get region container
    const $regionsContainer = this.DOMObject.find('#regions-container');
    const $targetWidget = $regionsContainer.find('#' + targetWidget.id);
    this.scrollOnLoad = {};

    if($targetWidget.length > 0) {
        //$regionsContainer.scrollLeft($regionsContainer.scrollLeft() - ($regionsContainer.offset().left - $targetWidget.offset().left));
        let calculateScrollToWidget = ($regionsContainer.scrollLeft() - ($regionsContainer.offset().left - $targetWidget.offset().left));

        this.properties.scrollPosition = calculateScrollToWidget / $regionsContainer.find("#regions").width();
        this.render(lD.layout);
    }
};

Timeline.prototype.highlightWidget = _.debounce(function(widget) {
    const widgetDOM = this.DOMObject.find('#' + widget.id);
    const self = this;

    widgetDOM.addClass('highlighted');

    setTimeout(function() {
        self.highlightOnLoad = {};
        widgetDOM.removeClass('highlighted');
    }, widgetHighlightAnimation.deltaTime);
}, widgetHighlightAnimation.delayTime);

/**
 * Scroll to first error widget
 */
Timeline.prototype.scrollToBrokenWidget = function() {
    const regions = Object.values(lD.layout.regions);
    const self = this;
    const app = this.parent;
    
    $.each(regions, function(){
        let breakFlag = true;
        let widgets = Object.values(this.widgets);

        $.each(widgets, function() {
            if(this.isValid == 0) {

                self.highlightOnLoad = this;
                self.scrollOnLoad = this;
                
                app.selectObject($('#' + this.id), true);

                breakFlag = false;
                return false;
            }
        });

        return breakFlag;
    });
};

module.exports = Timeline;