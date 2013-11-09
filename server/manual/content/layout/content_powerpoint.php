
	<a name="PowerPoint" id="PowerPoint"></a><h2>PowerPoint</h2>
	<p>You can upload your Microsoft PowerPoint files to show on a <?php echo PRODUCT_NAME; ?> layout.<br />
	<i>Note: PowerPoint media is not supported when a Python Client is used.</i>
	</p>

<blockquote>	
	<p>Add a PowerPoint Presentation</p>
	<ul>
		<li>First prepare the PowerPoint Presentation. PowerPoint will, by default, put scroll bars up the side of your presentation, 
			unless you do the following for each PowerPoint file BEFORE you upload it:
		<ul>
			<li>Open your PowerPoint Document</li>
			<li>Slide Show -&gt; Setup Show</li>
			<li>Under "Show Type", choose "Browsed by an individual (window)" and then untick "Show scrollbar"</li>
			<li>Click OK</li>
			<li>Save the Presentation</li>
			<li>Note also that <?php echo PRODUCT_NAME; ?> will not advance the slides in a Presentation, so you should record automatic slide timings by going 
				to "Slide Show -&gt; Rehearse Timings" and then saving the presentation.</li>
		</ul></li>
		
		<li>Once your PowerPoint file is prepared, click the "Add PowerPoint" icon</li>
		<li>A new dialogue will appear:<br />

		<p><img alt="Ss_layout_designer_add_PowerPoint" src="content/layout/Ss_layout_designer_add_powerpoint.png"
		style="display: block; text-align: center; margin-left: auto; margin-right: auto"
		width="458" height="288" border="1px"></p></li>
	
		<li>Click "Browse"</li>
		<li>Select the PowerPoint file you want to upload from your computer. Click OK</li>
		<li>While the file uploads, give the presentation a name for use inside <?php echo PRODUCT_NAME; ?>. Type the name in the "Name" box.</li>
		<li>Finally enter a duration in seconds that you want the presentation to play for.<br />
			<i>Note that if this is the only media item in a region, then this is the minimum amount of time the presentation will be 
			shown for as the total time shown will be dictated by the total run time of the longest-running region on the layout.</i></li>
		<li>Click "Save"</li>
		</ul>
</blockquote>
