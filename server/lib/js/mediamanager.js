/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2011-2012 Daniel Garner
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
var text_callback = function()
{
    // Conjure up a text editor
    $("#ta_text").ckeditor();

    // Make sure when we close the dialog we also destroy the editor
    $("#div_dialog").bind("dialogclose.xibo", function(event, ui){
        $("#ta_text").ckeditorGet().destroy();
        $("#div_dialog").unbind("dialogclose.xibo");
    });

    $('#div_dialog').dialog('option', 'width', 800);
    $('#div_dialog').dialog('option', 'height', 500);
    $('#div_dialog').dialog('option', 'position', 'center');


    return false; //prevent submit
}

var microblog_callback = function()
{
    // Conjure up a text editor
    $("#ta_template").ckeditor();
    $("#ta_nocontent").ckeditor();

    // Make sure when we close the dialog we also destroy the editor
    $("#div_dialog").bind("dialogclose.xibo", function(event, ui){
        $("#ta_template").ckeditorGet().destroy();
        $("#ta_nocontent").ckeditorGet().destroy();

        $("#div_dialog").unbind("dialogclose.xibo");
    })

    $('#div_dialog').dialog('option', 'width', 800);
    $('#div_dialog').dialog('option', 'height', 500);
    $('#div_dialog').dialog('option', 'position', 'center');


    return false; //prevent submit
}


var datasetview_callback = function()
{
    $("#columnsIn, #columnsOut").sortable({
		connectWith: '.connectedSortable',
		dropOnEmpty: true
	}).disableSelection();

    return false; //prevent submit
}

var DataSetViewSubmit = function() {
    // Serialise the form and then submit it via Ajax.
    var href = $("#ModuleForm").attr('action') + "&ajax=true";

    // Get the two lists
    serializedData = $("#columnsIn").sortable('serialize') + "&" + $("#ModuleForm").serialize();

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

function transitionFormLoad() {
    $("#transitionType").change(transitionSelectListChanged);
    
    // Fire once for initialisation
    transitionSelectListChanged();
}

function transitionSelectListChanged() {
    // See if we need to disable any of the other form elements based on this selection
    var selectionOption = $("#transitionType option:selected");
    
    if (!selectionOption.hasClass("hasDuration"))
        $("tr.transitionDuration").hide();
    else
        $("tr.transitionDuration").show();
        
    if (!selectionOption.hasClass("hasDirection"))
        $("tr.transitionDirection").hide();
    else
        $("tr.transitionDirection").show();
}