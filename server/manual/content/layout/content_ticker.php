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
	<a name="Ticker" id="Ticker"></a><h2>Ticker</h2>
	<p>The add ticker form is similar to the text form. It is used to add an RSS feed into your layout. An RSS feed can 
	be used to get up-to-date information from a variety of sources on the internet 
	e.g. http://newsrss.bbc.co.uk/rss/newsonline_world_edition/asia-pacific/rss.xml. There are a couple of additional
	options which are required.</P>

	<p><img alt="Ss_layout_designer_add_ticker" src="Ss_layout_designer_add_ticker.png"
	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	width="636" height="492"></p>

	<p> An RSS feed has a couple of default tags. Each section takes on the properties that you set for each keyword. 
	So if you make [Date] red, then your RSS feeds date will appear red.</p>

	<ul>
		<li>[Date]<br />
		This item is used to style the time and date of the story in a RSS feed.</li>

  		<li>[Title]<br />
		This item is used to extract the title from an RSS story.</li>

		<li>[Description]<br />
		This item can be used to style the description of the RSS story. This text provides a more detailed overview of an RSS title.</li>
	</ul>

	<p>Any of these options can be removed and the contents will not be shown. Therefore if you just want the titles of the RSS feed, 
	you just need to include the [Title] tag in the text window.</p>
 
	<?php include('../../template/footer.php'); ?>
</body>
</html>
