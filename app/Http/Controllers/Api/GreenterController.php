<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use DateTime;
use DateTimeZone;
use Greenter\Model\Client\Client;
use Greenter\Model\Company\Address;
use Greenter\Model\Company\Company;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado;
use Greenter\Model\Sale\Invoice;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Sale\SaleDetail;
use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;

use Illuminate\Http\Request;

class GreenterController extends Controller
{

    public function EmitirDocumento(Request $request)
    {

        $data = $request->json()->all();
        $datosEmpresa = [
            'ruc_empresa' => $data['company']['ruc'],
            'usuario_sol' => $data['usuario_sol'],
            'clave_sol' => $data['clave_sol'],
            'clave_certificado' => $data['clave_certificado']
        ];
        $see=Helper::IdentificacionDocumentoPruebas();
        // $see = Helper::IdentificacionDocumentoProduccion($datosEmpresa);
        switch ($data['tipoDoc']) {
            case '01':
                $documento = 'Factura';
                break;
            case '03':
                $documento = 'Boleta';
                break;
            default:
                $documento = '';
                break;
        }

        // Cliente
        $client = (new Client())
            ->setTipoDoc($data['client']['tipoDoc'])
            ->setNumDoc($data['client']['numDoc'])
            ->setRznSocial($data['client']['rznSocial']);

        // Emisor
        $address = (new Address())
            ->setUbigueo($data['company']['address']['ubigueo'])
            ->setDepartamento($data['company']['address']['departamento'])
            ->setProvincia($data['company']['address']['provincia'])
            ->setDistrito($data['company']['address']['distrito'])
            ->setUrbanizacion('-')
            ->setDireccion($data['company']['address']['direccion'])
            ->setCodLocal('0000'); // Codigo de establecimiento asignado por SUNAT, 0000 por defecto.

        $company = (new Company())
            ->setRuc($data['company']['ruc'])
            ->setRazonSocial($data['company']['razonSocial'])
            ->setNombreComercial($data['company']['nombreComercial'])
            ->setAddress($address);

        // Venta
        $invoice = (new Invoice())
            ->setUblVersion($data['ublVersion'])
            ->setTipoOperacion($data['tipoOperacion']) // Venta - Catalog. 51
            ->setTipoDoc($data['tipoDoc']) // Factura - Catalog. 01 
            ->setSerie($data['serie'])
            ->setCorrelativo($data['correlativo'])
            ->setFechaEmision(new DateTime($data['fechaEmision'])) // Zona horaria: Lima
            ->setFormaPago(new FormaPagoContado()) // FormaPago: Contado
            ->setTipoMoneda($data['formaPago']['moneda']) // Sol - Catalog. 02
            ->setCompany($company)
            ->setClient($client)
            ->setMtoOperGravadas($data['mtoOperGravadas'])
            ->setMtoIGV($data['mtoIGV'])
            ->setTotalImpuestos($data['totalImpuestos'])
            ->setValorVenta($data['valorVenta'])
            ->setSubTotal($data['subTotal'])
            ->setMtoImpVenta($data['mtoImpVenta']);
        $Datos_ventas = [];
        foreach ($data['details'] as $key => $element) {
            $item = (new SaleDetail())
                ->setCodProducto($element['codProducto'])
                ->setUnidad($element['unidad']) // Unidad - Catalog. 03
                ->setCantidad($element['cantidad'])
                ->setMtoValorUnitario($element['mtoValorUnitario'])
                ->setDescripcion($element['descripcion'])
                ->setMtoBaseIgv($element['mtoBaseIgv'])
                ->setPorcentajeIgv($element['porcentajeIgv']) // 18%
                ->setIgv($element['igv'])
                ->setTipAfeIgv($element['tipAfeIgv']) // Gravado Op. Onerosa - Catalog. 07
                ->setTotalImpuestos($element['totalImpuestos']) // Suma de impuestos en el detalle
                ->setMtoValorVenta($element['mtoValorVenta'])
                ->setMtoPrecioUnitario($element['mtoPrecioUnitario']);
            array_push($Datos_ventas, $item);
        }
        $legend = (new Legend())
            ->setCode($data['legends'][0]['code']) // Monto en letras - Catalog. 52
            ->setValue($data['legends'][0]['value']);

        $invoice->setDetails($Datos_ventas)
            ->setLegends([$legend]);

        $result = $see->send($invoice);

        $carpeta = public_path("Archivos/$documento");
        if (!file_exists($carpeta)) {
            mkdir($carpeta, 0777, true);
        }
        chmod($carpeta, 0777);

        file_put_contents($carpeta . '/' . $invoice->getName() . '.xml', $see->getFactory()->getLastXml());

        // Verificamos que la conexión con SUNAT fue exitosa.
        if (!$result->isSuccess()) {
            // Mostrar error al conectarse a SUNAT.
            return response()->json([
                'Mensaje Error' => $result->getError()->getMessage(),
                'Codigo Error' => $result->getError()->getCode()
            ], 400);
        }
        $cdrResponse = $result->getCdrResponse();
        $respuesta = [
            "id" => $cdrResponse->getId(),
            "code" => $cdrResponse->getCode(),
            "description" => $cdrResponse->getDescription(),
            "notes" => $cdrResponse->getNotes(),
            "ruta_xml" => $carpeta . '/' . $invoice->getName() . '.xml',
            "ruta_zip" => $carpeta . '/' . 'R-' . $invoice->getName() . '.zip',
        ];
        // Guardamos el CDR
        file_put_contents($carpeta . '/' . 'R-' . $invoice->getName() . '.zip', $result->getCdrZip());
        return response()->json($respuesta);
    }
}
