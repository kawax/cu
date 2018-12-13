<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use GrahamCampbell\GitHub\Facades\GitHub;

use App\Model\User;
use App\Jobs\UpdateJob;

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
        $user = User::where(function ($query) {
            return $query->whereDate('expired_at', '>=', today())
                         ->orWhereNull('expired_at');
        })
                    ->oldest('updated_at')
                    ->first();

        if (empty($user)) {
            return;
        }

        $user->touch();

        GitHub::authenticate($user->github_token, 'http_token');

        $repos = GitHub::me()->repositories('owner', 'pushed', 'desc', 'public', 'owner,organization_member');

        $delay = 0;

        foreach ($repos as $repo) {
            $this->info(data_get($repo, 'full_name'));

            UpdateJob::dispatch($user->github_token, $repo)->delay(now()->addMinutes($delay * 5));

            $delay++;
        }
    }
}
