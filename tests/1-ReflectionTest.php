<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ReflectionTest extends TestCase
{
    public function testCustom(): void
    {
        $reflection = new ReflectionNamespace('Custom');

        $this->assertEquals('Custom', $reflection->getName());
        $this->assertEquals('Custom', $reflection->getShortName());
        $this->assertEquals('', $reflection->getParentName());

        $this->assertEquals([
                "Custom\\FakeClass"
            ],
            $reflection->getClassNames()
        );

        $this->assertEquals([
                "Custom\\FakeClass"
            ],
            $reflection->getClassNames()
        );

        $this->assertEquals([
                "Custom\\FakeNamespace"
            ],
            $reflection->getNamespaceNames()
        );
    }

    public function testCustomFakeNamespace(): void
    {
        $reflection = new ReflectionNamespace('Custom\\FakeNamespace');

        $this->assertEquals('Custom\FakeNamespace', $reflection->getName());
        $this->assertEquals('FakeNamespace', $reflection->getShortName());
        $this->assertEquals('Custom', $reflection->getParentName());
        $this->assertEquals(new ReflectionNamespace('Custom'), $reflection->getParent());

        $this->assertEquals([
                "Custom\\FakeNamespace\\FakeClass"
            ],
            $reflection->getClassNames()
        );

        $this->assertEquals([
                "Custom\\FakeNamespace\\FakeClass"
            ],
            $reflection->getClassNames()
        );

        $this->assertEquals([],
            $reflection->getNamespaceNames()
        );
    }
}
