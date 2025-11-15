<?php

namespace App\Http\Controllers\SSO;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SSOController extends Controller
{
    public function login(Request $request)
    {
        $request->session()->put('state', $state = Str::random(40));
        $query = http_build_query([
            'client_id' => "a05c1dd4-da06-4dbb-9bb8-4059b187e74f",
            'redirect_uri' => "http://127.0.0.1:8080/callback",
            'response_type' => 'code',
            'scope' => ['view-user','create-post'],
            'state' => $state,
        ]);

        return redirect('http://127.0.0.1:8000/oauth/authorize?'.$query);
    }

    public function callback(Request $request)
    {
        $state = $request->session()->pull('state');

        throw_unless(
            strlen($state) > 0 && $state === $request->state,
            \InvalidArgumentException::class
        );

        $response = Http::asForm()->post(
            'http://127.0.0.1:8000/oauth/token',
            [
                'grant_type' => 'authorization_code',
                'client_id' => "a05c1dd4-da06-4dbb-9bb8-4059b187e74f",
                'client_secret' => "RoK4GAoTxEC84hUmxvVok4xY61TpOu5ssnWiT1x9",
                'redirect_uri' => "http://127.0.0.1:8080/callback",
                'code' => $request->code,
            ]
        );

        $request->session()->put(
            'access_token',
            $response->json()['access_token']
        );

        return redirect('/authuser');
    }

    public function connectUser(Request $request)
    {
        $accessToken = $request->session()->get('access_token');

        $response = Http::withHeaders([
            "Accept" => "application/json",
            "Authorization" => "Bearer ".$accessToken,
        ])->get('http://127.0.0.1:8000/api/user');

        $userArray = $response->json();

            try{
                $email = $userArray['email'];
            }catch(\Exception $e){
                return redirect('/login')->withErrors(['msg' => 'Unable to retrieve user information. Please try logging in again.']);
            }
            $user  = User::where('email',$email)->first();

            if(!$user){
                $user = new User();
                $user->name = $userArray['name'];
                $user->email = $userArray['email'];
                $user->email_verified_at = $userArray['email_verified_at'];
                $user->save();
            }


            Auth::login($user);

            return redirect('/home');
    }
}
