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
    // Regex back slash.
    const R_BACKSLASH = '\\\\';
    const R_NO_BACKSLASH = '((?:(?!'.self::R_BACKSLASH.').)+)';
    const R_WHATEVER_NAMESPACE = '('.self::R_BACKSLASH.self::R_NO_BACKSLASH.')*';

    const GLOB_FLAGS = GLOB_NOSORT | GLOB_ERR | GLOB_NOESCAPE;

    // Check if loaders changed.
    protected static $loaders;
    // Specify if the class should load declared classes.
    protected static $loadDeclaredClasses = false;
    // Specify if the class should load PSR0 declarations.
    protected static $loadPSR0 = false;

    protected $name;
    protected $parent;

    // Specific loaders for this instance.
    protected $customLoaders;
    protected $hasCustomLoaders = false;

    // Check if everything is loaded for this instance.
    protected $prepared = false;
    protected $classes;
    protected $namespaces;

    /**
     * Return an instance for the specific namespace.
     *
     * @param string $name The name of the namespace.
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Tell if the class should load declared classes.
     *
     * @param  boolean $load
     * @return string
     */
    public static function loadDeclaredClasses(bool $load = true) {
        static::$loadDeclaredClasses = $load;

        return static::class;
    }

    /**
     * Return if the class should load declared classes.
     *
     * @return boolean
     */
    public static function isLoadingDeclaredClasses() {
        return static::$loadDeclaredClasses;
    }

    /**
     * Tell if the class should load from PSR0 declarations.
     *
     * @param  boolean $load
     * @return string
     */
    public static function loadPRS0($load = true) {
        static::$loadPSR0 = $load;

        return static::class;
    }

    /**
     * Return if the class should load from PSR0 declarations.
     *
     * @return boolean
     */
    public static function isLoadingPSR0() {
        return static::$loadPSR0;
    }

    public static function getLoaders(bool $reload = false)
    {
        if (static::$loaders && !$reload) {
            return static::$loaders;
        }

        return static::$loaders = array_values(array_map(function ($autoloadClosure) {
            return $autoloadClosure[0];
        }, array_filter(spl_autoload_functions(), function ($autoloadClosure) {
            return is_array($autoloadClosure);
        })));
    }

    public function getLocalLoaders(bool $reload = false)
    {
        if ($this->hasCustomLoaders) {
            return $this->customLoaders;
        }

        return static::getLoaders($reload);
    }

    public function setLoaders(array $loaders = [], bool $hasCustomLoaders = true)
    {
        $this->customLoaders = $loaders;
        $this->hasCustomLoaders = $hasCustomLoaders;
    }

    public function fillWithLoader($loader) {
        $this->fillFromClassMap(array_keys($loader->getClassMap()));

        if (!$loader->isClassMapAuthoritative()) {
            $this->fillFromPsr4($loader->getPrefixesPsr4());

            if (static::isLoadingPSR0()) {
                $this->fillFromPsr4($loader->getPrefixes());
            }
        }
    }

    protected function fillFromClassMap(array $classes) {
        $this->fillClasses($classes);
        $this->fillNamespaces($classes);
    }

    protected function fillWithDeclaredClasses()
    {
        $this->fillFromClassMap(get_declared_classes());
        $this->fillFromClassMap(get_declared_interfaces());
        $this->fillFromClassMap(get_declared_traits());
    }

    protected function fillClasses(array $classes)
    {
        $this->classes = array_merge(
            array_fill_keys(array_map(function (string $class) {
                preg_match($this->getClassRegex(), $class, $matches);

                return $matches[1];
            }, preg_grep($this->getClassRegex(), $classes)), null),
            $this->classes
        );
    }

    protected function fillNamespaces(array $classes)
    {
        $this->namespaces = array_merge(
            array_fill_keys(array_map(function (string $class) {
                preg_match($this->getNamespaceRegex(), $class, $matches);

                return $matches[1];
            }, preg_grep($this->getNamespaceRegex(), $classes)), null),
            $this->namespaces
        );
    }

    protected function fillFromPsr4(array $namespaces)
    {
        $validNamespaces = preg_grep($this->getSharedNamespaceRegex(), array_keys($namespaces));
        $shorterNamespaces = preg_grep($this->getSharedNamespaceRegex(true), $validNamespaces);
        $name = $this->getName();
        $parts = explode('\\', trim($name));

        foreach ($shorterNamespaces as $namespace) {
            $paths = $namespaces[$namespace];

            foreach ($paths as $path) {
                $missingParts = array_diff($parts, explode('\\', trim($namespace)));

                if (count($missingParts)) {
                    $goodPaths = $this->getDirectoriesFromPath($path, $missingParts);

                    foreach ($goodPaths as $goodPath) {
                        $this->fillClasses($this->getClassesFromPath($goodPath, $name));
                        $this->fillNamespaces($this->getNamespacesFromPath($goodPath, $name));
                    }
                } else {
                    $this->fillClasses($this->getClassesFromPath($path, $name));
                    $this->fillNamespaces($this->getNamespacesFromPath($path, $name));
                }
            }
        }

        $this->fillNamespaces(array_diff($validNamespaces, $shorterNamespaces));
    }

    protected function getDirectoriesFromPath(string $path, array $missingParts = []) {
        $dirPaths = glob($path.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR, static::GLOB_FLAGS);

        if (!$dirPaths) {
            return [];
        }

        if (count($missingParts)) {
            $validDirs = [];
            $part = array_slice($missingParts, -1);

            foreach ($dirPaths as $dirPath) {
                if ($this->getNameFromFile($this->getDirFromPath($dirPath)) === $part) {
                    $validDirs = array_merge(
                        $this->getDirectoriesFromPath($dirPath, $missingParts),
                        $validDirs
                    );
                }
            }
        }

        return $dirPaths;
    }

    protected function getNamespacesFromPath(string $path, string $prefix = '') {
        $dirPaths = glob($path.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR, static::GLOB_FLAGS);

        if (!$dirPaths) {
            return [];
        }

        return array_map(function ($dirPath) use ($prefix) {
            return $prefix.'\\'.$this->getNameFromFile($this->getDirFromPath($dirPath)).'\\';
        }, $dirPaths);
    }

    protected function getDirFromPath(string $dirPath) {
        $dirs = explode(DIRECTORY_SEPARATOR, $dirPath);

        return $dirs[count($dirs) - 2];
    }

    protected function getClassesFromPath(string $path, string $prefix = '') {
        $files = glob($path.DIRECTORY_SEPARATOR.'*.php', static::GLOB_FLAGS);

        if (!$files) {
            return [];
        }

        return array_map(function ($filePath) use ($prefix) {
            return $prefix.'\\'.$this->getNameFromFile($this->getFileFromPath($filePath));
        }, $files);
    }

    protected function getFileFromPath(string $filePath) {
        $dirs = explode(DIRECTORY_SEPARATOR, $filePath);

        return end($dirs);
    }

    protected function getNameFromFile(string $file) {
        $class = explode('.', $file)[0];

        $class = str_replace('-', ' ', str_replace('_', ' ', $class));
        $class = ucwords($class);
        $class = str_replace(' ', '', $class);

        return $class;
    }

    protected function prepare()
    {
        if (!$this->hasCustomLoaders && static::$loaders !== static::getLoaders(true)) {
            $this->prepared = false;
        }

        if (!$this->prepared) {
            $this->classes = [];
            $this->namespaces = [];

            foreach ($this->getLocalLoaders() as $loader) {
                $this->fillWithLoader($loader);
            }

            if (static::isLoadingDeclaredClasses()) {
                $this->fillWithDeclaredClasses();
            }

            $this->prepared = true;
        }
    }

    public function reload()
    {
        $this->prepared = false;
        static::$loaders = null;

        $this->prepare();
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

        return $matches[1] ?? null;
    }

    public function getParent()
    {
        return ($this->parent ?? ($this->parent = new static($this->getParentName())));
    }

    protected function getNameForRegex()
    {
        return str_replace('\\', static::R_BACKSLASH, $this->getName());
    }

    protected function getClassRegex()
    {
        return '#^'.$this->getNameForRegex().static::R_BACKSLASH.static::R_NO_BACKSLASH.'$#';
    }

    protected function getNamespaceRegex()
    {
        return '#^'.$this->getNameForRegex().static::R_BACKSLASH.static::R_NO_BACKSLASH.static::R_BACKSLASH.static::R_NO_BACKSLASH.'?$#';
    }

    protected function getSharedNamespaceRegex(bool $strict = false)
    {
        $names = explode('\\', $this->getName());
        $begin = array_shift($names);
        $end = static::R_BACKSLASH.'?';

        if (count($names) === 0) {
            $begin = '('.$begin;
            $end = '|)'.$end;
        } else {
            foreach ($names as $name) {
                $begin .= '('.static::R_BACKSLASH.$name;
                $end = '|)'.$end;
            }
        }

        if (!$strict) {
            $begin .= '('.static::R_BACKSLASH.'.*|)*';
        }

        return '#^'.$begin.$end.'$#';
    }

    protected function getShortNameRegex()
    {
        return '#((?!'.static::R_BACKSLASH.').)+$#';
    }

    protected function getParentNameRegex()
    {
        return '#^(.*)'.static::R_BACKSLASH.'#';
    }

    public function getClassNames()
    {
        $this->prepare();

        return array_keys($this->classes);
    }

    public function getClasses()
    {
        $this->prepare();

        foreach ($this->classes as $className => $classReflection) {
            if (is_null($classReflection)) {
                $this->classes[$className] = new \ReflectionClass($className);
            }
        }

        return $this->classes;
    }

    public function hasClass(string $className)
    {
        return in_array($className, $this->getClassNames());
    }

    public function getClass(string $className)
    {
        $this->prepare();

        return ($this->classes[$className] ?? ($this->classes[$className] = new \ReflectionClass($className)));
    }

    public function getDeclaredClasses()
    {
        return array_intersect($this->$this->getClassNames(), get_declared_classes());
    }

    public function getNamespaceNames() {
        return array_keys($this->namespaces);
    }

    public function getNamespaces()
    {
        foreach ($this->namespaces as $className => $classReflection) {
            if (is_null($classReflection)) {
                $this->namespaces[$className] = new static($className);
            }
        }

        return $this->namespaces;
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
