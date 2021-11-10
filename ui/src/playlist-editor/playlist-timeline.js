// TIMELINE Module

// Load templates
const timelineTemplate = require('../templates/playlist-timeline.hbs');

/**
 * Timeline contructor
 * @param {object} container - the container to render the timeline to
 * @param {object =} [options] - Timeline options
 */
let PlaylistTimeline = function(container) {
    this.DOMObject = container;
};

/**
 * Render Timeline and the layout
 */
PlaylistTimeline.prototype.render = function() {

    // Render timeline template
    const html = timelineTemplate(pE.playlist);

    // Append html to the main div
    this.DOMObject.html(html);

    // Enable select for each widget
    this.DOMObject.find('.playlist-widget.selectable').click(function(e) {
        e.stopPropagation();
        if(!$(this).hasClass('to-be-saved')) {
            pE.selectObject($(this));
        }
    });

    this.DOMObject.find('.timeline-overlay-step').droppable({
        greedy: true,
        tolerance: 'pointer',
        accept: '[drop-to="region"]',
        drop: function(event, ui) {
            const position = parseInt($(event.target).data('position')) + 1;

            pE.playlist.addElement(event.target, ui.draggable[0], position);
        }
    });

    this.DOMObject.find('.timeline-overlay-step').click(function(e) {
        if(!$.isEmptyObject(pE.toolbar.selectedCard) || !$.isEmptyObject(pE.toolbar.selectedQueue)) {
            e.stopPropagation();
            const position = parseInt($(this).data('position')) + 1;

            pE.selectObject($(this).parents('#playlist-timeline'), false, {positionToAdd: position});
        }
    });

    this.DOMObject.find('.playlist-widget').droppable({
        greedy: true,
        tolerance: 'pointer',
        accept: function(el) {
            return ($(this).hasClass('editable') && $(el).attr('drop-to') === 'widget') ||
                ($(this).hasClass('permissionsModifiable') && $(el).attr('drop-to') === 'all' && $(el).data('subType') === 'permissions');
        },
        drop: function(event, ui) {
            pE.playlist.addElement(event.target, ui.draggable[0]);
        }
    });

    // Handle widget attached audio click
    this.DOMObject.find('.playlist-widget.editable .editProperty').click(function(e) {
        e.stopPropagation();

        const widget = pE.getElementByTypeAndId($(this).parents('.playlist-widget').data('type'), $(this).parents('.playlist-widget').attr('id'), $(this).parents('.playlist-widget').data('widgetRegion'));

        widget.editPropertyForm($(this).data('property'), $(this).data('propertyType'));
    });

    this.DOMObject.find('.playlist-widget').contextmenu(function(ev) {
        
        if($(ev.currentTarget).is('.editable, .deletable, .permissionsModifiable')) {
            // Open context menu
            pE.openContextMenu(ev.currentTarget, {
                x: ev.pageX,
                y: ev.pageY
            });
        }

        // Prevent browser menu to open
        return false;
    });

    // Save order function with debounce
    var saveOrderFunc = _.debounce(function() {
        pE.saveOrder();
        pE.timeline.DOMObject.find('#unsaved').hide();
        pE.timeline.DOMObject.find('#saved').show();
    }, 1000);

    // Sortable widgets
    this.DOMObject.find('#timeline-container').sortable({
        axis: 'y',
        items: '.playlist-widget',
        start: function(event, ui) {
            pE.timeline.DOMObject.find('#unsaved').hide();
            saveOrderFunc.cancel();
            pE.clearTemporaryData();
        },
        stop: function(event, ui) {
            // Mark target as "to be saved"
            $(ui.item).addClass('to-be-saved');

            pE.timeline.DOMObject.find('#unsaved').show();
            saveOrderFunc();
        }
    });
};

module.exports = PlaylistTimeline;
