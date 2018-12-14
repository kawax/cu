<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Mockery;
use GrahamCampbell\GitHub\Facades\GitHub;

use App\Model\User;

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
        $user = factory(User::class)->create([
            'name' => 'test_name',
        ]);

        GitHub::shouldReceive('authenticate')->once();
        GitHub::shouldReceive('me')->once()->andReturn(Mockery::self());
        GitHub::shouldReceive('repositories')->once()->andReturn([
            [
                'full_name' => 'test/test',
            ],
        ]);

        $response = $this->actingAs($user)
                         ->get('/home');

        $response->assertStatus(200)
                 ->assertSee('test_name')
                 ->assertSee('test/test');
    }

    public function testHomeRedirect()
    {
        $response = $this->get('/home');

        $response->assertRedirect();
    }
}
