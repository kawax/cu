<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use GrahamCampbell\GitHub\Facades\GitHub;
use GrahamCampbell\GitLab\Facades\GitLab;

use App\Model\User;
use App\Jobs\GitHubUpdateJob;
use App\Jobs\GitLabUpdateJob;

class UpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'composer:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     * @throws
     */
    public function handle()
    {
        $users = User::where(
            function ($query) {
                return $query->whereDate('expired_at', '>=', today())
                             ->orWhereNull('expired_at');
            }
        )->get();

        if (empty($users)) {
            return;
        }

        $users->each(
            function ($user) {
                if (! app()->isLocal()) {
                    $this->github($user->github_token);
                }

                $this->gitlab($user->gitlab_token);
            }
        );
    }

    /**
     * @param $token
     */
    private function github($token)
    {
        $this->comment('GitHub');

        GitHub::authenticate($token, 'http_token');

        $github_repos = GitHub::me()->repositories(
            'owner',
            'pushed',
            'desc',
            'all',
            'owner,organization_member'
        );

        foreach ($github_repos as $repo) {
            $this->info(data_get($repo, 'full_name'));

            GitHubUpdateJob::dispatch($token, $repo);
        }
    }

    /**
     * @param $token
     */
    private function gitlab($token)
    {
        $this->comment('GitLab');

        if (blank($token)) {
            return;
        }

        GitLab::authenticate($token);

        $gitlab_repos = GitLab::projects()->all(
            [
                'order_by' => 'last_activity_at',
                'sort'     => 'desc',
                'owned'    => true,
                'simple'   => true,
                'archived' => false,
            ]
        );

        if (app()->isLocal()) {
            $gitlab_repos = [head($gitlab_repos)];
        }

        foreach ($gitlab_repos as $repo) {
            $this->info(data_get($repo, 'path_with_namespace'));

            GitLabUpdateJob::dispatch($token, $repo);
        }
    }
}
