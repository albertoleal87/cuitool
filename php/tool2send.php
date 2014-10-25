<?php

# Tool1Send : Manda timbrar el cfdi y regresa la respuesta, sea cual sea
require_once('xmlprettyprint.php');

# Hay algo?
$xmltext = "";
if (isset($_GET['a'])) {
	$xmltext = $_GET['a'];
} elseif (isset($_POST['a'])) {
	$xmltext = $_POST['a'];
}
if ($xmltext === "") {
	die("No hay informacion");
}

$certtext = "";
if (isset($_GET['b'])) {
	$certtext = $_GET['b'];
} elseif (isset($_POST['b'])) {
	$certtext = $_POST['b'];
}
if ($certtext === "") {
	die("No hay certificado");
}

$seal = false; $debug = false; $envia = "demo";
if (isset($_GET['c'])) {
	if ("integrate" === $_GET['c']) { $seal = true; };
	if ("debug" === $_GET['c']) { $debug = true; };
	if ("php" === $_GET['c']) { $envia = "php"; };
	if ("cpp" === $_GET['c']) { $envia = "cpp"; };
} elseif (isset($_POST['c'])) {
	if ("integrate" === $_POST['c']) { $seal = true; };
	if ("debug" === $_POST['c']) { $debug = true; };
	if ("php" === $_POST['c']) { $envia = "php"; };
	if ("cpp" === $_POST['c']) { $envia = "cpp"; };
}

# Convierte a modelo DOM
$env = new DOMDocument();
$env->loadXML($xmltext) or die("\n\n\nXML no valido");

if ($debug) {
# Valida primero

# Regla 1: Que el sobre este bien
$env->schemaValidate('../xsd/sopa.xsd') or die("\n\n\nNo es un sobre valido");

# Regla 2: Que los rfcs coincidan
$info = $env->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI', 'InfoBasica')->item(0);
$cfdi = $env->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
if ($info->getAttribute("RfcEmisor") !== $cfdi->getElementsByTagName('Emisor')->item(0)->getAttribute('rfc') || $info->getAttribute("RfcReceptor") !== $cfdi->getElementsByTagName('Receptor')->item(0)->getAttribute('rfc')) {
	die("\n\n\nLos rfcs no coinciden");
}

# Regla 3: Verifica la firma

# Desensobreta :: En realidad extrae el nodo CFDi, no solo para manipular sino para validar contra esquema
# Convierte a modelo DOM
$paso = new DOMDocument('1.0', 'UTF-8');
# Extrae el nodo cfdi del cfdi
$paso2 = $env; ## copia o referencia?
$paso4 = $paso2->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
$paso4 = $paso->importNode($paso4, true);
$paso->appendChild($paso4);

# Extrae cadena original
$xslt = new XSLTProcessor();
$XSL = new DOMDocument();
$XSL->load( '../xslt/cadenaoriginal_3_0.xslt', LIBXML_NOCDATA);
error_reporting(0);
$xslt->importStylesheet( $XSL );
error_reporting(E_ALL);
$cadena = $xslt->transformToXML( $paso );
# DEBUG echo "[$cadena]\n";

$base = $cfdi->getAttribute("certificado");
$cert2  = "-----BEGIN CERTIFICATE-----\n";
$cert2 .= chunk_split($base, 64, "\n");
$cert2 .= "-----END CERTIFICATE-----\n";
# echo "$cert2\n";
if (!($pkey = openssl_pkey_get_public($cert2))) {
	echo "\n\n\nNo es posible extraer llave publica\n";
	echo "$cert2\n";
	die;
}
# print_r(openssl_pkey_get_details($pkey));

# Extrae sello
$crypttext = base64_decode($cfdi->getAttribute("sello"));
# DEBUG echo "[", base64_encode($crypttext), "]\n";
openssl_verify($cadena, $crypttext, $pkey) or die("Error en el firmado!!!\n");

# SOAP verificado

# Checa certificado
# Extrae la llave privada, en formato openssl
openssl_pkey_get_private($certtext) or die("No llave privada en el certificado\n");
openssl_pkey_get_public($certtext) or die("No llave publica en el certificado\n");
# DEBUG echo "todobien\n";

}

# mete el certificado en un temp.cert
file_put_contents('..\temp\temp.cert', $certtext) or die("No pudo escribir el archivo\n");

# Envia a timbrar
# Enviamos el cURL
if ($envia === "php") {
	$process = curl_init('http://172.17.1.207/timbre/timbrado/index.php');
} elseif ($envia === "cpp") {
	$process = curl_init('http://172.17.1.207/cgi-bin/ejemplos');
} else { # java1
	$process = curl_init('https://demotf.buzonfiscal.com/timbrado');
	curl_setopt($process, CURLOPT_SSLCERT, 'C:\wamp\www\demovalidador\temp\temp.cert');
}
curl_setopt($process, CURLOPT_HTTPHEADER, array('Content-Type: text/xml', 'charset=utf-8'));
curl_setopt($process, CURLOPT_POSTFIELDS, $env->saveXML());
curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
curl_setopt($process, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($process, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($process, CURLOPT_POST, true);

# curl_setopt($process, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
# curl_setopt($process, CURLOPT_HTTPHEADER, array('Content-Type: text/xml', 'charset=utf-8', 'Expect:'));

$return = curl_exec($process);

echo curl_error($process);
curl_close($process);


# regresa el resultado
if (!$seal) {

// echo "$return \nEND\n ";


echo htmlentities($return);


	# echo xmlprettyprint($return);
} else {
	# ok, seguimos. Lo primero es validar que realmente haya regresado un timbre
	$sobretimbre = new DOMDocument();
	$sobretimbre->loadXML($return) or die("\n\n\nXML de respuesta no valido\n$return");
	# Extrae el timbre (si existe)
	$timbre = new DOMDocument('1.0', 'UTF-8');
	# Extrae el nodo
	$paso2 = $sobretimbre; ## copia o referencia?
	$paso4 = $paso2->getElementsByTagNameNS('http://www.sat.gob.mx/TimbreFiscalDigital', 'TimbreFiscalDigital')->item(0);
	$paso4 = $timbre->importNode($paso4, true);
	$timbre->appendChild($paso4);
	# DEBUG echo $timbre->saveXML(), "\n";
	# Valida
	$timbre->schemaValidate('TimbreFiscalDigital.xsd') or die("\n\n\nError de validacion\n$return");
	$cfdi = $env->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
	$complemento = $cfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Complemento')->item(0);
	if (!$complemento) {
		$complemento = $env->createElementNS('http://www.sat.gob.mx/cfd/3', 'Complemento');
		$cfdi->appendChild($complemento);
	}
	$t = $timbre->getElementsByTagNameNS('http://www.sat.gob.mx/TimbreFiscalDigital', 'TimbreFiscalDigital')->item(0);
	$t = $env->importNode($t, true);
	$complemento->appendChild($t);
	echo xmlprettyprint($env->saveXML());
}
?>
