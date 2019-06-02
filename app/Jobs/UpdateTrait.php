<?php

namespace App\Jobs;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;

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
     * @var string
     */
    protected $output;

    /**
     *
     */
    protected function cloneRepository()
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

        $yaml = Yaml::parseFile(Storage::path($this->base_path.'/'.config('composer.yml')));

        if (data_get($yaml, 'enabled', false) == false) {
            return;
        }

        $updates = data_get($yaml, 'updates', []);

        foreach ($updates as $update) {
            if (Arr::has($update, 'path')) {
                $this->update(data_get($update, 'path'));
            }
        }
    }

    /**
     * @param  string  $update_path
     */
    protected function update(string $update_path)
    {
        $path = $this->base_path.$update_path;

        if (! Storage::exists($path.'/composer.json')) {
            return;
        }

        if (! Storage::exists($path.'/composer.lock')) {
            return;
        }

        $output = rescue(function () use ($path) {
            return $this->process('install', $path);
        }, '');

        if (blank($output)) {
            return;
        }

        $output = rescue(function () use ($path) {
            return $this->process('update', $path);
        }, '');

        if (blank($output)) {
            return;
        }

        $output = explode(PHP_EOL, $output);

        $this->output .= collect($output)
                ->filter(function ($item) {
                    return Str::contains($item, ' Updating');
                })->map(function ($item) {
                    return trim($item);
                })->implode(PHP_EOL).PHP_EOL;
    }

    /**
     * @param  string  $command
     * @param  string  $path
     *
     * @return string
     * @throws \Symfony\Component\Process\Exception\ProcessFailedException
     */
    protected function process(string $command, string $path): string
    {
        $exec = implode(' ', [
            'composer',
            $command,
            '--no-interaction',
            '--no-progress',
            '--no-suggest',
            '--no-autoloader',
        ]);

        $env = ['COMPOSER_HOME' => config('composer.home')];

        $process = Process::fromShellCommandline($exec)
                          ->setWorkingDirectory(Storage::path($path))
                          ->setEnv($env)
                          ->setTimeout(600)
                          ->mustRun();

        $output = $process->getOutput();
        if (blank($output)) {
            $output = $process->getErrorOutput();
        }

        return $output;
    }

    /**
     *
     */
    protected function commitPush()
    {
        $this->git->addAllChanges()
                  ->commit('composer update '.today()->toDateString().PHP_EOL.PHP_EOL.$this->output)
                  ->push('origin', [$this->branch]);
    }
}
