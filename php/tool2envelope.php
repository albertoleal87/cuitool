<?php

# Tool1Envelope
require_once("xmlprettyprint.php");

# Hay algo?
if (isset($_GET['a'])) {
	$xmltext = $_GET['a'];
} elseif (isset($_POST['a'])) {
	$xmltext = $_POST['a'];
}
if ($xmltext === "") {
	die("No hay informacion");
}
if (isset($_GET['d'])) {
	$docNode = $_GET['d'];
} elseif (isset($_POST['d'])) {
	$docNode = $_POST['d'];
}

# Ensobreta :: En realidad es doble ensobretado primero en request, actualiza la info basica y finalmente en soap
# Convierte a modelo DOM
$cfdi = new DOMDocument();
$cfdi->loadXML($xmltext) or die("XML no valido");
# Valida CFDi contra esquema
$cfdi->schemaValidate('../xsd/cfdv32.xsd') or die("- Estructura CFDi 3.2 \n");

# Sobre
$envtext = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tim="http://www.buzonfiscal.com/ns/xsd/bf/TimbradoCFD" xmlns:req="http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI" xmlns:cfdi="http://www.sat.gob.mx/cfd/3">
	<soapenv:Header/>
	<soapenv:Body>
		<tim:RequestTimbradoCFD>
			<req:InfoBasica RfcEmisor="" RfcReceptor=""/>
		</tim:RequestTimbradoCFD>
	</soapenv:Body>
</soapenv:Envelope>
XML;
$env = new DOMDocument();
$env->loadXML($envtext) or die("\n\n\nError interno en el sobre");

# Extrae el nodo cfdi del cfdi
$c = $cfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
### Regla de negocio. NO Ensobreta si no viene firmado
if ($c->getAttribute('noCertificado') === "") {
	die("\n\n\nRegla de negocio. NO Ensobreta si no viene firmado");
}
## Regla de negocio. NO Ensobreta si no viene firmado

# Ensobreta Comprobante
$t = $env->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/TimbradoCFD', 'RequestTimbradoCFD')->item(0);
if ($docNode == 3) { # Documento GZIP
	$doc = $env->createElementNS('http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI', "Documento");
	$doc->setAttribute('Tipo', 'GZ');
	$doc->setAttribute('Version', '3.2');
	$doc->setAttribute('Archivo', base64_encode(gzcompress($xmltext, 9)));
	$env->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/TimbradoCFD', 'RequestTimbradoCFD')->item(0)->appendChild($doc);
} elseif ($docNode == 2) { # Documento ZIP
	$zip = new ZipArchive;
	$rzip = $zip->open('temp/temp.zip', ZipArchive::CREATE | ZIPARCHIVE::OVERWRITE | ZIPARCHIVE::CM_REDUCE_4);
	if (!$rzip) die("\n\n\nNo pudo crear un temporal");
	$zip->addFromString('cfdi.txt', $xmltext);
	$zip->close();
	$doc = $env->createElementNS('http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI', "Documento");
	$doc->setAttribute('Tipo', 'ZIP');
	$doc->setAttribute('Version', '3.2');
	$doc->setAttribute('NombreArchivo', 'cfdi.txt');
	$doc->setAttribute('Archivo', base64_encode(file_get_contents("temp/temp.zip")));
	$env->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/TimbradoCFD', 'RequestTimbradoCFD')->item(0)->appendChild($doc);
} elseif ($docNode == 1) { # Documento XML
	$doc = $env->createElementNS('http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI', "Documento");
	$doc->setAttribute('Tipo', 'XML');
	$doc->setAttribute('Version', '3.2');
	$doc->setAttribute('Archivo', base64_encode($xmltext));
	$env->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/TimbradoCFD', 'RequestTimbradoCFD')->item(0)->appendChild($doc);
} else { # Comprobante
	$c = $env->importNode($c, true);
	$t->appendChild($c);
}

# Listo! ensobretado. Normaliza infoBasica
$t->getElementsByTagName('InfoBasica')->item(0)->setAttribute('RfcEmisor',   $c->getElementsByTagName('Emisor'  )->item(0)->getAttribute('rfc'));
$t->getElementsByTagName('InfoBasica')->item(0)->setAttribute('RfcReceptor', $c->getElementsByTagName('Receptor')->item(0)->getAttribute('rfc'));

# Regresa el resultado
echo xmlprettyprint($env->saveXML());
?>
