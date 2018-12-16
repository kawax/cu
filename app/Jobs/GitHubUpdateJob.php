<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use Cz\Git\GitRepository;
use Cz\Git\GitException;
use GrahamCampbell\GitHub\Facades\GitHub;

class GitHubUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use UpdateTrait;

    public $timeout = 600;

    const UPDATE = '.update.yml';

    /**
     * @var GitRepository
     */
    protected $git;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var array
     */
    protected $repo;

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

        $this->repo_owner = data_get($this->repo, 'owner.login');
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
        GitHub::authenticate($this->token, 'http_token');

        if (!$this->exists()) {
            return;
        };

        $url = $this->cloneUrl();

        $this->cloneRepository($url);

        if (!$this->commitPush()) {
            return;
        }

        $this->pullRequest();
    }

    /**
     * @return bool
     */
    private function exists(): bool
    {
        return GitHub::repo()->contents()->exists(
            $this->repo_owner,
            $this->repo_name,
            self::UPDATE
        );
    }

    /**
     * @return string
     */
    private function cloneUrl(): string
    {
        $url = data_get($this->repo, 'clone_url');
        $url = str_replace('https://', 'https://' . $this->token . '@', $url);

        return $url;
    }

    /**
     *
     */
    private function pullRequest()
    {
        $pullData = [
            'base'  => data_get($this->repo, 'default_branch'),
            'head'  => $this->branch,
            'title' => 'composer update',
            'body'  => $this->output,
        ];

        $pullRequest = GitHub::pullRequest()->create(
            $this->repo_owner,
            $this->repo_name,
            $pullData
        );
    }
}
