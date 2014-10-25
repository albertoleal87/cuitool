<?php

# Tool1Sign : Firma el cfdi
require_once('xmlprettyprint.php');

# Hay algo?
if (isset($_GET['a'])) {
	$xmltext = $_GET['a'];
} elseif (isset($_POST['a'])) {
	$xmltext = $_POST['a'];
}
if ($xmltext === "") {
	die("No hay informacion");
}

if (isset($_GET['b'])) {
	$pem = $_GET['b'];
} elseif (isset($_POST['b'])) {
	$pem = $_POST['b'];
}
if ($pem === "") {
	die("No hay informacion");
}

# Convierte a modelo DOM
$xmltext = utf8_encode($xmltext); // Quiza
$cfdi = new DOMDocument();
$cfdi->loadXML($xmltext) or die("XML no valido");
$c = $cfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);

# Extrae cadena original
$xslt = new XSLTProcessor();
$XSL = new DOMDocument();
$XSL->load( '../xslt/cadenaoriginal_3_2.xslt', LIBXML_NOCDATA);
error_reporting(0);
$xslt->importStylesheet( $XSL );
error_reporting(E_ALL);
$cadena = $xslt->transformToXML( $c );
# DEBUG echo "[$cadena]\n";

#############################################################
# Constantes HOY. Pendiente por verificar
$noCertificado = "30001000000100000809";
#############################################################
# Decodifica el certificado para efectos de validacion
$cert509 = openssl_x509_read($pem) or die("No se puede leer el certificado\n");
$data = openssl_x509_parse($cert509) or die("No se puede leer el certificado\n");
#DEBUG print_r($data);
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
$noCertificado = $serial4;
unset($serial1, $serial2, $serial3, $serial4, $serialt, $data, $cert509);
#############################################################



# Extrae valores relevantes
# Extrae el certificado, sin enters
preg_match('/-----BEGIN CERTIFICATE-----(.+)-----END CERTIFICATE-----/msi', $pem, $matches) or die("No certificado\n");
$algo = $matches[1];
$algo = preg_replace('/\n/', '', $algo);
$certificado = preg_replace('/\r/', '', $algo);
# DEBUG echo "certificate = [$certificado]\n";

# Extrae la llave privada, en formato openssl
$key = openssl_pkey_get_private($pem) or die("No llave privada\n");

# Firma
$crypttext = "";
openssl_sign($cadena, $crypttext, $key);
$sello = base64_encode($crypttext);
# DEBUG echo "sello = [$sello]\n";

# Incorpora los tres elementos al cfdi
$c->setAttribute('certificado', $certificado);
$c->setAttribute('sello', $sello);
$c->setAttribute('noCertificado', $noCertificado);

# regresa el resultado
echo xmlprettyprint($cfdi->saveXML());
?>
