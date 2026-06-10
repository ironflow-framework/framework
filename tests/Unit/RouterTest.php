<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit;

use Ironflow\Container;
use Ironflow\Exceptions\HttpException;
use Ironflow\Routing\Route;
use Ironflow\Routing\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router(new Container());
    }

    public function test_get_route_registered(): void
    {
        $this->router->get('/hello', fn() => 'hi')->name('hello');
        $route = $this->router->getRoutes()->getByName('hello');
        $this->assertNotNull($route);
        $this->assertSame('/hello', $route->getUri());
    }

    public function test_route_matches_uri(): void
    {
        // Route compiles automatically in constructor
        $route = new Route('GET', '/users/{id}', fn() => null);
        $params = $route->match('/users/42');
        $this->assertIsArray($params);
        $this->assertSame('42', $params['id']);
    }

    public function test_route_no_match_returns_null(): void
    {
        $route = new Route('GET', '/users/{id}', fn() => null);
        $this->assertNull($route->match('/posts/42'));
    }

    public function test_route_optional_param_matches_without_segment(): void
    {
        $route = new Route('GET', '/page/{num?}', fn() => null);
        $params = $route->match('/page');
        $this->assertIsArray($params);
        $this->assertArrayNotHasKey('num', $params);
    }

    public function test_route_where_constraint(): void
    {
        // where() triggers recompile
        $route = (new Route('GET', '/items/{id}', fn() => null))->where('id', '[0-9]+');
        $this->assertNotNull($route->match('/items/5'));
        $this->assertNull($route->match('/items/abc'));
    }

    public function test_route_generate_url(): void
    {
        $route = new Route('GET', '/users/{id}/profile', fn() => null);
        $url = $route->generateUrl(['id' => '7']);
        $this->assertSame('/users/7/profile', $url);
    }

    public function test_router_group_prefix(): void
    {
        $this->router->group(['prefix' => '/api'], function ($r) {
            $r->get('/users', fn() => null)->name('api.users');
        });
        $route = $this->router->getRoutes()->getByName('api.users');
        $this->assertNotNull($route);
        $this->assertSame('/api/users', $route->getUri());
    }

    public function test_route_collection_405_on_wrong_method(): void
    {
        $this->router->get('/only-get', fn() => null);

        $this->expectException(HttpException::class);
        try {
            $this->router->getRoutes()->match('POST', '/only-get');
        } catch (HttpException $e) {
            $this->assertSame(405, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_route_collection_404_on_missing_route(): void
    {
        $this->expectException(HttpException::class);
        try {
            $this->router->getRoutes()->match('GET', '/nonexistent-path-xyz');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_resource_generates_seven_routes(): void
    {
        $this->router->resource('articles', RouterArticleStub::class);
        $names = [
            'articles.index',
            'articles.create',
            'articles.store',
            'articles.show',
            'articles.edit',
            'articles.update',
            'articles.destroy'
        ];
        foreach ($names as $name) {
            $this->assertNotNull(
                $this->router->getRoutes()->getByName($name),
                "Route {$name} not found"
            );
        }
    }

    public function test_named_url_generation(): void
    {
        $this->router->get('/profile/{user}', fn() => null)->name('profile');
        $url = $this->router->route('profile', ['user' => 'alice']);
        $this->assertSame('/profile/alice', $url);
    }
}

class RouterArticleStub
{
    public function index(): void
    {
    }
    public function create(): void
    {
    }
    public function store(): void
    {
    }
    public function show(): void
    {
    }
    public function edit(): void
    {
    }
    public function update(): void
    {
    }
    public function destroy(): void
    {
    }
}
