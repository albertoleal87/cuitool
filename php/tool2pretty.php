<?php
	require_once("xmlprettyprint.php");
	if (isset($_GET['a'])) {
		$xmltext = $_GET['a'];
	} elseif (isset($_POST['a'])) {
		$xmltext = $_POST['a'];
	}
	if ($xmltext === "") {
		die("No hay informacion");
	}
	echo xmlPrettyprint($xmltext);
?>

