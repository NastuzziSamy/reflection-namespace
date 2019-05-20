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

use Composer\Autoload\ClassLoader;

class ReflectionNamespace implements \Reflector
{
    // Regex back slash.
    const R_BACKSLASH = '\\\\';
    const R_NO_BACKSLASH = '((?:(?!'.self::R_BACKSLASH.').)+)';
    const R_WHATEVER_NAMESPACE = '('.self::R_BACKSLASH.self::R_NO_BACKSLASH.')*';

    const GLOB_FLAGS = (GLOB_NOSORT | GLOB_ERR | GLOB_NOESCAPE);

    // Check if loaders changed.
    protected static $loaders;
    // Specify if the class should load declared classes.
    protected static $loadDeclaredClasses = false;
    // Specify if the class should load PSR0 declarations.
    protected static $loadPSRO = false;

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
        $names = explode('\\', $name);

        $this->name = implode('\\', $names);
    }

    /**
     * Tell if the class should load declared classes.
     *
     * @param  boolean $load
     * @return string
     */
    public static function loadDeclaredClasses(bool $load=true): string
    {
        static::$loadDeclaredClasses = $load;

        return static::class;
    }

    /**
     * Return if the class should load declared classes.
     *
     * @return boolean
     */
    public static function isLoadingDeclaredClasses(): bool
    {
        return static::$loadDeclaredClasses;
    }

    /**
     * Tell if the class should load from PSR0 declarations.
     *
     * @param  boolean $load
     * @return string
     */
    public static function loadPRS0(bool $load=true): string
    {
        static::$loadPSRO = $load;

        return static::class;
    }

    /**
     * Return if the class should load from PSR0 declarations.
     *
     * @return boolean
     */
    public static function isLoadingPSR0(): bool
    {
        return static::$loadPSRO;
    }

    /**
     * Get loaders used by the class to find classes and namespaces.
     *
     * @param  boolean $reload Force reloading all available loaders.
     * @return array
     */
    public static function getLoaders(bool $reload=false): array
    {
        if (static::$loaders && !$reload) {
            return static::$loaders;
        }

        // Only Composer class loaders are managed.
        return static::$loaders = array_values(array_map(function ($autoloadClosure) {
            return $autoloadClosure[0];
        }, array_filter(spl_autoload_functions(), function ($autoloadClosure) {
            return is_array($autoloadClosure);
        })));
    }

    /**
     * Get loaders used for this instance.
     *
     * @param  boolean $reload Force reloading all available loaders if this instance has no custom loaders.
     * @return array
     */
    public function getLocalLoaders(bool $reload=false): array
    {
        if ($this->hasCustomLoaders) {
            return $this->customLoaders;
        }

        return static::getLoaders($reload);
    }

    /**
     * Define loaders for this instance.
     *
     * @param array   $loaders
     * @param boolean $hasCustomLoaders Define if this instance uses our loaders or not.
     * @return self
     */
    public function setLoaders(array $loaders=[], bool $hasCustomLoaders=true): self
    {
        $this->customLoaders = $loaders;
        $this->hasCustomLoaders = $hasCustomLoaders;

        return $this;
    }

    /**
     * Fill classes and namespaces for this instance with a specific Composer Loader.
     *
     * @param  ClassLoader $loader
     * @return void
     */
    public function fillWithLoader(ClassLoader $loader): void
    {
        $this->fillFromClassMap(array_keys($loader->getClassMap()));

        if (!$loader->isClassMapAuthoritative()) {
            $this->fillFromPsr4($loader->getPrefixesPsr4());

            if (static::isLoadingPSR0()) {
                $this->fillFromPsr4($loader->getPrefixes());
            }
        }
    }

    /**
     * Fill from a Composer classMap.
     *
     * @param array $classes List of classes.
     * @return void
     */
    protected function fillFromClassMap(array $classes): void
    {
        $this->fillClasses($classes);
        $this->fillNamespaces($classes);
    }

    /**
     * Auto fill with declared classes.
     * Could be called by the user to fill manually (by default declared classes aren't used).
     * Use this only if you have autoloaded classes or a field "files" field in a composer.json.
     *
     * @return void
     */
    public function fillWithDeclaredClasses(): void
    {
        $this->fillFromClassMap(get_declared_classes());
        $this->fillFromClassMap(get_declared_interfaces());
        $this->fillFromClassMap(get_declared_traits());
    }

    /**
     * Fill from classes this instance of classes, whatever there are, there are filtered.
     *
     * @param  array $classes
     * @return void
     */
    protected function fillClasses(array $classes): void
    {
        $this->classes = array_merge(
            array_fill_keys(array_map(function (string $class) {
                preg_match($this->getClassRegex(), $class, $matches);

                return $matches[1];
            }, preg_grep($this->getClassRegex(), $classes)), null),
            $this->classes
        );
    }

    /**
     * Fill from classes, this instance of classes, whatever there are, there are filtered.
     *
     * @param  array $classes
     * @return void
     */
    protected function fillNamespaces(array $classes): void
    {
        $this->namespaces = array_merge(
            array_fill_keys(array_map(function (string $class) {
                preg_match($this->getNamespaceRegex(), $class, $matches);

                return $matches[1];
            }, preg_grep($this->getNamespaceRegex(), $classes)), null),
            $this->namespaces
        );
    }

    /**
     * Fill from PSR-4 (and PSR-0) declarations in Composer ClassLoader.
     * Hard part to use and to understand. This operation could be expensive.
     * To fill classes and namespaces, this will get as argument as keys
     * root namespaces and as values root path lists. First, it will filter
     * and match only shared namespaces in order to search in "right" paths.
     *
     * After that, all files and directories are searched in order to match
     * and own to our name of the instance namespace.
     *
     * @param  array $namespaces PSR-4 declaration from a ClassLoader.
     * @return void
     */
    protected function fillFromPsr4(array $namespaces): void
    {
        // Filter namespaces.
        $validNamespaces = preg_grep($this->getSharedNamespaceRegex(), array_keys($namespaces));
        // Here are stored parent namespaces.
        $shorterNamespaces = preg_grep($this->getSharedNamespaceRegex(true), $validNamespaces);
        $name = $this->getName();
        // Allow to detect how many parts are missing in our root namespace.
        $parts = explode('\\', trim($name));

        foreach ($shorterNamespaces as $namespace) {
            $paths = $namespaces[$namespace];

            foreach ($paths as $path) {
                $missingParts = array_diff($parts, explode('\\', trim($namespace)));

                // If the root namespace is a parent namespace.
                if (count($missingParts)) {
                    // Search deeper and deeper in directories in order to match our namespace name.
                    $goodPaths = $this->getDirectoriesFromPath($path, $missingParts);

                    // Many paths could correspond to our search.
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

        // Fill namespaces from sub namespaces.
        $this->fillNamespaces(array_diff($validNamespaces, $shorterNamespaces));
    }

    /**
     * Get all directory paths that match our root path and our missing parts.
     * It will recursevly search directories that could match our missing parts,
     * in order to find directories matching our namespace name.
     *
     * @param  string $path         A root path.
     * @param  array  $missingParts Namespace short name list.
     * @return array
     */
    protected function getDirectoriesFromPath(string $path, array $missingParts=[]): array
    {
        $dirPaths = glob($path.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR, static::GLOB_FLAGS);

        // Errors could be found, or just nothing, returns FALSE.
        if (!$dirPaths) {
            return [];
        }

        // Continue to search if there are missing parts.
        if (count($missingParts)) {
            $validDirs = [];
            $part = array_shift($missingParts);

            foreach ($dirPaths as $dirPath) {
                if ($this->getNameFromFile($this->getDirFromPath($dirPath)) === $part) {
                    $validDirs = array_merge(
                        count($missingParts) ? $this->getDirectoriesFromPath($dirPath, $missingParts) : [$dirPath],
                        $validDirs
                    );
                }
            }

            return $validDirs;
        }

        return $dirPaths;
    }

    /**
     * Get namespaces (directory based) from a root path.
     *
     * @param  string $path
     * @param  string $prefix Namespace prefix.
     * @return array
     */
    protected function getNamespacesFromPath(string $path, string $prefix=''): array
    {
        $dirPaths = glob($path.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR, static::GLOB_FLAGS);

        if (!$dirPaths) {
            return [];
        }

        return array_map(function ($dirPath) use ($prefix) {
            return $prefix.'\\'.$this->getNameFromFile($this->getDirFromPath($dirPath)).'\\';
        }, $dirPaths);
    }

    /**
     * Get directory name from its path.
     *
     * @param  string $dirPath
     * @return string
     */
    protected function getDirFromPath(string $dirPath): string
    {
        $dirs = explode(DIRECTORY_SEPARATOR, $dirPath);

        return $dirs[(count($dirs) - 2)];
    }

    /**
     * Get classes (php file based) from a root path.
     *
     * @param  string $path
     * @param  string $prefix Namespace prefix.
     * @return array
     */
    protected function getClassesFromPath(string $path, string $prefix=''): array
    {
        $files = glob($path.DIRECTORY_SEPARATOR.'*.php', static::GLOB_FLAGS);

        if (!$files) {
            return [];
        }

        return array_map(function ($filePath) use ($prefix) {
            return $prefix.'\\'.$this->getNameFromFile($this->getFileFromPath($filePath));
        }, $files);
    }

    /**
     * Get file name from its path.
     *
     * @param  string $filePath
     * @return string
     */
    protected function getFileFromPath(string $filePath): string
    {
        $dirs = explode(DIRECTORY_SEPARATOR, $filePath);

        return end($dirs);
    }

    /**
     * Convert file name to class name (based on PSR-4 rules).
     *
     * @param  string $file
     * @return string
     */
    protected function getNameFromFile(string $file): string
    {
        $class = explode('.', $file)[0];

        $class = str_replace('-', ' ', str_replace('_', ' ', $class));
        $class = ucwords($class);
        $class = str_replace(' ', '', $class);

        return $class;
    }

    /**
     * Prepare this instance to be used. It checks if loaders changed in
     * order to reload all classes and namespaces names.
     *
     * @return boolean
     */
    protected function prepare(): bool
    {
        if (!$this->hasCustomLoaders && static::$loaders !== static::getLoaders(true)) {
            $this->prepared = false;
        }

        if (!$this->prepared) {
            $this->classes = [];
            $this->namespaces = [];

            foreach ($this->getLocalLoaders() as $loader) {
                if ($loader instanceof ClassLoader) {
                    $this->fillWithLoader($loader);
                }
            }

            if (static::isLoadingDeclaredClasses()) {
                $this->fillWithDeclaredClasses();
            }

            return $this->prepared = true;
        }

        return false;
    }

    /**
     * Force reloading for this instance.
     *
     * @return void
     */
    public function reload(): void
    {
        $this->prepared = false;
        static::$loaders = null;

        $this->prepare();
    }

    /**
     * Get the name of the namespace.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the shortname of the namespace.
     *
     * @return string
     */
    public function getShortName(): string
    {
        preg_match($this->getShortNameRegex(), $this->getNameForRegex(), $matches);

        return reset($matches);
    }

    /**
     * Get the parent name of the namespace.
     *
     * @return string|null
     */
    public function getParentName()
    {
        preg_match($this->getParentNameRegex(), $this->getName(), $matches);

        return ($matches[1] ?? null);
    }

    /**
     * Get the parent reflection of the namespace.
     *
     * @return self
     */
    public function getParent()
    {
        return ($this->parent ?? ($this->parent = new static($this->getParentName())));
    }

    /**
     * Prepare the name for a regex.
     *
     * @return string
     */
    protected function getNameForRegex(): string
    {
        return str_replace('\\', static::R_BACKSLASH, $this->getName());
    }

    /**
     * Match only a sub class.
     *
     * @return string
     */
    protected function getClassRegex(): string
    {
        return '#^'.$this->getNameForRegex().static::R_BACKSLASH.static::R_NO_BACKSLASH.'$#';
    }

    /**
     * Match only a sub namespace.
     *
     * @return string
     */
    protected function getNamespaceRegex(): string
    {
        $backslashCarac = static::R_BACKSLASH.static::R_NO_BACKSLASH;

        return '#^'.$this->getNameForRegex().$backslashCarac.$backslashCarac.'?$#';
    }

    /**
     * Match only shared namespaces.
     *
     * @param  boolean $strict Must be a sub namespace.
     * @return string
     */
    protected function getSharedNamespaceRegex(bool $strict=false): string
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

    /**
     * Match only the short name.
     *
     * @return string
     */
    protected function getShortNameRegex(): string
    {
        return '#((?!'.static::R_BACKSLASH.').)+$#';
    }

    /**
     * Match the parent name.
     *
     * @return string
     */
    protected function getParentNameRegex(): string
    {
        return '#^(.*)'.static::R_BACKSLASH.'#';
    }

    /**
     * Return all class names owned by this namespace.
     *
     * @return array
     */
    public function getClassNames(): array
    {
        $this->prepare();

        return array_keys($this->classes);
    }

    /**
     * Return all reflection classes owned by this namespace.
     *
     * @return array
     */
    public function getClasses(): array
    {
        $this->prepare();

        foreach ($this->classes as $className => $classReflection) {
            if (is_null($classReflection)) {
                $this->classes[$className] = new \ReflectionClass($this->getName().'\\'.$className);
            }
        }

        return $this->classes;
    }

    /**
     * Check if a class name is owned by this namespace.
     *
     * @param  string $className
     * @return boolean
     */
    public function hasClass(string $className): bool
    {
        return in_array($className, $this->getClassNames());
    }

    /**
     * Get a class by its name if it is owned by this namespace.
     *
     * @param  string $className
     * @return ReflectionClass
     */
    public function getClass(string $className): ReflectionClass
    {
        $this->prepare();

        if (is_null($this->classes[$className])) {
            $this->classes[$className] = new \ReflectionClass($this->getName().'\\'.$className);
        }

        return $this->classes[$className];
    }

    /**
     * Get all classes already declared and loaded from this namespace.
     * Could be usefull to detect if we can hack the loading of a specific class and load ours before the originally one does.
     *
     * @return array
     */
    public function getDeclaredClasses(): array
    {
        return array_intersect($this->$this->getClassNames(), get_declared_classes());
    }

    /**
     * Get all sub namespace names.
     *
     * @return array
     */
    public function getNamespaceNames(): array
    {
        $this->prepare();

        return array_keys($this->namespaces);
    }

    /**
     * Get all sub namespaces.
     *
     * @return array
     */
    public function getNamespaces(): array
    {
        $this->prepare();

        foreach ($this->namespaces as $namespace => $reflection) {
            if (is_null($reflection)) {
                $this->namespaces[$namespace] = new static($this->getName().'\\'.$namespace);
            }
        }

        return $this->namespaces;
    }

    /**
     * Get a sub namespace by its name if it is owned by this namespace.
     *
     * @param  string $namespace
     * @return ReflectionNamespace
     */
    public function getNamespace(string $namespace): ReflectionNamespace
    {
        $this->prepare();

        if (is_null($this->namespaces[$namespace])) {
            $this->namespaces[$namespace] = new static($this->getName().'\\'.$namespace);
        }

        return $this->namespaces[$namespace];
    }

    /**
     * Needed.
     *
     * @return null
     */
    public static function export()
    {
        return null;
    }

    /**
     * Return the current namespace name.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }
}
