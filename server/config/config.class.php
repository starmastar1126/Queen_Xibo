<?php
/*
 * Xibo - Digitial Signage - http://www.xibo.org.uk
 * Copyright (C) 2006,2007,2008 Daniel Garner and James Packer
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
defined('XIBO') or die("Sorry, you are not allowed to directly access this page.<br /> Please press the back button in your browser.");
 
class Config 
{
	private $db;
	private $extensions;
	private $envTested;
	private $envFault;
	private $envWarning;
	
	public function __construct(database $db)
	{
		$this->db			=& $db;
		
		// Populate an array of loaded extensions just in case we need it for something.
		$this->extensions 	= get_loaded_extensions();
		
		// Assume the environment is OK
		$this->envFault		= false;
		$this->envWarning	= false;
		$this->envTested	= false;
		
		return;
	}
	
	/**
	 * Loads the settings from file.
	 * @return 
	 */
	static function Load() 
	{
		include("settings.php");
	}
	
	/**
	 * Gets the requested setting from the DB object given
	 * @return 
	 * @param $db Object
	 * @param $setting Object[optional]
	 */
	static function GetSetting(database $db, $setting = "") 
	{		
		$SQL = "";
		$SQL.= sprintf("SELECT value FROM setting WHERE setting='%s'", $setting);
		
		if(!$results = $db->query($SQL, true))
		{
			trigger_error($db->error());
			trigger_error('Unable to get setting: ' . $setting, E_USER_WARNING);			
		} 
		
		if($db->num_rows($results)==0) 
		{
			return false;
		}
		else 
		{
			$row = $db->get_row($results);
			return $row[0];
		}
	}
	
	/**
	 * Defines the Version and returns it
	 * @return 
	 * @param $db Object
	 * @param $object String [optional]
	 */
	static function Version(database $db, $object = '') 
	{
		if (!$results = $db->query("SELECT app_ver, XlfVersion, XmdsVersion, DBVersion FROM version")) 
		{
			trigger_error("No Version information - please contact technical support", E_USER_WARNING);
		}
		
		$row 		= $db->get_assoc_row($results);
		
		$appVer     = Kit::ValidateParam($row['app_ver'], _STRING);
		$xlfVer     = Kit::ValidateParam($row['XlfVersion'], _INT);
		$xmdsVer    = Kit::ValidateParam($row['XmdsVersion'], _INT);
		$dbVer      = Kit::ValidateParam($row['DBVersion'], _INT);
	
		if (!defined('VERSION')) 
                    define('VERSION', $appVer);

		if (!defined('DBVERSION')) 
                    define('DBVERSION', $dbVer);
		
		if ($object != '')
		{
			return Kit::GetParam($object, $row, _STRING, '');
		}
		
		return $row;
	}
	
	/**
	 * Checks the Environment and Determines if it is suitable for Xibo
	 * @return 
	 */
	public function CheckEnvironment()
	{
		$db 	 =& $this->db;
		
		$output  = '';
		$imgGood = '<img src="install/dot_green.gif"> ';
		$imgBad  = '<img src="install/dot_red.gif"> ';
		$imgWarn = '<img src="install/dot_amber.gif"> ';
		
		$output .= '<div class="checks">';
		
		// Check for PHP version
		$message = __('PHP Version 5.2.4 or later');

		if ($this->CheckPHP() == 1) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else if ($this->CheckPHP() == 2)
		{
			$this->envWarning = true;
			$output .= $imgWarn.$message.'<br />';
			$output .= <<<END
			<div class="check_explain">
			<p>Xibo requires PHP version 5.2.4 or later. It may run on PHP 5.1.0 and we have provided compatibility functions to enable that.</p>
			<p>However, we recommend upgrading your version of PHP to 5.2.4 or later.</p>
			</div>
END;
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= '<div class="check_explain"> <p>' . __("PHP version 5.2.4 or later required.") . '</p></div>';
		}
		
		// Check for file system permissions
		$message = __('Filesystem Permissions');

		if ($this->CheckFsPermissions()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __("Write access required for the following:");
			$output .= <<<END
      			<ul>
        			<li> settings.php
        			<li> install.php
				<li> upgrade.php
      			</ul>
END;
      			$output .= __('Please fix this, and retest.') . '</p></div>';
		}
		
		// Check for MySQL
		$message = __('MySQL database required. Ensure PHP MySQL client extension is installed');

		if ($this->CheckMySQL()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= <<<END
			<div class="check_explain">
      			<p>Xibo requires the PHP MySQL Extension to function.</p>
      		</div>
END;
		}
		
		// Check for JSON
		$message = __('JSON Extension');

		if ($this->CheckJson())
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;

			$output .= $imgBad.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('PHP JSON extension required to function.') . '</p></div>';
		}

                // Check for SOAP
		$message = __('SOAP Extension');

		if ($this->CheckSoap())
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;

			$output .= $imgBad.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('PHP SOAP extension required to function.') . '</p></div>';
		}
		
		// Check for GD (graphics)
		$message = __('GD Extension');

		if ($this->CheckGd()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('PHP GD extension to function.') . '</p></div>';
		}

		// Check for PHP Session
		$message = __('Session');

		if ($this->CheckSession()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('PHP session support to function.') . '</p></div>';
		}
		
		// Check for PHP PCRE
		$message = __('PCRE');

		if ($this->CheckPCRE()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('PHP PCRE support to function.') . '</p></div>';
		}
		
		// Check for PHP Gettext
		$message = __('Gettext');

		/**
         * we now use PHP-Gettext which is shipped.
         */
		if ($this->CheckGettext())
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('PHP Gettext support to function.') . '</p></div>';
		}
	
		// Check for Calendar
		$message = __('Calendar Extension');

		if ($this->CheckCal()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('PHP Calendar extension to function.') . '</p></div>';
		}
		
		// Check for DOM
		$message = __('DOM Extension');

		if ($this->CheckDom()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('PHP DOM core functionality enabled.') . '</p></div>';
		}
		
		// Check for DOM XML
		$message = __('DOM XML Extension');

		if ($this->CheckDomXml()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('PHP DOM XML extension to function.') . '</p></div>';
		}
		
		// Check for Mcrypt
		$message = __('Mcrypt Extension');

		if ($this->CheckMcrypt()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envFault = true;
			
			$output .= $imgBad.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('PHP Mcrypt extension to function.') . '</p></div>';
		}
		
		// Check to see if we are allowed to open remote URLs (homecall will not work otherwise)
		$message = __('Allow PHP to open external URLs');

		if (ini_get('allow_url_fopen')) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			// Not a fault as this will not block installation/upgrade. Informational.
			$this->envWarning = true;
			$output .= $imgWarn.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('You must have allow_url_fopen = On in your PHP.ini file for anonymous statistics gathering to function.') . '<br />';
			$output .= __('If you do not intend to enable anonymous statistics gathering you need not worry about this problem.') . '</p></div>';
		}

		// Check to see if timezone_identifiers_list exists
		$message = 'DateTimeZone';

		if (function_exists('timezone_identifiers_list')) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envWarning = true;
			
			$output .= $imgWarn.$message.'<br />';
		}
		
		// Check to see if large file uploads enabled
		$message = 'Large File Uploads';

		if ($this->CheckPHPUploads()) 
		{
			$output .= $imgGood.$message.'<br />';
		}
		else
		{
			$this->envWarning = true;
			$output .= $imgWarn.$message.'<br />';
			$output .= '<div class="check_explain"><p>' . __('You probably want to allow larger files to be uploaded than is currently available with your PHP configuration.') . '<br />';
			$output .= __('We suggest setting your PHP post_max_size and upload_max_size to at least 128M, and also increasing your max_execution_time to at least 120 seconds.') . '</p></div>';
		}
				
		$output .= '</div>';
		
		$this->envTested = true;
		return $output;
	}
	
	/**
	 * Is there an environment fault
	 * @return 
	 */
	public function EnvironmentFault()
	{
		if (! $this->envTested) {
			$this->CheckEnvironment();
		}

		return $this->envFault;
	}
	
	/**
	 * Is there an environment warning
	 * @return 
	 */
	public function EnvironmentWarning()
	{
		if (! $this->envTested) {
			$this->CheckEnvironment();
		}

		return $this->envWarning;
	}


	/**
	 * Check FileSystem Permissions
	 * @return 
	 */
	function CheckFsPermissions() 
	{
	  return ((is_writable("install.php") && (is_writable("settings.php")) && (is_writable("upgrade.php")) || is_writable(".")));
	}
	
	/**
	 * Check PHP version > 5
	 * @return 
	 */
	function CheckPHP() 
	{
		if (phpversion() >= '5.2.4') 
		{
			return 1;
		}
	
		if (phpversion() >= '5.1.0') 
		{
			return 2;
		}

		return 0;
	}
	
	/**
	 * Check PHP has MySQL module installed
	 * @return 
	 */
	function CheckMySQL() 
	{
		return extension_loaded("mysql");
	}
	
	/**
	 * Check PHP has the GetText module installed
	 * @return 
	 */
	function CheckGettext() 
	{
		return extension_loaded("gettext");
	}
	
	/**
	 * Check PHP has JSON module installed
	 * @return 
	 */
	function CheckJson() 
	{
		return extension_loaded("json");
	}
	/**
         *
	 * Check PHP has SOAP module installed
	 * @return
	 */
	function CheckSoap()
	{
		return extension_loaded("soap");
	}
	
	/** 
	 * Check PHP has JSON module installed
	 * @return 
	 */
	function CheckGd() 
	{
		return extension_loaded("gd");
	}
	
	/**
	 * Check PHP has JSON module installed
	 * @return 
	 */
	function CheckCal() 
	{
		return extension_loaded("calendar");
	}
	
	/**
	 * Check PHP has the DOM XML functionality installed
	 * @return 
	 */
	function CheckDomXml()
	{
		return extension_loaded("dom");
	}
	
	/**
	 * Check PHP has the Mcrypt functionality installed
	 * @return 
	 */
	function CheckMcrypt()
	{
		return extension_loaded("mcrypt");
	}
	
	/**
	 * Check PHP has the DOM functionality installed
	 * @return 
	 */
	function CheckDom()
	{
		return class_exists("DOMDocument");
	}

	/**
	 * Check PHP has session functionality installed
	 * @return 
	 */
	function CheckSession()
	{
		return extension_loaded("session");
	}
	
	/**
	 * Check PHP has PCRE functionality installed
	 * @return 
	 */
	function CheckPCRE()
	{
		return extension_loaded("pcre");
	}
	
	/**
	 * Check PHP is setup for large file uploads
	 * @return
	 */
	function CheckPHPUploads()
	{
		# Consider 0 - 128M warning / < 120 seconds
		# Variables to check:
		#    post_max_size
		#    upload_max_filesize
		#    max_execution_time
		
		$minSize = $this->return_bytes('128M');
		
		if ($this->return_bytes(ini_get('post_max_size') < $minSize))
		{
			return false;
	        }
	        
	        if ($this->return_bytes(ini_get('upload_max_filesize') < $minSize))
	        {
	        	return false;
		}
		
		if (ini_get('max_execution_time') < 120)
		{
			return false;
		}
		
		return true;
	}

	/**
	 * Helper function to convert strings like 8M or 3G into bytes
	 * by Stas Trefilov. Assumed Public Domain.
	 * Taken from the PHP Manual (http://www.php.net/manual/en/function.ini-get.php#96996)
	 * @return
	 */
	function return_bytes($size_str)
	{
		switch (substr ($size_str, -1))
		{
                	case 'M': case 'm': return (int)$size_str * 1048576;
                        case 'K': case 'k': return (int)$size_str * 1024;
                        case 'G': case 'g': return (int)$size_str * 1073741824;
                        default: return $size_str;
                }
        }
}
?>