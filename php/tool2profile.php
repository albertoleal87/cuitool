<?php

require_once('xmlprettyprint.php');

if (isset($_GET['n'])) {
	$n = $_GET['n'];
} elseif (isset($_POST['n'])) {
	$n = $_POST['n'];
} else {
	$n = 10;
}

# Partimos de un CFDi a medias, solo como muestra
$xmltext = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/3" xsi:schemaLocation="http://www.sat.gob.mx/cfd/3 http://www.sat.gob.mx/sitio_internet/cfd/3/cfdv32.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" tipoDeComprobante="ingreso" total="9999.99999" subTotal="9999.999999" certificado="" noCertificado="" sello="" metodoDePago="TARJETA DE CREDITO" NumCtaPago="1234" LugarExpedicion="MONTERREY N.L."  formaDePago="Pago en una sola exhibición" fecha="2011-01-08T12:16:40" version="3.2" >
  <cfdi:Emisor nombre="EMPRESA DEMO" rfc="AAA010101AAA">
    <cfdi:DomicilioFiscal codigoPostal="53400" pais="México" estado="Nuevo Leon" municipio="Monterrey" colonia="Obispado" noExterior="1640" calle="Padre Mier"/>
  <cfdi:RegimenFiscal Regimen="PERSONA MORAL"/>

  </cfdi:Emisor>


  <cfdi:Receptor nombre="RECEPTOR DE PRUEBAS" rfc="XXAX010101XXX">
    <cfdi:Domicilio codigoPostal="64060" pais="Mexico" estado="Nuevo Leon" municipio="Monterrey" noExterior="5512" calle="Padre Mier"/>
  </cfdi:Receptor>
  <cfdi:Conceptos>
  </cfdi:Conceptos>
  <cfdi:Impuestos totalImpuestosTrasladados="9999.999999">
    <cfdi:Traslados>
      <cfdi:Traslado importe="9999.999999" tasa="16.00" impuesto="IVA"/>
    </cfdi:Traslados>
  </cfdi:Impuestos>
  <cfdi:Complemento> 
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;

# El emisor no cambia. Si cambiara el codigo sería semejante al de receptor
$receptor = array ("nombre" => "Juan Perez Galvan",
		"rfc" => "GAPJ700202XX0",
		"Domicilio" => array (
			"codigoPostal" => "53499",
			"pais" => "México",
			"estado" => "Nuevo Leon",
			"municipio" => "Monterrey",
			"colonia" => "Obispado",
			"noExterior" => "1660",
			"calle" => "Venustiano Carroza"
			)
		);

# 10 posibles conceptos
$concepto[0] = array( "unidad" => "kilo", "valorUnitario" =>  "80.00", "descripcion" => "Grillo gigante marinado");
$concepto[1] = array( "unidad" => "kilo", "valorUnitario" => "100.00", "descripcion" => "Cucaracha cocida");
$concepto[2] = array( "unidad" => "lata", "valorUnitario" =>  "50.00", "descripcion" => "Hormiga en chocolate");
$concepto[3] = array( "unidad" => "bolsa", "valorUnitario" =>  "30.00", "descripcion" => "Tamal de vibora");
$concepto[4] = array( "unidad" => "kilo", "valorUnitario" =>  "200.00", "descripcion" => "Iguana doble pechuga");
$concepto[5] = array( "unidad" => "kilo", "valorUnitario" =>  "120.00", "descripcion" => "Chapulines al ajillo");
$concepto[6] = array( "unidad" => "lata", "valorUnitario" =>  "75.00", "descripcion" => "Caracol junior natural");
$concepto[7] = array( "unidad" => "kilo", "valorUnitario" =>  "180.00", "descripcion" => "Gusano ahumado en mezquite");
$concepto[8] = array( "unidad" => "bolsa", "valorUnitario" =>  "94.00", "descripcion" => "Ajolotl lampreado en maiz");
$concepto[9] = array( "unidad" => "kilo", "valorUnitario" =>  "60.00", "descripcion" => "Cucaracha coctelera");
#faltaria cantidad e importe

# En este caso solo hay iva fijo a 16%. En la realidad podría variar

# Convierte a objeto DOM $cfdi
$cfdi = new DOMDocument();
$cfdi->loadXML($xmltext);

# Modifica codigos semifijos
$cfdireceptor = $cfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Receptor')->item(0);
$cfdireceptor->setAttribute('nombre', $receptor['nombre']);
$cfdireceptor->setAttribute('rfc', $receptor['rfc']);
$cfdireceptordomicilio = $cfdireceptor->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Domicilio')->item(0);
foreach($receptor["Domicilio"] as $key => $value) {
	$cfdireceptordomicilio->setAttribute($key, $value);
}
unset($cfdireceptor);
unset($cfdireceptordomiocilio);

# Agrega n conceptos
$cfdiconceptos = $cfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Conceptos')->item(0);
for($i=0; $i<$n; $i++) {
	$cfdiconcepto = $cfdi->createElementNS('http://www.sat.gob.mx/cfd/3', 'Concepto');
	$conc = $concepto[mt_rand(0,9)];
	$cant = mt_rand(1, 10);
	$importe = $cant * intval($conc['valorUnitario']);
	foreach($conc as $key => $value) {
		$cfdiconcepto->setAttribute($key, $value);
	}
	$cfdiconcepto->setAttribute('cantidad', $cant);
	$cfdiconcepto->setAttribute('importe', $importe);
	$cfdiconceptos->appendChild($cfdiconcepto);
	unset($cfdiconcepto);
	unset($conc);
	unset($cant);
	unset($importe);
}
unset($cfdiconceptos);

# "Normaliza" la informacion, pero NO FIRMA
$c = $cfdi->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0);
$total = 0;
foreach ($c->getElementsByTagName('Conceptos')->item(0)->getElementsByTagName('Concepto') as $concepto) {
	$total += $concepto->getAttribute('importe');
}
$iva = $total * 0.16;
$c->setAttribute('subTotal', $total);
$c->setAttribute('total', $total + $iva);
$c->setAttribute('fecha', date('c')); # "2011-01-08T12:16:40"
$c->getElementsByTagName('Impuestos')->item(0)->setAttribute('totalImpuestosTrasladados', $iva);
$c->getElementsByTagName('Impuestos')->item(0)->getElementsByTagName('Traslados')->item(0)->getElementsByTagName('Traslado')->item(0)->setAttribute('importe', $iva);
unset($c);
unset($total);
unset($iva);

echo xmlprettyprint($cfdi->saveXML());

?>

