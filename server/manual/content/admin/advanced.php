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

		<h1>Advanced</h1>
		<p>The Advanced page is mainly used by system administrator to help troubleshooting system problem or reporting fault 
		to the <?php echo PRODUCT_NAME; ?> xstreamedia team for assistance</p>
    	<p>Click Advanced from the Navigation Bar. System loads the Log page by default.</p>

 	<blockquote>
  		<a name="Error_Log_Help" id="Error_Log_Help"></a><h3>System Log</h3>
		 
    	<p><img alt="SA Advanced" src="sa_advanced.png"
	   style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   width="803" height="161"></p>

    	<p>The system log is used to help troubleshoot problems with <?php echo PRODUCT_NAME; ?>. When you encounter an error it will be logged
    	and listed in the system here. These error messages can help the xstreamedia team solve your problem.</p>
   	 	<p>Truncating the log helps you to troubleshoot a problem by clearing the current error messages</p>
    	<p>The page and sub page items helps locate where the error has been generated from.</p>
    	<ul>
    		<li><strong>Log Date</strong><p> states the date and time the error message was logged.</p></li>
    		<li><strong>Page</strong><p> states the page that the error has been generated from.</p></li>
    		<li><strong>Function</strong><p>state function that the error message has been generated from.</p></li>
    		<li><strong>Message</strong><p> gives details of what error has occurred in the system.</p></li>
    	</ul>
   		<a name="Session" id="Session"></a><h3>Sessions</h3>
   		<p>Sessions provide details of the current user activity on the network</p>
    	<p><img alt="SA Advanced Session" src="sa_advanced_sessions.png"
	   	style="display: block; text-align: center; margin-left: auto; margin-right: auto"
	   	width="805" height="208"></p>

   		<a name="Report_Fault" id="Report_Fault"></a><h3>Report Fault</h3>
   		<p>Simple instruaction on the collection of system error and report fault to <?php echo PRODUCT_NAME; ?> xsteamedia team.</p>

   		<a name="License" id="License"></a><h3>License Information</h3>
   		<p>The license page provides details of all the relevant licenses for the system.</p>
 </blockquote>

		<?php include('../../template/footer.php'); ?>
	</body>
</html>
