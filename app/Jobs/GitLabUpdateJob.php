<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use GrahamCampbell\GitLab\Facades\GitLab;

class GitLabUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use UpdateTrait;

    public $timeout = 600;

    /**
     * @var integer
     */
    protected $repo_id;

    /**
     * Create a new job instance.
     *
     * @param string $token
     * @param array  $repo
     *
     * @return void
     */
    public function __construct(string $token, array $repo)
    {
        $this->token = $token;
        $this->repo = $repo;

        $this->repo_id = data_get($this->repo, 'id');

        $this->repo_owner = data_get($this->repo, 'namespace.path');
        $this->repo_name = data_get($this->repo, 'name');

        $this->random = str_random(6);
        $this->base_path = 'repos/' . $this->random;
        $this->branch = 'composer-update/' . $this->random;

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
        if (cache()->lock('update_job', 60 * 5)->get()) {
            GitLab::authenticate($this->token);

            if (!$this->exists()) {
                return;
            };

            $this->cloneRepository();

            if (!$this->git->hasChanges()) {
                return;
            }

            $this->commitPush();

            $this->createRequest();

            cache()->lock('update_job')->release();
        }
    }

    /**
     * @return bool
     */
    protected function exists(): bool
    {
        try {
            $update = GitLab::repositoryFiles()->getFile(
                $this->repo_id,
                config('composer.yml'),
                $this->default_branch
            );

            return true;
        } catch (\Exception $e) {
            //404 File Not Found

            return false;
        }
    }

    /**
     * @return string
     */
    protected function cloneUrl(): string
    {
        $url = data_get($this->repo, 'http_url_to_repo');
        $url = str_replace('https://', 'https://oauth2:' . $this->token . '@', $url);

        return $url;
    }

    /**
     *
     */
    protected function createRequest()
    {
        GitLab::mergeRequests()->create(
            $this->repo_id,
            $this->branch,
            $this->default_branch,
            'composer update ' . today()->toDateString(),
            null,
            null,
            $this->output
        );
    }
}
