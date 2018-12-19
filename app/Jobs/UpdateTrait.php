<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Storage;

use Cz\Git\GitRepository;
use Cz\Git\GitException;

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

trait UpdateTrait
{
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
    protected $default_branch;

    /**
     * @var array
     */
    protected $trees;

    /**
     * @var string
     */
    protected $output;

    /**
     *
     */
    private function cloneRepository()
    {
        $url = $this->cloneUrl();

        try {
            $this->git = GitRepository::cloneRepository($url, Storage::path($this->base_path), ['-q', '--depth=1']);

            $this->git->execute(['config', '--local', 'user.name', config('composer.name')]);
            $this->git->execute(['config', '--local', 'user.email', config('composer.email')]);

            $this->git->createBranch($this->branch, true);
        } catch (GitException $e) {
            logger()->error($e->getMessage());

            return;
        }

        $yaml = Yaml::parseFile(Storage::path($this->base_path . '/' . config('composer.yml')));

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
     */
    private function update(string $update_path)
    {
        $path = $this->base_path . $update_path;

        if (!Storage::exists($path . '/composer.json')) {
            return;
        }

        if (!Storage::exists($path . '/composer.lock')) {
            return;
        }

        $cwd = Storage::path($path);
        $env = ['HOME' => config('composer.home')];

        $exec = ['composer', 'install', '--no-interaction', '--no-progress', '--no-suggest', '--no-autoloader'];

        $process = new Process($exec, $cwd, $env);
        $process->setTimeout(300);

        $process->run();
        if (!$process->isSuccessful()) {
            return;
        }

        $exec = ['composer', 'update', '--no-interaction', '--no-progress', '--no-suggest', '--no-autoloader'];

        $process = new Process($exec, $cwd, $env);
        $process->setTimeout(300);

        $process->run();
        if (!$process->isSuccessful()) {
            return;
        }

        $output = $process->getOutput();
        if (empty($output)) {
            $output = $process->getErrorOutput();
        }
        $output = explode(PHP_EOL, $output);

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
    private function commitPush()
    {
        $this->git->addAllChanges();
        $this->git->commit('composer update');
        $this->git->push('origin', [$this->branch]);
    }
}
