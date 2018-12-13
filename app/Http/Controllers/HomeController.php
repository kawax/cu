<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use GrahamCampbell\GitHub\Facades\GitHub;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        GitHub::authenticate($request->user()->github_token, 'http_token');

        $repos = cache()->remember('repos/' . $request->user()->id, 60, function () {
            $repos = GitHub::me()->repositories('owner', 'pushed', 'desc', 'public', 'owner,organization_member');

            return array_pluck($repos, 'full_name');
        });

        return view('home')->with(compact('repos'));
    }
}
