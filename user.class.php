<?php
##
# 29.03.2013: initial start - forked from an old project
# 30.03.2013: tested register, login, logout, validate_username, need to test check_user 
# 05.04.2013: adapted course to register 
# 05.04.2013: fixed check_user 
# 06.04.2013: updated documentation, extended some methods, updated the constants, new features(set user to prof or student, disable/enable at registration, set adminflag)
# 07.04.2013: fixed typo in register_function, updated check_user, updated login to save userid in session 
#
# ToDo: update validate_username, maybe we want to disallow some special chars like @,.!"§$%& 
# ToDo: update validate_password, it is just a dummy 
# ToDo: set a disabled/enabled flag? 
# ToDo: build api to create password 
# ToDo: set a MaxiumLoginAttempts? 
##

##
# these are some helpfull links:
# http://stackoverflow.com/questions/1561174/sha512-vs-blowfish-and-bcrypt
# http://www.phpbuddy.eu/login-systeme-einfach-bis-profi.html?start=4
# http://www.phpgangsta.de/schoener-hashen-mit-bcrypt
##
/*
// mysql syntax
DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
`user_ID` int(11) NOT NULL AUTO_INCREMENT,
`username` varchar(20) NOT NULL,
`password` varchar(100) NOT NULL,
`course_IDFK` int(11) NOT NULL,
`salt` varchar(32) NOT NULL,
`ip` varchar(255) NOT NULL,
`useragent` varchar(255) NOT NULL,
`request_time` varchar(32) NOT NULL,
`last_active` datetime NOT NULL,
`salt_rounds` tinyint(2) NOT NULL,
`is_admin` tinyint(1) NOT NULL DEFAULT '0',
`is_prof` tinyint(1) NOT NULL DEFAULT '0',
`is_active` tinyint(1) NOT NULL DEFAULT '0',
`wrong_login_count` tinyint(2) NOT NULL DEFAULT '0',
PRIMARY KEY (`user_ID`),
UNIQUE KEY `username` (`username`),
KEY `course_IDFK` (`course_IDFK`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
 */

##
# Usage:
# every site needs the following (really):
# ini_set( 'session.use_only_cookies', '1' );
# ini_set( 'session.use_trans_sid', '0' );
# session_start();
# ---------------------------------------------
# call the script:
#		register
#			create a html form, 
#			make $User = new user() and then $User->register($_POST['username'], $_POST['password'], $_POST['course'], 12) // if last number gets higher the passwordhash gets stronger
# 		it returns true if the registration was successfull
#		login
#			needs also the following:
#			if (!isset($_SESSION['server_SID'])){
#				session_unset();
#				$_SESSION = array();
#				session_destroy();
#				session_start();
#				session_regenerate_id();
#				$_SESSION['server_SID'] = true;
#			}
#			create a login form, $User = new user() and then $User->login($_POST['username'], $_POST['password'])
# 		returns true if login was successfull
#		logout
#			$User = new user(), $User->logout()
#		check if a visitor is a logged in user
#			$User = new user(), then $User->check_user() has to return true if he is logged in
# ---------------------------------------------
# things that have to be defined:
/*
	 define('USERS','users'); // table with user information
	 define('ID', 'user_ID'); // ID for the table, auto increment
	 define('USER', 'username'); // field with all usernames/loginnames
	 define('PASS', 'password'); // field with all secured passwords
	 define('SALT_ADD', 'salt'); // field with an additional salt
	 define('IP', 'ip'); // field with ip address
	 define('USERAGENT', 'useragent'); // field with useragnet
	 define('REQUEST_TIME', 'request_time'); // field with useragnet
	 define('LIVESIGN', 'last_active'); // field with last logged activity
	 define('SALT_ROUNDS', 'salt_rounds'); // field with amount of rounds for salting the pw hash
	 define('COURSE_IDFK', 'course_IDFK'); // ID for table course, auto increment
	 define('IS_ADMIN', 'is_admin'); // 1 if user is a admin, 0 if not
	 define('IS_PROF', 'is_prof'); // 1 if user is a prof, 0 if he's a student
	 define('IS_ACTIVE', 'is_active'); // 1 if user is enabled, 0 if not
	 define('WRONG_LOGIN_COUNT', 'wrong_login_count'); // number of wrong logins*/
# ---------------------------------------------
# the get_mysqli method:
/*function get_mysqli($charset = false){
	global $host;
	global $user;
	global $password;
	global $database;

	if($charset === false OR !is_string($charset)){
	$charset = 'utf8';
	}
	$mysqli = mysqli_init();
	if($mysqli->real_connect($host, $user, $password, $database)){
	unset($host);
	unset($user);
	unset($password);
	unset($database);
	$mysqli->query("SET 
	character_set_results = '".$charset."', 
	character_set_client = '".$charset."', 
	character_set_connection = '".$charset."', 
	character_set_database = '".$charset."', 
	character_set_server = '".$charset."'
	");
	return $mysqli;
	}else{
	die('Connect Error ('.mysqli_connect_errno().') '.mysqli_connect_error());
	}
	}*/
##
class User {

	/* Some userstats */
	private $login_time 				= false;
	private $username 					= false;
	private $max_failed_logins	= 10; 		// set number of max wrong login attempts 
	private $sql 								= false;
	private $is_enabled					= true; 	// set to false if new users should be disabled
	private $is_admin						= false;	// set to true if new users should have admin privileges
	private $is_prof						= false;	// set to true if new users are profs, else they are students
	private $error							= '';			// string with the last error

	function  __construct(){
		$this->sql = get_mysqli();
	}
	/**
	 * login
	 *
	 * check if we got valid login credentials (for later: check for max failed login attempts, set user to [dis|en]abled)
	 *
	 * @param String $user
	 * @param String $password
	 *
	 * @return TRUE if login was successfull
	 * @return FALSE if not
	 */
	public function login($user, $password){
		$user = $this->sql->real_escape_string($user);
		$password = $this->sql->real_escape_string($password);
		/* search user in db */
		$query = "SELECT 
			`".PASS."`,
			`".ID."`,
			`".COURSE_IDFK."`,
			`".SALT_ADD."`,
			`".IS_PROF."`
				FROM
				`".USERS."`
				WHERE
				`".USER."` = '".$user."'";
		if($result = $this->sql->query($query)){
			/* if we found one valid username... */
			if ($result->num_rows == 1){
				$data = $result->fetch_object();
				$result->close();
				/* recreate the string that was used for passwordhashgenerating at the registration */
				$salt_add = SALT_ADD;
				$string = hash_hmac("whirlpool", str_pad($password, strlen($password)*4, sha1($user), STR_PAD_BOTH), $data->$salt_add, true );
				/* rehash the password with the previously created string */
				$pass = PASS;
				$new_hash = crypt($string, substr($data->$pass, 0, 30));
				/* if this new hash is the same as the selected from the db, then the username/password combination is right */
				if($new_hash == $data->$pass){
					/* update the userinformation inside the db and set the session */
					$query = "UPDATE
						`".USERS."`
						SET
						`".IP."` = '".$this->sql->real_escape_string($_SERVER['REMOTE_ADDR'])."',
						`".USERAGENT."` = '".$this->sql->real_escape_string($_SERVER['HTTP_USER_AGENT'])."',
						`".REQUEST_TIME."` = '".$this->sql->real_escape_string(md5($_SERVER['REQUEST_TIME']))."',
						`".LIVESIGN."` = NOW()
							WHERE
							`".USER."` = '".$user."'
							LIMIT 1";
					if ($this->sql->query($query)){
						$_SESSION['angemeldet']   = true;
						$_SESSION['benutzername'] = $user;
						$course 									= COURSE_IDFK;
						$_SESSION['course']				= $data->$course;
						$is_prof 									= IS_PROF;
						$_SESSION['is_prof']			=	$data->$is_prof;
						$id 											= ID;
						$_SESSION['user_id']			= $data->user_ID;
						$_SESSION['anmeldung']    = md5($_SERVER['REQUEST_TIME']);
						return TRUE;
					}else{
						return FALSE;
					}
				}else{
					// we could place here the error handler for failed login
					RETURN FALSE;
				}
			}else{
				return FALSE;
			}
		}else{
			return FALSE;
		}
	}

	/**
	 * update_failed_logins
	 *
	 * increase or reset the failed_logins filed in the db
	 */
	public function update_failed_logins(){

	}

	/**
	 * logout
	 *
	 * We logout the user
	 */
	public function logout(){
		session_destroy();
		header("location: index.php");
		exit;
	}

	/**
	 * validate_username
	 *
	 * check if the username has only valid chars and is unique, if $exists is set, the user has to be in the database
	 *
	 * @param String $user
	 * @param String $exists
	 *
	 * @return TRUE if username is correct, free and $exists false(for registration), OR if username is correct and once inside the db (logincheck)
	 * @return FALSE for every other case
	 */
	public function validate_username($user, $exists){
		if(empty($user)){
			return FALSE;
		}elseif(!is_string($user)){
			return FALSE;
		}elseif($user != trim($user)){
			return FALSE;
		}elseif($user != $this->sql->real_escape_string($user)){
			return FALSE;
		}else{
			$user = $this->sql->real_escape_string(trim($user));
			if($result = $this->sql->query("SELECT 
						`".USER."` 
						FROM 
						`".USERS."` 
						WHERE 
						`".USER."` = '".$user."'")){
				/* return true if one user found and we want an existing user */
				if($result->num_rows === 1 AND $exists){
					return TRUE;
					/* return true if no user found and we want to add an new one */
				}elseif($result->num_rows === 0 AND !$exists){
					return TRUE;
				}
		}	
		}
		return FALSE;
	}

	/**
	 * validate_password
	 *
	 * check if the password has only valid chars and is unique 
	 *
	 * @param string $password	
	 *
	 * @return TRUE/FALSE if the password is save or insecure
	 */
	private function validate_password($password){
		return TRUE;
	}

	/**
	 * register
	 *
	 * User can signup
	 *
	 * @param string $user
	 * @param string $password
	 * @param int		$course		whats the course that the people is visiting?
	 * @param int		$rounds		how good should the password be saved?
	 *
	 * @return TRUE/FALSE if the user was successfully added to the db or not
	 */
	public function register($user, $password, $course, $rounds){
		// get current time 
		$salt_add = md5(microtime());
		if(empty($rounds) OR !is_int($rounds)){
			$rounds = 08; 
		}
		if(!is_int($course)){
			return FALSE;
		}
		if(!$this->validate_password($password)){
			return FALSE;
		}elseif(!$this->validate_username($user, false)){
			return FALSE;
		}else{
			$course = $this->sql->real_escape_string($course);
			$user = $this->sql->real_escape_string($user);
			// extend the password with usernamehash
			$password_hash = str_pad($password, strlen($password)*4, sha1($user), STR_PAD_BOTH);
			// create hash from hmac
			$string = hash_hmac("whirlpool", $password_hash, $salt_add, true);
			// get a bunch (22) of random chars and numbers 
			$salt = substr(str_shuffle('./0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' ),0,22);
			// create a blowfish hash
			$pw_hash = crypt($string, '$2a$'.$rounds.'$'.$salt);
			// write everything to the database
			$query = "INSERT INTO 
				`".USERS."` 
				( `".USER."`, 
					`".PASS."`, 
					`".SALT_ADD."`,
					`".IP."`,
					`".USERAGENT."`,
					`".REQUEST_TIME."`,
					`".LIVESIGN."`,
					`".SALT_ROUNDS."`,
					`".COURSE_IDFK."`,
					`".IS_ACTIVE."`,
					`".IS_PROF."`,
					`".IS_ADMIN."`)
				VALUES
				( '".$user."',
					'".$pw_hash."',
					'".$salt_add."',
					'".$_SERVER['REMOTE_ADDR']."',
					'".$_SERVER['HTTP_USER_AGENT']."',
					'".$_SERVER['REQUEST_TIME']."',
					'0000-00-00 00:00:00',
					'".$rounds."',
					'".$course."',
					'".$this->is_enabled."',
					'".$this->is_prof."',
					'".$this->is_admin."'
				)";
			if($this->sql->query($query)AND $this->sql->affected_rows == 1){
				return TRUE;
			}else{
				return FALSE;
			}
		}
	}

	/**
	 * check_user
	 *
	 * We look if the visitor is a logged in user or a stranger
	 *
	 * @return TRUE if the visitor is a logged in user
	 * @return FALSE if he is a stranger
	 */
	public function check_user(){
		session_regenerate_id(true);
		if((isset($_SESSION['angemeldet']) AND $_SESSION['angemeldet'] !== TRUE) OR !isset($_SESSION['angemeldet'])){
			return FALSE;
		}else{
			$user = $this->sql->real_escape_string($_SESSION['benutzername']);
			$query = "SELECT
				`".IP."`, 
				`".USERAGENT."`,
				`".REQUEST_TIME."`,
				UNIX_TIMESTAMP(`".LIVESIGN."`) as ".LIVESIGN."
					FROM
					`".USERS."`
					WHERE
					`".USER."` = '".$user."'";
			$result = $this->sql->query($query);
			if ($result->num_rows == 1){
				$data = $result->fetch_object();
				$result->close();
				$ip 					= IP;
				$useragent 		= USERAGENT;
				$request_time = REQUEST_TIME;
				$livesign			= LIVESIGN;
				if ($data->$ip !== $_SERVER['REMOTE_ADDR']) return FALSE;
				if ($data->$useragent !== $_SERVER['HTTP_USER_AGENT']) return FALSE;
				if ($data->$request_time !== $_SESSION['anmeldung']) return FALSE;
				if (($data->$livesign + 6000) <= $_SERVER['REQUEST_TIME']) return FALSE;
			}else{
				return FALSE;
			}
			$query = "UPDATE
				`".USERS."`
				SET
				`".LIVESIGN."` = NOW()
				WHERE
				`".USER."` = '".$user."'
				LIMIT 1";
			if($this->sql->query($query)){
				return TRUE;
			}else{
				return FALSE;
			}
		}
	}
}

?>
