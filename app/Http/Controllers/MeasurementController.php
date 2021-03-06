<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use GuzzleHttp\Client;
use Throwable;


class MeasurementController extends Controller
{

    public function index(Request $request)
    {
        $client = new Client();
        $baseUrl = env('PIS_SERVICE_BASE_URL2');

        $requestData = $request->all();

        // set given query parameters, to be able to forward them
        $query = $request->query();
        $passOnQuery = "";
        if( count($query) ){
            $passOnQuery .= '?';
            foreach($query as $key=>$value){
                $passOnQuery .= $key.'='.urlencode($value).'&';
            }
        }

        $bearer_token = '';
        if (Session::has('bearer_token')) {
            $bearer_token = Session::get('bearer_token');
        } else {
            return redirect('login');
        }

        $options = [
            'headers' =>[
                'Authorization' => 'Bearer ' .$bearer_token,
                'Accept'        => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'json' => $requestData
        ];

        $response = null;
        $callUrl = $baseUrl.'device_records'.$passOnQuery;

        try{
            $response = $client->request('GET', $callUrl, $options );   // call API by serial+article-nr.
        }catch(Throwable $e){
            // fail
            $message = 'Unknown Error';
            if ($e->getCode() == 401) {
                Session::forget('bearer_token');
            } else {
                switch($e->getCode()){
                    case 404: $message = 'Not found';break;
                    case 422: $message = 'Wrong parameter';break;
                    case 409: $message = 'Create/store failed';break;
                }
            }
            return response(['code' => $e->getCode(), 'error' =>  'Backend call failed.'.$e->getMessage()], $e->getCode());
        }

        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents());
        return response()->json($body, $statusCode);
    }

    public function getMeasurement(Request $request, $id)
    {
        $client = new Client();
        $baseUrl = env('PIS_SERVICE_BASE_URL2');

        $bearer_token = '';
        if (Session::has('bearer_token')) {
            $bearer_token = Session::get('bearer_token');
        } else {
            return redirect('login');
        }

        $options = [
            'headers' =>[
                'Authorization' => 'Bearer ' .$bearer_token,
                'Accept'        => 'application/json',
                'Content-Type' => 'application/json'
            ],
        ];

        $response = null;
        $callUrl = $baseUrl.'device_records/'.$id;

        try{
            $response = $client->request('GET', $callUrl, $options );   // call API by serial+article-nr.
        }catch(Throwable $e){
            // fail
            $message = 'Unknown Error';
            if ($e->getCode() == 401) {
                Session::forget('bearer_token');
            } else {
                switch($e->getCode()){
                    case 404: $message = 'Not found';break;
                    case 422: $message = 'Wrong parameter';break;
                    case 409: $message = 'Create/store failed';break;
                }
            }
            return response(['code' => $e->getCode(), 'error' =>  'Backend call failed.'.$e->getMessage()], $e->getCode());
        }

        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents());
        return response()->json($body, $statusCode);
    }

    public function reloadMeasurements(Request $request) {

        $client = new Client();
        $baseUrl = env('PIS_SERVICE_BASE_URL2');

        $bearer_token = '';
        if (Session::has('bearer_token')) {
            $bearer_token = Session::get('bearer_token');
        } else {
            return redirect('login');
        }

        $options = [
            'http_errors'=> false,
            'headers' =>[
                'Authorization' => 'Bearer ' .$bearer_token,
                'Accept'        => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ];

        $uuid = $request->input('id', '');
        $sectionId = $request->input('sectionId', '');

        $putData = array('id' => $uuid, 'sectionId' => $sectionId);
        //'products/{id or serialNr}/section/{sectionId}'
        $requestString = 'products/'.$uuid.'/section/'.$sectionId;
        $response = $client->request('PUT', $baseUrl.$requestString, array_merge($options, ['json' => $putData]));
        $statusCode = $response->getStatusCode();

//        $responseContent = json_decode((string)$response->getBody(), true);
//        print_r(array($requestString, $statusCode, $responseContent));
//        die(__FILE__);
        if( $statusCode != 201 && $statusCode != 200 ){
            $statusMessage = 'Could not create product.';
            if( $response &&  !empty($response->getBody()) && !empty((string)$response->getBody())){
                $responseContent = json_decode((string)$response->getBody(), true);
                $statusMessage = (array_key_exists('error', $responseContent))?$responseContent['error']:$statusMessage;
                $statusMessage = (array_key_exists('message', $responseContent))?$responseContent['message']:$statusMessage;
            }
            //echo 'Product with serial '.$serialNr.' could not be created ('.$statusMessage.')'."\r\n";
            return array('status'=>false, 'id'=>$uuid);
        }

        $product = json_decode((string)$response->getBody());
        $product = $product->data;
        return array('status'=>true, 'data'=>$product);
    }

    public function showSupportValues() {
        $client = new GuzzleHttp\Client();
        $baseUrl = env('PIS_SERVICE_BASE_URL2');
        $requestString = 'device_records/form_support';

        $bearer_token = '';
        if (Session::has('bearer_token')) {
            $bearer_token = Session::get('bearer_token');
        } else {
            return redirect('login');
        }

        $options = [
            'headers' =>[
                'Authorization' => 'Bearer ' .$bearer_token,
                'Accept'        => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ];

        $response = $client->request('GET', $baseUrl.$requestString, $options);   // call API
        $body = json_decode($response->getBody()->getContents());

        return response()->json($body);
    }
}
