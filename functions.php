<?php
require('config.php');
/* create a menu with every category */
function create_category_menu($bar){
	$mysqli = get_mysqli();
	if($result = $mysqli->query("SELECT id,title FROM categories WHERE 1")){
		global $output;
		if($bar){
			$menu = "<ul class='clearfix' style='list-style:none;text-align:center;margin:auto;padding:0'>\n";
			$menu .= "<li><a href='".$_SERVER['SCRIPT_NAME']."'>Alle Seiten</a></li>\n";
			//$menu = "<ul class='menu' rel='sam1'>\n";
		}else{
			$menu = "<ul style='list-style:none;margin:0;padding:0'>\n";
			$menu .= "<li><a href='".$_SERVER['SCRIPT_NAME']."'>Alle Seiten</a></li>\n";
		}
		while($data = $result->fetch_object()){
			if($bar and $data->title != STATIC_CATEGORY){
				$menu .= "<li><a href='".$_SERVER['SCRIPT_NAME']."?category=".$data->id."'>".$data->title."</a></li>\n";
			}elseif($bar and $data->title == STATIC_CATEGORY){
				if($result2 = $mysqli->query("SELECT id, title FROM posts WHERE category='".$data->id."'")){
					while($data2 = $result2->fetch_object()){
						$menu .= "<li><a href='".$_SERVER['SCRIPT_NAME']."?post=".$data2->id."'>".$data2->title."</a></li>\n";
					}
				}else{
					$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: red;font-weight:bold; text-align: center; width: auto;'>".$mysqli->error."</div>\n";
				}
			}else{
				$menu .= "<li style='width:100%'><a href='".$_SERVER['SCRIPT_NAME']."?category=".$data->id."'>".$data->title."</a></li>\n";
			}
		}
		$menu .= "</ul>\n";
	}else{
		$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: red;font-weight:bold; text-align: center; width: auto;'>".$mysqli->error."</div>\n";
	}
	return $menu;
}

/* create a select form element with every category */
function create_category_form($category){
	global $output;
	global $debug;
	$form = '';
	$mysqli = get_mysqli();
	if($result = $mysqli->query("SELECT id,title FROM categories WHERE 1")){
		$form = "<select name='category'>\n";
		while($data = $result->fetch_object()){
			if($data->id === $category OR (empty($category) AND $data->title === NORMAL_CATEGORY)){
				$form .= "	<option value='".$data->id."' selected>".$data->title."</option>\n";
			}else{
				$form .= "	<option value='".$data->id."'>".$data->title."</option>\n";
			}
		}
		$form .= "</select><br>\n";
	}elseif($debug){
		$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: red;font-weight:bold; text-align: center; width: auto;'>".$mysqli->error."</div>\n";
	}elseif(!$debug){
		$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: red;font-weight:bold; text-align: center; width: auto;'>MySQL hat einen Fehler</div>\n";
	}
	return $form;
}

/* create a mysqli connection and set it to $charset (default utf8) */
function get_mysqli($charset = false){
	global $host;
	global $user;
	global $password;
	global $database;
	
	if(!$charset){
		$charset = 'utf8';
	}
	$mysqli = mysqli_init();
	if($mysqli->real_connect($host, $user, $password, $database)){
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
}

/* make a nice and fancy output with every blogpost in category $category(if false = every category, if $admin we get links to the admin panel)*/
function get_posts($category, $admin){
	$mysqli = get_mysqli();
	$script_name = $mysqli->real_escape_string($_SERVER['SCRIPT_NAME']);
	global $output;
	global $debug;
	$blog = '';
	$query = '';
	$admin_line = '';
	if(empty($category) and $admin === false){
		$query = "SELECT 
								posts.content, 
								posts.title as post_title, 
								posts.id, 
								posts.category, 
								posts.created, 
								posts.last_modified, 
								categories.title as category_title
							FROM 
								posts, 
								categories
							WHERE
								categories.id = posts.category
							ORDER BY posts.created DESC
							";
	}elseif(!empty($category)and $admin === false){
		$query = "SELECT 
								posts.content, 
								posts.title as post_title, 
								posts.id, 
								posts.category, 
								posts.created, 
								posts.last_modified, 
								categories.title as category_title 
							FROM 
								posts, 
								categories
							WHERE
								categories.id = posts.category
							AND
								category = '".$mysqli->real_escape_string($category)."'
							ORDER BY posts.created DESC
							";
	}elseif(empty($category) and $admin === true){
		$query = "SELECT content, title as post_title, id, category, created, last_modified FROM posts WHERE 1 ORDER BY created DESC";
	}elseif(!empty($category) and $admin === true){
		$query = "SELECT content, title as post_title, id, category, created, last_modified FROM posts WHERE category = '".$mysqli->real_escape_string($category)."' ORDER BY created DESC";
		
	}
	if($result = $mysqli->query($query)){
		while($data = $result->fetch_object()){
			if($data->last_modified === "0000-00-00 00:00:00"){
				$date = $data->created;
			}else{
				$date = $data->last_modified;
			}
			if($admin){
				$admin_line = " || <a href='".$script_name."?category=".$data->category."&amp;id=".$data->id."'><img src='report_edit.png' alt='edit this post'></a> || <a href='".$script_name."?category=".$data->category."&amp;delete=".$data->id."'><img src='delete.png'  alt='Post l%ouml;schen'></a>";
				$blog .= "<fieldset class='box'><legend>".$data->post_title." || ".$date.$admin_line."</legend>".$data->content."</fieldset>\n";
			}else{
				$blog .= "<fieldset class='box'><legend><a href='".$script_name."?post=".$data->id."'>".$data->post_title."</a> || <a href='".$script_name."?category=".$data->category."'>".$data->category_title."</a> || ".$date."</legend>".$data->content."</fieldset>\n";
			}
		}
		if($debug){
			$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: green; font-weight:bold; text-align: center; width: auto;'>Erfolgreich ".$result->num_rows." Blogposts aus der DB gelesen<br>".$query."</div>\n";
		}
		$mysqli->close();
	}elseif($debug){
		$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: red;font-weight:bold; text-align: center; width: auto;'>".$mysqli->error."<br>".$query."</div>\n";
	}
	return $blog;
}

/* just return one post an all comments */ //note: comments will be added later
function get_post($postid){
	global $debug;
	global $output;
	global $mysqli;
	$blog = '';
	$date = '';
	$postid = $mysqli->real_escape_string($postid);
	$script_name = $mysqli->real_escape_string($_SERVER['SCRIPT_NAME']);
	if(!empty($postid)){
		$query = "SELECT 
								posts.title as post_title, 
								posts.content, 
								posts.created, 
								posts.last_modified,
								posts.category,
								categories.title as category_title
							FROM 
								posts,
								categories
							WHERE
								posts.id = '".$postid."'
							LIMIT 1";
		if($result = $mysqli->query($query)){
			if ($result->num_rows == 1){
				$row = $result->fetch_object();
				if($row->last_modified === "0000-00-00 00:00:00"){
					$date = $row->created;
				}else{
					$date = $row->last_modified;
				}
				$blog = "<fieldset class='box'><legend>".$row->post_title." || <a href='".$script_name."?category=".$row->category."'>".$row->category_title."</a> || ".$date."</legend>".$row->content."</fieldset>\n";
				return $blog;
			}
		}elseif($debug){
			$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: red;font-weight:bold; text-align: center; width: auto;'>".$mysqli->error."<br>".$query."</div>\n";
		}
	}else{
		return "wrong id as parameter";
	}
}

/*get all comments related to one post */
function get_comments($postid){
	global $debug;
	global $output;
	global $mysqli;
	$blog = '';
	$date = '';
	$postid = $mysqli->real_escape_string($postid);
	$script_name = $mysqli->real_escape_string($_SERVER['SCRIPT_NAME']);
	if(!empty($postid)){
		$query = "SELECT 
								comments.comment
								comments.mail
								comments.nickname
								comments.created
								commnets.postid
							FROM 
								comments
							WHERE
								comments.postid = '".$postid."'
							LIMIT 1";
		if($result = $mysqli->query($query)){
			while($data = $result->fetch_object()){
				if($data->last_modified === "0000-00-00 00:00:00"){
					$date = $data->created;
				}else{
					$date = $data->last_modified;
				}
				$blog = "<fieldset class='box'><legend>".$data->post_title." || <a href='".$script_name."?category=".$data->category."'>".$data->category_title."</a> || ".$date."</legend>".$data->content."</fieldset>\n";
				return $blog;
			}
		}elseif($debug){
			$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: red;font-weight:bold; text-align: center; width: auto;'>".$mysqli->error."<br>".$query."</div>\n";
		}
	}else{
		return "wrong id as parameter";
	}
}
?>