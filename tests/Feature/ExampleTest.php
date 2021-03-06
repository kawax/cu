<?php

namespace Tests\Feature;

use App\Jobs\GitHubUpdateJob;
use App\Jobs\GitLabUpdateJob;
use App\Models\User;
use GrahamCampbell\GitHub\Facades\GitHub;
use GrahamCampbell\GitLab\Facades\GitLab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testBasicTest()
    {
        $response = $this->get('/');

        $response->assertStatus(200)
                 ->assertSee('Login with GitHub');
    }

    public function testHome()
    {
        $user = User::factory()->create(
            [
                'name' => 'test_name',
            ]
        );

        GitHub::shouldReceive('authenticate')->once();
        GitHub::shouldReceive('me->repositories')->once()->andReturn(
            [
                [
                    'full_name' => 'github/test',
                ],
            ]
        );

        GitLab::shouldReceive('authenticate')->once();
        GitLab::shouldReceive('projects->all')->once()->andReturn(
            [
                [
                    'path_with_namespace' => 'gitlab/test',
                ],
            ]
        );

        $response = $this->actingAs($user)
                         ->get('/home');

        $response->assertStatus(200)
                 ->assertSee('test_name')
                 ->assertSee('github/test')
                 ->assertSee('gitlab/test');
    }

    public function testHomeRedirect()
    {
        $response = $this->get('/home');

        $response->assertRedirect();
    }

    public function testUpdateCommand()
    {
        Bus::fake();

        $user = User::factory()->create();

        GitHub::shouldReceive('authenticate')->once();
        GitHub::shouldReceive('me->repositories')->once()->andReturn(
            [
                [
                    'full_name' => 'github/test',
                ],
            ]
        );

        GitLab::shouldReceive('authenticate')->once();
        GitLab::shouldReceive('projects->all')->once()->andReturn(
            [
                [
                    'path_with_namespace' => 'gitlab/test',
                ],
            ]
        );

        $this->artisan('composer:update')
             ->expectsOutput('github/test')
             ->expectsOutput('gitlab/test')
             ->assertExitCode(0);

        Bus::assertDispatched(GitHubUpdateJob::class);
        Bus::assertDispatched(GitLabUpdateJob::class);
    }

    public function testUpdateCommandExpired()
    {
        Bus::fake();

        $user = User::factory()->create(
            [
                'expired_at' => now()->subMonth(),
            ]
        );

        GitHub::shouldReceive('authenticate')->never();

        $this->artisan('composer:update')
             ->assertExitCode(0);

        Bus::assertNotDispatched(GitHubUpdateJob::class);
        Bus::assertNotDispatched(GitLabUpdateJob::class);
    }
}
