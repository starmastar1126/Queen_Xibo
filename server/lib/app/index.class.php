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

class indexDAO 
{
	private $db;

	function indexDAO(database $db) 
	{
		$this->db =& $db;
	}
	
	function on_page_load() 
	{
    	return "";
	}
	
	function echo_page_heading() 
	{
		echo "Schedule";
		return true;
	}

	function login() 
	{
		global $user;
		global $session;
		$db =& $this->db;

		//this page must be called from a form therefore we expect POST variables		
		$username 		= Kit::GetParam('username', _POST, _USERNAME);
		$password 		= Kit::GetParam('password', _POST, _PASSWORD);
		$password		= md5($password);
		
		$referingpage 	= Kit::GetParam('referingPage', _GET, _WORD, 'index');
		
		if (isset($_REQUEST['ajax'])) 
		{
			//ajax request handler
			$arh = new AjaxRequest();
			
			//use the ajax login method
			if($user->login($username,$password)) 
			{
				$userid 	= Kit::GetParam('userid', _SESSION, _INT);
				$username 	= Kit::GetParam('username', _SESSION, _USERNAME);
				
				$session->set_user(session_id(), $userid, 'user');
				
				$arh->decode_response(true, $username . ' logged in');
			}
			else 
			{
				//re-display the login form
				$_SESSION['message'] = "";
				
				//send the failed info
				$arh->decode_response(false, "Incorrect Login Information");
			}
			exit;
		}
		
		if (!CheckFormToken($_POST['token'])) 
		{
			setMessage("Form expired. Please refresh and try again.");
			
			header("Location:index.php");
			exit;
		}

		if($user->login($username,$password)) 
		{
			$userid 	= Kit::GetParam('userid', _SESSION, _INT);
			$username 	= Kit::GetParam('username', _SESSION, _USERNAME);
				
			setMessage($username . ' logged in');
			$session->set_user(session_id(), $userid, 'user');
			
			//if the referingpage is blank, then use the users homepage
			if ($referingpage == 'index') $referingpage = $user->homepage($userid);
		}
		
		Debug::LogEntry($db, 'audit', 'Login with refering page: ' . $referingpage);
		
		if ($referingpage == 'index') 
		{
			header("Location:index.php?p=dashboard");
		}
		else 
		{
			header("Location:index.php?p=".$referingpage);			
		}

		exit;
	}

	function logout($referingpage = "") 
	{
		global $user;
		$db =& $this->db;
		
		$username 	= Kit::GetParam('username', _SESSION, _USERNAME);

		if($referingpage == "client") 
		{
			setMessage($username . " logged out. You have to be a Display User to access this page!");
		}
		else 
		{
			setMessage($username . " logged out. You have to be an Admin or User Type to access this page!");
		}

		//logs the user out -- true if ok
		$user->logout();

		if($referingpage=="") 
		{
			$referingpage="index";
		}

		//then go back to the index page
		header("Location:index.php?p=".$referingpage);
		exit;
	}
	
	function forgotten() 
	{
		//Called by a submit to the Forgotten Details form 
		//	Checks the validity of the data provided, and emails a new password to the user
		$db =& $this->db;
		
		$username 	= Kit::GetParam('f_username', _POST, _USERNAME);
		$email	 	= Kit::GetParam('f_email', _POST, _STRING);
		$return 	= "index.php";
		
		if ($username == "" || $email == "") 
		{
			setMessage("Username and Email address need to be filled in");
			return $return;
		}
		
		//send the email
		$from = Config::GetSetting($db, "mail_from");
		if ($from == "") 
		{
			setMessage("Email is not set up, please contact your IT manager");
			return $return;
		}
		
		//check the user details
		$SQL = sprintf("SELECT userid FROM user WHERE username = '%s' AND email = '%s'", $db->escape_string($username), $db->escape_string($email));
		
		if (!$results = $db->query($SQL)) 
		{
			trigger_error($db->error);
			trigger_error("Can not get the user information", E_USER_ERROR);
		}
		
		if ($db->num_rows($results) < 0 || $db->num_rows($results) > 1) 
		{
			setMessage("The details you entered are incorrect.");
			return $return;
		}
		
		$row = $db->get_row($results);
		
		$userid 		= Kit::ValidateParam($row[0], _INT); //user ID for the user that wants a new password

		$password_plain = $this->random_word(8); //generate a new password
		$password 		= md5($password_plain);
		
		//update the password
		$SQL = sprintf("UPDATE user SET UserPassword = '%s' WHERE userid = %d", $db->escape_string($password), $userid);
		
		if (!$db->query($SQL)) 
		{
			trigger_error($db->error());
			trigger_error("Unable to send new password", E_USER_ERROR);
		}

		$headers = "From: $from" . "\r\n" . "Reply-To: $from" . "\r\n" .
			"X-Mailer: PHP/" . phpversion();
		
		if (!@mail($email,"Xibo: New Password request for $username","Your new password is $password_plain \n  . You may now login with these details.", $headers)) 
		{
			setMessage("Email is not set up, please contact your IT manager");
			return $return;
		}
		
		setMessage("New Password Sent to your email address");
		return $return;
	}
	
	function random_word($length)
	{
	    srand((double)microtime()*1000000);   

	    $vowels = array("a", "e", "i", "o", "u");
	    $cons = array("b", "c", "d", "g", "h", "j", "k", "l", "m", "n", "p", "r", "s", "t", "u", "v", "w", "tr",
	    "cr", "br", "fr", "th", "dr", "ch", "ph", "wr", "st", "sp", "sw", "pr", "sl", "cl");

	    $num_vowels = count($vowels);

	    $num_cons = count($cons);
	    
		$password = "";
		for($i = 0; $i < $length; $i++)
		{
	        $password .= $cons[rand(0, $num_cons - 1)] . $vowels[rand(0, $num_vowels - 1)];
	    }
	    return substr($password, 0, $length);
	}  
	
	function displayPage() 
	{
		$db =& $this->db;
		
		include("lib/app/schedule.class.php");
		
		$schedule = new scheduleDAO($db);
		
		$schedule->displayPage();
		
		exit;
	}
}

?>