<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use Illuminate\Support\Facades\Storage;
use Cz\Git\GitRepository;
use Cz\Git\GitException;
use GrahamCampbell\GitLab\Facades\GitLab;
use Symfony\Component\Yaml\Yaml;

class GitLabUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use UpdateTrait;

    public $timeout = 600;

    const UPDATE = '.update.yml';

    /**
     * @var string
     */
    protected $token;

    /**
     * @var array
     */
    protected $repo;

    /**
     * @var integer
     */
    protected $repo_id;

    /**
     * @var string
     */
    protected $repo_owner;

    /**
     * @var string
     */
    protected $repo_name;

    /**
     * @var string
     */
    protected $random;

    /**
     * @var string
     */
    protected $base_path;

    /**
     * @var string
     */
    protected $branch;

    /**
     * @var array
     */
    protected $trees;

    /**
     * @var string
     */
    protected $output;

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
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws
     */
    public function handle()
    {
        GitLab::authenticate($this->token);

        if (!$this->exists()) {
            return;
        };

        $url = $this->cloneUrl();

        $this->cloneRepository($url);

        $this->commitPush();

        $this->mergeRequests();
    }

    /**
     * @return bool
     */
    private function exists(): bool
    {
        try {
            $update = GitLab::repositoryFiles()->getFile(
                $this->repo_id,
                self::UPDATE,
                data_get($this->repo, 'default_branch')
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
    private function cloneUrl(): string
    {
        $url = data_get($this->repo, 'http_url_to_repo');
        $url = str_replace('https://', 'https://oauth2:' . $this->token . '@', $url);

        return $url;
    }

    /**
     * @throws GitException
     */
    private function mergeRequests()
    {
        GitLab::mergeRequests()->create(
            $this->repo_id,
            $this->branch,
            data_get($this->repo, 'default_branch'),
            'composer update',
            null,
            null,
            $this->output
        );
    }
}
