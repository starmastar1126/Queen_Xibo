<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<?php include('../../template.php'); ?>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
		<title><?php echo PRODUCT_NAME; ?> Documentation</title>
		<link rel=stylesheet type="text/css" href="../../css/doc.css">
		<meta http-equiv="Content-Type" content="text/html" />
		<meta name="keywords" content="digital signage, signage, narrow-casting, <?php echo PRODUCT_NAME; ?>, open source, agpl" />
		<meta name="description" content="<?php echo PRODUCT_NAME; ?> is an open source digital signage solution. It supports all main media types and can be interfaced to other sources of data using CSV, Databases or RSS." />

		<link href="img/favicon.ico" rel="shortcut icon"/>
		<!-- Javascript Libraries -->
		<script type="text/javascript" src="lib/jquery.pack.js"></script>
		<script type="text/javascript" src="lib/jquery.dimensions.pack.js"></script>
		<script type="text/javascript" src="lib/jquery.ifixpng.js"></script>
	</head>
	<body>
		
		<h1>Modules</h1>
    	<p><?php echo PRODUCT_NAME; ?> server content handling is done by Modules.</p>
    	<p>Click "Administration -> Modules" load the modules parameters for edit.</p>
      
    	<p><img alt="SA_Modules" src="sa_modules.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="644" height="418"></p>

		<p><?php echo PRODUCT_NAME; ?> server modules table contains parameters for user edit.</p>
		<ul>
			<li><strong>Name:</strong>
      		<p>System given Name for the specific module.</p</li>
			<li><strong>Description:</strong>
      		<p>A breif description of the modules.</p</li>
			<li><strong>Library Media:</strong>
      		<p>Indicated if the content is saved in the <?php echo PRODUCT_NAME; ?> Library. Unchecked indicate the specific content is only save
      		with the Layout-Regions.</p</li>
			<li><strong>Valid Extensions:</strong>
      		<p>Valid file extensions that are supported by the specific module.</p</li>
			<li><strong>Image Uri:</strong>
      		<p>Link to the module icon for UI display, usually leave as it.</p></li>
			<li><strong>Enabled:</strong>
      		<p>System administrator may enable/disable the specific content for <?php echo PRODUCT_NAME; ?> user access.</p</li>
		</ul>

		<h2>Edit</h2>
		<p>Click Edit button for the specific content type load the following form for user changes.</p>

		<ul>
		<li><h3>Content Without Extension:</h3>
    	<p>Admininstrator may enable or disable the content for user access.</p>
    
    	<p><img alt="SA_Modules" src="sa_modules_text.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="358" height="179"></p></li>

		<li><h3>Content With Extension:</h3>
    	<p>Administrator may add other valid files extensions that are supported by the <?php echo PRODUCT_NAME; ?> Client Display e.g. mp4.</p>
    
    	<p><img alt="SA_Modules" src="sa_modules_video.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="358" height="211"></p></li>
    </ul>    

	<?php include('../../template/footer.php'); ?>
	</body>
</html>
