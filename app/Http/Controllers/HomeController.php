<?php

namespace App\Http\Controllers;

use GrahamCampbell\GitHub\Facades\GitHub;
use GrahamCampbell\GitLab\Facades\GitLab;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @param  Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        GitHub::authenticate($request->user()->github_token, 'http_token');

        $github_repos = cache()->remember(
            'github_repos/'.$request->user()->id,
            now()->addHours(1),
            function () {
                $repos = GitHub::me()->repositories('owner', 'pushed', 'desc', 'all', 'owner,organization_member');

                return Arr::pluck($repos, 'full_name');
            }
        );

        if (filled($request->user()->gitlab_token)) {
            GitLab::authenticate($request->user()->gitlab_token);

            $gitlab_repos = cache()->remember(
                'gitlab_repos/'.$request->user()->id,
                now()->addHours(1),
                function () {
                    $gitlab_repos = GitLab::projects()->all(
                        [
                            'order_by' => 'last_activity_at',
                            'sort'     => 'desc',
                            'owned'    => true,
                            'simple'   => true,
                            'archived' => false,
                        ]
                    );

                    return Arr::pluck($gitlab_repos, 'path_with_namespace');
                }
            );
        }

        return view('home')->with(compact('github_repos', 'gitlab_repos'));
    }
}
