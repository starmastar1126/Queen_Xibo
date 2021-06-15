var text_callback = function(dialog, extraData) {

    var extra = extraData;

    if (extraData === undefined || extraData === null) {
        extra = $('.bootbox').data().extra;
    }

    // Choose a complementary color
    var color = $c.complement($("#layout").data().backgroundColor);

    // Apply some CSS to set a scale for these editors
    var $layout = $("#layout");
    var scale = $layout.attr('designer_scale');
    var regionWidth = $("#region_" + $layout.data().currentRegionId).attr("width");
    var regionHeight = $("#region_" + $layout.data().currentRegionId).attr("height");
    var applyContentsToIframe = function(field) {
        //console.log('Applying iframe adjustments to ' + field);
        $("#cke_" + field + " iframe").contents().find("head").append("" +
            "<style>" +
            "body {" +
            "width: " + regionWidth + "px; " +
            "height: " + regionHeight + "px; border:2px solid red; " +
            "background: " + $('#layout').css('background-color') + "; " +
            "transform: scale(" + scale + "); " +
            "transform-origin: 0 0; }" +
            "h1, h2, h3, h4, p { margin-top: 0;}" +
            "</style>");
    };

    var applyTemplateContentIfNecessary = function(data, extra) {
        // Check to see if the override template check box is unchecked
        if (!$("#overrideTemplate").is(":checked")) {
            // Get the currently selected templateId
            var templateId = $("#templateId").val();

            // Parse each field
            $.each(extra, function(index, value) {
                if (value.id == templateId) {
                    data = value.template.replace(/#Color#/g, color);
                    $("#ta_css").val(value.css);

                    // Go through each property
                    $.each(value, function (key, value) {

                        if (key != "template" && key != "css") {
                            // Try to match a field
                            $("#" + key).val(value);
                        }
                    });
                }
            });
        }

        return data;
    };

    var convertLibraryReferences = function(data) {
        // We need to convert any library references [123] to their full URL counterparts
        // we leave well alone non-library references.
        var regex = /\[[0-9]+]/gi;

        data = data.replace(regex, function (match) {
            var inner = match.replace("]", "").replace("[", "");
            return CKEDITOR_DEFAULT_CONFIG.imageDownloadUrl.replace(":id", inner);
        });

        return data;
    };

    // Conjure up a text editor
    CKEDITOR.replace("ta_text", CKEDITOR_DEFAULT_CONFIG);

    // Bind to instance ready so that we can adjust some things about the editor.
    CKEDITOR.instances["ta_text"].on('instanceReady', function() {
        // Apply scaling to this editor instance
        applyContentsToIframe("ta_text");

        // Reapply the background style after switching to source view and back to the normal editing view
        CKEDITOR.instances["ta_text"].on('contentDom', function () { applyContentsToIframe("ta_text") });

        // Get the template data
        var data = CKEDITOR.instances["ta_text"].getData();

        // Default config for fonts
        if (data == "" && !$("#overrideTemplate").is(":checked")) {
            data = "<span style=\"font-size: 48px;\"><span style=\"color: " + color + ";\">" + translations.enterText + "</span></span>";
        }

        // Handle initial template set up
        data = applyTemplateContentIfNecessary(data, extra);
        data = convertLibraryReferences(data);

        CKEDITOR.instances["ta_text"].setData(data);
    });

    // Register an onchange listener to manipulate the template content if the selector is changed.
    $("#templateId").on('change', function() {
        CKEDITOR.instances["ta_text"].setData(applyTemplateContentIfNecessary(CKEDITOR.instances["ta_text"].getData(), extra));
    });

    // Create a no data message editor if one is present
    if ($("#noDataMessage").length > 0) {
        CKEDITOR.replace("noDataMessage", CKEDITOR_DEFAULT_CONFIG);
        CKEDITOR.instances["noDataMessage"].on('instanceReady', function () {
            // Apply scaling to this editor instance
            applyContentsToIframe("noDataMessage");

            // Reapply the background style after switching to source view and back to the normal editing view
            CKEDITOR.instances["noDataMessage"].on('contentDom', function () { applyContentsToIframe("noDataMessage") });

            // Get the template data
            var data = CKEDITOR.instances["noDataMessage"].getData();
            if (data === "") {
                data = "<span style=\"font-size: 48px;\"><span style=\"color: " + color + ";\">" + translations.noDataMessage + "</span></span>";
            }

            // Handle initial template set up
            data = convertLibraryReferences(data);

            CKEDITOR.instances["noDataMessage"].setData(data);
        });
    }

    // Make sure when we close the dialog we also destroy the editor
    dialog.on("hide.bs.modal", function(e) {
        if(e.namespace === 'bs.modal') {
            try {
                if (CKEDITOR.instances["ta_text"] !== undefined) {
                    CKEDITOR.instances["ta_text"].destroy();
                }

                if (CKEDITOR.instances["noDataMessage"] !== undefined) {
                    CKEDITOR.instances["noDataMessage"].destroy();
                }
            } catch (e) {
                console.log("Unable to remove CKEditor instance. " + e);
            }

            // Remove colour picker
            destroyColorPicker('#backgroundColor');
        }
    });

    // Do we have any items to click on that we might want to insert? (these will be our items and not CKEditor ones)
    $('.ckeditor_snippits', dialog).dblclick(function(){
        // Linked to?
        var linkedTo = $(this).attr("linkedto");
        var text;

        if (CKEDITOR.instances[linkedTo] != undefined) {
            if ($(this).attr("datasetcolumnid") != undefined)
                text = "[" + $(this).html() + "|" + $(this).attr("datasetcolumnid") + "]";
            else
                text = "[" + $(this).html() + "]";

            CKEDITOR.instances[linkedTo].insertText(text);
        }

        return false;
    });

    // Do we have a media selector?
    var $selectPicker = $(".ckeditor_library_select");
    if ($selectPicker.length > 0) {
        $selectPicker.select2({
            ajax: {
                url: $selectPicker.data().searchUrl,
                dataType: "json",
                data: function(params) {
                    var queryText = params.term;
                    var queryTags = '';

                    // Tags
                    if(params.term != undefined) {
                        var tags = params.term.match(/\[([^}]+)\]/);
                        if(tags != null) {
                            // Add tags to search
                            queryTags = tags[1];

                            // Replace tags in the query text
                            queryText = params.term.replace(tags[0], '');
                        }

                        // Remove whitespaces and split by comma
                        queryText = queryText.replace(' ', '');
                        queryTags = queryTags.replace(' ', '');
                    }

                    var query = {
                        media: queryText,
                        tags: queryTags,
                        type: "image",
                        retired: 0,
                        assignable: 1,
                        start: 0,
                        length: 10
                    };

                    // Set the start parameter based on the page number
                    if (params.page != null) {
                        query.start = (params.page - 1) * 10;
                    }

                    // Find out what is inside the search box for this list, and save it (so we can replay it when the list
                    // is opened again)
                    if (params.term !== undefined) {
                        localStorage.liveSearchPlaceholder = params.term;
                    }

                    return query;
                },
                processResults: function(data, params) {
                    var results = [];

                    $.each(data.data, function(index, element) {
                        results.push({
                            "id": element.mediaId,
                            "text": element.name,
                            'imageUrl': $selectPicker.data().imageUrl.replace(':id', element.mediaId),
                            'disabled': false
                        });
                    });

                    var page = params.page || 1;
                    page = (page > 1) ? page - 1 : page;

                    return {
                        results: results,
                        pagination: {
                            more: (page * 10 < data.recordsTotal)
                        }
                    }
                },
                delay: 250
            },
            dropdownParent: $(dialog)
        }).on('select2:select', function (e) {
                var linkedTo = $(this).data().linkedTo;
                var value = e.params.data.imageUrl;

                console.log('Value is ' + value + ", linked control is " + linkedTo);

                if (value !== undefined && value !== "" && linkedTo != null) {
                    if (CKEDITOR.instances[linkedTo] != undefined) {
                        CKEDITOR.instances[linkedTo].insertHtml("<img src=\"" + value + "\" />");
                    }
                }
            });
    }

    // Turn the background colour into a picker
    createColorPicker('#backgroundColor');

    return false;
};

/**
 * Switches an item between 2 connected lists.
 */
function switchLists(e) {
   // determine which list they are in
   // http://www.remotesynthesis.com/post.cfm/working-with-related-sortable-lists-in-jquery-ui
   var otherList = $($(e.currentTarget).parent().sortable("option","connectWith")).not($(e.currentTarget).parent());

   otherList.append(e.currentTarget);
}

function GroupSecurityCallBack(dialog)
{
    $("#groupsIn, #groupsOut").sortable({
        connectWith: '.connectedSortable',
        dropOnEmpty: true
    }).disableSelection();

    $(".li-sortable", dialog).dblclick(switchLists);
}

function GroupSecuritySubmit() {
    // Serialise the form and then submit it via Ajax.
    var href = $("#groupsIn").attr('href') + "&ajax=true";
    
    // Get the two lists        
    serializedData = $("#groupsIn").sortable('serialize');
    
    $.ajax({
        type: "post",
        url: href,
        cache: false,
        dataType: "json",
        data: serializedData,
        success: XiboSubmitResponse
    });
    
    return;
}

function DisplayGroupManageMembersCallBack(dialog)
{
    $("#displaysIn, #displaysOut").sortable({
        connectWith: '.connectedSortable',
        dropOnEmpty: true
    }).disableSelection();

    $(".li-sortable", dialog).dblclick(switchLists);
}

function DisplayGroupMembersSubmit() {
    // Serialise the form and then submit it via Ajax.
    var href = $("#displaysIn").attr('href') + "&ajax=true";

    // Get the two lists
    serializedData = $("#displaysIn").sortable('serialize');

    $.ajax({
        type: "post",
        url: href,
        cache: false,
        dataType: "json",
        data: serializedData,
        success: XiboSubmitResponse
    });

    return;
}

/**
 * Library Assignment Form Callback
 */
var FileAssociationsCallback = function()
{
    // Attach a click handler to all of the little pointers in the grid.
    $("#FileAssociationsTable .library_assign_list_select").click(function(){
        // Get the row that this is in.
        var row = $(this).parent().parent();

        // Construct a new list item for the lower list and append it.
        var newItem = $("<li/>", {
            text: row.attr("litext"),
            id: row.attr("rowid"),
            "class": "li-sortable",
            dblclick: function(){
                $(this).remove();
            }
        });

        newItem.appendTo("#FileAssociationsSortable");

        // Add a span to that new item
        $("<span/>", {
            "class": "fa fa-minus",
            click: function(){
                $(this).parent().remove();
                $(".modal-body .XiboGrid").each(function(){

                    var gridId = $(this).attr("id");

                    // Render
                    XiboGridRender(gridId);
                });
            }
        })
        .appendTo(newItem);

        // Remove the row
        row.remove();
    });

    // Attach a click handler to all of the little points in the trough
    $("#FileAssociationsSortable li .fa-minus").click(function() {

        // Remove this and refresh the table
        $(this).parent().remove();

    });

    $("#FileAssociationsSortable").sortable().disableSelection();
};

var FileAssociationsSubmit = function(displayGroupId)
{
    // Serialize the data from the form and call submit
    var mediaList = $("#FileAssociationsSortable").sortable('serialize');

    $.ajax({
        type: "post",
        url: "index.php?p=displaygroup&q=SetFileAssociations&displaygroupid="+displayGroupId+"&ajax=true",
        cache: false,
        dataType: "json",
        data: mediaList,
        success: XiboSubmitResponse
    });
};

var settingsUpdated = function(response) {
    if (!response.success) {
        SystemMessage((response.message == "") ? translation.failure : response.message, true);
    }
};

function permissionsFormOpen(dialog) {

    var grid = $("#permissionsTable").closest(".XiboGrid");

    // initialise the permissions array
    if (grid.data().permissions.length <= 0)
        grid.data().permissions = {};

    var table = $("#permissionsTable").DataTable({ "language": dataTablesLanguage,
        serverSide: true, stateSave: true,
        "filter": false,
        searchDelay: 3000,
        "order": [[ 0, "asc"]],
        ajax: {
            url: grid.data().url,
            "data": function(d) {
                $.extend(d, grid.find(".permissionsTableFilter form").serializeObject());
            }
        },
        "columns": [
            {
                "data": "group",
                "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    if (row.isUser == 1)
                        return data;
                    else
                        return '<strong>' + data + '</strong>';
                }
            },
            { "data": "view", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in grid.data().permissions) {
                        var cache = grid.data().permissions[row.groupId];

                        checked = (cache.view !== undefined && cache.view === 1) ? 1 : 0;
                    } else {
                        checked = data;
                    }

                    // Cached changes to this field?
                    return "<input type=\"checkbox\" data-permission=\"view\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            },
            { "data": "edit", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in grid.data().permissions) {
                        var cache = grid.data().permissions[row.groupId];

                        checked = (cache.edit !== undefined && cache.edit === 1) ? 1 : 0;
                    } else {
                        checked = data;
                    }

                    return "<input type=\"checkbox\" data-permission=\"edit\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            },
            { "data": "delete", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in grid.data().permissions) {
                        var cache = grid.data().permissions[row.groupId];

                        checked = (cache.delete !== undefined && cache.delete === 1) ? 1 : 0;
                    } else {
                        checked = data;
                    }

                    return "<input type=\"checkbox\" data-permission=\"delete\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            }
        ]
    });

    table.on('draw', function (e, settings) {
        dataTableDraw(e, settings);

        // Bind to the checkboxes change event
        var target = $("#" + e.target.id);
        target.find("input[type=checkbox]").change(function() {
            // Update our global permissions data with this
            var groupId = $(this).data().groupId;
            var permission = $(this).data().permission;
            var value = $(this).is(":checked");
            //console.log("Setting permissions on groupId: " + groupId + ". Permission " + permission + ". Value: " + value);
            if (grid.data().permissions[groupId] === undefined) {
                grid.data().permissions[groupId] = {};
            }
            grid.data().permissions[groupId][permission] = (value) ? 1 : 0;
        });
    });
    table.on('processing.dt', dataTableProcessing);

    // Bind our filter
    grid.find(".permissionsTableFilter form input, .permissionsTableFilter form select").change(function() {
        table.ajax.reload();
    });
}

function permissionsFormSubmit(id) {

    var form = $("#" + id);
    var $formContainer = form.closest(".permissions-form");
    var permissions = {
        "groupIds": $(form).data().permissions,
        "ownerId": $formContainer.find("select[name=ownerId]").val()
    };
    var data = $.param(permissions);

    $.ajax({
        type: "POST",
        url: form.data().url,
        cache: false,
        dataType: "json",
        data: data,
        success: function(xhr, textStatus, error) {
            XiboSubmitResponse(xhr, form);
        },
        error: function(xhr, textStatus, errorThrown) {
            SystemMessage(xhr.responseText, false);
        }
    });
}

function permissionsMultiFormOpen(dialog) {
    var $permissionsTable = $(dialog).find("#permissionsMultiTable");
    var $grid = $permissionsTable.closest(".XiboGrid");

    var table = $permissionsTable.DataTable({ "language": dataTablesLanguage,
        serverSide: true, 
        stateSave: true,
        "filter": false,
        searchDelay: 3000,
        "order": [[ 0, "asc"]],
        ajax: {
            url: $grid.data().url,
            "data": function(d) {
                $.extend(d, $grid.find(".permissionsMultiTableFilter form").serializeObject());

                $.extend(d, {
                    ids: $grid.data().targetIds
                });
            },
            "dataSrc": function(json) {
                var newData = json.data;

                for (var dataKey in newData) {
                    if (newData.hasOwnProperty(dataKey)) {
                        var permissionGrouped = {
                            "view": null,
                            "edit": null,
                            "delete": null
                        }

                        for (var key in newData[dataKey].permissions) {
                            if (newData[dataKey].permissions.hasOwnProperty(key)) {
                                var permission = newData[dataKey].permissions[key];

                                if(permission.view != permissionGrouped.view) {
                                    if(permissionGrouped.view != null) {
                                        permissionGrouped.view = 2;
                                    } else {
                                        permissionGrouped.view = permission.view;
                                    }
                                }

                                if(permission.edit != permissionGrouped.edit) {
                                    if(permissionGrouped.edit != null) {
                                        permissionGrouped.edit = 2;
                                    } else {
                                        permissionGrouped.edit = permission.edit;
                                    }
                                }

                                if(permission.delete != permissionGrouped.delete) {
                                    if(permissionGrouped.delete != null) {
                                        permissionGrouped.delete = 2;
                                    } else {
                                        permissionGrouped.delete = permission.delete;
                                    }
                                }
                            }
                        }

                        newData[dataKey] = Object.assign(permissionGrouped, newData[dataKey]);
                        delete newData[dataKey].permissions;
                    }
                }

                // initialise the permission and start permissions arrays
                if ($grid.data().permissions == undefined) {
                    $grid.data().permissions = newData;
                }

                if ($grid.data().startPermissions == undefined) {
                    $grid.data().startPermissions = JSON.parse(JSON.stringify(newData));
                }

                if ($grid.data().savePermissions == undefined) {
                    $grid.data().savePermissions = {};
                }

                // Return an array of permissions
                return Object.values(newData);
            }
        },
        "columns": [
            {
                "data": "group",
                "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    if (row.isUser == 1)
                        return data;
                    else
                        return '<strong>' + data + '</strong>';
                }
            },
            { "data": "view", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in $grid.data().permissions) {
                        var cache = $grid.data().permissions[row.groupId];

                        checked = (cache.view !== undefined && cache.view !== 0) ? cache.view : 0;
                    } else {
                        checked = data;
                    }

                    // Cached changes to this field?
                    return "<input type=\"checkbox\" class=\"" + ((checked === 2) ? "indeterminate" : "") + "\" data-permission=\"view\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            },
            { "data": "edit", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in $grid.data().permissions) {
                        var cache = $grid.data().permissions[row.groupId];

                        checked = (cache.edit !== undefined && cache.edit !== 0) ? cache.edit : 0;
                    } else {
                        checked = data;
                    }

                    return "<input type=\"checkbox\" class=\"" + ((checked === 2) ? "indeterminate" : "") + "\" data-permission=\"edit\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            },
            { "data": "delete", "render": function (data, type, row, meta) {
                    if (type != "display")
                        return data;

                    var checked;
                    if (row.groupId in $grid.data().permissions) {
                        var cache = $grid.data().permissions[row.groupId];

                        checked = (cache.delete !== undefined && cache.delete !== 0) ? cache.delete : 0;
                    } else {
                        checked = data;
                    }

                    return "<input type=\"checkbox\" class=\"" + ((checked === 2) ? "indeterminate" : "") + "\" data-permission=\"delete\" data-group-id=\"" + row.groupId + "\" " + ((checked === 1) ? "checked" : "") + " />";
                }
            }
        ]
    });

    table.on('draw', function (e, settings) {
        dataTableDraw(e, settings);

        // Bind to the checkboxes change event
        var target = $("#" + e.target.id);
        target.find("input[type=checkbox]").change(function() {
            // Update our global permissions data with this
            var groupId = $(this).data().groupId;
            var permission = $(this).data().permission;
            var value = $(this).is(":checked");
            var valueNumeric = (value) ? 1 : 0;

            //console.log("Setting permissions on groupId: " + groupId + ". Permission " + permission + ". Value: " + value);
            // Update main permission object
            if ($grid.data().permissions[groupId] === undefined) {
                $grid.data().permissions[groupId] = {};
            }
            $grid.data().permissions[groupId][permission] = valueNumeric;

            // Update save permissions object
            if($grid.data().savePermissions[groupId] === undefined) {
                $grid.data().savePermissions[groupId] = {};
                $grid.data().savePermissions[groupId][permission] = valueNumeric;
            } else {
                if($grid.data().startPermissions[groupId][permission] === valueNumeric) {
                    // if changed value is the same as the initial permission object, remove it from the save permissions object
                    delete $grid.data().savePermissions[groupId][permission];

                    // Remove group if it's an empty object
                    if($.isEmptyObject($grid.data().savePermissions[groupId])) {
                        delete $grid.data().savePermissions[groupId]; 
                    }
                } else {
                    // Add new change to the save permissions object
                    $grid.data().savePermissions[groupId][permission] = valueNumeric;
                }
            }

            // Enable save button only if we have permission changes to save
            $(dialog).find('.save-button').toggleClass('disabled', $.isEmptyObject($grid.data().savePermissions));
        });

        // Mark indeterminate checkboxes and add title
        target.find('input[type=checkbox].indeterminate').prop('indeterminate', true).prop('title', translations.indeterminate);
    });

    // Disable save button by default
    $(dialog).find('.save-button').addClass('disabled');

    table.on('processing.dt', dataTableProcessing);

    // Bind our filter
    $grid.find(".permissionsMultiTableFilter form input, .permissionsMultiTableFilter form select").change(function() {
        table.ajax.reload();
    });
}

function permissionsMultiFormSubmit(id) {
    var form = $("#" + id);
    var permissions = $(form).data().savePermissions;
    var targetIds = $(form).data().targetIds;
    
    var data = $.param({
        groupIds: permissions,
        ids: targetIds
    });
    
    $.ajax({
        type: "POST",
        url: form.data().url,
        cache: false,
        dataType: "json",
        data: data,
        success: function(xhr, textStatus, error) {
            XiboSubmitResponse(xhr, form);
        },
        error: function(xhr, textStatus, errorThrown) {
            SystemMessage(xhr.responseText, false);
        }
    });
}

function membersFormOpen(dialog) {

    // Get our table
    var table = $(dialog).find(".membersTable");

    if (table.data().members == undefined)
        table.data().members = {};

    // Bind to the checkboxes change event
    table.find("input[type=checkbox]").change(function() {
        // Update our global members data with this
        var memberId = $(this).data().memberId;
        var value = $(this).is(":checked");

        //console.log("Setting memberId: " + memberId + ". Value: " + value);

        table.data().members[memberId] = (value) ? 1 : 0;
    });
}

function membersFormSubmit(id) {

    var form = $("#" + id);
    var members = form.find(".membersTable").data().members;

    // There may not have been any changes
    if (members == undefined) {
        // No changes
        XiboDialogClose();
        return;
    }

    // Build an array of id's to assign and an array to unassign
    var assign = [];
    var unassign = [];

    $.each(members, function(name, value) {
        if (value == 1)
            assign.push(name);
        else
            unassign.push(name);
    });

    var error = false;
    var data = {};
    data[form.data().param] = assign;
    data[form.data().paramUnassign] = unassign;

    $.ajax({
        type: "POST",
        url: form.data().url,
        cache: false,
        dataType: "json",
        data: $.param(data),
        success: function(xhr, textStatus, error) {
            XiboSubmitResponse(xhr, form);
        },
        error: function(xhr, textStatus, errorThrown) {
            SystemMessage(xhr.responseText, false);
        }
    });
}

// Callback for the media form
function mediaDisplayGroupFormCallBack() {

    var container = $("#FileAssociationsAssign");
    if (container.data().media == undefined)
        container.data().media = {};

    var mediaTable = $("#mediaAssignments").DataTable({ "language": dataTablesLanguage,
            serverSide: true, stateSave: true,
            searchDelay: 3000,
            "order": [[ 0, "asc"]],
            "filter": false,
            ajax: {
                "url": $("#mediaAssignments").data().url,
            "data": function(d) {
                $.extend(d, $("#mediaAssignments").closest(".XiboGrid").find(".FilterDiv form").serializeObject());
            }
        },
        "columns": [
            { "data": "name" },
            { "data": "mediaType" },
            {
                "sortable": false,
                "data": function(data, type, row, meta) {
                    if (type != "display")
                        return "";

                    // Create a click-able span
                    return "<a href=\"#\" class=\"assignItem\"><span class=\"fa fa-plus\"></a>";
                }
            }
        ]
    });

    mediaTable.on('draw', function (e, settings) {
        dataTableDraw(e, settings);

        // Clicky on the +spans
        $(".assignItem", "#mediaAssignments").click(function() {
            // Get the row that this is in.
            var data = mediaTable.row($(this).closest("tr")).data();

            // Append to our media list
            container.data().media[data.mediaId] = 1;

            // Construct a new list item for the lower list and append it.
            var newItem = $("<li/>", {
                "text": data.name,
                "data-media-id": data.mediaId,
                "class": "btn btn-sm btn-white"
            });

            newItem.appendTo("#FileAssociationsSortable");

            // Add a span to that new item
            $("<span/>", {
                "class": "fa fa-minus",
                click: function(){
                    container.data().media[$(this).parent().data().mediaId] = 0;
                    $(this).parent().remove();
                }
            }).appendTo(newItem);
        });
    });
    mediaTable.on('processing.dt', dataTableProcessing);

    // Make our little list sortable
    $("#FileAssociationsSortable").sortable();

    // Bind to the existing items in the list
    $("#FileAssociationsSortable").find('li span').click(function () {
        container.data().media[$(this).parent().data().mediaId] = 0;
        $(this).parent().remove();
    });

    // Bind to the filter
    $("#mediaAssignments").closest(".XiboGrid").find(".FilterDiv input, .FilterDiv select").change(function() {
        mediaTable.ajax.reload();
    });
}

function mediaAssignSubmit() {
    // Collect our media
    var container = $("#FileAssociationsAssign");

    // Build an array of id's to assign and an array to unassign
    var assign = [];
    var unassign = [];

    $.each(container.data().media, function(name, value) {
        if (value == 1)
            assign.push(name);
        else
            unassign.push(name);
    });

    assignMediaToCampaign(container.data().url, assign, unassign);
}

var assignMediaToCampaign = function(url, media, unassignMedia) {
    toastr.info("Assign Media", media);

    $.ajax({
        type: "post",
        url: url,
        cache: false,
        dataType: "json",
        data: {mediaId: media, unassignMediaId: unassignMedia},
        success: XiboSubmitResponse
    });
};

// Callback for the media form
function layoutFormCallBack() {

    var container = $("#FileAssociationsAssign");
    if (container.data().layout == undefined)
        container.data().layout = {};

    var layoutTable = $("#layoutAssignments").DataTable({ "language": dataTablesLanguage,
        serverSide: true, stateSave: true,
        searchDelay: 3000,
        "order": [[ 0, "asc"]],
        "filter": false,
        ajax: {
            "url": $("#layoutAssignments").data().url,
            "data": function(d) {
                $.extend(d, $("#layoutAssignments").closest(".XiboGrid").find(".FilterDiv form").serializeObject());
            }
        },
        "columns": [
            { "data": "layout" },
            {
                "sortable": false,
                "data": function(data, type, row, meta) {
                    if (type != "display")
                        return "";

                    // Create a click-able span
                    return "<a href=\"#\" class=\"assignItem\"><span class=\"fa fa-plus\"></a>";
                }
            }
        ]
    });

    layoutTable.on('draw', function (e, settings) {
        dataTableDraw(e, settings);

        // Clicky on the +spans
        $(".assignItem", "#layoutAssignments").click(function() {
            // Get the row that this is in.
            var data = layoutTable.row($(this).closest("tr")).data();

            // Append to our layout list
            container.data().layout[data.layoutId] = 1;

            // Construct a new list item for the lower list and append it.
            var newItem = $("<li/>", {
                "text": data.layout,
                "data-layout-id": data.layoutId,
                "class": "btn btn-sm btn-white"
            });

            newItem.appendTo("#FileAssociationsSortable");

            // Add a span to that new item
            $("<span/>", {
                "class": "fa fa-minus",
                click: function(){
                    container.data().layout[$(this).parent().data().layoutId] = 0;
                    $(this).parent().remove();
                }
            }).appendTo(newItem);
        });
    });
    layoutTable.on('processing.dt', dataTableProcessing);

    // Make our little list sortable
    $("#FileAssociationsSortable").sortable();

    // Bind to the existing items in the list
    $("#FileAssociationsSortable").find('li span').click(function () {
        container.data().layout[$(this).parent().data().layoutId] = 0;
        $(this).parent().remove();
    });

    // Bind to the filter
    $("#layoutAssignments").closest(".XiboGrid").find(".FilterDiv input, .FilterDiv select").change(function() {
        layoutTable.ajax.reload();
    });
}

function layoutAssignSubmit() {
    // Collect our layout
    var container = $("#FileAssociationsAssign");

    // Build an array of id's to assign and an array to unassign
    var assign = [];
    var unassign = [];

    $.each(container.data().layout, function(name, value) {
        if (value == 1)
            assign.push(name);
        else
            unassign.push(name);
    });

    assignLayoutToCampaign(container.data().url, assign, unassign);
}

var assignLayoutToCampaign = function(url, layout, unassignLayout) {
    toastr.info("Assign Layout", layout);

    $.ajax({
        type: "post",
        url: url,
        cache: false,
        dataType: "json",
        data: {layoutId: layout, unassignLayoutId: unassignLayout},
        success: XiboSubmitResponse
    });
};

function regionEditFormSubmit() {
    XiboFormSubmit($("#regionEditForm"), null, function(xhr, form) {

        if (xhr.success)
            window.location.reload();
    });
}

function userProfileEditFormOpen() {

    $("#qRCode").addClass("d-none");
    $("#recoveryButtons").addClass("d-none");
    $("#recoveryCodes").addClass("d-none");

    $("#twoFactorTypeId").on("change", function (e) {
        e.preventDefault();
        if ($("#twoFactorTypeId").val() == 2 && $('#userEditProfileForm').data().currentuser != 2) {
            $.ajax({
                url: $('#userEditProfileForm').data().setup,
                type: "GET",
                beforeSend: function () {
                    $("#qr").addClass('fa fa-spinner fa-spin loading-icon')
                },
                success: function (response) {
                    let qRCode = response.data.qRUrl;
                    $("#qrImage").attr("src", qRCode);
                },
                complete: function () {
                    $("#qr").removeClass('fa fa-spinner fa-spin loading-icon')
                }
            });
            $("#qRCode").removeClass("d-none");
        } else {
            $("#qRCode").addClass("d-none");
        }

        if ($("#twoFactorTypeId").val() == 0) {
            $("#recoveryButtons").addClass("d-none");
            $("#recoveryCodes").addClass("d-none");
        }

        if ($('#userEditProfileForm').data().currentuser != 0 && $("#twoFactorTypeId").val() != 0) {
            $("#recoveryButtons").removeClass("d-none");
        }
    });

    if ($('#userEditProfileForm').data().currentuser != 0) {
        $("#recoveryButtons").removeClass("d-none");
    }
    let generatedCodes = '';

    $('#generateCodesBtn').on("click", function (e) {
        $("#codesList").html("");
        $("#recoveryCodes").removeClass('d-none');
        $(".recBtn").attr("disabled", true).addClass("disabled");
        generatedCodes = '';

        $.ajax({
            url: $('#userEditProfileForm').data().generate,
            async: false,
            type: "GET",
            beforeSend: function () {
                $("#codesList").removeClass('card').addClass('fa fa-spinner fa-spin loading-icon');
            },
            success: function (response) {
                generatedCodes = JSON.parse(response.data.codes);
                $("#recoveryCodes").addClass('d-none');
                $(".recBtn").attr("disabled", false).removeClass("disabled");
                $('#showCodesBtn').click();
            },
            complete: function () {
                $("#codesList").removeClass('fa fa-spinner fa-spin loading-icon');
            }
        });
    });

    $('#showCodesBtn').on("click", function (e) {
        $(".recBtn").attr("disabled", true).addClass("disabled");
        $("#codesList").html("");
        $("#recoveryCodes").toggleClass('d-none');
        let codesList = [];

        $.ajax({
            url: $('#userEditProfileForm').data().show,
            type: "GET",
            data: {
                generatedCodes: generatedCodes,
            },
            success: function (response) {
                if (generatedCodes != '') {
                    codesList = generatedCodes;
                } else {
                    codesList = response.data.codes;
                }

                $('#twoFactorRecoveryCodes').val(JSON.stringify(codesList));
                $.each(codesList, function (index, value) {
                    $("#codesList").append(value + "<br/>");
                });
                $("#codesList").addClass('card');
                $(".recBtn").attr("disabled", false).removeClass("disabled");
            }
        });
    });
}

function tagsWithValues(formId) {
    $('#tagValue, label[for="tagValue"], #tagValueRequired').addClass("d-none");
    $('#tagValueContainer').hide();

    let tag;
    let tagWithOption = '';
    let tagN = '';
    let tagV = '';
    let tagOptions = [];
    let tagIsRequired = 0;

    let formSelector = '#' + formId + ' input#tags' + ', #' + formId + ' input#tagsToAdd';

    $(formSelector).on('beforeItemAdd', function(event) {
        $('#tagValue').html('');
        $('#tagValueInput').val('');
        tag = event.item;
        tagOptions = [];
        tagIsRequired = 0;
        tagN = tag.split('|')[0];
        tagV = tag.split('|')[1];

        if ($(formSelector).val().indexOf(tagN) === -1 && tagV === undefined) {
            $.ajax({
                url: $('form#'+formId).data().gettag,
                type: "GET",
                data: {
                    name: tagN,
                },
                beforeSend: function () {
                    $("#loadingValues").addClass('fa fa-spinner fa-spin loading-icon')
                },
                success: function (response) {
                    if (response.success) {
                        if (response.data.tag != null) {
                            tagOptions = JSON.parse(response.data.tag.options);
                            tagIsRequired = response.data.tag.isRequired;

                            if (tagOptions != null && tagOptions != []) {
                                $('#tagValue, label[for="tagValue"]').removeClass("d-none");

                                if ($('#tagValue option[value=""]').length <= 0) {
                                    $('#tagValue')
                                        .append($("<option></option>")
                                            .attr("value", '')
                                            .text(''));
                                }

                                $.each(tagOptions, function (key, value) {
                                    if ($('#tagValue option[value='+value+']').length <= 0) {
                                        $('#tagValue')
                                            .append($("<option></option>")
                                                .attr("value", value)
                                                .text(value));
                                    }
                                });

                                $('#tagValue').focus();
                            } else {
                                // existing Tag without specified options (values)
                                $('#tagValueContainer').show();

                                // if the isRequired flag is set to 0 change the helpText to be more user friendly.
                                if (tagIsRequired === 0) {
                                    $('#tagValueInput').parent().find('span.help-block').text(translations.tagInputValueHelpText)
                                } else {
                                    $('#tagValueInput').parent().find('span.help-block').text(translations.tagInputValueRequiredHelpText)
                                }

                                $('#tagValueInput').focus();
                            }
                        } else {
                            // new Tag
                            $('#tagValueContainer').show();
                            $('#tagValueInput').focus();

                            // isRequired flag is set to 0 (new Tag) change the helpText to be more user friendly.
                            $('#tagValueInput').parent().find('span.help-block').text(translations.tagInputValueHelpText)
                        }
                    }
                },
                complete: function () {
                    $("#loadingValues").removeClass('fa fa-spinner fa-spin loading-icon')
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error(jqXHR, textStatus, errorThrown);
                }
            });
        }
    });

    $(formSelector).on('itemAdded', function(event) {
        if (tagOptions != null && tagOptions !== []) {
            $('#tagValue').focus();
        }
    });

    $(formSelector).on('itemRemoved', function(event) {
        if(tagN === event.item) {
            $('#tagValueRequired, label[for="tagValue"]').addClass('d-none');
            $('.save-button').prop('disabled', false);
            $('#tagValue').html('').addClass("d-none");
            $('#tagValueInput').val('');
            $('#tagValueContainer').hide();
            tagN = '';
        } else if ($(".save-button").is(":disabled")) {
            // do nothing with jQuery
        } else {
            $('#tagValue').html('').addClass("d-none");
            $('#tagValueInput').val('');
            $('#tagValueContainer').hide();
            $('label[for="tagValue"]').addClass("d-none");
        }
    });

    $("#tagValue").on("change", function (e) {
        e.preventDefault();
        tagWithOption = tagN + '|' + $(this).val();

        // additional check, helpful for multi tagging.
        if (tagN != '') {
            if (tagIsRequired === 0 || (tagIsRequired === 1 && $(this).val() !== '')) {
                $(formSelector).tagsinput('add', tagWithOption);
                $(formSelector).tagsinput('remove', tagN);
                $('#tagValue').html('').addClass("d-none");
                $('#tagValueRequired, label[for="tagValue"]').addClass('d-none');
                $('.save-button').prop('disabled', false);
            } else {
                $('#tagValueRequired').removeClass('d-none');
                $('#tagValue').focus();
            }
        }
    });

    $('#tagValue').blur(function() {
        if($(this).val() === '' && tagIsRequired === 1 ) {
            $('#tagValueRequired').removeClass('d-none');
            $('#tagValue').focus();
            $('.save-button').prop('disabled', true);
        } else {
            $('#tagValue').html('').addClass("d-none");
            $('label[for="tagValue"]').addClass("d-none");
        }
    });

    $('#tagValueInput').on('keypress focusout', function(event) {

        if ( (event.keyCode === 13 || event.type === 'focusout') && tagN != '') {
            event.preventDefault();
            let tagInputValue = $(this).val();
            tagWithOption = (tagInputValue !== '') ? tagN + '|' + tagInputValue : tagN;

            if (tagIsRequired === 0 || (tagIsRequired === 1 && tagInputValue !== '')) {
                $(formSelector).tagsinput('add', tagWithOption);
                // remove only if we have value (otherwise it would be left empty)
                if (tagInputValue !== '') {
                    $(formSelector).tagsinput('remove', tagN);
                }

                $('#tagValueInput').val('');
                $('#tagValueContainer').hide();
                $('#tagValueRequired').addClass('d-none');
                $('.save-button').prop('disabled', false);
            } else {
                $('#tagValueContainer').show();
                $('#tagValueRequired').removeClass('d-none');
                $('#tagValueInput').focus();
            }
        }
    })
}



/**
 * Called when the ACL form is opened on Users/User Groups
 * @param dialog
 */
function featureAclFormOpen(dialog) {
    // Start everything collapsed.
    $(dialog).find("tr.feature-row").hide();

    // Bind to clicking on the feature header cells
    $(dialog).find("td.feature-group-header-cell").on("click", function() {
        // Toggle state
        var $header = $(this);
        var isOpen = $header.hasClass("open");

        if (isOpen) {
            // Make closed
            $header.find(".feature-group-description").show();
            $header.find("i.fa").removeClass("fa-arrow-circle-up").addClass("fa fa-arrow-circle-down");
            $header.closest("tbody.feature-group").find("tr.feature-row").hide();
            $header.removeClass("open").addClass("closed");
        } else {
            // Make open
            $header.find(".feature-group-description").hide();
            $header.find("i.fa").removeClass("fa-arrow-circle-down").addClass("fa fa-arrow-circle-up");
            $header.closest("tbody.feature-group").find("tr.feature-row").show();
            $header.removeClass("closed").addClass("open");
        }
    }).each(function(index, el) {
        // Set the initial state of the 3 way checkboxes
        setFeatureGroupCheckboxState($(this));
    });

    // Bind to checkbox change event
    $(dialog).find("input[name='features[]']").on("click", function() {
        setFeatureGroupCheckboxState($(this));
    });

    // Bind to group checkboxes to check/uncheck all below.
    $(dialog).find("input.feature-select-all").on("click", function() {
        // Force this down to all child checkboxes
        $(this)
            .closest("tbody.feature-group")
            .find("input[name='features[]']")
            .prop("checked", $(this).is(":checked"));
    });
}

/**
 * Set the checkbox state based on the adjacent features
 * @param triggerElement
 */
function setFeatureGroupCheckboxState(triggerElement) {
    // collect up the checkboxes belonging to the same group
    var $featureGroup = triggerElement.closest("tbody.feature-group");
    var countChecked = $featureGroup.find("input[name='features[]']:checked").length;
    var countTotal = $featureGroup.find("input[name='features[]']").length;

    if (countChecked <= 0) {
        $featureGroup.find(".feature-select-all")
            .prop("checked", false)
            .prop("indeterminate", false);
    } else if (countChecked === countTotal) {
        $featureGroup.find(".feature-select-all")
            .prop("checked", true)
            .prop("indeterminate", false);
    } else {
        $featureGroup.find(".feature-select-all")
            .prop("checked", false)
            .prop("indeterminate", true);
    }
}