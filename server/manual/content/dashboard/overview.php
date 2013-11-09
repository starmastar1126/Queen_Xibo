
	<h1>Navigation Overview</h1>
	
	<p><?php echo PRODUCT_NAME; ?> is a large application with different concepts and components that can be enabled and disabled.
	These components make up the different types of content that can be used and the different ways they can 
	be presented on the <?php echo PRODUCT_NAME; ?> client display.</p>
	
	<p>Each component will be discussed in the following chapters of this manual, however a brief overview is 
	provided here. Two sections will be considered - components that are "Creation Orientated", i.e. they deal 
	with how things are displayed, and components that are "Interface Orientated", i.e . they deal
	with how to navigate through <?php echo PRODUCT_NAME; ?>.</p>
	
	<h2>Creation Orientated Components</h2>
	
	<p>Creation oritentated components deal with how content is created and displayed on the <?php echo PRODUCT_NAME; ?> Display Clients.</p>
	
	<blockquote>
	<h3>Content</h3>
	
	<p>Content is the core of an <?php echo PRODUCT_NAME; ?> system. Content is "items to display" such as images, text, videos, web content 
	(rss, websites, services), powerpoint and much more.</p>
	
	<p>Content can also be used to create "playlists" (lists) of content to play in sequence.</p>
	
	<h3>Layouts</h3>
	
	<p>Layouts are the mechanism for grouping content together, positioning it and styling it for display. Layouts use a 
	background to provide a canvas for your display and regions are used to define a position where content can be added 
	as a playlis. A layout can have one or more regions- allowing the content to change while the playlist is being shown.</p>
	
	<h3>Scheduling</h3>
	
	<p>The <?php echo PRODUCT_NAME; ?> scheduling system provides a calendar approach to getting a layout shown on the correct display at 
	the correct time.</p>
	
	<p>Events (layouts shown for a time) can be recurring and across multiple displays, for example Layout 1 (which 
	contains several regions and content items) could be shown between 10:00 and 10:30 in the Canteen and Recreation
	room every day for a month.</p>
	</blockquote>
	
	<h2>Interface Orientated Components</h2>
	
	<p>Interface Orientated Components define the ways content, playlists and schedules can be created and which 
	users are allowed to do it.</p>
	
	<blockquote>
	<h3>Navigation</h3>
	
	<p>The primary navigation in <?php echo PRODUCT_NAME; ?> is the navigation bar. This shows what components the user logged in has access 
	to - and provides a "click to nagivate" method which is instinctive to web sites.</p>
	
	<p>The navigation bar also features catagories which will expand into menus.</p>
	
	<h3>Dashboard</h3>
	
	<p>The first component to be presented when the user logs in is the Dashboard. This is used to provide all the 
	components that the user is allowed to access.</p>
	
	<p>The dashboard is an easy and intuitive feature for navigating the <?php echo PRODUCT_NAME; ?> admin interface and provides an outline 
	of the applications components. It is particularly useful for first time users of <?php echo PRODUCT_NAME; ?> - as it graphically
	represents components.</p>
	
	<p>The dashboard automatically displays all the areas of <?php echo PRODUCT_NAME; ?> that user has permission to access.</p>
	
	<h3>Users</h3>
	
	<p>In <?php echo PRODUCT_NAME; ?> content, layouts or schedules are all attributed to the user that are created or modified by them. 
	<?php echo PRODUCT_NAME; ?> also uses a permissions attribute on items in <?php echo PRODUCT_NAME; ?>. This allows all users of the system to share things 
	they have created in the system with each other and also allows "admins" of the system to oversee what is being 
	shown on displays.</p>
	
	<p>Users also have a "Home Page". This will become their "Dashboard" page. Using the home page users can be 
	directed to a simple page allowing very restricted access to <?php echo PRODUCT_NAME; ?> - or a complex page showing all components
	available.</p>
	
	<p>In this way <?php echo PRODUCT_NAME; ?> is also a "Content Management System".</p>
	
	<h3>User Groups &amp; Types</h3>
	
	<p>To add further flexibility to the user system <?php echo PRODUCT_NAME; ?> has a "groups" component. This allows users to be assigned 
	a group. They can then operate inside that group without effecting content in other groups.</p>
	
	<p>There are also three user types; Super Admin, Group Admin and User. These types give more permissions to specific 
	users of the system.</p>
	
	<h3>Menu Page Permissions</h3>
	
	<p>As mentioned in the Navigation and Dashboard sections only the components available to the user logged in are 
	shown. This can be set on a group by group basis.</p>
	
	<p>Super Admins can see all components in the system regardless of the group they are in.</p>
	</blockquote>

