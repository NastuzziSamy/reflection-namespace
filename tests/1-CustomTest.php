<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CustomTest extends TestCase
{
    public function testCustom(): void
    {
        $reflection = new ReflectionNamespace('Custom');

        $this->assertEquals('Custom', $reflection->getName());
        $this->assertEquals('Custom', $reflection->getShortName());
        $this->assertEquals('', $reflection->getParentName());
        $reflection->getClassNames();

        $this->assertEquals([
                "FakeClass2"
            ],
            $reflection->getClassNames()
        );

        $this->assertEquals([
                "FakeNamespace"
            ],
            $reflection->getNamespaceNames()
        );
    }

    public function testCustomFakeNamespace(): void
    {
        $reflection = new ReflectionNamespace('Custom\\FakeNamespace');

        $this->assertEquals('Custom\\FakeNamespace', $reflection->getName());
        $this->assertEquals('FakeNamespace', $reflection->getShortName());
        $this->assertEquals('Custom', $reflection->getParentName());
        $this->assertEquals(new ReflectionNamespace('Custom'), $reflection->getParent());

        $this->assertEquals([
                "FakeClass1"
            ],
            $reflection->getClassNames()
        );

        $this->assertEquals([],
            $reflection->getNamespaceNames()
        );
    }
}
