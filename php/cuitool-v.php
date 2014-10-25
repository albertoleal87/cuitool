<?php

# Tool1Verify : Checa la integridad del CFDi o sobre SOAP
# version 01. Hector Isais. 2011-02-18T16:21-06:00 - Valida CFDi contra esquema y validez del sello. Si es sobre soap, valida integridad de la infoBasica

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

# Valida UTF8
if (!mb_check_encoding($xmltext, "UTF-8")) die("El xml no esta en UTF8\n");
$diag .= "Ok UTF8\n";

# Convierte a modelo DOM
$xml = new DOMDocument();
$xml->loadXML($xmltext) or die("XML no valido\n\n$diag");

# Identifica si el xml cargado es CFDi o SOAP o Error
if ($xml->schemaValidate('cfdv3.xsd')) {
	# Es CFDi, nada que hacer (hasta ahora)
	$diag .= "Ok CFDi detectado\n";
} elseif ($xml->schemaValidate('sopa.xsd')) {
	# Es sobre SOAP
	$is_soap = true;
	$diag .= "Ok Sobre SOAP detectado\n";
} else {
	die("Esquema no valido\n\n$diag");
}
echo "\n\n***********************************************\n\n";

# Extrae el cfdi
$docu = $xml->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI', 'Documento')->item(0);
$cfdi = $xml->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
if ($cfdi == null and $docu == null) die ("No se encontro el nodo Comprobante\n\n$diag");
if ($cfdi != null and $docu != null) die ("El sobre contiene ambos nodos Comprobante y Documento\n\n$diag");
if ($docu != null and $cfdi == null) {
	// Convierte el documento y de algun modo extrae un $cfdi valido
	$diag .= "Ok nodo Documento detectado\n";
	# Desensobreta el CFDI
	$cfditext = base64_decode($docu->getAttribute("Archivo"));
	$xmlcfdi = new DOMDocument();
	$xmlcfdi->loadXML($cfditext) or die("CFDi XML no valido\n$diag");
	$cfdi = $xmlcfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
	unset($cfditext);
} else {
	$diag .= "Ok nodo Comprobante detectado\n";
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
$XSL->load( 'cadenaoriginal_3_0.xslt', LIBXML_NOCDATA);
error_reporting(0);
$xslt->importStylesheet( $XSL );
error_reporting(E_ALL);
$cadena = $xslt->transformToXML( $cfdi );
if ($cadena !== "|||") {
	$diag .= "Ok Cadena Original = \"$cadena\"\n";
} else die("No fue posible formar la cadena original\n$diag");

# Valida el certificado
$base = $cfdi->getAttribute("certificado");
$cert2  = "-----BEGIN CERTIFICATE-----\n";
$cert2 .= chunk_split($base, 64, "\n");
$cert2 .= "-----END CERTIFICATE-----\n";
$pkey = openssl_pkey_get_public($cert2);
if ($pkey == null) {
	die("No es posible extraer llave publica\n$diag");
}
$diag .= "Ok Llave publica accesible\n";
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
	$diag .= "Ok [$f] entre [$validFrom] y [$validTo]\n";
} else {
	echo "[$f] NO está entre [$validFrom] y [$validTo]\n$diag";
	die;
}

# Extrae RFC, Issuer
$rfc2 = $data['subject']['x500UniqueIdentifier'];
if (preg_match('/^([A-Za-z]{3,4}[0-9]{6}\w{3})/', $rfc2, $m)) {
	$rfc = "{$m[1]}";
	if ($rfc === $cfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Emisor')->item(0)->getAttribute("rfc")) {
		$diag .= "Ok Verificado que el RFC del certificado coincida con el emisor\n";
	} else echo "ADVERTENCIA: El RFC del certificado no coincide con el emisor\n$diag"; # DIE, pero en este caso solo lanza un warning
} else die("No es posible extraer el rfc del certificado\n$diag");
$issuer = $data['issuer']['O'];
if ($issuer === "Servicio de Administración Tributaria") {
	$diag .= "Ok certificado expedido por el SAT\n";
} else die("Certificado no expedido por el SAT\n$diag");
$use = $data['extensions']['keyUsage'];
if ("Digital Signature, Non Repudiation" === $use) {
	$diag .= "Ok verificado que es un certificado NO FIEL\n";
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
if ($serial !== $cfdi->getAttribute("noCertificado")) die("El numero de serie del sello no corresponde con el certificado\n");

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
	echo  "El firmado es correcto\n$diag";
} else die ("Error en el firmado\n$diag");

?>
