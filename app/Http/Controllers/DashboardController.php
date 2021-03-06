<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use GuzzleHttp;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
//        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('dashboard');
    }

    public function dashboardInfo(Request $request)
    {
        $paramDate = $request->input('selectedDate', '');
        $client = new GuzzleHttp\Client();
        $baseUrl = env('PIS_SERVICE_BASE_URL2');
        $requestString = isset($paramDate) ? 'dashboard/info?selectedDate='.$paramDate : 'dashboard/info';
        $options = [
            'headers' =>[
                'Authorization' => 'Bearer ' .env('PIS_BEARER_TOKEN'),
                'Accept'        => 'application/json',
                'Content-Type' => 'application/json'
            ],
        ];
        $response = $client->request('get', $baseUrl.$requestString, $options);   // call API
        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents());
        return response()->json($body, $statusCode);
    }

    public function homeDashboard(Request $request)
    {
        $paramDate = $request->input('selectedDate', '');
        $client = new GuzzleHttp\Client();
        $baseUrl = env('PIS_SERVICE_BASE_URL2');
        $requestString = isset($paramDate) ? 'dashboard/info?selectedDate='.$paramDate : 'dashboard/info';

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
        $response = $client->request('get', $baseUrl.$requestString, $options);   // call API
        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody()->getContents());
        return response()->json($body, $statusCode);
    }
}
