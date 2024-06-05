<?php

namespace Tests\Unit;

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route as FacadesRoute;
use Tests\TestCase;

class RoutesTest extends TestCase
{
    const V1_ROUTES = 'v1Routes';
    private Collection $routes;
    private Collection $groupedRegisteredRoutes;

    public function setUp(): void
    {
        parent::setUp();
        $this->groupedRegisteredRoutes = Collection::make();
        $this->routes                  = Collection::make(FacadesRoute::getRoutes()->getRoutes());
        $v1Routes                      = $this->routes->filter(function(Route $a) {
            return Str::startsWith($a->uri(), 'api');
        });

        $baseRoutes = Collection::make();
        $v1Routes->each(function(Route $route) use ($baseRoutes) {
            $uri     = Str::substr($route->uri(), Str::length('api'));
            $methods = Collection::make($baseRoutes[$uri] ?? []);
            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'])) {
                    continue;
                }
                $methods->put($method, $method);
            }
            $baseRoutes->put($uri, $methods);
        });
        $this->groupedRegisteredRoutes->put(self::V1_ROUTES, $baseRoutes);
    }

    /**
     * @test
     *
     * @param array  $methods
     * @param string $uri
     *
     * @dataProvider dataProvider
     */
    public function it_should_have_a_specific_path_and_verb(array $methods, string $uri)
    {
        $this->assertNotNull($this->routes->first(function(Route $route) use ($methods, $uri) {
            $uriCondition    = $route->uri() == $uri;
            $methodCondition = !array_diff($methods, $route->methods());

            return $uriCondition && $methodCondition;
        }), 'Route: ' . implode('|', $methods) . ' ' . $uri . ' not found.');
    }

    public function dataProvider(): array
    {

        return [
            [['POST'], 'api/call/incoming'],
            [['POST'], 'api/call/keypress'],
            [['POST'], 'api/call/voicemail'],
            [['POST'], 'api/call/no-answer'],
        ];
    }

    /**
     * @test
     *
     * @param string     $routePrefix
     * @param string     $registeredRoutes
     * @param Collection $ourRoutes
     *
     * @dataProvider registeredRoutesProvider
     */
    public function registered_routes_should_be_tested(
        string $routePrefix,
        string $registeredRoutes,
        Collection $ourRoutes
    ) {
        $failed = Collection::make();
        $this->groupedRegisteredRoutes->get($registeredRoutes)->each(function(Collection $methods, string $uri) use (
            $routePrefix,
            $ourRoutes,
            $failed
        ) {
            if (!$ourRoutes->has($uri)) {
                $failed->push("Failed asserting that $routePrefix/$uri is tested.");

                return;
            }

            /** @var Collection $ourMethods */
            $ourMethods = $ourRoutes->get($uri);
            $methods->each(function(string $method) use ($uri, $ourMethods, $failed) {
                if (!$ourMethods->contains($method)) {
                    $failed->push("Failed asserting that api/$uri has method $method.");
                }
            });
        });

        $messages = $failed->implode("\n");
        $this->assertEmpty($failed, $messages);
    }

    private function ourV1Routes(): Collection
    {
        return Collection::make([
            '/call/incoming'  => Collection::make(['POST', 'GET', 'PUT', 'DELETE', 'PATCH']),
            '/call/keypress'  => Collection::make(['POST', 'GET', 'PUT', 'DELETE', 'PATCH']),
            '/call/voicemail' => Collection::make(['POST', 'GET', 'PUT', 'DELETE', 'PATCH']),
            '/call/no-answer' => Collection::make(['POST', 'GET', 'PUT', 'DELETE', 'PATCH']),
        ]);
    }

    /**
     * @test
     *
     * @param string $routeGroup
     * @param string $uri
     * @param array  $methods
     *
     * @dataProvider routesProvider
     */
    public function intended_routes_should_be_registered(string $routeGroup, string $uri, array $methods)
    {
        $this->assertTrue($this->groupedRegisteredRoutes->get($routeGroup)->has($uri),
            "Route $uri for group $routeGroup does not exist");
        /** @var Collection $actualMethods */
        $actualMethods = $this->groupedRegisteredRoutes->get($routeGroup)->get($uri);
        foreach ($methods as $method) {
            $this->assertTrue($actualMethods->has($method), "Path: $uri. Method $method does not exist.");
        }
    }

    public function routesProvider(): array
    {
        $v1Routes = $this->ourV1Routes()->map(fn(Collection $methods, string $uri) => [
            self::V1_ROUTES,
            $uri,
            $methods->toArray(),
        ])->values()->toArray();

        return array_merge($v1Routes);
    }

    public function registeredRoutesProvider(): array
    {
        return [
            [
                "routePrefix"      => 'api',
                "registeredRoutes" => self::V1_ROUTES,
                "ourRoutes"        => $this->ourV1Routes(),
            ],
        ];
    }
}
