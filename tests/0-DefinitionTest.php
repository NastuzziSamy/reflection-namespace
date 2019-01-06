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
            'Custom\\FakeClass' => __DIR__.'/custom/FakeClass.php',
            'Custom\\FakeNamespace\\FakeClass' => __DIR__.'/custom/FakeNamespace/FakeClass.php'
        ]);
        self::$newLoader->register();

        new \Custom\FakeClass;

        $this->assertEquals(
            $this->getCustomAndSupposedLoaders(),
            ReflectionNamespace::getLoaders()
        );
    }

    /**
     * This test allows to check that the ReflectionNamespace
     * uses all Composer Loaders. As multiple dependencies
     * may exist, multiple Composer Loaders need to be used.
     */
    public function testGetLoadersClasses(): void
    {
        $classes = [];

        foreach ($this->getCustomAndSupposedLoaders() as $loader) {
            $classes = array_merge($classes, array_keys($loader->getClassMap()));
        }

        $this->assertEquals(
            $classes,
            ReflectionNamespace::getLoaderClasses()
        );
    }
}
