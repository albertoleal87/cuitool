/* Funciones accesorio y variables globales
*/

var buff = "";

function updateLeftSize() {
	var sz;
	sz = document.getElementById("left").value.length;
	document.getElementById("left").title = "Para capturar ya sea el texto completo del sobre SOAP o bien el comprobante a ensobretar segun sea el caso [" + sz + "]";
}

function cleanChars(s) {
	s = s.toString(); // No jala
	s = s.replace(/\+/g, '%2B').replace(/\&/g, '%26'); // .replace(/\n/g, '%13').replace(/\b/, '%20');
	return(s);
}
//manda llamar el certificado de comunicacion default
function loadDefaultCertificate() {
	document.getElementById("certykey").value = "Haciendo peticion..."
	var xmlhttp;
	if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	} else { // code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState==4 && (xmlhttp.status==200 || xmlhttp.status==0)) {
			document.getElementById("certykey").value = xmlhttp.responseText;
		}
	}
	xmlhttp.open("POST",'pem/demoaaa.pem', true);
	xmlhttp.send();

}


//manda llamar el certificado de sellos default
function loadDefaultSignCertificate() {
	document.getElementById("personal").value = "Haciendo peticion..."
	var xmlhttp;
	if (window.XMLHttpRequest) 
	{ // code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();}

	else { // code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");}
	xmlhttp.onreadystatechange = function() 
	{if (xmlhttp.readyState==4 && (xmlhttp.status==200 || xmlhttp.status==0)) 
		{document.getElementById("personal").value = xmlhttp.responseText;}
	}
	
	xmlhttp.open("POST",'pem/aaa.pem', true);
	xmlhttp.send();
}

function okSoap() {
	errtext = "";
	soap = document.getElementById("left").value;
	if (soap == "" || soap == "SOAP") { errtext = "Campo SOAP vacío"; }
	if (errtext != "") {
		alert(errtext);
		return false;
	} else {
		return true;
	}
}

function okSoapCertificado() {
	errtext = "";
	soap = document.getElementById("left").value;
	if (soap == "" || soap == "SOAP") { errtext = "Campo SOAP vacío"; }
	cert = document.getElementById("certykey").value;
	if (cert == "" || cert == "Certykey") {
		if (errtext != "") {
			errtext += " y "
		}
		errtext += "Campo CERTYKEY vacío";
	}
	if (errtext != "") {
		alert(errtext);
		return false;
	} else {
		return true;
	}
}

function okSoapCertificadoFirma() {
	errtext = "";
	soap = document.getElementById("left").value;
	if (soap == "" || soap == "Certificado de Conexion") { errtext = "Campo SOAP vacío"; }
	cert = document.getElementById("personal").value;
	if (cert == "" || cert == "Certificado de Sello") {
		if (errtext != "") {
			errtext += " y "
		}
		errtext += "Certificado de sello vacío";
	}
	if (errtext != "") {
		alert(errtext);
		return false;
	} else {
		return true;
	}
}

/* Funciones botonadas
*/

function undo() {
	var aux;
	if (buff != "") {
		aux = document.getElementById("left").value;
		document.getElementById("left").value = buff;
		buff = aux;
		updateLeftSize();
	}
}

function unenvelope() {
	if (okSoap()) {
		// document.getElementById("right").value = "Haciendo peticion..."
		var xmlhttp;
		buff = document.getElementById("left").value;
		if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
			xmlhttp = new XMLHttpRequest();
		} else { // code for IE6, IE5
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		}
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState==4) {
				if (xmlhttp.status==200 || xmlhttp.status==0) {
					document.getElementById("left").value = xmlhttp.responseText.replace(/^\n/, "");
					updateLeftSize();
				} else {
					alert("Error " + xmlhttp.status);
				}
			}
		}
		params = "a=" + cleanChars(buff);
		xmlhttp.open("POST",'tool2unenvelope.php', true);
		xmlhttp.setRequestHeader("Content-length", params.length);
		xmlhttp.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
		xmlhttp.send(params);
	}
}

function envelope(d) {
	if (okSoap()) {
		// document.getElementById("right").value = "Haciendo peticion..."
		var xmlhttp;
		buff = document.getElementById("left").value;
		if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
			xmlhttp = new XMLHttpRequest();
		} else { // code for IE6, IE5
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		}
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState==4) {
				if (xmlhttp.status==200 || xmlhttp.status==0) {
					document.getElementById("left").value = xmlhttp.responseText.replace(/^\n/, "");
					updateLeftSize();
				} else {
					alert("Error " + xmlhttp.status);
				}
			}
		}
		params = "a=" + cleanChars(buff) + "&d=" + d;
		xmlhttp.open("POST",'php/tool2envelope.php', true);
		xmlhttp.setRequestHeader("Content-length", params.length);
		xmlhttp.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
		xmlhttp.send(params);
	}
}

function profile() {
	// document.getElementById("right").value = "Haciendo peticion..."
	var xmlhttp;
	if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp = new XMLHttpRequest();
	} else { // code for IE6, IE5
		xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState==4 && (xmlhttp.status==200 || xmlhttp.status==0)) {
			document.getElementById("left").value = xmlhttp.responseText.replace(/^\n/, "");
			updateLeftSize();
		}
	}
	xmlhttp.open("POST",'php/tool2profile.php?n=' + document.getElementById("cuantos").value, true);
	xmlhttp.send();
}

function sign() {
	if (okSoapCertificadoFirma()) {
		var xmlhttp;
		buff = document.getElementById("left").value;
		if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
			xmlhttp = new XMLHttpRequest();
		} else { // code for IE6, IE5
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		}
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState==4) {
				if (xmlhttp.status==200 || xmlhttp.status==0) {
					document.getElementById("left").value = xmlhttp.responseText.replace(/^\n/, "");
					updateLeftSize();
				} else {
					alert("Error " + xmlhttp.status);
				}
			}
		}
		// params = "a=" + cleanChars(buff);
		params = "a=" + cleanChars(buff) + "&b=" + cleanChars(document.getElementById('personal').value);
		xmlhttp.open("POST",'php/tool2sign.php', true);
		xmlhttp.setRequestHeader("Content-length", params.length);
		xmlhttp.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
		xmlhttp.send(params);
	}
}

/* Funciones que NO regresan resultado en el mismo lado izquierdo */

function verify() {
	if (okSoap()) {
		document.getElementById("right").value = "Haciendo peticion..."
		var xmlhttp;
		if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
			xmlhttp = new XMLHttpRequest();
		} else { // code for IE6, IE5
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		}
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState==4) {
				if (xmlhttp.status==200 || xmlhttp.status==0) {
					document.getElementById("right").value = xmlhttp.responseText.replace(/^\n/, "");
				} else {
					alert("Error " + xmlhttp.status);
				}
			}
		}
		params = "a=" + cleanChars(document.getElementById('left').value);
		xmlhttp.open("POST",'php/tool2verify.php', true);
		xmlhttp.setRequestHeader("Content-length", params.length);
		xmlhttp.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
		xmlhttp.send(params).innerHTML;
	}
}

function send(c) {
	if (okSoapCertificado()) {
		document.getElementById("right").value = "Haciendo peticion..."
		var xmlhttp;
		if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
			xmlhttp = new XMLHttpRequest();
		} else { // code for IE6, IE5
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		}
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState==4) {
				if (xmlhttp.status==200 || xmlhttp.status==0) {
					document.getElementById("right").value = xmlhttp.responseText.replace(/^\n/, "");
				} else {
					alert("Error " + xmlhttp.status);
				}
			}
		}
		params = "a=" + cleanChars(document.getElementById('left').value) + "&b=" + cleanChars(document.getElementById('certykey').value) + "&c=" + c;
		xmlhttp.open("POST",'php/tool2send.php', true);
		xmlhttp.setRequestHeader("Content-length", params.length);
		xmlhttp.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
		xmlhttp.send(params);
	}
}

function validapf(c) {
	if (okSoap()) {
		document.getElementById("right").value = "Haciendo peticion..."
		var xmlhttp;
		if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
			xmlhttp = new XMLHttpRequest();
		} else { // code for IE6, IE5
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		}
		xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState==4) {
				if (xmlhttp.status==200 || xmlhttp.status==0) {
					document.getElementById("right").value = xmlhttp.responseText.replace(/^\n/, "");
				} else {
					alert("Error " + xmlhttp.status);
				}
			}
		}
		params = "cfdi=" + cleanChars(document.getElementById('left').value);
		xmlhttp.open("POST",'php/valida.php', true);
		xmlhttp.setRequestHeader("Content-length", params.length);
		xmlhttp.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
		xmlhttp.send(params);
	}
}



