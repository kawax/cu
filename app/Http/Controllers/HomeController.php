<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use GrahamCampbell\GitHub\Facades\GitHub;
use GrahamCampbell\GitLab\Facades\GitLab;

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

        $github_repos = cache()->remember('github_repos/' . $request->user()->id, 60, function () {
            $repos = GitHub::me()->repositories('owner', 'pushed', 'desc', 'all', 'owner,organization_member');

            return array_pluck($repos, 'full_name');
        });

        if (filled($request->user()->gitlab_token)) {
            GitLab::authenticate($request->user()->gitlab_token);

            $gitlab_repos = cache()->remember('gitlab_repos/' . $request->user()->id, 60, function () {
                $gitlab_repos = GitLab::projects()->all([
                    'order_by' => 'last_activity_at',
                    'sort'     => 'asc',
                    'owned'    => true,
                    'simple'   => true,
                ]);

                return array_pluck($gitlab_repos, 'path_with_namespace');
            });
        }

        return view('home')->with(compact('github_repos', 'gitlab_repos'));
    }
}
