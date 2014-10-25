<?php

require_once('xmlprettyprint.php');

# Get parameters
if (isset($_GET['a'])) {
	$xmltext = $_GET['a'];
} elseif (isset($_POST['a'])) {
	$xmltext = $_POST['a'];
}
if ($xmltext === "") {
	die("No hay informacion");
}
# Convierte a modelo DOM
$env = new DOMDocument();
$env->loadXML($xmltext) or die("\n\n\nXML no valido");

# Valida
$env->schemaValidate('sopa.xsd') or die("\n\n\nNo es un sobre valido");

# Unenvelopea
# Desensobreta :: En realidad extrae el nodo CFDi, no solo para manipular sino para validar contra esquema

if ($env->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0) != null) {
	# Convierte a modelo DOM
	$cfdi = new DOMDocument('1.0', 'UTF-8');
	# Extrae el nodo cfdi del cfdi
	$paso2 = $env; ## copia o referencia?
	$paso4 = $paso2->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
	$paso4 = $cfdi->importNode($paso4, true);
	$cfdi->appendChild($paso4);

	# Listo, devuelve el resultado
	echo xmlprettyprint($cfdi->saveXML());
} elseif ($env->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI', "Documento")->item(0) != null) {
	if ($env->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI', "Documento")->item(0)->getAttribute("Tipo") === "XML") {
	# Extrae texto
	$texto = base64_decode($env->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI', "Documento")->item(0)->getAttribute("Archivo"));
	# Listo, devuelve el resultado
	} elseif ($env->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI', "Documento")->item(0)->getAttribute("Tipo") === "GZ") { # GZIP
		$texto = gzuncompress(base64_decode($env->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI', "Documento")->item(0)->getAttribute("Archivo")));
	} else { # ZIP
		file_put_contents("temp/tempo.zip", base64_decode($env->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI', "Documento")->item(0)->getAttribute("Archivo")));
		$zip = new ZipArchive;
		$rzip = $zip->open('temp/tempo.zip');
		if (!$rzip) die("\n\n\nNo pudo crear un temporal");
		$texto = $zip->getFromName($env->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI', "Documento")->item(0)->getAttribute("NombreArchivo"));
		$zip->close();
	}
	echo xmlprettyprint($texto);
}
?>

