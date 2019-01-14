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

        $this->assertEquals([
                'FakeClass',
                'FakeClass2'
            ],
            $reflection->getClassNames()
        );

        $this->assertEquals([
                'FakeSubPackage',
                'FakeSubNamespace'
            ],
            $reflection->getNamespaceNames()
        );
    }
    //
    // public function testFakeNamespaceFakeSubNamespace(): void
    // {
    //     $reflection = new ReflectionNamespace('FakeNamespace\\FakeSubNamespace');
    //
    //     $this->assertEquals('FakeNamespace\\FakeSubNamespace', $reflection->getName());
    //     $this->assertEquals('FakeSubNamespace', $reflection->getShortName());
    //     $this->assertEquals('FakeNamespace', $reflection->getParentName());
    //     $this->assertEquals(new ReflectionNamespace('FakeNamespace'), $reflection->getParent());
    //
    //     $this->assertEquals([
    //             'FakeClass'
    //         ],
    //         $reflection->getClassNames()
    //     );
    //
    //     $this->assertEquals([],
    //         $reflection->getNamespaceNames()
    //     );
    // }
    //
    // public function testFakeNamespaceFakeSubNamespace2(): void
    // {
    //     $reflection = new ReflectionNamespace('FakeNamespace\\FakeSubNamespace2');
    //
    //     $this->assertEquals('FakeNamespace\\FakeSubNamespace2', $reflection->getName());
    //     $this->assertEquals('FakeSubNamespace2', $reflection->getShortName());
    //     $this->assertEquals('FakeNamespace', $reflection->getParentName());
    //     $this->assertEquals(new ReflectionNamespace('FakeNamespace'), $reflection->getParent());
    //
    //     $this->assertEquals([
    //             'FakeClass'
    //         ],
    //         $reflection->getClassNames()
    //     );
    //
    //     $this->assertEquals([],
    //         $reflection->getNamespaceNames()
    //     );
    // }
}
