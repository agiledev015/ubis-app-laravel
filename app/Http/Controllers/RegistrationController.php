<?php

namespace App\Http\Controllers;

use App\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use GuzzleHttp;
use Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RegistrationController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Index resource
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index() {

        return view('registration');
    }

    public function articles(Request $request) {

        $client = new GuzzleHttp\Client();
        $baseUrl = env('PIS_SERVICE_BASE_URL2');
        $requestString = 'articles?size=10&search='.$request->search_artnr;
        $options = [
            'headers' =>[
            'Authorization' => 'Bearer ' .env('PIS_BEARER_TOKEN'),
            'Accept'        => 'application/json',
            'Content-Type' => 'application/json'
            ]
        ];

        $response = $client->request('GET', $baseUrl.$requestString, $options);   // call API
    	$statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents());

        return response()->json(array('data' => $body->data), $statusCode);
    }

    public function updateArticleOptions(Request $request, $id) {

        $client = new GuzzleHttp\Client();
        $baseUrl = env('PIS_SERVICE_BASE_URL2');
        $endpoint = 'articles/'.$id.'/options';

        $requestData = $request->all();

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
        $callUrl = $baseUrl.$endpoint;

        try{
            $response = $client->request('POST', $callUrl, $options );   // call API by serial+article-nr.
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

    public function article($id) {
        $cacheKeyArticle = 'article_by_nr_with_bom_'.$id;
        $response = Cache::get($cacheKeyArticle);
        if( $response == null ){
            $client = new GuzzleHttp\Client();
            $baseUrl = env('PIS_SERVICE_BASE_URL2');
            $requestString = 'articles/'.$id;
            $options = [
                'http_errors'=> false,
                'headers' =>[
                'Authorization' => 'Bearer ' .env('PIS_BEARER_TOKEN'),
                'Accept'        => 'application/json',
                'Content-Type' => 'application/json'
                ]
            ];

            $response = $client->request('GET', $baseUrl.$requestString, $options);   // call API
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents());

            if( \property_exists($body, 'data') ){
                $articleData = array();
                if( \property_exists($body->data, 'bom') ){
                    $bom = is_string($body->data->bom)?json_decode($body->data->bom):$body->data->bom;  // former PCService implementation delivered BOM as string, so take care of this
                    $bom = (is_array($bom))?$bom:array();  // prepare $bom for foreach()
                    foreach($bom as $key=>$value){
                        /*
                        {
                        "id": 48798,
                        "version": 0,
                        "quantity": 1,
                        "articleId": 46793,
                        "createDate": "2019-10-16 15:16:51.288",
                        "articleNumber": "10000214A1",
                        "positionNumber": 1,
                        "lastModifiedDate": "2019-10-16 15:16:51.288"
                        },
                        */
                        for($quantity=0; $quantity<$value->quantity; $quantity++){
                            $requestString = 'articles/'.$value->articleNumber;
                            $response = $client->request('GET', $baseUrl.$requestString, $options);   // call API
                            $articleData[] = json_decode($response->getBody()->getContents())->data;
                        }
                    };
                }
                $body->data->bom = $articleData;    // assigne reworked reworked BOM, or empty array to response
                Cache::put($cacheKeyArticle, array('data' => $body->data, 'code' => $statusCode), now()->addMinutes(20));
                return response()->json(array('data' => $body->data), $statusCode);
            }else{
                return response()->json(array('data' => $body), $statusCode);   // could not find data section, return body as given
            }
        }
        //Log::notice('Cache hit for '.$cacheKeyArticle);
        return  response()->json(array('data' => $response['data']), $response['code']);   // could not find data section, return body as given
    }

    /**
     * Create new product including subcomponent, by adding a subcomponent serial nr.
     * $id == '-' or null
     */
    public function createProduct(Request $request, $id){
        $requestData = array_merge($request->all(), array('product_id' => $id));

        $validator = Validator::make($requestData, [
            'component_article_nr' => 'string|required|between:5,64',
            'component_serial_nr' => 'string|required|between:1,64',
            'production_order_nr' => 'string|between:1,64|nullable',
            'article_nr' => 'required_if:product_id,-|nullable|string|between:5,64',
            'product_id' => 'exclude_if:product_id,-|required|uuid',    // if product id == - -> skip check
        ]);

        if($validator->fails()){
            return response()->json(['message' =>  'Append component failed. Wrong parameter. '.implode (' ',$validator->errors()->all()).' '.implode('#',$request->all()) ], 422);
        }
        $articleNr = $request->input('article_nr', null);

        $client = new GuzzleHttp\Client();
        $baseUrl = env('PIS_SERVICE_BASE_URL2');
        $options = [
            'http_errors'=> false,
            'headers' =>[
            'Authorization' => 'Bearer ' .env('PIS_BEARER_TOKEN'),
            'Accept'        => 'application/json',
            'Content-Type' => 'application/json'
            ]
        ];

        $productionOrderNr = $request->input('production_order_nr', null);
        $postData = array('st_article_nr' => $articleNr);
        if( $productionOrderNr != null && $productionOrderNr != ''){
            $postData['production_order_nr'] = $productionOrderNr;
        }
        $productNewlyCreated = false;

        $product = null;
        if( empty($id) || $id === '-'){
            // no product ID given -> create product
            $requestString = 'products';
            $response = $client->request('POST', $baseUrl.$requestString, array_merge($options, ['json' => $postData]));
            $statusCode = $response->getStatusCode();
            if( $statusCode != 201){
                $statusMessage = 'Could not create product.';
                if( $response &&  !empty($response->getBody()) && !empty((string)$response->getBody())){
                        $responseContent = json_decode((string)$response->getBody(), true);
                        $statusMessage = (array_key_exists('error', $responseContent))?$responseContent['error']:$statusMessage;
                        $statusMessage = (array_key_exists('message', $responseContent))?$responseContent['message']:$statusMessage;
                }
                return response()->json(['code' => $statusCode, 'error' =>  $statusMessage], $statusCode);
            }
            $productNewlyCreated = true;
            // success
        }else{
            // product ID is given, request product
            $requestString = 'products/'.$id;
            $response = $client->request('GET', $baseUrl.$requestString, $options );
            $statusCode = $response->getStatusCode();
            if( $statusCode != 200){
                $statusMessage = 'Could not fetch product.';
                if( $response &&  !empty($response->getBody()) && !empty((string)$response->getBody())){
                        $responseContent = json_decode((string)$response->getBody(), true);
                        $statusMessage = (array_key_exists('error', $responseContent))?$responseContent['error']:$statusMessage;
                        $statusMessage = (array_key_exists('message', $responseContent))?$responseContent['message']:$statusMessage;
                }
                return response()->json(['code' => $statusCode, 'error' =>  $statusMessage], $statusCode);
            }
            // success
        }
        $product = json_decode((string)$response->getBody());
        $product = $product->data;

        // must have a valid product on that place -> adopt component
        $requestString = 'products/'.$product->id.'/components';
        $response = $client->request('POST', $baseUrl.$requestString, array_merge($options, ['json' => [
            'st_article_nr' => $requestData['component_article_nr'],
            'serial_nr' => $requestData['component_serial_nr']
            ]]));
        $statusCode = $response->getStatusCode();
        if( $statusCode != 200){
            $statusMessage = 'Could not create component.';
            if( $response &&  !empty($response->getBody()) && !empty((string)$response->getBody())){
                    $responseContent = json_decode((string)$response->getBody(), true);
                    $statusMessage = (array_key_exists('error', $responseContent))?$responseContent['error']:$statusMessage;
                    $statusMessage = (array_key_exists('message', $responseContent))?$responseContent['message']:$statusMessage;
            }
            if($productNewlyCreated){
                // we have to delete the product on component creation error, otherwise it will tangel around and block serial
                // TODO: missing API to delete product
                // $client->request('DELETE', $baseUrl.$requestString.'/', array_merge($options, ['json' => ['st_article_nr' => $articleNr]]));
            }
            return response()->json(['code' => $statusCode, 'error' =>  $statusMessage], $statusCode);
        }
        // success, we get back the product on component creation
        $product = json_decode((string)$response->getBody());
        $product = $product->data;
        $componentId = null;
        foreach($product->components as $component){
            if( $component->st_article_nr == $requestData['component_article_nr'] && $component->serial_nr == $requestData['component_serial_nr']){
                // found right one
                $componentId = $component->id;
                break;
            }
        }

        $statusCode = 200;
        return response()->json(array('data' =>[
            'product_serial' => $product->st_serial_nr,
            'product_id' => $product->id,
            'component_id' => $componentId
            ]), $statusCode);
    }

    /**
     * Show product information
     */
    public function showProduct(Request $request, $id){
        $requestData = array_merge($request->all(), array('id' => $id));

        $validator = Validator::make($requestData, [
            'article_nr' => 'string|between:5,65',
            'product_id' => 'nullable|string|between:1,64',    // if product id == - -> skip check
        ]);

//        print_r($requestData);die(__FILE__);
        if($validator->fails()){
            return response()->json(['message' =>  'Requesting product failed. Wrong parameter. '.implode (' ',$validator->errors()->all()).' '.implode('#',$request->all()) ], 422);
        }
        $articleNr = $request->input('article_nr', null);
        $cacheKey = $id.$articleNr;
        $cacheStatusCode = $cacheKey.'statuscode';
        if (Cache::has($cacheKey.'_disabled')) {
            $body = Cache::get($cacheKey);
            $statusCode = Cache::get($cacheStatusCode);
            return response()->json($body, $statusCode);
        } else {
            $client = new GuzzleHttp\Client();
            $baseUrl = env('PIS_SERVICE_BASE_URL2');
            $options = [
                'http_errors'=> false,
                'headers' =>[
                    'Authorization' => 'Bearer ' .env('PIS_BEARER_TOKEN'),
                    'Accept'        => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ];

            $checkUUid = Validator::make(['id' => $id], [
                'id' => 'required|uuid'
            ]);

            $product = null;
            $requestString = 'products/'.$id.($request->input('lookup_subcomponents', false)?'?lookup_subcomponents='.$request['lookup_subcomponents']:'');
            if( $articleNr != null && $checkUUid->fails() ){
                // article nr given, get product by serial
                $requestString = 'products/'.urlencode($id).'?article_nr='.$articleNr.($request->input('lookup_subcomponents', false)?'&lookup_subcomponents='.$request['lookup_subcomponents']:'');
            }

            $response = $client->request('GET', $baseUrl.$requestString, $options);

            $statusCode = $response->getStatusCode();
            if( $statusCode != 200){
                $statusMessage = 'Could not fetch product.';
                if( $response &&  !empty($response->getBody()) && !empty((string)$response->getBody())){
                    $responseContent = json_decode((string)$response->getBody(), true);
                    $statusMessage = (array_key_exists('error', $responseContent))?$responseContent['error']:$statusMessage;
                    $statusMessage = (array_key_exists('message', $responseContent))?$responseContent['message']:$statusMessage;
                }
                return response()->json(['code' => $statusCode, 'error' =>  $statusMessage], $statusCode);
            }

            $body = json_decode((string)$response->getBody());

            // resolve subcomponents article names
            // $body->data->components exists anyways, even empty
            foreach($body->data->components as $component){
                $requestString = 'articles/'.$component->st_article_nr;
                $response = $client->request('GET', $baseUrl.$requestString, $options);   // call API
                $statusCode = $response->getStatusCode();
                $articleBody = json_decode($response->getBody()->getContents());
                if( $statusCode == 200 ){
                    $component->st_article_name = $articleBody->data->name;
                }else{
                    $statusMessage = 'Could not fetch subcomponents.';
                    if( $response &&  !empty($response->getBody()) && !empty((string)$response->getBody())){
                        $responseContent = json_decode((string)$response->getBody(), true);
                        $statusMessage = (array_key_exists('error', $responseContent))?$responseContent['error']:$statusMessage;
                        $statusMessage = (array_key_exists('message', $responseContent))?$responseContent['message']:$statusMessage;
                    }
                    return response()->json(['code' => $statusCode, 'error' =>  $statusMessage], $statusCode);
                }
            }
            Cache::put($cacheKey, $body, now()->addMinutes(20));
            Cache::put($cacheStatusCode, $statusCode, now()->addMinutes(20));
            return response()->json($body, $statusCode);
        }
    }

    /**
     * Delete component by id
     */
    public function deleteComponent(Request $request, $product_id, $component_id){
        $requestData = ['product_id' => $product_id, 'component_id' => $component_id];

        $validator = Validator::make($requestData, [
            'component_id' => 'uuid|required',
            'product_id' => 'uuid|required'
        ]);

        if($validator->fails()){
            return response()->json(['message' =>  'Delete component failed. Wrong parameter. '.implode (' ',$validator->errors()->all()).' '.implode('#',$request->all()) ], 422);
        }

        $client = new GuzzleHttp\Client();
        $baseUrl = env('PIS_SERVICE_BASE_URL2');
        $options = [
            'http_errors'=> false,
            'headers' =>[
            'Authorization' => 'Bearer ' .env('PIS_BEARER_TOKEN'),
            'Accept'        => 'application/json',
            'Content-Type' => 'application/json'
            ]
        ];

        $requestString = 'products/'.$product_id.'/components/'.$component_id;
        $response = $client->request('DELETE', $baseUrl.$requestString, $options);
        $statusCode = $response->getStatusCode();
        if( $statusCode != 200){
            $statusMessage = 'Could not delete component.';
            if( $response &&  !empty($response->getBody()) && !empty((string)$response->getBody())){
                    $responseContent = json_decode((string)$response->getBody(), true);
                    $statusMessage = (array_key_exists('error', $responseContent))?$responseContent['error']:$statusMessage;
                    $statusMessage = (array_key_exists('message', $responseContent))?$responseContent['message']:$statusMessage;
            }
            return response()->json(['code' => $statusCode, 'error' =>  $statusMessage], $statusCode);
        }
        return response()->json([], $statusCode);
    }
}
