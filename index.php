<?php
error_reporting(E_ALL); 
ini_set( 'display_errors','1');
$debug = false;
require('functions.php');
$mysqli = get_mysqli();
$category = '';

if(isset($_GET['post']) and !empty($_GET['post'])){
	$content = get_post($mysqli->real_escape_string($_GET['post']));
}elseif(isset($_GET['category']) and !empty($_GET['category'])){
	$category = $mysqli->real_escape_string($_GET['category']);
	$content = get_posts($category, false);
}else{
	$content = get_posts($category, false);
}
$menu = create_category_menu(true);
?>
<!DOCTYPE html>
<html>
	<head>
		<title>bastelfreak</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"> 
		<link rel='stylesheet' type='text/css' href='/style.css'>

	</head>
	<body>
		<div id="main" style="height:100%">
			<?php if($debug){echo "<div class='box'>".$output."</div>";}?>
			<div class="box" style="padding:0px">
				<!-- <img style="width:100%" src="http://blog.bastelfreak.de/wp-content/themes/twentyten/images/headers/path.jpg"> -->
				<img style="width:100%;display:block;" src="controller.png" alt="head picture">
				<!--<div class="wrapper">
					<div class="container"> -->
						<?php echo $menu;?>
					<!--</div>
				</div>-->
			</div>
			<?php echo $content; ?>
		</div>
	</body>
</html>
