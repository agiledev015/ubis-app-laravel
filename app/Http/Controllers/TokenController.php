<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Session;
use Exception;
use Log;

class TokenController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    private function processCall($endpoint, $type='GET')
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
        $callUrl = $baseUrl.$endpoint;

        try{
            $response = $client->request($type, $callUrl, $options );   // call API by serial+article-nr.
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


    public function getDeveloperAccessToken() {

        return $this->processCall('tokens/getDeveloperToken');

    }

    public function createDeveloperAccessToken() {

        return $this->processCall('tokens/createDeveloperToken');

    }

    public function resetDeveloperAccessToken() {

        return $this->processCall('tokens/resetDeveloperToken');

    }

    public function createApiAccessToken() {

        return $this->processCall('tokens/createApiToken');

    }

    public function getDeveloperAccessTokens() {

        return $this->processCall('tokens/getDeveloperTokens');

    }

    public function getApiAccessTokens() {

        return $this->processCall('tokens/getApiTokens');

    }
}
