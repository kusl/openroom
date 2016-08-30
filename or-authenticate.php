<?php
declare(strict_types = 1);
if (!isset($_SESSION)) {
	session_start();
}
if (!isset($_SESSION)) {
	session_start();
}

$_SESSION["systemid"] = "";
$_SESSION["username"] = "";
$_SESSION["isadministrator"] = "FALSE";
$_SESSION["isreporter"] = "FALSE";


/*
*or-authenticate.php(POST:username,password,ajax_indicator)
*
*This file receives three parameters, a username, a password, and ajax_indicator from $_POST[].
*The file checks db->settings->login_method.
*If "normal" the username and password are checked against db->users.
*If "ldap" the username and password are checked against the provided ldap connection.
*An XML response is formed and the SESSION variable "username" is set on success.
*If ajax_indicator is TRUE(1) this file returns with headers type text/xml, allowing it to be used by AJAX.
*If ajax_indicator is FALSE(0) this file returns all text with no headers, allowing it to be used by PHP and converted to a string.
*
*USAGE
*To use this file with AJAX, simply call it using open() sending the following parameters over POST: username, password, TRUE.
*To pull this file's data into a string using PHP, set the POST variables before including the file:
*$_POST["username"] = "test";
*$_POST["password"] = "test";
*$_POST["ajax_indicator"] = FALSE;
*$xmloutput = include("or-authenticate.php");
*Then you may wish to convert $xmloutput into a simpleXML object
*/
require_once("includes/or-dbinfo.php");

/*
*AuthenticateUser()
*Function Written by: Tim Sprowl
*Date: 2008
*/
/**
 * @param $username
 * @param $password
 * @param $settings
 * @return bool
 * @throws Exception
 */
function IsNotNullOrEmptyString($question): bool
{
	return (!isset($question) || trim($question) === '');
}

/**
 * @param string $username
 * @param string $password
 * @param array $settings
 * @return bool
 */
function AuthenticateUser(string $username, string $password, array $settings): bool
{
	$Host = "ldap://149.4.100.201:3268";
	$qc_username = "qc\\";
	$instr_username = "instr\\";
	$qc_username .= $username;
	$instr_username .= $username;
	$ldap = ldap_connect($Host);
	if (!IsNotNullOrEmptyString($username) && !IsNotNullOrEmptyString($password)) {
		sleep(1);
		if ($bind = ldap_bind($ldap, $qc_username, $password)) {
			return true;
		} else if ($bind = ldap_bind($ldap, $instr_username, $password)) {
			return true;
		} else {
			return false;
		}
	} else {
		return false;
	}
}


$username = isset($_POST["username"]) ? $_POST["username"] : "";
$password = isset($_POST["username"]) ? $_POST["password"] : "";
$ajax_indicator = isset($_POST["ajax_indicator"]) ? $_POST["ajax_indicator"] : "FALSE";
$output = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\n<authresponse>\n";
if ($username != "" && $password != "" && $ajax_indicator != "") {
	//LDAP
	if ($isAuthenticated = AuthenticateUser($username, $password, $settings)) {
	} else {
		$output .= "\t<errormessage>" . "Sorry, authentication failed." . "</errormessage>\n";
	}
	if (!$isAuthenticated) {
		$output .= "\t<authenticated>false</authenticated>\n";
	} else {
		if (mysql_num_rows(mysql_query("SELECT * FROM bannedusers WHERE username='" . $username . "';")) <= 0) {
			$_SESSION["systemid"] = $settings["systemid"];
			$_SESSION["username"] = $username;
			$output .= "\t<errormessage></errormessage>\n";
			$output .= "\t<authenticated>true</authenticated>\n";
			//Check if logged in user is an administrator
			$aresult = mysql_query("SELECT * FROM administrators WHERE username='" . $username . "';");
			if (mysql_num_rows($aresult) == 1) {
				$_SESSION["isadministrator"] = "TRUE";
				$output .= "\t<isadministrator>true</isadministrator>\n";
			} else {
				$_SESSION["isadministrator"] = "FALSE";
				$output .= "\t<isadministrator>false</isadministrator>\n";
			}
			//Check if logged in user is a reporter
			$rresult = mysql_query("SELECT * FROM reporters WHERE username='" . $username . "';");
			if (mysql_num_rows($rresult) == 1) {
				$_SESSION["isreporter"] = "TRUE";
				$output .= "\t<isreporter>true</isreporter>\n";
			} else {
				$_SESSION["isreporter"] = "FALSE";
				$output .= "\t<isreporter>false</isreporter>\n";
			}
		} else {
			$output .= "\t<errormessage>This user has been banned. Please contact an administrator to fix this problem.</errormessage>\n";
			$output .= "\t<authenticated>false</authenticated>\n";
			$_SESSION["systemid"] = "";
			$_SESSION["username"] = "";
			$_SESSION["isadministrator"] = "FALSE";
			$_SESSION["isreporter"] = "FALSE";
		}
	}//Normal
	/*
    elseif ($settings["login_method"] == "normal") {
        $encpass = sha1($password);
        $lresult = mysql_query("SELECT * FROM users WHERE username='" . $username . "' AND password='" . $encpass . "';");
        if (mysql_num_rows($lresult) == 1) {
            $isactivea = mysql_fetch_array($lresult);
            $isactive = $isactivea["active"];
            if (mysql_num_rows(mysql_query("SELECT * FROM bannedusers WHERE username='" . $username . "';")) <= 0 && $isactive == "0") {
                //Set lastlogin time
                $llresult = mysql_query("UPDATE users SET lastlogin=NOW() WHERE username='" . $username . "';");
                if ($llresult) {
                    //Set session for user
                    $_SESSION["systemid"] = $settings["systemid"];
                    $_SESSION["username"] = $username;
                    $output .= "\t<errormessage></errormessage>\n";
                    $output .= "\t<authenticated>true</authenticated>\n";
                    //Check if logged in user is an administrator
                    $aresult = mysql_query("SELECT * FROM administrators WHERE username='" . $username . "';");
                    if (mysql_num_rows($aresult) == 1) {
                        $_SESSION["isadministrator"] = "TRUE";
                        $output .= "\t<isadministrator>true</isadministrator>\n";
                    } else {
                        $_SESSION["isadministrator"] = "FALSE";
                        $output .= "\t<isadministrator>false</isadministrator>\n";
                    }
                    //Check if logged in user is a reporter
                    $rresult = mysql_query("SELECT * FROM reporters WHERE username='" . $username . "';");
                    if (mysql_num_rows($rresult) == 1) {
                        $_SESSION["isreporter"] = "TRUE";
                        $output .= "\t<isreporter>true</isreporter>\n";
                    } else {
                        $_SESSION["isreporter"] = "FALSE";
                        $output .= "\t<isreporter>false</isreporter>\n";
                    }
                } else {
                    $output .= "\t<authenticated>false</authenticated>\n\t<errormessage>Could not set last login time.</errormessage>\n";
                }
            } else {
                $output .= "\t<errormessage>This user has been banned or has not activated their account. Please contact an administrator to fix this problem.</errormessage>\n";
                $output .= "\t<authenticated>false</authenticated>\n";
                $_SESSION["systemid"] = "";
                $_SESSION["username"] = "";
                $_SESSION["isadministrator"] = "FALSE";
                $_SESSION["isreporter"] = "FALSE";
            }
        } else {
            $output .= "\t<authenticated>false</authenticated>\n\t<errormessage>Incorrect username or password. Please try again.</errormessage>\n";
        }
    }
    */
} else {
	$output .= "\t<authenticated>false</authenticated>\n\t<errormessage>One or more parameters not provided.</errormessage>\n";
}

$output .= "</authresponse>";

if ($ajax_indicator == "TRUE") {
	header("content-type: text/xml");
	echo $output;
} else {
	return $output;
}

?>