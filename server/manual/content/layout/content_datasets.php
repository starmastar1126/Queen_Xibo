<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<?php include('../../template.php'); ?>
<html>
<head>
  	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
  	<title><?php echo PRODUCT_NAME; ?> Documentation</title>
  	<link rel="stylesheet" type="text/css" href="../../css/doc.css">
	<meta name="keywords" content="digital signage, signage, narrow-casting, <?php echo PRODUCT_NAME; ?>, open source, agpl" />
	<meta name="description" content="<?php echo PRODUCT_NAME; ?> is an open source digital signage solution. It supports all main media types and can be interfaced to other sources of data using CSV, Databases or RSS." />
  	<link href="img/favicon.ico" rel="shortcut icon">
  	<!-- Javascript Libraries -->
  	<script type="text/javascript" src="lib/jquery.pack.js"></script>
  	<script type="text/javascript" src="lib/jquery.dimensions.pack.js"></script>
  	<script type="text/javascript" src="lib/jquery.ifixpng.js"></script>
</head>

<body>
	<a name="DataSets" id="DataSets"></a><h2>DataSets</h2>
<blockquote>
  	<h3>Overview</h3>
  	<p>DataSets are a new <?php echo PRODUCT_NAME; ?> feature to design and display tabular data, formatted nicely, in a region on a layout.</p>
  	<p>Examples of where this could be used are:</p>
  	<ul>
    	<li>A drinks menu at a bar</li>
    	<li>Tee times at a golf club</li>
    	<li>Meeting room bookings</li>
  	</ul>

  	<p>DataSets have been designed to be versatile and reusable and therefore come in two parts:</p>
  	<ul>
    	<li>The Data (DataSet)</li>
    	<li>The Display (DataSet View)</li>
  	</ul>
  	<p>This means that you can define a data set as a number of columns, add rows and then create &#8220;views&#8221; of this data 
  	on your layouts.</p>

  	<a name="Create_Dataset" id="Create_Dataset"></a><h3>Creating a DataSet</h3>
  
    <p>DataSets are accessed using the &#8220;DataSets&#8221; link in the &#8220;Library&#8221; menu, navigating here will bring you
    to a very familiar <?php echo PRODUCT_NAME; ?> &#8220;table&#8221; view of all the data sets you have permission to access. You can add a new dataset 
    by giving it a name and an optional description, you can also edit existing ones and add data.</p>
    
	<p><img alt="Add Dataset" src="ss_layout_add_dataset.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="455" height="408"></p>
 
    <p>The creator of a dataset (or an admin) is able to set the permissions for the DataSet on a user group, or on a user by 
    user basis. Only users with Edit permissions will be able to add/edit data and reorganise the data structure, only users with 
    a view permission will be able to use the DataSet in their layouts.</p>

    <p>The first thing to do is Add a new DataSet using the "Add Dataset" button, after doing so the columns 
    of the DataSet can be defined to describe the structure of the data.</p>

  	<a name="Dataset_Column" id="Dataset_Column"></a><h3>Defining Dataset Structure</h3>
    <p>Data Columns are used to define the structure of the data, each column can have a number of settings to achieve this, these are:</p>
    <ul>
      <li><strong>Heading</strong>: the column heading to appear when you enter data</li>
      <li><strong>List Content</strong>: enter a comma separated list of values in here. The list is displayed in the drop down 
      list during row data entry</li>
      <li><strong>Column Order</strong>: the order to place the column</li>
    </ul>

	<p><img alt="Dataset Column" src="ss_layout_dataset_column.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="558" height="256"></p>

    <p>There is not a theoretical limit to the number of columns <?php echo PRODUCT_NAME; ?> can support; although a smaller DataSet is often easier to enter 
     and display than an overly large one. Columns may be extended in the future to have support for different data types. Currently only 
     strings are supported.</p>

    <p>Note: Columns can be added and removed after data has been entered. The ordering and list content of columns can also be changed 
    after data has been collected.</p>

  	<a name="Dataset_Row" id="Dataset_Row"></a><h3>Adding Data</h3>
    <p>Once all the required columns have been added, the DataSet is ready to have data recorded against it. This is done using 
    the &#8220;View Data&#8221; task on the DataSet table view. This view will contain all of the columns that were added in the 
    previous step and allow you to go through each one and enter data.</p>
    
	<p><img alt="Dataset Row" src="ss_layout_dataset_row.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="758" height="557"></p>

    <p>There is no &#8220;save&#8221; button on this interface, <?php echo PRODUCT_NAME; ?> will automatically save your changes after each data entry.</p>
    <p>Note: If all the rows are taken, more rows can be added to the data set by clicking the &#8220;Add Rows&#8221; button.</p>
    <p>The DataSet is ready to be used on a layout!</p>

  	<h3>Using the DataSet</h3>
    <p>Once a DataSet has been defined, anyone with &#8220;View&#8221; permissions can use the DataSet on layouts. They are added 
    by selecting the &#8220;DataSet&#8221; button from a region Timeline, which presents a drop down list of all DataSets available 
    to the user as well as the usual duration field for entering the duration in seconds that the DataSet should be shown.</p>

	<p><img alt="Dataset Add View" src="ss_layout_dataset_addview.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="358" height="178"></p>

    <p>Once added, the edit dialog will automatically appear allowing the user to pick their options for the DataSet View.</p>

	<p><img alt="Dataset View Edit" src="ss_layout_dataset_view_edit.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="658" height="583"></p>

    <p>On this form the user can specify:</p>
    <ul>
      <li><strong>Duration</strong>: The display duration in seconds</li>
      <li><strong>Update Interval</strong>: The duration in minutes the data should be cached on the client</li>
      <li><strong>Lower Row Limit</strong>: The row number to start displaying the data</li>
      <li><strong>Upper Row Limit</strong>: The row number to end displaying the data</li>
      <li><strong>Order</strong>: The Ordering of the data (column name ASC|DESC)</li>
      <li><strong>Filter</strong>: A filter option to filter the data with (Column Name=Value, Column Name=Value)</li>
      <li><strong>Show Table Headings</strong>: Whether to show the column headings in the table</li>
      <li><strong>Columns Selected</strong>: The columns to display (drag or double click to move between lists)</li>
      <li><strong>Columns Available</strong>: The columns available to select (drag or double click to move between lists)</li>
      <li><strong>Stylesheet</strong>: A CSS Stylesheet to render with the table</li>
    </ul>

    <p>Following is an example of the "Styleshee for the Table" which will produce the table on the Clent Display as shown below:</p>
    
<pre>
table.DataSetTable {
font-family:"Trebuchet MS", Arial, Helvetica, sans-serif;  
width:100%;
border-collapse:collapse;
}

tr.HeaderRow {
font-size:1.1em;
text-align:center;
padding-top:5px;
padding-bottom:4px;
background-color:#A7C942;
color:#ffffff;
}

tr#row_1 {
color:#000000;
background-color:#EAF2D3;
}

td#col_1 {
color:#000000;
background-color:#EAF2D3;
}

td.DataSetColumn {
color:#000000;
background-color:#EAF2D3;
border:1px solid #98bf21
}

tr.DataSetRow {
text-align:center;
color:#000000;
background-color:#EAF2D3;
border:1px solid #98bf21
padding-top:5px;
padding-bottom:4px;
}

th.DataSetColumnHeaderCell {
font-size:1em;
border:1px solid #98bf21;
padding:3px 7px 2px 7px;
}

span#1_1 {

}

span.DataSetColumnSpan {

}
</pre>    
    <p>The resulting view will be showing the region preview window and displayed on the client.</p>

	<p><img alt="Dataset Table" src="ss_layout_dataset_table.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="474" height="182"></p>

    <p>Note: Once the DataSet view is configured it will automatically respond to edits made on the data &#8211; and it
    multiple views on the same DataSet can be created.</p>
</blockquote>	

	<?php include('../../template/footer.php'); ?>
</body>
</html>
