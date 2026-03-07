<?php

namespace App\Http\Middleware;

use Closure;
// use Illuminate\Console\View\Components\Alert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Alert;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;


class DemoMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Exclude URLs
        $exclude_uri = array(
            '/login',
            '/logout',
            '/api/user_signup',
            '/api/before-logout',
            '/api/post_property',
            '/api/update_post_property',
            '/api/post_project',
            '/api/add_favourite',
            '/api/system_settings',
            '/project-generate-slug',
            'article/generate-slug',
            'category/generate-slug',
            'parameter/generate-slug',
            '/property/generate-slug',
            '/api/gemini/generate-meta',
            '/api/gemini/generate-description',
        );

        // Exclude Emails
        $excludeEmails = [
            "superadmin@gmail.com",
        ];

        /**
         * Conditions
         * 1. Demo Mode is True.
         * 2. Request is not get
         * 3. Authenticated user
         * 4. Authenticated user's email is not in excluded emails
         * 5. Request URL is not in Excluded URL
        */
        if (env('DEMO_MODE') && !$request->isMethod('get')) {
            if(Auth::check()){
                if(in_array(Auth::user()->email, $excludeEmails)){
                    return $next($request);
                }else if(Auth::user()->auth_id != '6a1Zdl2TxORQGbCazj4XDGfgBBG3' && !in_array(Auth::user()->email, ['wrteamdemo@gmail.com', 'admin@gmail.com']) && !(Auth::user()->country_code == '91' && Auth::user()->mobile == '1234567890')){
                    return $next($request);
                }else{
                    if(!in_array($request->getRequestUri(), $exclude_uri)){
                        if ($request->ajax()) {
                            $response['error'] = true;
                            $response['message'] = trans('This is not allowed in the Demo Version');
                            $response['code'] = 403;
                            return response()->json($response);
                        } else if (request()->wantsJson() || Str::startsWith(request()->path(), 'api')) {
                            $response['error'] = true;
                            $response['message'] = trans('This is not allowed in the Demo Version');
                            $response['code'] = 403;
                            return response()->json($response);
                        } else {
                            return back()->with('error', trans('This is not allowed in the Demo Version'));
                        }
                    }
                }
            }else{
                if($request->getRequestUri() == '/api/update-number-password'){
                    if($request->firebase_id == '6a1Zdl2TxORQGbCazj4XDGfgBBG3' && $request->mobile == '1234567890' && $request->country_code == '91'){
                         $response['error'] = true;
                        $response['message'] = trans('This is not allowed in the Demo Version');
                        $response['code'] = 403;
                        return response()->json($response);
                    }
                }
            }
        }
        return $next($request);
    }
}
