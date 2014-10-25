<?php

# Get parameters
if (isset($_GET['a'])) {
	$xmltext = $_GET['a'];
} elseif (isset($_POST['a'])) {
	$xmltext = $_POST['a'];
}
if (isset($_GET['b'])) {
	$schema = $_GET['b'];
} elseif (isset($_POST['b'])) {
	$schema = $_POST['b'];
}
if ($xmltext === "" || $schema === "") {
	die("No hay informacion");
}
# Convierte a modelo DOM
$env = new DOMDocument();
$env->loadXML($xmltext) or die("\n\n\nXML no valido");

# Valida
if ($schema === "cfdi") {
	echo ($env->schemaValidate('cfdv3.xsd')) ? "\n\nEsquema válido" : "\n\nEsquema no válido";
} elseif ($schema === "soap") {
	echo ($env->schemaValidate('sopa.xsd')) ? "\n\nEsquema válido" : "\n\nEsquema no válido";
} else {
	echo "\n\nEsquema no reconocido";
}

?>
