<?php

require_once("check_utf8.php");

if(!$_POST["cfdi"]){
	die("1|El parametro CFDi esta vacio");
}

# Parametro con el CFDi
$cfdi = /*base64_decode(*/$_POST["cfdi"];//);
#$cfdiP = $_POST["cfdi"];

# Valida UTF8
if (!mb_check_encoding($cfdi, "UTF-8")) die("2|El encoding del parametro CFDi no es UTF-8");
if (!check_utf8($cfdi)) die("2|El encoding del parametro CFDi no es UTF-8");

# Convierte $cfdi a DOM
$xml = new DOMDocument();
$xml->loadXML($cfdi) or die("3|El XML recuperado no es valido");

# Valido contra Esquema
if (!$xml->schemaValidate('../xsd/cfdv32.xsd')){
	die("4|El esquema no es valido");
}


$docu = $xml->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/RequestTimbraCFDI', 'Documento')->item(0);
$cfdi = $xml->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
if ($cfdi == null and $docu == null) die ("5|No se encontro el nodo Comprobante");
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
		file_put_contents("temp/tempo.zip", base64_decode($docu->getAttribute("Archivo")));
		$zip = new ZipArchive;
		$rzip = $zip->open('temp/tempo.zip');
		if (!$rzip) die("\n\n\nNo pudo crear un temporal");
		$cfditext = $zip->getFromName($docu->getAttribute("NombreArchivo"));
		$zip->close();
		$xmlcfdi = new DOMDocument();
		$xmlcfdi->loadXML($cfditext) or die("CFDi XML no valido\n$diag");
		$cfdi = $xmlcfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
		unset($cfditext);
	}
} else {
	#Debug o Out - $diag .= "Nodo Comprobante detectado<br>";
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
$XSL->load( 'cadenaoriginal_3_2.xslt', LIBXML_NOCDATA);
error_reporting(0);
$xslt->importStylesheet( $XSL );
error_reporting(E_ALL);
$cadena = $xslt->transformToXML( $cfdi );
if ($cadena !== "|||") {
	#debug o out - $diag .= "Cadena original bien formada <br>";
} else { die("6|Error al intentar formar la cadena original"); }


# Valida el certificado
$base = $cfdi->getAttribute("certificado");
$cert2  = "-----BEGIN CERTIFICATE-----\n";
$cert2 .= chunk_split($base, 64, "\n");
$cert2 .= "-----END CERTIFICATE-----\n";
$pkey = openssl_pkey_get_public($cert2);
if ($pkey == null) {
	die("7|No es posible extraer llave publica del certificado");
}
#debug o out - $diag .= "Ok Llave publica accesible\n";
# DEBUG print_r(openssl_pkey_get_details($pkey));

# Decodifica el certificado para efectos de validacion
$cert509 = openssl_x509_read($cert2) or die("8|No se puede leer el certificado");
$data = openssl_x509_parse($cert509) or die("8|No se puede leer el certificado");
#DEBUG print_r($data);

# Extrae fechas validFrom, validTo
$validFrom = $data['validFrom'];
$validTo = $data['validTo'];
if (preg_match('/(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)Z/', $validFrom, $m)) {
	$validFrom = "20{$m[1]}-{$m[2]}-{$m[3]}T{$m[4]}:{$m[5]}:{$m[6]}";
} else die("9|No es posible extraer las fechas del certificado");
if (preg_match('/(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)(\d\d)Z/', $validTo, $m)) {
	$validTo = "20{$m[1]}-{$m[2]}-{$m[3]}T{$m[4]}:{$m[5]}:{$m[6]}";
} else die("9|No es posible extraer las fechas del certificado");
unset($m);

# Valida que la fecha de expedicion esté en el rango de validez del certificado, usando la clase DateTime disponible desde la version 5.2.2
$f = $cfdi->getAttribute("fecha"); //fecha de generacion del cfdi
$df = new DateTime($f); // conversion a fecha comparable de la fecha de generacion del cfdi
$d1 = new DateTime($validFrom); // fecha convertida del certificado valido desde 
$d2 = new DateTime($validTo); //fecha convertida del certificado valido hasta 
$ok = ($d1 <= $df && $df <= $d2);  // compara que la fecha de expedicion del certificado sea menor o igual a fecha generacion | compara que la fecha de generacion sea menor o igual que la fecha de vencimiento del certificado     
unset($df, $d1, $d2);
if ($ok) {
	#debug - out $diag .= "Ok [$f] entre [$validFrom] y [$validTo]\n";
} else {
	echo "10|Fecha de Expedicion mayor a el vencimiento del certificado";
	die;
}


###### HASTA AQUI BIEN #######

# Extrae RFC, Issuer
$rfc2 = $data['subject']['x500UniqueIdentifier'];
if (preg_match('/^([A-Za-z]{3,4}[0-9]{6}\w{3})/', $rfc2, $m)) {
	$rfc = "{$m[1]}";
	if ($rfc === $cfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Emisor')->item(0)->getAttribute("rfc")) {
		#debug o out - $diag .= "Ok Verificado que el RFC del certificado coincida con el emisor\n";
	} else echo "28|El RFC del certificado no coincide con el emisor"; # DIE, pero en este caso solo lanza un warning
} else die("11|No es posible extraer el rfc del certificado");
$issuer = $data['issuer']['O'];
//echo $issuer;
if ($issuer === utf8_encode("Servicio de Administración Tributaria")) {
	#debug o out - $diag .= "Ok certificado expedido por el SAT\n";
} else die("12|Certificado no expedido por el SAT");
$use = $data['extensions']['keyUsage'];
if ("Digital Signature, Non Repudiation" === $use) {
	#debug o out - $diag .= "Ok verificado que es un certificado NO FIEL\n";
} else die ("13|El uso del certificado es incorrecto, posible FIEL o certificado de conexion");
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
if ($serial !== $cfdi->getAttribute("noCertificado")) die("14|El numero de serie del sello no corresponde con el certificado");


/* Hasta aqui bien  */


# Si es soap, valida infobasica
if ($is_soap) {
	$t = $xml->getElementsByTagNameNS('http://www.buzonfiscal.com/ns/xsd/bf/TimbradoCFD', 'RequestTimbradoCFD')->item(0);
	if ($t->getElementsByTagName('InfoBasica')->item(0)->getAttribute('RfcEmisor') === $cfdi->getElementsByTagName('Emisor')->item(0)->getAttribute('rfc')) {
		//$diag .= "Ok Coincide RFC de emisor en infoBasica y nodo Emisor\n";
	} else die("15|No coincide RFC de emisor con el nodo Emisor");
	if ($t->getElementsByTagName('InfoBasica')->item(0)->getAttribute('RfcReceptor') === $cfdi->getElementsByTagName('Receptor')->item(0)->getAttribute('rfc')) {
		//$diag .= "Ok Coincide RFC de receptor en infoBasica y nodo Receptor\n";
	} else die("15|No coincide RFC de receptor con el nodo Receptor");
}

# Extrae sello
$sello = base64_decode($cfdi->getAttribute("sello"));
if (!$sello) die ("16|No es posible extraer el sello");
if (openssl_verify($cadena, $sello, $pkey)) {
	//Firmado OK  - echo  "El firmado es correcto\n$diag";
} else die ("17|Error en el firmado");



###########################################
#### Modificacion 02 - Valida Timbre

$diag = "";

$tim = $cfdi->getElementsByTagNameNS('http://www.sat.gob.mx/TimbreFiscalDigital', 'TimbreFiscalDigital')->item(0);
if ($tim == null) {
	echo "WARNING: No existe elemento TimbreFiscalDigital\n";
}

# Validaciones de timbre
# 1. Basica Que el selloCFD coincida con sello
if ($cfdi->getAttribute("sello") == $tim->getAttribute("selloCFD")) {
	//$diag .= "El sello del timbre coincide con el del comprobante\n";
} else die ("18|Error. El sello del timbre no coincide con el del comprobante");
$base = $cfdi->getAttribute("certificado");

# 2. No existe el certificado en el folder
$noCertificado = $tim->getAttribute("noCertificadoSAT");
$der_data = file_get_contents("sellosPAC/$noCertificado.cer");
$pem = chunk_split(base64_encode($der_data), 64, "\n");
$pem = "-----BEGIN CERTIFICATE-----\n".$pem."-----END CERTIFICATE-----\n";
$pkey = openssl_pkey_get_public($pem);
if ($pkey == null) {
	die("19|No es posible extraer llave publica");
}
//$diag .= "Ok Llave publica accesible\n";

# 3. La buena
# Genera cadena del timbre
$uuid = $tim->getAttribute("UUID");
$hora = $tim->getAttribute("FechaTimbrado");
$sello = $tim->getAttribute("selloCFD");

$cadena = "||1.0|$uuid|$hora|$sello|$noCertificado||";
//$diag .= "$cadena\n";

# Valida sello de la cadena
$sellosat = base64_decode($tim->getAttribute("selloSAT"));
if (!$sellosat) die ("20|No es posible extraer el selloSAT");
if (openssl_verify($cadena, $sellosat, $pkey)) {
	//echo  "El firmado es correcto\n$diag";
} else die ("21|Error en el timbre");

echo "0|CFDi Valido|[$cadena]";


?>
