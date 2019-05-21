<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FakePackageTest extends TestCase
{
    public function testFakePackage(): void
    {
        $reflection = new ReflectionNamespace('FakePackage');

        $this->assertEquals('FakePackage', $reflection->getName());
        $this->assertEquals('FakePackage', $reflection->getShortName());
        $this->assertEquals('', $reflection->getParentName());
        $reflection->getClassNames();

        $this->assertEquals(array_diff([
                'FakeClass4' => 'FakePackage\\FakeClass4',
                'FakeClass9' => 'FakePackage\\FakeClass9',
            ],
            $reflection->getClassNames()
        ), []);

        $this->assertEquals(array_diff([
                'FakeSubPackage' => 'FakePackage\\FakeSubPackage',
                'FakeSubNamespace' => 'FakePackage\\FakeSubNamespace',
            ],
            $reflection->getNamespaceNames()
        ), []);
    }

    public function testFakePackageFakeSubNamespace(): void
    {
        $reflection = new ReflectionNamespace('FakePackage\\FakeSubNamespace');

        $this->assertEquals('FakePackage\\FakeSubNamespace', $reflection->getName());
        $this->assertEquals('FakeSubNamespace', $reflection->getShortName());
        $this->assertEquals('FakePackage', $reflection->getParentName());
        $this->assertEquals(new ReflectionNamespace('FakePackage'), $reflection->getParent());

        $this->assertEquals([
                'FakeClass3' => 'FakePackage\\FakeSubNamespace\\FakeClass3',
            ],
            $reflection->getClassNames()
        );

        $this->assertEquals([],
            $reflection->getNamespaceNames()
        );
    }

    public function testFakePackageFakeSubPackage(): void
    {
        $reflection = new ReflectionNamespace('FakePackage\\FakeSubPackage');

        $this->assertEquals('FakePackage\\FakeSubPackage', $reflection->getName());
        $this->assertEquals('FakeSubPackage', $reflection->getShortName());
        $this->assertEquals('FakePackage', $reflection->getParentName());
        $this->assertEquals(new ReflectionNamespace('FakePackage'), $reflection->getParent());

        $this->assertEquals([
                'FakeClass5' => 'FakePackage\\FakeSubPackage\\FakeClass5',
            ],
            $reflection->getClassNames()
        );

        $this->assertEquals([
                'EmptyNamespace' => 'FakePackage\\FakeSubPackage\\EmptyNamespace',
            ],
            $reflection->getNamespaceNames()
        );
    }
}
