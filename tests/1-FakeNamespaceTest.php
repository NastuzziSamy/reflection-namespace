<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FakeNamespaceTest extends TestCase
{
    public function testFakeNamespace(): void
    {
        $reflection = new ReflectionNamespace('FakeNamespace');

        $this->assertEquals('FakeNamespace', $reflection->getName());
        $this->assertEquals('FakeNamespace', $reflection->getShortName());
        $this->assertEquals('', $reflection->getParentName());
        $reflection->getClassNames();

        $this->assertEquals([
                'FakeClass'
            ],
            $reflection->getClassNames()
        );

        $this->assertEquals([
                'FakeSubNamespace',
                'FakeSubNamespace2'
            ],
            $reflection->getNamespaceNames()
        );
    }

    public function testFakeNamespaceFakeSubNamespace(): void
    {
        $reflection = new ReflectionNamespace('FakeNamespace\\FakeSubNamespace');

        $this->assertEquals('FakeNamespace\\FakeSubNamespace', $reflection->getName());
        $this->assertEquals('FakeSubNamespace', $reflection->getShortName());
        $this->assertEquals('FakeNamespace', $reflection->getParentName());
        $this->assertEquals(new ReflectionNamespace('FakeNamespace'), $reflection->getParent());

        $this->assertEquals([
                'FakeClass'
            ],
            $reflection->getClassNames()
        );

        $this->assertEquals([],
            $reflection->getNamespaceNames()
        );
    }

    public function testFakeNamespaceFakeSubNamespace2(): void
    {
        $reflection = new ReflectionNamespace('FakeNamespace\\FakeSubNamespace2');

        $this->assertEquals('FakeNamespace\\FakeSubNamespace2', $reflection->getName());
        $this->assertEquals('FakeSubNamespace2', $reflection->getShortName());
        $this->assertEquals('FakeNamespace', $reflection->getParentName());
        $this->assertEquals(new ReflectionNamespace('FakeNamespace'), $reflection->getParent());

        $this->assertEquals([
                'FakeClass'
            ],
            $reflection->getClassNames()
        );

        $this->assertEquals([],
            $reflection->getNamespaceNames()
        );
    }
}
