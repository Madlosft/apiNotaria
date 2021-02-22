<?php
//workflow del importador
// autoriza->creartramite->estadotramite->descarga de pdf
set_time_limit(0);
require_once("notariaconfig.php");
//parametrizacion de la configuracion de la api
$token=$notaria['api']['token'];
$time=$notaria['api']['time'];
$materia=$notaria['docs']['tipo'];
$cnombre=$notaria['docs']['nombreempresa'];
$crut=$notaria['docs']['rutempresa'];
$crol=$notaria['docs']['rolempresa'];
$anombre=$notaria['docs']['nombresolicitud'];
$arut=$notaria['docs']['rutsolicitud'];
$arol=$notaria['docs']['rolsolicitud'];

//global $token,$materia,$cnombre,$crut,$crol,$anombre,$arut,$arol;

// crear carpetas para la carga de archivos
$destino = 'notariadocs';
$download='download';
if (!file_exists($destino)){
    mkdir($destino,0775,true);
}
if (!file_exists($download)){
    mkdir($download,0775,true);
}
//crear carpeta de log
$logFolder="log";
$logFile="log/logNotaria.log";
if(file_exists($destino)){
    if(!file_exists($logFolder)){
        mkdir($logFolder,0775,true);
    }
    if(!file_exists($logFile)){
        $texto="*** Creando archivo de log ".date("d/m/Y H:i:s")." Aplicacion trabajando Developer Nicolás Villablanca *** \n";
        write_log($texto);
    }
}
//obteniendo y validando autorizacion
write_log("*** Autoimport notaria Ronchera, desarrollada por Nicolás Villablanca ***");
write_log("*** Inicio del procesamiento ".date("d/m/Y H:i:s")."*** \n");

//$data="nWNwNREamK7ETf7cD";
//status($data);
autorizacionApi($destino);

//verifica si el usuario esta autenticado
function autorizacionApi($destino){
    global $token,$materia,$cnombre,$crut,$crol,$anombre,$arut,$arol;
    $curl = curl_init();
    //$token="ZGVtb25vdGFyaWFs.ccbafa20566a0a6d97c23b88af517b2e4b8f67102848a0bad435da768827f539";
    curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://beta.api.notariaronchera.cl/v2/cliente',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        'Authorization: '.$token.''
    ),
    ));

    $response = curl_exec($curl);
    $result = json_decode($response);
    $info=curl_getinfo($curl,CURLINFO_HTTP_CODE);

    curl_close($curl);

    //echo $response;
    $rutAutoriza= $result->rut;
    $nameAutoriza=$result->nombres;
    $autorizaTipo=$result->tipoPersona;

    $texto=date("d/m/Y H:i:s") . ": Cliente autorizado para la carga de archivos: $nameAutoriza, rut: $nameAutoriza, tipo de persona: $autorizaTipo \n";
    write_log($texto);
    
    if($info==200){
        $files=array_diff(scandir($destino),array('.','..'));
        //recorremos cada archivo dentro directorio
        foreach($files as $file){
            write_log("Procesando el archivo: $file, iniciando tramite de documento \n");
            //$size = filesize($destino .'/' . $file);
            $base=base64($destino .'/'.$file);
            $size =getimagesizebase($destino .'/'.$file);
            //createDocs($base,$size);
            createDocs($base);
            
        }
    }
    else{
        write_log(date("d/m/Y H:i:s") . ": Se detiene proceso, error en la autorizacion del usuario: $nameAutoriza y/o token ingresado: token \n");
    }

}

//funcion para crear un nuevo documento
function createDocs($base){
    global $token,$materia,$cnombre,$crut,$crol,$anombre,$arut,$arol;
    $size= intval(strlen(base64_decode($base)));
    write_log("Cargando nuevo tramite a API Ronchera \n");
    $curl = curl_init();

    curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://beta.api.notariaronchera.cl/v2/tramites-empresa',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS =>'{
        "materia": "'.$materia.'",
        "comparecientes": [
            {
                "nombre": "'.$cnombre.'",
                "numeroDocumento": "'.$crut.'",
                "rol": "'.$crol.'"
            },
            {
                "nombre": "'.$anombre.'",
                "numeroDocumento": "'.$arut.'",
                "rol": "'.$arol.'"
            }
        ],
        "documentSize": '.$size.',
        "documento_b64": "'.$base.'"
        
        
    }',
    CURLOPT_HTTPHEADER => array(
        'Authorization: '.$token.'',
        'Content-Type: application/json'
    ),
    ));

    $response = curl_exec($curl);
    $info=curl_getinfo($curl,CURLINFO_HTTP_CODE);
    $result = json_decode($response);
    //echo $base;
    //echo $size;
    curl_close($curl);
    //modifico para verificar info
    $id=$result->tramiteId;

    if($info==200){
        write_log("Carga exitosa: Tramite $result->tramiteId cargado al sistema correctamente \n");
        status($id);
    }
    else{
        write_log(date("d/m/Y H:i:s") . "Error en la gestion del tramite, agregando info: $response \n");
    }
}

//obtiene el estado del documento solicitado

function status($tramiteid){
    global $token,$materia,$cnombre,$crut,$crol,$anombre,$arut,$arol,$time;
    //se genero un sleep por demora de la carga del documento en la API
    sleep($time);
    write_log("Consultando por el estado del tramite: $tramiteid \n");
    $curl = curl_init();

    $url='https://beta.api.notariaronchera.cl/v2/tramites-empresa/';
    $url .=$tramiteid;
    write_log($url."\n");

    curl_setopt_array($curl, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        'Authorization: '.$token.''
    ),
    ));

    $response = curl_exec($curl);
    $result = json_decode($response);
    $info=curl_getinfo($curl,CURLINFO_HTTP_CODE);
    write_log($response."\n");
    curl_close($curl);
    //$data=strval($result->archivoOriginal);
    //write_log($data."\n");
    //echo $response;
    if($info==200){
        write_log("Consulta: Realizando la consulta y descarga del documento \n");
        write_log("Documento $result->tramiteId fue ingresado el $result->fechaIngreso,
        numero de OT: $result->numeroOt, materia del documento: $result->materia en el estado: $result->estado \n");
        download($result->archivoOriginal);
    }
    else{
        write_log("Consulta: Error no se pudo consultar el estado del tramite, $response \n");
    }
    
    
    //return $data;
    
}

//descargar archivo original desde notaria ok
function download($url){
    $curl = curl_init($url); 
    $dir = "./download/"; 
    $file_name = basename($url); 
    $save_file_loc = $dir . $file_name; 
    $fp = fopen($save_file_loc, 'wb'); 
    curl_setopt($curl, CURLOPT_FILE, $fp); 
    curl_setopt($curl, CURLOPT_HEADER, 0); 
    curl_exec($curl); 
    curl_close($curl); 
    fclose($fp);
    echo $url;
}

//funcion para ingresar log api
function write_log($text){
    $backup_log = "log/logNotaria.log";;
    $handle = fopen($backup_log, "ab");
    fwrite($handle, $text);
    fclose($handle);
}

//convertir archivo base64
function base64($file){
    $img=file_get_contents($file);
    $data=base64_encode($img);
    //write_log($data."\n");
    return $data;
}

function getimagesizebase($data){
        $uri = 'data://application/octet-stream;base64,' . base64_encode($data);
        return getimagesize($uri);
    }

/*
$dir = opendir($destino);
foreach($file in $dir){
    echo "file "

}*/