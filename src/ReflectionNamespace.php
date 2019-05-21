<?php
/**
 * @author Samy Nastuzzi <samy@nastuzzi.fr>
 *
 * @copyright Copyright (c) 2019
 * @license MIT
 */

/**
 * Reflect a namespace by its full name.
 *
 * Usefull for:
 * - Finding all sub namespaces,
 * - Finding all classes,
 * - Searching by namespace and no more by directory,
 * - Finding all definitions in a namespace (whatever by you or your dependencies).
 */
class ReflectionNamespace implements Reflector
{
    // Regex back slash.
    const R_BACKSLASH = '\\\\';
    const R_NO_BACKSLASH = '((?:(?!\\\\).)+)';
    const R_WHATEVER_NAMESPACE = '(\\\\((?:(?!\\\\).)+))*';

    // Check if loaders changed.
    protected static $loaders;
    // Specify if the class should load declared classes.
    protected static $loadDeclaredClasses = FALSE;
    // Specify if the class should load PSR0 declarations.
    protected static $loadPSRO = FALSE;

    protected $name;
    protected $parent;

    // Specific loaders for this instance.
    protected $customLoaders;
    protected $hasCustomLoaders = FALSE;

    // Check if everything is loaded for this instance.
    protected $prepared = FALSE;
    protected $classes;
    protected $namespaces;

    /**
     * Return an instance for the specific namespace.
     *
     * @param string $name The name of the namespace.
     */
    public function __construct(string $name)
    {
        $this->name = trim($name, " \t\n\r\0\x0B\\");
    }

    /**
     * Tell if the class should load declared classes.
     *
     * @param  boolean $load
     * @return void
     */
    public static function loadDeclaredClasses(bool $load=NULL)
    {
        static::$loadDeclaredClasses = is_null($load) ? TRUE : $load;
    }

    /**
     * Return if the class should load declared classes.
     *
     * @return boolean
     */
    public static function isLoadingDeclaredClasses()
    {
        return static::$loadDeclaredClasses;
    }

    /**
     * Tell if the class should load from PSR0 declarations.
     *
     * @param  boolean $load
     * @return void
     */
    public static function loadPRS0(bool $load=NULL)
    {
        static::$loadPSRO = is_null($load) ? TRUE : $load;
    }

    /**
     * Return if the class should load from PSR0 declarations.
     *
     * @return boolean
     */
    public static function isLoadingPSR0()
    {
        return static::$loadPSRO;
    }

    /**
     * Get loaders used by the class to find classes and namespaces.
     *
     * @param  boolean $reload Force reloading all available loaders.
     * @return array
     */
    public static function getLoaders(bool $reload=NULL)
    {
        if (static::$loaders && (is_null($reload) || !$reload)) {
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
    public function getLocalLoaders(bool $reload=NULL)
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
    public function setLoaders(array $loaders=array(), bool $hasCustomLoaders=NULL)
    {
        $this->customLoaders = $loaders;
        $this->hasCustomLoaders = is_null($hasCustomLoaders) ? TRUE : $hasCustomLoaders;

        return $this;
    }

    /**
     * Fill classes and namespaces for this instance with a specific Composer Loader.
     *
     * @param  Composer\Autoload\ClassLoader $loader
     * @return void
     */
    public function fillWithLoader(Composer\Autoload\ClassLoader $loader)
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
    public function fillFromClassMap(array $classes)
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
    public function fillWithDeclaredClasses()
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
    protected function fillClasses(array $classes)
    {
        $this->classes = array_merge(
            array_fill_keys(array_map(function (string $class) {
                preg_match($this->getClassRegex(), $class, $matches);

                return $matches[1];
            }, preg_grep($this->getClassRegex(), $classes)), NULL),
            $this->classes
        );
    }

    /**
     * Fill from classes, this instance of classes, whatever there are, there are filtered.
     *
     * @param  array $classes
     * @return void
     */
    protected function fillNamespaces(array $classes)
    {
        $this->namespaces = array_merge(
            array_fill_keys(array_map(function (string $class) {
                preg_match($this->getNamespaceRegex(), $class, $matches);

                return $matches[1];
            }, preg_grep($this->getNamespaceRegex(), $classes)), NULL),
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
    protected function fillFromPsr4(array $namespaces)
    {
        // Filter namespaces.
        $validNamespaces = preg_grep($this->getSharedNamespaceRegex(), array_keys($namespaces));
        // Here are stored parent namespaces.
        $shorterNamespaces = preg_grep($this->getSharedNamespaceRegex(TRUE), $validNamespaces);
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
    protected function getDirectoriesFromPath(string $path, array $missingParts=array())
    {
        $dirPaths = glob($path.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR, (GLOB_NOSORT | GLOB_ERR | GLOB_NOESCAPE));

        // Errors could be found, or just nothing, returns FALSE.
        if (!$dirPaths) {
            return array();
        }

        // Continue to search if there are missing parts.
        if (count($missingParts)) {
            $validDirs = array();
            $part = array_shift($missingParts);

            foreach ($dirPaths as $dirPath) {
                if ($this->getNameFromFile($this->getDirFromPath($dirPath)) === $part) {
                    $validDirs = array_merge(
                        count($missingParts) ? $this->getDirectoriesFromPath($dirPath, $missingParts) : array($dirPath),
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
    protected function getNamespacesFromPath(string $path, string $prefix=NULL)
    {
        $dirPaths = glob($path.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR, (GLOB_NOSORT | GLOB_ERR | GLOB_NOESCAPE));

        if (!$dirPaths) {
            return array();
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
    protected function getDirFromPath(string $dirPath)
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
    protected function getClassesFromPath(string $path, string $prefix=NULL)
    {
        $files = glob($path.DIRECTORY_SEPARATOR.'*.php', (GLOB_NOSORT | GLOB_ERR | GLOB_NOESCAPE));

        if (!$files) {
            return array();
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
    protected function getFileFromPath(string $filePath)
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
    protected function getNameFromFile(string $file)
    {
        $exploded = explode('.', $file);
        $class = $exploded[0];

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
    protected function prepare()
    {
        if (!$this->hasCustomLoaders && static::$loaders !== static::getLoaders(TRUE)) {
            $this->prepared = FALSE;
        }

        if (!$this->prepared) {
            $this->classes = array();
            $this->namespaces = array();

            foreach ($this->getLocalLoaders() as $loader) {
                if ($loader instanceof Composer\Autoload\ClassLoader) {
                    $this->fillWithLoader($loader);
                }
            }

            if (static::isLoadingDeclaredClasses()) {
                $this->fillWithDeclaredClasses();
            }

            return $this->prepared = TRUE;
        }

        return FALSE;
    }

    /**
     * Force reloading for this instance.
     *
     * @return void
     */
    public function reload()
    {
        $this->prepared = FALSE;
        static::$loaders = NULL;

        $this->prepare();
    }

    /**
     * Get the name of the namespace.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the shortname of the namespace.
     *
     * @return string
     */
    public function getShortName()
    {
        preg_match($this->getShortNameRegex(), $this->getNameForRegex(), $matches);

        return reset($matches);
    }

    /**
     * Get the parent name of the namespace.
     *
     * @return string|NULL
     */
    public function getParentName()
    {
        preg_match($this->getParentNameRegex(), $this->getName(), $matches);

        if (isset($matches[1])) {
            return $matches[1];
        }

        return NULL;
    }

    /**
     * Get the parent reflection of the namespace.
     *
     * @return static
     */
    public function getParent()
    {
        if (is_null($this->parent)) {
            $this->parent = new static($this->getParentName());
        }

        return $this->parent;
    }

    /**
     * Prepare the name for a regex.
     *
     * @return string
     */
    protected function getNameForRegex()
    {
        return str_replace('\\', static::R_BACKSLASH, $this->getName());
    }

    /**
     * Match only a sub class.
     *
     * @return string
     */
    protected function getClassRegex()
    {
        return '#^'.$this->getNameForRegex().static::R_BACKSLASH.static::R_NO_BACKSLASH.'$#';
    }

    /**
     * Match only a sub namespace.
     *
     * @return string
     */
    protected function getNamespaceRegex()
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
    protected function getSharedNamespaceRegex(bool $strict=NULL)
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
    protected function getShortNameRegex()
    {
        return '#((?!'.static::R_BACKSLASH.').)+$#';
    }

    /**
     * Match the parent name.
     *
     * @return string
     */
    protected function getParentNameRegex()
    {
        return '#^(.*)'.static::R_BACKSLASH.'#';
    }

    /**
     * Return all class names owned by this namespace.
     *
     * @return array
     */
    public function getClassNames()
    {
        $this->prepare();

        $classes = [];

        foreach (array_keys($this->classes) as $className) {
            $classes[$className] = $this->getName().'\\'.$className;
        }

        return $classes;
    }

    /**
     * Return all reflection classes owned by this namespace.
     *
     * @return array
     */
    public function getClasses()
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
    public function hasClass(string $className)
    {
        return in_array($className, array_keys($this->getClassNames()));
    }

    /**
     * Get a class by its name if it is owned by this namespace.
     *
     * @param  string $className
     * @return ReflectionClass
     */
    public function getClass(string $className)
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
    public function getDeclaredClasses()
    {
        return array_intersect(array_values($this->$this->getClassNames()), get_declared_classes());
    }

    /**
     * Get all sub namespace names.
     *
     * @return array
     */
    public function getNamespaceNames()
    {
        $this->prepare();

        $namespaces = [];

        foreach (array_keys($this->namespaces) as $namespace) {
            $namespaces[$namespace] = $this->getName().'\\'.$namespace;
        }

        return $namespaces;
    }

    /**
     * Get all sub namespaces.
     *
     * @return array
     */
    public function getNamespaces()
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
    public function getNamespace(string $namespace)
    {
        $this->prepare();

        if (is_null($this->namespaces[$namespace])) {
            $this->namespaces[$namespace] = new static($this->getName().'\\'.$namespace);
        }

        return $this->namespaces[$namespace];
    }

    /**
     * Check if a namespace is owned by this namespace.
     *
     * @param  string $namespace
     * @return boolean
     */
    public function hasNamespace(string $namespace)
    {
        return in_array($namespace, array_keys($this->getNamespace()));
    }

    /**
     * Needed.
     *
     * @return NULL
     */
    public static function export()
    {
        return NULL;
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
