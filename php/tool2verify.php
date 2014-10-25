<?php

# Tool1Verify : Checa la integridad del CFDi o sobre SOAP
# version 01. Hector Isais. 2011-02-18T16:21-06:00 - Valida CFDi contra esquema y validez del sello. Si es sobre soap, valida integridad de la infoBasica
# Version 02. Hector Isais. 2011-06-28T22:45-06:00 - Valida el TIMBRE y marca un Warning si no viene el elemento
require_once("check_utf8.php");

$diag = ""; # Ristra de diagnosticos
$is_soap = false;

# Hay algo?
$xmltext = "";
if (isset($_POST['a'])) {
	$xmltext = $_POST['a'];
} elseif (isset($_GET['a'])) {
	$xmltext = $_GET['a'];
}
if ($xmltext === "") die("No hay informacion");

echo "\n\n***************Resultado de la validación***************<br><br>";


# Valida UTF8
if (!mb_check_encoding($xmltext, "UTF-8")) die("El xml no esta en UTF8\n");
if (!check_utf8($xmltext)) die("El string no esta en UTF8\n");
// $diag .= "Ok UTF8<br>";
echo "<font color='#2E8B57'>- Encoding UTF-8</font><br>";

libxml_use_internal_errors(true);

# Convierte a modelo DOM
$xml = new DOMDocument();
//$xml->loadXML($xmltext) or die("XML no valido\n\n$diag");

$xml->loadXML($xmltext) or die("<font color='#8B0000'>- XML no valido</font><br>");



# Identifica si el xml cargado es CFDi o SOAP o Error
## Version 2. Checa SOAP por el namespace http://schemas.xmlsoap.org/soap/envelope/
if (preg_match('|http://schemas.xmlsoap.org/soap/envelope/|', $xmltext, $m)) { # Se presume un SOAP
	if ($xml->schemaValidate('../xsd/sopa.xsd')) {
		$is_soap = true;
		$diag .= "Ok Sobre SOAP detectado\n";
	} else die("Esquema no valido\n\n$diag");
} else { # Se presume un CFDi
	if ($xml->schemaValidate('../xsd/cfdv32.xsd') )  {

echo "<font color='#2E8B57'>- Estructura CFDi 3.2 correcta</font><br>";

//	} else die("Esquema no valido\n\n$diag");
	} 
else die(  " <font color='#8B0000'>- Estructura CFDi 3.2 incorrecta</font><br>  ");

}
unset($m);

# Extrae el cfdi
$docu = $xml->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI', 'Documento')->item(0);
$cfdi = $xml->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
if ($cfdi == null and $docu == null) die ("No se encontro el nodo Comprobante\n\n$diag");
if ($cfdi != null and $docu != null) die ("El sobre contiene ambos nodos Comprobante y Documento\n\n$diag");
if ($docu != null and $cfdi == null) {
	// Convierte el documento y de algun modo extrae un $cfdi valido
	if ($docu->getAttribute("Tipo") === "XML") { // Plano
		$diag .= "Ok nodo Documento detectado\n";
		# Desensobreta el CFDI
		$cfditext = base64_decode($docu->getAttribute("Archivo"));
		$xmlcfdi = new DOMDocument();
		$xmlcfdi->loadXML($cfditext) or die("CFDi XML no valido\n$diag");
		$cfdi = $xmlcfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
		unset($cfditext);
	} elseif ($docu->getAttribute("Tipo") === "GZ") { // GZIP
		$diag .= "Ok nodo Documento-GZIP detectado\n";
		# Desensobreta el CFDI
		$cfditext = gzuncompress(base64_decode($docu->getAttribute("Archivo")));
		$xmlcfdi = new DOMDocument();
		$xmlcfdi->loadXML($cfditext) or die("CFDi XML no valido\n$diag");
		$cfdi = $xmlcfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
		unset($cfditext);
	} else { // ZIP
		$diag .= "Ok nodo Documento-ZIP detectado\n";
		# Desensobreta el CFDI
		file_put_contents("../temp/tempo.zip", base64_decode($docu->getAttribute("Archivo")));
		$zip = new ZipArchive;
		$rzip = $zip->open('../temp/tempo.zip');
		if (!$rzip) die("\n\n\nNo pudo crear un temporal");
		$cfditext = $zip->getFromName($docu->getAttribute("NombreArchivo"));
		$zip->close();
		$xmlcfdi = new DOMDocument();
		$xmlcfdi->loadXML($cfditext) or die("CFDi XML no valido\n$diag");
		$cfdi = $xmlcfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
		unset($cfditext);
	}
} else {
	//$diag .= "Ok nodo Comprobante detectado\n";
	if ($is_soap) {
		# Desensobreta el CFDI
		$xmlcfdi = new DOMDocument('1.0', 'UTF-8');
		# Extrae el nodo cfdi del cfdi
		$paso = $xml->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
		$paso = $xmlcfdi->importNode($paso, true);
		$xmlcfdi->appendChild($paso);
		$cfdi = $xmlcfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
		unset($paso);
	}
}

# Extrae cadena original
$xslt = new XSLTProcessor();
$XSL = new DOMDocument();
$XSL->load( '../xslt/cadenaoriginal_3_2.xslt', LIBXML_NOCDATA);
error_reporting(0);
$xslt->importStylesheet( $XSL ) or die("<font color='#8B0000'>- No fue posible formar la cadena original</font><br>");
error_reporting(E_ALL);
$cadena = $xslt->transformToXML( $cfdi );
if ($cadena !== "|||") {
//	$diag .= "ok Cadena Original = \"$cadena\"\n";
echo "<font color='#2E8B57'>- Cadena original correcta </font><br>";


} else die("No fue posible formar la cadena original\n$diag");

# Valida el certificado
$base = $cfdi->getAttribute("certificado");
$cert2  = "-----BEGIN CERTIFICATE-----\n";
$cert2 .= chunk_split($base, 64, "\n");
$cert2 .= "-----END CERTIFICATE-----\n";
$pkey = openssl_pkey_get_public($cert2);
if ($pkey == null) {
//	die("No es posible extraer llave publica\n$diag");

	die("<font color='#8B0000'>- No es posible extraer llave publica del CSD emisor</font><br>");




}
//$diag .= "Ok Llave publica accesible\n";

echo "<font color='#2E8B57'>- Llave publica accesible</font><br>";


# DEBUG print_r(openssl_pkey_get_details($pkey));

# Decodifica el certificado para efectos de validacion
$cert509 = openssl_x509_read($cert2) or die("No se puede leer el certificado\n$diag");
$data = openssl_x509_parse($cert509) or die("No se puede leer el certificado\n$diag");
#DEBUG print_r($data);

# Extrae fechas validFrom, validTo
$validFrom = $data['validFrom'];
$validTo = $data['validTo'];
if (preg_match('/(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)Z/', $validFrom, $m)) {
	$validFrom = "20{$m[1]}-{$m[2]}-{$m[3]}T{$m[4]}:{$m[5]}:{$m[6]}";
} else die("No es posible extraer las fechas del certificado\n$diag");
if (preg_match('/(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)Z/', $validTo, $m)) {
	$validTo = "20{$m[1]}-{$m[2]}-{$m[3]}T{$m[4]}:{$m[5]}:{$m[6]}";
} else die("No es posible extraer las fechas del certificado\n$diag");
unset($m);
# Valida que la fecha de expedicion esté en el rango de validez del certificado, usando la clase DateTime disponible desde la version 5.2.2
$f = $cfdi->getAttribute("fecha");
$df = new DateTime($f);
$d1 = new DateTime($validFrom);
$d2 = new DateTime($validTo);
$ok = ($d1 <= $df && $df <= $d2);
unset($df, $d1, $d2);
if ($ok) {

//	$diag .= "Ok [$f] entre [$validFrom] y [$validTo]\n";

echo "<font color='#2E8B57'>- Fecha emisión [$f] <br>entre [$validFrom] y [$validTo]</font><br>";


} else {
	echo "[$f] NO está entre [$validFrom] y [$validTo]\n$diag";
	die;
}

# Extrae RFC, Issuer
$rfc2 = $data['subject']['x500UniqueIdentifier'];
if (preg_match('/^([A-Za-z]{3,4}[0-9]{6}\w{3})/', $rfc2, $m)) {
	$rfc = "{$m[1]}";
	if ($rfc === $cfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Emisor')->item(0)->getAttribute("rfc")) {
	//	$diag .= "Ok Verificado que el RFC del certificado coincida con el emisor\n";
echo "<font color='#2E8B57'>- El certificado corresponde al emisor </font><br>";


//	} else echo "ADVERTENCIA: El RFC del certificado no coincide con el emisor\n"; # DIE, pero en este caso solo lanza un warning

} else echo "<font color='#8B0000'>- El RFC del certificado no coincide con el emisor</font><br>"; # DIE, pero en este caso solo lanza un warning


} else die("No es posible extraer el rfc del certificado\n$diag");
$issuer = $data['issuer']['O'];
if ($issuer === "Servicio de Administración Tributaria") {
//	$diag .= "Ok certificado expedido por el SAT\n";

echo "<font color='#2E8B57'>- Certificado expedido por el SAT </font><br>";


} else die("Certificado no expedido por el SAT\n$diag");
$use = $data['extensions']['keyUsage'];
if ("Digital Signature, Non Repudiation" === $use) {

//	$diag .= "Ok verificado que es un certificado NO FIEL\n";

echo "<font color='#2E8B57'>- Firmado por un CSD y no FIEL </font><br>";

} else die ("El uso del certificado es incorrecto, posible FIEL o certificado de conexion");
unset($rfc2, $rfc, $issuer, $use);

#####################
## Serial Number. REQUIRES GMPlib
#####################
$serial1 = $data['serialNumber'];
$serial2 = gmp_strval($serial1, 16);
# chunk_split = inserts \n every n chars
# explode = return array of strings from a \n string
# chr (0x+...
$serial3 = explode("\n", chunk_split($serial2, 2, "\n"));
# DEBUG print_r($serial3);
$serial4 = "";
foreach ($serial3 as $serialt) {
	if (2 == strlen($serialt))
		$serial4 .= chr('0x' . $serialt);
}
# DEBUG echo "$serial1 - $serial2 - $serial4\n";
$serial = $serial4;
unset($serial1, $serial2, $serial3, $serial4, $serialt);

//if ($serial !== $cfdi->getAttribute("noCertificado")) die("El numero de serie del sello no corresponde con el certificado\n");

if ($serial !== $cfdi->getAttribute("noCertificado")) die("<font color='#8B0000'>- El numero de serie del sello no corresponde con el certificado</font><br>");


# Si es soap, valida infobasica
if ($is_soap) {
	$t = $xml->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/TimbradoCFD', 'RequestTimbradoCFD')->item(0);
	if ($t->getElementsByTagName('InfoBasica')->item(0)->getAttribute('RfcEmisor') === $cfdi->getElementsByTagName('Emisor')->item(0)->getAttribute('rfc')) {
		$diag .= "Ok Coincide RFC de emisor en infoBasica y nodo Emisor\n";
	} else die("No coincide RFC de emisor con el nodo Emisor\n$diag");
	if ($t->getElementsByTagName('InfoBasica')->item(0)->getAttribute('RfcReceptor') === $cfdi->getElementsByTagName('Receptor')->item(0)->getAttribute('rfc')) {
		$diag .= "Ok Coincide RFC de receptor en infoBasica y nodo Receptor\n";
	} else die("No coincide RFC de receptor con el nodo Receptor\n$diag");
}

# Extrae sello
$sello = base64_decode($cfdi->getAttribute("sello"));
if (!$sello) die ("No es posible extraer el sello\n$diag");
if (openssl_verify($cadena, $sello, $pkey)) {
	// echo  "El firmado es correcto\n$diag";

echo  "<font color='#2E8B57'>- El sello del emisor es valido</font><br>";

} else die ("<font color='#8B0000'>-Error en el firmado del emisor</font><br>");

###########################################
#### Modificacion 02 - Valida Timbre

$diag = "";

ini_set('display_errors', '1');
error_reporting(0);

$tim = $cfdi->getElementsByTagNameNS('http://www.sat.gob.mx/TimbreFiscalDigital','TimbreFiscalDigital')->item(0);
if ($tim == null) {
//	echo "WARNING: No existe elemento TimbreFiscalDigital\n";

echo  "<font color='#8B0000'>- No existe elemento TimbreFiscalDigital</font><br>";



}

# Validaciones de timbre
# 1. Basica Que el selloCFD coincida con sello
if ($cfdi->getAttribute("sello") == $tim->getAttribute("selloCFD") ) {
//	$diag .= "El sello del timbre coincide con el del comprobante\n";

echo  "<font color='#2E8B57'>- El sello del timbre coincide con el del comprobante</font><br>";


} else die ("Error. El sello del timbre no coincide con el del comprobante\n$diag");
$base = $cfdi->getAttribute("certificado");

# 2. No existe el certificado en el folder
$noCertificado = $tim->getAttribute("noCertificadoSAT");
$der_data = file_get_contents("../sellosPAC/$noCertificado.cer");
$pem = chunk_split(base64_encode($der_data), 64, "\n");
$pem = "-----BEGIN CERTIFICATE-----\n".$pem."-----END CERTIFICATE-----\n";
$pkey = openssl_pkey_get_public($pem);
if ($pkey == null) {
	die("No es posible extraer llave publica\n$diag");
}

//$diag .= "Ok Llave publica accesible<br>";

echo  "<font color='#2E8B57'>- Llave publica del certificado SAT accesible</font><br>";


# 3. La buena
# Genera cadena del timbre
$uuid = $tim->getAttribute("UUID");
$hora = $tim->getAttribute("FechaTimbrado");
$sello = $tim->getAttribute("selloCFD");

$cadena = "||1.0|$uuid|$hora|$sello|$noCertificado||";

// $diag .= "$cadena\n";
echo  "<font color='#2E8B57'>- Cadena del timbre: $cadena</font><br>";


# Valida sello de la cadena
$sellosat = base64_decode($tim->getAttribute("selloSAT"));
if (!$sellosat) die ("No es posible extraer el sello SAT\n$diag");
if (openssl_verify($cadena, $sellosat, $pkey)) {
//	echo  "El firmado es correcto\n$diag";

echo  "<font color='#2E8B57'>- El sello del timbre es valido </font><br>";

//} else die ("Error en el firmado\n$diag");

} else die ("<font color='#8B0000'>- El sello del timbre es invalido </font><br>");


// echo "OK valido";

echo  "<font color='#2E8B57'>- El CFDi es valido </font><br>";


?>
