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
use GrahamCampbell\GitHub\Facades\GitHub;
use Symfony\Component\Yaml\Yaml;

class GitHubUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        GitHub::authenticate($this->token, 'http_token');

        $exists = GitHub::repo()->contents()->exists(
            $this->repo_owner,
            $this->repo_name,
            self::UPDATE
        );

        if (!$exists) {
            return;
        };

        $this->cloneRepository();

        $this->pullRequest();
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function cloneRepository()
    {
        $url = data_get($this->repo, 'clone_url');
        $url = str_replace('https://', 'https://' . $this->token . '@', $url);

        try {
            $git = GitRepository::cloneRepository($url, Storage::path($this->base_path), ['-q', '--depth=1']);

            $git->createBranch($this->branch, true);

            $git->execute(['config', '--local', 'user.name', config('composer.name')]);
            $git->execute(['config', '--local', 'user.email', config('composer.email')]);
        } catch (GitException $e) {
            logger()->error($e->getMessage());
        }

        $yaml = Yaml::parseFile(Storage::path($this->base_path . '/' . self::UPDATE));

        if (data_get($yaml, 'enabled', false) == false) {
            return;
        }

        $updates = data_get($yaml, 'updates', []);

        foreach ($updates as $update) {
            if (array_has($update, 'path')) {
                $this->update(data_get($update, 'path'));
            }
        }
    }

    /**
     * @param string $update_path
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function update(string $update_path)
    {
        if (!Storage::exists($this->base_path . $update_path . '/composer.json')) {
            return;
        }

        if (!Storage::exists($this->base_path . $update_path . '/composer.lock')) {
            return;
        }

        $exec = 'env HOME=' . config('composer.home') . ' composer install -d ' . Storage::path($this->base_path) . $update_path . ' --no-interaction --no-progress --no-suggest 2>&1';
        exec($exec);

        $exec = 'env HOME=' . config('composer.home') . ' composer update -d ' . Storage::path($this->base_path) . $update_path . ' --no-interaction --no-progress --no-suggest 2>&1';

        exec($exec, $output, $return_var);

        if ($return_var !== 0) {
            return;
        }

        $this->output .= collect($output)
                ->filter(function ($item) {
                    return str_contains($item, '- Updating');
                })->map(function ($item) {
                    return trim($item);
                })->implode(PHP_EOL) . PHP_EOL;
    }

    /**
     *
     */
    private function pullRequest()
    {
        $git = new GitRepository(Storage::path($this->base_path));

        if (!$git->hasChanges()) {
            return;
        }

        $git->addAllChanges();
        $git->commit('composer update');
        $git->push('origin', [$this->branch]);

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
