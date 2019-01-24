// WIDGET Module

/**
 * Widget contructor
 * @param {number} id - widget id
 * @param {object} data - data from the API request
 * @param {number} regionId - region where the widget belongs ( if exists )
 */
let Widget = function(id, data, regionId = null, layoutObject = null) {
    
    this.widgetId = id;

    if(regionId != null) {
        this.id = 'widget_' + regionId + '_' + id; // widget_regionID_widgetID
        this.regionId = 'region_' + regionId;
    } else {
        this.id = 'widget_' + id; // widget_widgetID
    }

    this.widgetName = data.name;

    this.layoutObject = layoutObject;

    this.isValid = data.isValid;

    // widget type
    this.type = 'widget';
    this.subType = data.type;

    // Permissions
    this.isEditable = data.isEditable;
    this.isDeletable = data.isDeletable;
    this.isPermissionsModifiable = data.isPermissionsModifiable;

    // widget tags
    this.tags = data.tags;

    // Widget colouring
    if(playlistRegionColouring === 'Permissions Colouring') {
        this.widgetColouring = (data.isEditable) ? 'timelineMediaItemColouring_enabled' : 'timelineMediaItemColouring_disabled';
    } else {
        this.widgetColouring = '';

        for(let index = 0;index < this.tags.length; index++) {
            const element = this.tags[index];
            this.widgetColouring += this.tags[index].tag + ' ';
        }
    }
    
    this.selected = false;

    this.singleWidget = false;
    this.loop = false;
    this.extend = false;

    // by default, the widget duration is null ( to be calculated )
    this.duration = null;

    this.widgetDurationNotSet = false;
    this.widgetDefaultDuration = 10; // in the case of the duration has not being calculated

    this.widgetOptions = data.widgetOptions;
    this.calculatedDuration = data.calculatedDuration;

    this.audio = data.audio;

    this.fromDt = data.fromDt;
    this.toDt = data.toDt;

    /**
     * Get transitions from options
     */
    this.transitions = function() {

        let trans = {};
        let widgetDurationInMs = this.getDuration() * 1000;

        if(this.getOptions().transIn) {
            trans.in = {
                name: 'transitionIn',
                type: this.getOptions().transIn,
                duration: this.getOptions().transInDuration,
                percDuration: (this.getOptions().transInDuration != undefined) ? (parseFloat(this.getOptions().transInDuration) / widgetDurationInMs * 100) : 0,
                direction: this.getOptions().transInDirection
            };
        }

        if(this.getOptions().transOut) {
            trans.out = {
                name: 'transitionIn',
                type: this.getOptions().transOut,
                duration: this.getOptions().transOutDuration,
                percDuration: (this.getOptions().transOutDuration != undefined) ? (parseFloat(this.getOptions().transOutDuration) / widgetDurationInMs * 100) : 0,
                direction: this.getOptions().transOutDirection
            };
        }

        return trans;
    };


    /**
     * Return the percentage for the widget on the timeline
     * @returns {number} - Widget duration percentage related to the layout duration
     */
    this.durationPercentage = function() {

        if(this.layoutObject == null) {
            return false;
        }

        // Get duration percentage based on the layout
        const duration = (this.getTotalDuration() / this.layoutObject.duration) * 100;
        
        // If the widget doesn't have the loop flag and is a single widget, extend it
        if(!this.loop && this.singleWidget){
            
            // Verify if the widget duration is less than the layout duration 
            if(parseFloat(this.getTotalDuration()) < parseFloat(this.layoutObject.duration)) {
                this.extend = true;
                this.extendSize = 100 - duration; // Extend size is the rest of the region width
            }
        }

        return duration;
    };

    /**
     * Get an object containing options returned from the back end
     * @returns {object} - Options object
     */
    this.getOptions = function() {

        let options = {};

        for(let option in this.widgetOptions) {
            const currOption = this.widgetOptions[option];

            if(currOption.type === 'attrib'){
                options[currOption.option] = currOption.value;
            }
        }
        
        return options;
    };

    /**
     * Return the value if the widget is selected or not for the CSS
     * @returns {string} - Selected flag, to change widget selection on templates
     */
    this.selectedFlag = function() {
        return (this.selected) ? 'selected-widget' : '';
    };

    /**
     * Get widget calculated duration ( could be differente for some widgets )
     * @param {boolean=} [recalculate = false] - Force the duration to be recalculated
     * @returns {number} - Widget duration in seconds
     * @
     */
    this.getDuration = function(recalculate = false) {

        if(recalculate || this.duration === null){

            let calculatedDuration = parseFloat(this.calculatedDuration);

            // if calculated duration is not calculated, see it to the default duration 
            if(calculatedDuration === 0) {
                calculatedDuration = this.widgetDefaultDuration;
            }
            
            // Fix for negative durations
            if(calculatedDuration <= 0) {
                calculatedDuration = 1;
            }

            // set the duration to the widget
            this.duration = calculatedDuration;
        }

        return this.duration;
    };

    /**
     * Get widget calculated duration with the transition out value if exists
     * @returns {number} - Widget duration in seconds
     * @
     */
    this.getTotalDuration = function() {

        let totalDuration = this.getDuration();

            // Extend with transition out duration if exists
        if(this.getOptions().transOutDuration != undefined) {
            totalDuration += parseFloat(this.getOptions().transOutDuration) / 1000;
        }

        return totalDuration;
    };
};

/**
 * Create clone from widget
 */
Widget.prototype.createClone = function() {
    const self = this;

    const widgetClone = {
        id: 'ghost_' + this.id,
        widgetName: this.widgetName,
        subType: this.subType,
        duration: this.getTotalDuration(),
        regionId: this.regionId,
        durationPercentage: function() { // so that can be calculated on template rendering time
            return (this.duration / self.layoutObject.duration) * 100;
        }
    };

    return widgetClone;
};

/**
 * Edit property form
 *
 * @param {string} property - property to edit
 * @param {object} data - data from the API request
 */
Widget.prototype.editPropertyForm = function(property, type) {

    const self = this;

    const app = getXiboApp();

    // Load form the API
    const linkToAPI = urlsForApi.widget['get' + property];

    let requestPath = linkToAPI.url;

    // Replace type
    requestPath = requestPath.replace(':type', type);

    // Replace widget id
    requestPath = requestPath.replace(':id', this.widgetId);

    // Create dialog
    var calculatedId = new Date().getTime();

    // Create dialog
    let dialog = bootbox.dialog({
        className: 'second-dialog',
        title: 'Load ' + property + ' for widget',
        message: '<p><i class="fa fa-spin fa-spinner"></i> Loading...</p>',
        buttons: {
            cancel: {
                label: translations.cancel,
                className: "btn-default"
            },
            done: {
                label: translations.done,
                className: "btn-primary test",
                callback: function(res) {

                    const form = dialog.find('form');

                    app.common.showLoadingScreen();

                    let dataToSave = '';
                    let options = {
                        addToHistory: false // options.addToHistory
                    };

                    // Get data to save
                    if(property === 'Permissions') {
                        dataToSave = formHelpers.permissionsFormBeforeSubmit(dialog);
                        options.customRequestPath = {
                            url: dialog.find('.permissionsGrid').data('url'),
                            type: 'POST'
                        };
                    } else {
                        dataToSave = form.serialize();
                    }

                    // If there is a type to replace
                    if(type !== undefined) {
                        options.customRequestReplace = {
                            tag: ':type',
                            replace: type
                        };
                    }

                    app.manager.addChange(
                        'save' + property,
                        'widget', // targetType 
                        self.widgetId,  // targetId
                        null,  // oldValues
                        dataToSave, // newValues
                        options
                    ).then((res) => { // Success

                        app.common.hideLoadingScreen();

                        // Behavior if successful 
                        toastr.success(res.message);

                        dialog.modal('hide');

                        app.reloadData(app.layout);

                    }).catch((error) => { // Fail/error

                        app.common.hideLoadingScreen();
                        
                        // Show error returned or custom message to the user
                        let errorMessage = '';

                        if(typeof error == 'string') {
                            errorMessage += error;
                        } else {
                            errorMessage += error.errorThrown;
                        }

                        // Display message in form
                        formHelpers.displayErrorMessage(dialog.find('form'), errorMessage, 'danger');

                        // Show toast message
                        toastr.error(errorMessage);
                    });
                }

            }
        }
    }).attr('id', calculatedId).attr('data-test', 'widgetPropertiesForm');

    // Request and load element form
    $.ajax({
        url: requestPath,
        type: linkToAPI.type
    }).done(function(res) {

        if(res.success) {
            // Add title
            dialog.find('.modal-title').html(res.dialogTitle);

            // Add body main content
            dialog.find('.bootbox-body').html(res.html);

            dialog.data('extra', res.extra);

            if(property === 'Permissions') { 
                formHelpers.permissionsFormAfterOpen(dialog);
            }

            // Call Xibo Init for this form
            XiboInitialise('#' + dialog.attr('id'));

        } else {

            // Login Form needed?
            if(res.login) {
                window.location.href = window.location.href;
                location.reload(false);
            } else {

                toastr.error(property + ' form load failed!');

                // Just an error we dont know about
                if(res.message == undefined) {
                    console.error(res);
                } else {
                    console.error(res.message);
                }

                dialog.modal('hide');
            }
        }

    }).catch(function(jqXHR, textStatus, errorThrown) {

        console.error(jqXHR, textStatus, errorThrown);
        toastr.error(property + ' form load failed!');

        dialog.modal('hide');
    });
};

/**
 * Edit attached audio
 */
Widget.prototype.editAttachedAudio = function() {
    this.editPropertyForm('Audio');
};

/**
 * Edit expiry dates
 */
Widget.prototype.editExpiry = function() {
    this.editPropertyForm('Expiry');
};

/**
 * Edit transitions dates
 * @param {string} type - transition type, in or out
 */
Widget.prototype.editTransition = function(type) {
    this.editPropertyForm('Transition', type);
};

/**
 * Edit permissions
 */
Widget.prototype.editPermissions = function() {
    this.editPropertyForm('Permissions');
};

module.exports = Widget;
