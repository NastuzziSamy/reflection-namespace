<?php
/**
 * Reflect a namespace by its full name.
 *
 * Usefull for:
 * - Finding all sub namespaces,
 * - Finding all classes,
 * - Searching by namespace and no more by directory,
 * - Finding all definitions in a namespace (whatever by you or your dependencies).
 *
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

class ReflectionNamespace implements \Reflector
{
    // Regex back slash
    const R_BACKSLASH = '\\\\';

    const R_NO_BACKSLASH = '((?!'.self::R_BACKSLASH.').)+';
    const R_SUB_NAMESPACE = '^(.*)'.self::R_BACKSLASH.self::R_NO_BACKSLASH.'$';

    protected $name;
    protected $parent;

    protected $ownedClasses;
    protected $subClasses;

    protected $ownedNamespaces;
    protected $subNamespaces;

    public function __construct($name)
    {
        $this->name = $name;
        $classes = preg_grep($this->getNamespaceRegex(), self::getLoaderClasses());
        $this->ownedClasses = array_fill_keys(preg_grep($this->getOwnedRegex(), $classes), null);
        $subClasses = preg_grep($this->getOwnedRegex(), $classes, PREG_GREP_INVERT);
        $this->subClasses = array_fill_keys($subClasses, null);

        $namespaces = array_map(function ($class) {
            preg_match('#'.self::R_SUB_NAMESPACE.'#', $class, $matches);

            return $matches[1];
        }, $subClasses);

        $this->ownedNamespaces = array_fill_keys(preg_grep($this->getOwnedRegex(), $namespaces), null);
        $this->subNamespaces = array_fill_keys(preg_grep($this->getOwnedRegex(), $namespaces, PREG_GREP_INVERT), null);
    }

    public static function getLoaders()
    {
        return array_values(array_map(function ($autoloadFunction) {
            return $autoloadFunction[0];
        }, array_filter(spl_autoload_functions(), function ($autoloadFunction) {
            return is_array($autoloadFunction);
        })));
    }

    public static function getLoaderClasses()
    {
        $loaderClasses = [];

        foreach (static::getLoaders() as $loader) {
            $loaderClasses = array_merge($loaderClasses, array_keys($loader->getClassMap()));
        }

        return $loaderClasses;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getShortName()
    {
        preg_match($this->getShortNameRegex(), $this->getNameForRegex(), $matches);

        return reset($matches);
    }

    public function getParentName()
    {
        preg_match($this->getParentNameRegex(), $this->getName(), $matches);

        return $matches[1] ?? '';
    }

    public function getParent()
    {
        return ($this->parent ?? ($this->parent = new static($this->getParentName())));
    }

    protected function getNameForRegex()
    {
        return str_replace('\\', self::R_BACKSLASH, $this->getName());
    }

    protected function getNamespaceRegex()
    {
        return '#^'.$this->getNameForRegex().self::R_BACKSLASH.'#';
    }

    protected function getOwnedRegex()
    {
        return '#^'.$this->getNameForRegex().self::R_BACKSLASH.self::R_NO_BACKSLASH.'$#';
    }

    protected function getShortNameRegex()
    {
        return '#((?!'.self::R_BACKSLASH.').)+$#';
    }

    protected function getParentNameRegex()
    {
        return '#^(.*)'.self::R_BACKSLASH.'#';
    }

    public function getClassNames()
    {
        return ($this->getOwnedClassNames() + $this->getSubClassNames());
    }

    public function getOwnedClassNames()
    {
        return array_keys($this->ownedClasses);
    }

    public function getSubClassNames($direct=true)
    {
        return array_keys($this->subClasses);
    }

    public function getClasses()
    {
        return array_merge($this->getOwnedClasses(), $this->getSubClasses());
    }

    public function getOwnedClasses()
    {
        foreach ($this->ownedClasses as $className => $classReflection) {
            if (is_null($classReflection)) {
                $this->ownedClasses[$className] = new \ReflectionClass($className);
            }
        }

        return $this->ownedClasses;
    }

    public function getSubClasses()
    {
        foreach ($this->subClasses as $className => $classReflection) {
            if (is_null($classReflection)) {
                $this->subClasses[$className] = new \ReflectionClass($className);
            }
        }

        return $this->subClasses;
    }

    public function hasClass(string $className)
    {
        return in_array($className, $this->getClassNames());
    }

    public function hasOwnedClass(string $className)
    {
        return in_array($className, $this->getOwnedClassNames());
    }

    public function hasSubClass(string $className)
    {
        return in_array($className, $this->getSubClassNames());
    }

    public function getClass(string $className)
    {
        if ($this->hasOwnedClass($className)) {
            return $this->getOwnedClass($className);
        } else {
            return $this->getSubClass($className);
        }
    }

    public function getOwnedClass(string $className)
    {
        return ($this->ownedClasses[$className] ?? ($this->ownedClasses[$className] = new \ReflectionClass($className)));
    }

    public function getSubClass(string $className)
    {
        return ($this->subClasses[$className] ?? ($this->subClasses[$className] = new \ReflectionClass($className)));
    }

    public function getDeclaredClasses()
    {
        return array_intersect($this->$this->getClassNames(), get_declared_classes());
    }

    public function getDeclaredOnwedClasses()
    {
        return array_intersect($this->getOwnedClassNames(), get_declared_classes());
    }

    public function getDeclaredSubClasses()
    {
        return array_intersect($this->getSubClassNames(), get_declared_classes());
    }

    public function getOwnedNamespaceNames() {
        return array_keys($this->ownedNamespaces);
    }

    public function getSubNamespaceNames() {
        return array_keys($this->subNamespaces);
    }

    public function getNamespaceNames() {
        return ($this->getOwnedNamespaceNames() + $this->getSubNamespaceNames());
    }

    public function getOwnedNamespaces()
    {
        foreach ($this->ownedNamespaces as $className => $classReflection) {
            if (is_null($classReflection)) {
                $this->ownedNamespaces[$className] = new static($className);
            }
        }

        return $this->ownedNamespaces;
    }

    public function getSubNamespaces()
    {
        foreach ($this->subNamespaces as $className => $classReflection) {
            if (is_null($classReflection)) {
                $this->subNamespaces[$className] = new static($className);
            }
        }

        return $this->subNamespaces;
    }

    public function getNamespaces()
    {
        return array_merge($this->getOwnedNamespaces(), $this->getSubNamespaces());
    }

    public static function export()
    {
        return null;
    }

    public function __toString()
    {
        return $this->name;
    }
}
