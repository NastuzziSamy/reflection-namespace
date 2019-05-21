<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DefinitionTest extends TestCase
{
    protected static $newLoader;

    public function testNoNamespace(): void
    {
        $this->assertEquals(
            ReflectionNamespace::class,
            \ReflectionNamespace::class
        );
    }

    public function testNeedNamespace(): void
    {
        $this->expectException(ArgumentCountError::class);

        new ReflectionNamespace;
    }

    protected function generateForName(string $namespace)
    {
        return [
            new ReflectionNamespace($namespace),
            new ReflectionNamespace($namespace.'\\'),
            new ReflectionNamespace('\\'.$namespace),
            new ReflectionNamespace('\\'.$namespace.'\\'),
        ];
    }

    public function testTrimNamespaces(): void
    {
        $reflections = $this->generateForName('App');

        $this->assertEquals(
            count(array_unique(array_map(function ($reflection) {
                return $reflection->getName();
            }, $reflections))),
            1
        );

        $this->assertEquals(
            count(array_unique(array_map(function ($reflection) {
                return $reflection->getShortName();
            }, $reflections))),
            1
        );
    }

    public function testTrimSubNamespaces(): void
    {
        $reflections = $this->generateForName('App\\Models');

        $this->assertEquals(
            count(array_unique(array_map(function ($reflection) {
                return $reflection->getName();
            }, $reflections))),
            1
        );

        $this->assertEquals(
            count(array_unique(array_map(function ($reflection) {
                return $reflection->getShortName();
            }, $reflections))),
            1
        );
    }

    /**
     * This test allows to check that the ReflectionNamespace
     * uses all Composer Loaders. As multiple dependencies
     * may exist, multiple Composer Loaders need to be used.
     */
    public function testLoaders(): void
    {
        $this->assertEquals(
            $this->getSupposedLoaders(),
            ReflectionNamespace::getLoaders()
        );
    }

    protected function getSupposedLoaders(): array
    {
        return [
            // Load the test package Composer Loader
            require(__DIR__.'/package/vendor/autoload.php'),
            // Load the current Composer Loader
            require(__DIR__.'/vendor/autoload.php'),
            // Load the app Composer Loader
            require(__DIR__.'/../vendor/autoload.php')
        ];
    }

    protected function getCustomAndSupposedLoaders(): array
    {
        return [
            // Load the test package Composer Loader
            require(__DIR__.'/package/vendor/autoload.php'),
            // Load the current Composer Loader
            require(__DIR__.'/vendor/autoload.php'),
            // Load the app Composer Loader
            require(__DIR__.'/../vendor/autoload.php'),
            // Load custom Composer Loader
            self::$newLoader
        ];
    }

    /**
     * This test checks that after the user creates a new loader,
     * the ReflectionNamescape uses also this new loader.
     */
    public function testWithNewLoaders(): void
    {
        $loaders = $this->getSupposedLoaders();

        self::$newLoader = new \Composer\Autoload\ClassLoader();
        self::$newLoader->addClassMap([
            'Custom\\FakeNamespace\\FakeClass1' => __DIR__.'/custom/FakeNamespace/FakeClass1.php',
            'Custom\\FakeClass2' => __DIR__.'/custom/FakeClass2.php'
        ]);
        self::$newLoader->register();

        new \Custom\FakeClass2;

        // Loaders are not reloaded without a user action
        $this->assertEquals(
            $loaders,
            ReflectionNamespace::getLoaders()
        );

        $this->assertEquals(
            $this->getCustomAndSupposedLoaders(),
            ReflectionNamespace::getLoaders(true)
        );

        new \Custom\FakeNamespace\FakeClass1;
    }
}
