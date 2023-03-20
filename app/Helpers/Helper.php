<?php

namespace App\Helpers;

use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;
use Greenter\XMLSecLibs\Certificate\X509Certificate;
use Greenter\XMLSecLibs\Certificate\X509ContentType;

class Helper
{

    public static function IdentificacionDocumentoPruebas()
    {
        $see = new See();
        $see->setCertificate(file_get_contents(public_path('Certificados/certificate_prueba.pem')));
        $see->setService(SunatEndpoints::FE_BETA);
        $see->setClaveSOL('20000000001', 'MODDATOS', 'moddatos');
        return $see;
    }
    public static function IdentificacionDocumentoProduccion($datosEmpresa=[])
    {
        $pfx = file_get_contents(public_path('Certificados/certificado.p12'));
        $password = $datosEmpresa['clave_certificado'];//'durand019' 
        $certificate = new X509Certificate($pfx, $password);
        $see = new See();
        $see->setCertificate($certificate->export(X509ContentType::PEM));
        $see->setService(SunatEndpoints::FE_PRODUCCION);
        $see->setClaveSOL($datosEmpresa['ruc_empresa'],$datosEmpresa['usuario_sol'],$datosEmpresa['clave_sol']);//'10157622680' / 'CALEL019'  /  'Durand019'
        return $see;
    }
}
