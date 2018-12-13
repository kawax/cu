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

class UpdateJob implements ShouldQueue
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
     * @var array
     */
    protected $trees;

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

        if ($exists == false) {
            return;
        };

        $this->cloneRepository();

        if (blank($this->trees)) {
            return;
        }

        $this->commit();

        $this->pullRequest();
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function cloneRepository()
    {
        $url = data_get($this->repo, 'clone_url');

        try {
            GitRepository::cloneRepository($url, Storage::path($this->base_path), ['-q', '--depth=1']);
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

        $before_md5 = md5(Storage::get($this->base_path . $update_path . '/composer.lock'));

        $exec = 'export HOME=' . config('composer.home') . '; composer update -d ' . Storage::path($this->base_path) . $update_path . ' --no-progress --no-suggest 2>&1';
        info($exec);

        exec($exec, $output, $return_var);

        $after_md5 = md5(Storage::get($this->base_path . $update_path . '/composer.lock'));

        if ($before_md5 === $after_md5) {
            return;
        }

        $content = Storage::get($this->base_path . $update_path . '/composer.lock');

        if (!empty($content)) {
            $this->createBlob($update_path, $content);
        }
    }

    /**
     * @param string $update_path
     * @param string $content
     */
    private function createBlob(string $update_path, string $content)
    {
        $blob = GitHub::gitData()->blobs()->create(
            $this->repo_owner,
            $this->repo_name,
            [
                'content'  => $content,
                'encoding' => 'utf-8',
            ]
        );

        $blob_sha = data_get($blob, 'sha');

        $this->trees[] = [
            'path' => ltrim($update_path, '/') . 'composer.lock',
            'mode' => '100644',
            'type' => 'blob',
            'sha'  => $blob_sha,
        ];
    }

    /**
     *
     */
    private function commit()
    {
        $reference = GitHub::gitData()->references()->show(
            $this->repo_owner,
            $this->repo_name,
            'heads/' . data_get($this->repo, 'default_branch')
        );

        $base_commit_sha = data_get($reference, 'object.sha');

        $parent_commit = GitHub::gitData()->commits()->show(
            $this->repo_owner,
            $this->repo_name,
            $base_commit_sha
        );

        $base_tree_sha = data_get($parent_commit, 'tree.sha');

        $treeData = [
            'base_tree' => $base_tree_sha,
            'tree'      => $this->trees,
        ];

        $tree = GitHub::gitData()->trees()->create(
            $this->repo_owner,
            $this->repo_name,
            $treeData
        );

        $tree_sha = data_get($tree, 'sha');

        $commitData = [
            'message'   => 'composer update',
            'tree'      => $tree_sha,
            'parents'   => [$base_commit_sha],
            'committer' => [
                'name'  => 'cu',
                'email' => 'cu@kawax.biz',
                'date'  => now()->toIso8601String(),
            ],
        ];

        $new_commit = GitHub::gitData()->commits()->create(
            $this->repo_owner,
            $this->repo_name,
            $commitData
        );

        $new_commit_sha = data_get($new_commit, 'sha');

        $referenceData = [
            'ref' => 'refs/heads/' . $this->branch,
            'sha' => $new_commit_sha,
        ];

        $new_reference = GitHub::gitData()->references()->create(
            $this->repo_owner,
            $this->repo_name,
            $referenceData
        );
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
            'body'  => '',
        ];

        $pullRequest = GitHub::pullRequest()->create(
            $this->repo_owner,
            $this->repo_name,
            $pullData
        );
    }
}
