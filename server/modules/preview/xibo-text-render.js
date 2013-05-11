/**
* Xibo - Digital Signage - http://www.xibo.org.uk
* Copyright (C) 2009-2013 Daniel Garner
*
* This file is part of Xibo.
*
* Xibo is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* any later version.
*
* Xibo is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
*/
jQuery.fn.extend({
    xiboRender: function(options) {

        console.log("[Xibo] Render");

        // Default options
        var defaults = {
            "type": "ticker",
            "direction": "single",
            "duration": "50",
            "durationIsPerItem": false,
            "numItems": 0,
            "takeItemsFrom": "start",
            "itemsPerPage": 0,
            "scrollSpeed": "2",
            "scaleMode": "scale",
            "items": []
        };

        var options = $.extend({}, defaults, options);

        // Set the width and height
        options.width = $(window).width();
        options.height = $(window).height();

        // Calculate the scale factor?
        options.scaleFactor = Math.min(options.width / options.originalWidth, options.height / options.originalHeight);

        // For each matched element
        this.each(function() {

            console.log("[Xibo] Selected: " + this.tagName.toLowerCase());
            console.log("[Xibo] Options: " + JSON.stringify(options));

            // Deal with the array of items.
            if (options.type == "ticker") {
                // This is a ticker - expect an array of items that we need to work on.
                console.log("[Xibo] Ticker");
                console.log("[Xibo] There are " + options.items.length + " items.");

                // What source does this data come from?
                if (options.sourceid == undefined) {
                    console.error("Source ID undefined - assuming 1");
                    options.sourceid = 1;                
                }

                console.log("[Xibo] SourceId: " + options.sourceid);

                if (options.sourceid == 1) {
                    // 1st Objective - filter the items array we have been given
                    // settings involved: 
                    //  items, 
                    //  numItems (ticker number of items from the start/end),
                    //  takeItemsFrom (ticker sort or reverse sort the array)
                    if (options.takeItemsFrom == "end") {
                        console.log("[Xibo] Reversing items");
                        options.items.reverse();
                    }

                    // Make sure the num items is not greater than the actual number of items
                    console.log("[Xibo] Module requested " + options.numItems + " there are " + options.items.length + " in the array of items");

                    if (options.numItems > options.items.length || options.numItems == 0)
                        options.numItems = options.items.length;

                    // Get a new array with only the first N elements
                    options.items = options.items.slice(0, options.numItems);

                    // Reverse the items again (so they are in the correct order)
                    if (options.takeItemsFrom == "end") {
                        console.log("[Xibo] Reversing items");
                        options.items.reverse();
                    }
                }
                else {
                    options.numItems = options.items.length;
                }
            }

            // 2nd objective - put the items on the page
            // settings involved:
            //  direction (if we are single we might need to configure some pages for this)
            //  itemsPerPage (tells us how many items to put on per page)
            console.log("[Xibo] Putting " + options.numItems + " Items on the page"); 

            // Store the number of items (we might change this to number of pages)
            var numberOfItems = options.numItems;

            // How many pages to we need?
            var numberOfPages = (options.itemsPerPage > 0) ? Math.ceil(options.numItems / options.itemsPerPage) : options.numItems;
            var itemsThisPage = 1;

            console.log("[Xibo] We need to have " + numberOfPages + " pages");

            var appendTo = this;
            
            // Loop around each of the items we have been given and append them to this element (in a div)
            for (var i = 0; i < options.items.length; i++) {

                // If we need to set pages, have we switched over to a new page?
                if (options.direction == "single" && (options.itemsPerPage > 0 && (itemsThisPage >= options.itemsPerPage || i == 0))) {
                    // Append a new page to the body
                    appendTo = $("<div/>").addClass("page").appendTo(this);

                    // Reset the row count on this page
                    itemsThisPage = 0;
                }

                // For each item output a DIV
                $("<div/>")
                    .addClass("item")
                    .html(options.items[i]).appendTo(appendTo);

                itemsThisPage++;
            }

            // 3rd Objective Scale the entire thing accoring to the scaleMode
            // settings involved:
            //  scaleMode
            if (options.scaleMode == "fit") {

                console.log("[Xibo] Applying jQuery FitText");

                // Remove the font-size property of all children
                $("*", this).css("font-size", "");

                // Run the Fit Text plugin
                $(this)
                    .css({
                        width: options.originalWidth,
                        height: options.originalHeight
                    })
                    .fitText(1.75);
            }
            else if (options.scaleMode == "scale") {
                console.log("[Xibo] Applying CSS ZOOM");

                $(this).css({
                    zoom: options.scaleFactor,
                    width: options.originalWidth,
                    height: options.originalHeight
                });
            }
            
            // 4th objective - move the items around, start the timer
            // settings involved:
            //  direction (the way we are moving effects the HTML required)
            //  scrollSpeed (how fast we need to move)
            //  scaleMode (using CSS zoom speeds up or slows down the movement)
            var marquee = false;

            if (options.direction == "single") {

                // Cycle slides are either page or item
                var slides = (options.itemsPerPage > 0) ? "> .page" : "> .item";
                var numberOfSlides = (options.itemsPerPage > 0) ? numberOfPages : numberOfItems;

                var duration = (options.durationIsPerItem) ? options.duration : options.duration / numberOfSlides;

                console.log("[Xibo] initialising the cycle2 plugin with " + numberOfSlides + " slides and selector " + slides + ". Duration per slide is " + duration + " seconds.");

                // Cycle handles this for us
                $(this).cycle({
                    fx: options.transition,
                    timeout: (duration * 1000),
                    slides: slides
                });
            }
            else if (options.direction == "left" || options.direction == "right") {
                marquee = true;
                
                // Stack the articles up and move them across the screen
                $(' .item', this).css({
                    display: "inline",
                    "padding-left": "4px"
                });

                $(' .item p', this).css({
                    display: "inline"
                });
            }
            else if (options.direction == "up" || options.direction == "down") {
                // We want a marquee
                marquee = true;
            }

            if (marquee) {
                
                // Create a DIV to scroll, and put this inside the body
                var scroller = $("<div/>")
                    .addClass("scroll")
                    .attr({
                        scrollamount: options.scrollSpeed,
                        scaleFactor: options.scaleFactor,
                        behaviour: "scroll",
                        direction: options.direction,
                        height: options.originalHeight,
                        width: options.originalWidth
                    });

                $(this).wrapInner(scroller);

                // Set some options on the extra DIV and make it a marquee
                $(this).find('.scroll').marquee();
            }
        });
    },
    dataSetRender: function(options) {

        // Any options?
        if (options === undefined || options === null) {
            options = {
                duration : 5,
                transition: "fade"
            };
        }

        $(this).each(function() {

            var numberItems = $(this).attr("totalPages");

            // Cycle handles this for us
            $(this).cycle({
                fx: options.transition,
                timeout: (options.duration * 1000) / numberItems,
                slides: '> table'
            });
        });
    }
});

if ( ! window.console ) {

    (function() {
      var names = ["log", "debug", "info", "warn", "error",
          "assert", "dir", "dirxml", "group", "groupEnd", "time",
          "timeEnd", "count", "trace", "profile", "profileEnd"],
          i, l = names.length;

      window.console = {};

      for ( i = 0; i < l; i++ ) {
        window.console[ names[i] ] = function() {};
      }
    }());
}