<?php

namespace Alcaeus\BsonDiffQueryGenerator\Tests;

use MongoDB\Client;
use MongoDB\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use function getenv;

class FunctionalTestCase extends TestCase
{
    private ?Client $client = null;

    protected function getClient(): Client
    {
        return $this->client ??= static::createTestClient();
    }

    protected function getCollection(?string $collectionName = null, array $options = []): Collection
    {
        return $this->getClient()->selectCollection(
            static::getDatabaseName(),
            $collectionName ?? static::getCollectionName(),
            $options,
        );
    }

    protected function tearDown(): void
    {
        $this->client
            ?->selectDatabase(static::getDatabaseName())
            ->dropCollection(static::getCollectionName());

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        static::createTestClient()->dropDatabase(static::getDatabaseName());

        parent::tearDownAfterClass();
    }

    protected static function getCollectionName(): string
    {
        return (new ReflectionClass(static::class))->getShortName();
    }

    protected static function getDatabaseName(): string
    {
        return (string) getenv('MONGODB_DATABASE');
    }

    private static function createTestClient(?string $uri = null, array $options = [], array $driverOptions = []): Client
    {
        return new Client(
            $uri ?? static::getUri(),
            $options,
            $driverOptions,
        );
    }

    private static function getUri(): string
    {
        $uriFromEnv = getenv('MONGODB_URI');

        return is_string($uriFromEnv) ? $uriFromEnv : 'mongodb://127.0.0.1:27017';
    }
}
