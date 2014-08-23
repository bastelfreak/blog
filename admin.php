<?php
require('functions.php');
error_reporting(E_ALL); 
ini_set( 'display_errors','1');
$debug = true;
$mysqli = get_mysqli();
$category = '';
$output = '';
$created = date('d-m-Y H:i:s');
$last_modified = '';
$content = '';
$title = '';
$blog = '';

if (isset($_GET['category']) AND !empty($_GET['category'])) {
	$category = $mysqli->real_escape_string($_GET['category']);
//	$output .= "<div class='box' style='margin-bottom: 3px;color: white; font-weight: bold;background-color: green; text-align: center; width: auto;'>".print_r($_GET,true).", category hat den wert ".$category."</div>\n";
}
/* delete a post */
# check if there are comments related to this post
if(isset($_GET['delete']) AND !empty($_GET['delete'])){
	if($mysqli->query("DELETE FROM posts WHERE id='".$mysqli->real_escape_string($_GET['delete'])."'")){
		if($debug){
			$output .= "<div class='box' style='margin-bottom:3px;color:white;font-weight:bold;background-color:green;width:auto;'>Post erfolgreich gel&ouml;scht</div>\n";
		}
	}else{
		$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: red;font-weight:bold; text-align: center; width: auto;'>Post ll&ouml;schen fehlgeschlagen :(</div>\n";
		if($debug){
			$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: red;font-weight:bold; text-align: center; width: auto;'>".$mysqli->real_escape_string($_GET['delete'])."<br>".$mysqli->error."</div>\n";
		}
	}
}

/* update an post */
if (isset($_POST['save']) AND $_POST['save'] === "Speichern" AND isset($_GET['id'])){
	$query = "UPDATE posts SET 
						title='".$mysqli->real_escape_string($_POST['title'])."',
						content='".$mysqli->real_escape_string($_POST['content'])."',
						category='".$mysqli->real_escape_string($_POST['category'])."',
						last_modified='".$mysqli->real_escape_string($_GET['last_modified'])."',
						created='".$mysqli->real_escape_string($_GET['created'])."'
						WHERE id='".$mysqli->real_escape_string($_GET['id'])."'";
	if($mysqli->query($query)){
		$output .= "<div class='box' style='margin-bottom: 3px;color: white; font-weight: bold;background-color: green; text-align: center; width: auto;'>Gespeichert!<br>".$query."</div>\n";
	}else{
		$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: red;font-weight:bold; text-align: center; width: auto;'>Fehlgeschlagen!</div>\n";
		if($debug){
			$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: red;font-weight:bold; text-align: center; width: auto;'>".$mysqli->error."</div>\n";
		}
	}
}

/* select the current post */
$category_form_category = '';
if(isset($_GET['id'])){
	$result = $mysqli->query("SELECT content,title,created,last_modified FROM posts WHERE id='".$mysqli->real_escape_string($_GET['id'])."'");	
	if($result->num_rows == 1){
		$data = $result->fetch_object();
		$content = $data->content;
		$title = $data->title;
		$created = $data->created;
		$last_modified = $data->last_modified;
		$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: green; font-weight:bold; text-align: center; width: auto;'>".$last_modified." ".$created."</div>\n";
		$result->close();
		if($debug){
			$output .= "<div class='box' style='margin-bottom: 3px;color: white; font-weight: bold;background-color: green; text-align: center; width: auto;'>Erfolgreich aktuellen Datensatzaus der DB gelesen</div>\n";
		}
	}
}
/* create new post */
if(
		isset($_POST['save']) && 
		$_POST['save'] == 'Speichern' && 
		!isset($_GET['id']) && 
		isset($_POST['title']) &&
		!empty($_POST['title']) &&
		isset($_POST['content']) &&
		!empty($_POST['content'])){
	if($debug){
		$output .= "<div class='box' style='margin-bottom:3px;color:white;font-weight:bold;background-color:green;width:auto;'><pre>".print_r($_POST, true)."</pre></div>\n";
	}
	$query = "INSERT INTO posts (
							title, 
							content, 
							category,
							created
						) VALUES ( 
							'".$mysqli->real_escape_string($_POST['title'])."', 
							'".$mysqli->real_escape_string($_POST['content'])."', 
							'".$mysqli->real_escape_string($_POST['category'])."',
							'".$mysqli->real_escape_string($_POST['created'])."'
						)";
	if($mysqli->query($query)){
		$output .= "<div class='box' style='margin-bottom: 3px;color: white; font-weight: bold;background-color: green; text-align: center; width: auto;'>Gespeichert!</div>\n";
	}else{
		$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: red;font-weight:bold; text-align: center; width: auto;'>Fehlgeschlagen!</div>\n";
		if($debug){
			$output .= "<div class='box' style='margin-bottom: 3px;color: white;background-color: red;font-weight:bold; text-align: center; width: auto;'>".$mysqli->error."<br>".$query."</div>\n";
		}
	}	
}
/* get some stuff */
$blog = get_posts($category, true); 
$menu = create_category_menu(false);
$form = create_category_form($category);

?>
			
<!DOCTYPE html>
<html>
	<head>
		<title>Backend</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"> 
		<link rel='stylesheet' type='text/css' href='/style.css'>
	</head>
	<body>
		<div id="main" style="width:100%;">
			<?php if($debug){echo $output."<pre>".print_r($_SERVER,true)."</pre>";}?>
			<div class="box">
				<h1>Backend</h1>
			</div>
			<div class="box">
				<div class="link"  style="float:left;text-align:center;width:20%;">
					<?php echo $menu;?>
				</div>
				<div class="link" style="width:70%;text-align:left;">
					<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">
						<h3>Ein Artikel kann nur in einer vorhandenen Kategorie erstellt werden</h3>
						<h3>Kategorie '<?php echo STATIC_CATEGORY; ?>' sind statische Seiten ohne Kategorie (Impressum ...)</h3>
						Titel: <input id="title" name="title" maxlength="50" value="<?php echo $title; ?>"><br>
						Erstelldatum: <input id='created' name='created' value='<?php echo $created; ?>'><br>
						<?php if($last_modified != "0000-00-00 00:00:00" AND !empty($last_modified)){echo "Modifiziert: <input id='last_modified' name='last_modified' value='".$last_modified."'><br>\n";}?>
						Kategorie: <?php echo $form;
						echo "<textarea id='text' name='content' rows='16' cols='50'>".$content."</textarea><br>\n"; ?>
						<input type="submit" name="save" value="Speichern">
					</form>
				</div>
				<div style="clear:both"></div>
			</div>
			<div class="box" style="text-align:center">
				<h2>Vorhandene Blogposts</h2>
			</div>
			<?php echo $blog; ?>
		</div>
	</body>
</html>
