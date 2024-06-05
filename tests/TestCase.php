<?php

namespace Tests;


use App\Models\User;
use Arr;
use Closure;
use DB;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\HttpFoundation\Response;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    public function assertValidSchema(array $expected, $actual, string $message = ''): void
    {
        $validator = new Validator();

        $result = $validator->validate($actual, json_encode($expected));

        if ($result->hasError()) {
            $formatter = new ErrorFormatter();
            $message   = implode("\n", $formatter->formatFlat($result->error()));
        }

        $this->assertTrue($result->isValid(), $message);
    }



    public function assertCollectionOfClass(string $expected, Collection $collection, string $message = ''): void
    {
        $this->assertTrue(class_exists($expected), "Class {$expected} doesn't exist.");

        $message = $message ?: "Failed asserting that all elements in the collection are instances of {$expected}";

        $this->assertCount($collection->count(), $collection->whereInstanceOf($expected), $message);
    }

    public function assertResourceCollectionOfClass(
        string $expected,
        ResourceCollection $resourceCollection,
        string $message = ''
    ): void {
        $this->assertCollectionOfClass($expected, $resourceCollection->collection, $message);
    }

    public function assertFormValidationErrorFieldContains(
        string $expectedField,
        string $expectedError,
        TestResponse $response
    ): void {
        $response->assertJsonValidationErrors([$expectedField]);
        $errors = $response->json('errors');

        $fieldErrors = Collection::make($errors[$expectedField]);

        $validFieldErrors = $fieldErrors->filter(function(string $error) use ($expectedError) {
            return Str::contains(Str::lower($error), Str::lower($expectedError));
        });

        $this->assertNotEmpty($validFieldErrors,
            "The field \"{$expectedField}\" has not an error that contains \"{$expectedError}\" string. Current errors: " . json_encode($fieldErrors));
    }

    public function assertResponseDoesNotContainsFieldValidationError(
        string $expectedField,
        ?string $expectedError,
        TestResponse $response
    ): void {
        if (Response::HTTP_UNPROCESSABLE_ENTITY !== $response->getStatusCode()) {
            $this->assertNotEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());

            return;
        }

        $errors = Collection::make($response->json('errors'));

        if (!$errors->has($expectedField)) {
            $this->assertArrayNotHasKey($expectedField, $errors->toArray());

            return;
        }

        if (is_null($expectedError)) {
            $this->assertNull($expectedError);

            return;
        }

        $fieldErrors = Collection::make($errors->get($expectedField));

        $validFieldErrors = $fieldErrors->filter(function(string $error) use ($expectedError) {
            return Str::contains($error, $expectedError);
        });

        $this->assertEmpty($validFieldErrors,
            "The field \"{$expectedField}\" has an error that contains \"{$expectedError}\" string. Current errors: " . json_encode($fieldErrors));
    }

    public function validateResponseSchema(array $schema, TestResponse $response)
    {
        $this->assertJson($response->content());
        $this->assertValidSchema($schema, json_decode($response->content()));
    }


    /**
     * @param       $object
     * @param       $methodName
     * @param array $parameters
     *
     * @return mixed
     * @throws ReflectionException
     */
    public function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * @param UserContract|User|null $authenticatable
     *
     * @return UserContract|User
     */
    protected function login(?UserContract $authenticatable = null): UserContract
    {
        $authenticatable ??= User::factory()->create();

        $token = JWTAuth::fromUser($authenticatable);
        $this->withHeaders(['Authorization' => 'Bearer ' . $token]);
        $this->actingAs($authenticatable);

        return $authenticatable;
    }

    protected function assertArrayHasKeyAndValue(string $key, $value, array $array, string $message = ''): void
    {
        $this->assertArrayHasKey($key, $array, $message);
        $this->assertEquals($value, $array[$key]);
    }

    protected function assertArrayHasKeysAndValues(iterable $expected, array $current, string $message = ''): void
    {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKeyAndValue($key, $value, $current, $message);
        }
    }

    protected function assertResourceArrayHasResourceOf(string $key, string $class, Model $model, array $current): void
    {
        $this->assertArrayHasKey($key, $current);
        $this->assertInstanceOf($class, $current[$key]);
        $this->assertInstanceOf(JsonResource::class, $current[$key]);
        $this->assertEquals($model->getKey(), $current[$key]->resource->getKey());
    }

    public function getWithParameters(string $uri, array $parameters, array $headers = [])
    {
        $server  = $this->transformHeadersToServerVars($headers);
        $cookies = $this->prepareCookiesForRequest();

        return $this->call('GET', $uri, $parameters, $cookies, [], $server);
    }

    public function assertValidXML($xml): void
    {
        $this->assertIsString($xml,
            'Failed asserting that provided data is a valid XML. Provided data is not a string.');
        try {
            simplexml_load_string($xml);
        } catch (Exception $exception) {
            $this->fail('Failed asserting that provided data is a valid XML. ' . $exception->getMessage());
        }
    }

    protected function jsonSchema(array $baseSchema, bool $pagination = false, bool $dataWrapping = true): array
    {
        if (!$pagination) {
            return $dataWrapping ? $this->dataWrappingSchema($baseSchema) : $baseSchema;
        }

        return $this->paginationSchema($baseSchema);
    }

    private function dataWrappingSchema(array $baseSchema): array
    {
        return [
            'type'                 => 'object',
            'properties'           => [
                'data' => $baseSchema,
            ],
            'required'             => ['data'],
            'additionalProperties' => false,
        ];
    }

    private function paginationSchema(array $baseSchema): array
    {
        return [
            'type'                 => 'object',
            'properties'           => [
                'data'  => [
                    'type'  => 'array',
                    'items' => $baseSchema,
                ],
                'links' => [
                    'type'       => ['object'],
                    'properties' => [
                        'first' => ['type' => ['string']],
                        'last'  => ['type' => ['string']],
                        'prev'  => ['type' => ['string', 'null']],
                        'next'  => ['type' => ['string', 'null']],
                    ],
                    'required'   => ['first', 'last', 'prev', 'next'],
                ],
                'meta'  => [
                    'type'       => ['object'],
                    'properties' => [
                        'current_page' => ['type' => ['integer']],
                        'from'         => ['type' => ['integer', 'null']],
                        'last_page'    => ['type' => ['integer']],
                        'links'        => [
                            'type'  => ['array'],
                            'items' => [
                                'type'       => ['object'],
                                'properties' => [
                                    'url'    => ['type' => ['string', 'null']],
                                    'label'  => ['type' => ['string', 'number', 'null']],
                                    'active' => ['type' => ['boolean']],
                                ],
                                'required'   => ['url', 'label', 'active'],
                            ],

                        ],
                        'path'         => ['type' => ['string']],
                        'per_page'     => ['type' => ['integer']],
                        'to'           => ['type' => ['integer', 'null']],
                        'total'        => ['type' => ['integer', 'null']],
                    ],
                    'required'   => [
                        'current_page',
                        'from',
                        'last_page',
                        'links',
                        'path',
                        'per_page',
                        'to',
                        'total',
                    ],
                ],
            ],
            'required'             => ['data', 'links', 'meta'],
            'additionalProperties' => false,
        ];
    }

    public function collectionSchema(array $baseSchema, bool $wrapped = true): array
    {
        if (!$wrapped) {
            return [
                'type'  => 'array',
                'items' => $baseSchema,
            ];
        }

        return [
            'type'                 => 'object',
            'properties'           => [
                'data' => [
                    'type'  => 'array',
                    'items' => $baseSchema,
                ],
            ],
            'required'             => ['data'],
            'additionalProperties' => false,
        ];
    }

    public function assertEventHasListeners(string $eventClassName, array $listenersArray): void
    {
        $events = [];
        foreach (App::getProviders(EventServiceProvider::class) as $provider) {
            $providerEvents = array_merge_recursive($provider->shouldDiscoverEvents() ? $provider->discoverEvents() : [],
                $provider->listens());

            $events = array_merge_recursive($events, $providerEvents);
        }

        $eventListeners = Collection::make($events)->filter(function($listeners, $event) use ($eventClassName) {
            return Str::contains($event, $eventClassName);
        })->values()->collapse()->toArray();

        foreach ($listenersArray as $listener) {
            $this->assertContains($listener, $eventListeners,
                "Failed asserting that a class listen to provided {$eventClassName} event.");
        }
    }

    /**
     * @throws ReflectionException
     */
    public function assertUseTrait(string $class, string $trait, array $overriddenMethods = [])
    {
        $reflection = new ReflectionClass($class);
        $usedTraits = $reflection->getTraits();

        $this->assertTrue(Arr::exists($usedTraits, $trait));

        $traitReflection = new ReflectionClass($trait);

        $traitMethods         = $traitReflection->getMethods();
        $filteredTraitMethods = Collection::make($traitMethods)->filter(function($method) use ($overriddenMethods) {
            return !in_array($method->getName(), $overriddenMethods);
        });

        $filteredTraitMethods->each(function($traitMethod) use ($reflection) {
            $classMethod = $reflection->getMethod($traitMethod->getName());
            $this->assertEquals($traitMethod->getFileName(), $classMethod->getFileName(),
                "Method {$traitMethod->getName()} was overridden");
        });

        $overriddenTraitMethods = Collection::make($traitMethods)->filter(function($method) use ($overriddenMethods) {
            return in_array($method->getName(), $overriddenMethods);
        });

        $overriddenTraitMethods->each(function($traitMethod) use ($reflection) {
            $classMethod = $reflection->getMethod($traitMethod->getName());
            $this->assertNotEquals($traitMethod->getFileName(), $classMethod->getFileName(),
                "Method {$traitMethod->getName()} was not overridden");
        });
    }

    public function assertRelationExists(string $class, string $method, string $relationType)
    {
        $constraint = static::callback(function($class) use ($method, $relationType) {
            try {
                $relation = new \ReflectionMethod($class, $method);
            } catch (\ReflectionException $exception) {
                static::fail("{$class} does not have relation method `{$method}`");
            }

            return $relation->getReturnType()->getName() === $relationType;
        });

        static::assertThat($class, $constraint,
            "{$class} does not have relation `{$method}` of type `{$relationType}`");
    }

    public function array_cartesian_product($arrays, $dump = false): array
    {
        $result = [];
        $arrays = array_values($arrays);
        $sizeIn = sizeof($arrays);
        $size   = $sizeIn > 0 ? 1 : 0;
        foreach ($arrays as $array) {
            $size = $size * sizeof($array);
        }
        for ($i = 0; $i < $size; $i++) {
            $result[$i] = [];
            for ($j = 0; $j < $sizeIn; $j++) {
                array_push($result[$i], current($arrays[$j]));
            }
            for ($j = ($sizeIn - 1); $j >= 0; $j--) {
                if (next($arrays[$j])) {
                    break;
                } elseif (isset ($arrays[$j])) {
                    reset($arrays[$j]);
                }
            }
        }

        if ($dump) {
            dump($result);
        }

        return $result;
    }
}
