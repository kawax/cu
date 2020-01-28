<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use Illuminate\Support\Str;

use GrahamCampbell\GitHub\Facades\GitHub;

class GitHubUpdateJob implements ShouldQueue
{
    use Queueable;
    use Dispatchable;
    use SerializesModels;
    use InteractsWithQueue;

    use UpdateTrait;

    public $timeout = 600;

    /**
     * Create a new job instance.
     *
     * @param  string  $token
     * @param  array  $repo
     *
     * @return void
     */
    public function __construct(string $token, array $repo)
    {
        $this->token = $token;
        $this->repo = $repo;

        $this->repo_owner = data_get($this->repo, 'owner.login');
        $this->repo_name = data_get($this->repo, 'name');

        $this->random = Str::random(6);
        $this->base_path = 'repos/'.$this->random;
        $this->branch = 'cu/'.$this->random;

        $this->default_branch = data_get($this->repo, 'default_branch');
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws
     */
    public function handle()
    {
        GitHub::authenticate($this->token, 'http_token');

        if (! $this->exists()) {
            return;
        }

        info($this->repo_name);

        $this->cloneRepository();

        if (blank($this->git) or ! $this->git->hasChanges()) {
            return;
        }

        $this->commitPush();

        $this->createRequest();
    }

    /**
     * @return bool
     */
    protected function exists(): bool
    {
        return GitHub::repo()->contents()->exists(
            $this->repo_owner,
            $this->repo_name,
            config('composer.yml')
        );
    }

    /**
     * @return string
     */
    protected function cloneUrl(): string
    {
        $url = data_get($this->repo, 'clone_url');
        $url = str_replace('https://', 'https://'.$this->token.'@', $url);

        return $url;
    }

    /**
     *
     */
    protected function createRequest()
    {
        $pullData = [
            'base'  => $this->default_branch,
            'head'  => $this->branch,
            'title' => 'composer update '.today()->toDateString(),
            'body'  => $this->output,
        ];

        $pullRequest = GitHub::pullRequest()->create(
            $this->repo_owner,
            $this->repo_name,
            $pullData
        );
    }

    /**
     * @return array
     */
    public function tags()
    {
        return ['repo:'.$this->repo_name];
    }
}
