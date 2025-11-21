<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

use Psr\Container\ContainerInterface;
use Rcalicdan\Event\Attributes\ListenOnce;
use Rcalicdan\Event\Attributes\ListenTo;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use SplFileInfo;

class ListenerDiscovery
{
    /** @var array<string, bool> */
    private static array $discoveredPaths = [];

    /** @var array<int, string> */
    private static array $registeredFunctions = [];

    /** @var array<int, string> */
    private static array $loadedFiles = [];

    /** @var ContainerInterface|null */
    private static ?ContainerInterface $container = null;

    /**
     * Discover and register all listeners in one or more directories.
     *
     * @param string|array<int, string> $directory The directory or directories to scan for listeners.
     * @param bool|null $failFast Optional. If true, exceptions will be thrown. If false, resilient mode. If null, uses env config.
     * @param string|null $cachePath Optional. The absolute path to a writable directory to store the cache file. If null, caching is disabled.
     * @param bool $refreshCache Optional. If true and caching is enabled, the cache is invalidated if listener files change. Set to false in production.
     * @param ContainerInterface|null $container Optional. PSR-11 container for dependency injection. If null, classes are instantiated directly.
     */
    public static function discover(
        string|array $directory,
        ?bool $failFast = null,
        ?string $cachePath = null,
        bool $refreshCache = false,
        ?ContainerInterface $container = null
    ): void {
        if ($failFast !== null) {
            Event::setThrowOnListenerError($failFast);
        }

        self::$container = $container;

        $directories = is_array($directory) ? $directory : [$directory];

        foreach ($directories as $dir) {
            self::discoverSingle($dir, $cachePath, $refreshCache);
        }
    }

    /**
     * Discover and register listeners in a single directory.
     *
     * @param string $directory
     * @param string|null $cachePath
     * @param bool $refreshCache
     */
    private static function discoverSingle(
        string $directory,
        ?string $cachePath,
        bool $refreshCache
    ): void {
        $pathKey = md5($directory);

        if (isset(self::$discoveredPaths[$pathKey])) {
            return;
        }

        $cacheFile = self::prepareCacheFile($cachePath, $directory);

        if (self::tryLoadFromCache($cacheFile, $directory, $refreshCache)) {
            self::$discoveredPaths[$pathKey] = true;

            return;
        }

        $realDirectory = self::validateAndResolveDirectory($directory);
        $discoveredData = self::scanAndDiscoverListeners($realDirectory);

        self::writeCacheIfEnabled($cacheFile, $discoveredData);
        self::registerListenersFromCache($discoveredData['listeners']);

        self::$discoveredPaths[$pathKey] = true;
    }

    /**
     * Prepare the cache file path if caching is enabled.
     *
     * @param string|null $cachePath
     * @param string $directory
     * @return string|null The cache file path or null if caching is disabled
     * @throws \InvalidArgumentException
     */
    private static function prepareCacheFile(?string $cachePath, string $directory): ?string
    {
        if ($cachePath === null) {
            return null;
        }

        if (! is_dir($cachePath)) {
            @mkdir($cachePath, 0775, true);
        }

        if (! is_dir($cachePath) || ! is_writable($cachePath)) {
            throw new \InvalidArgumentException("Cache path is not a writable directory: {$cachePath}");
        }

        return $cachePath . DIRECTORY_SEPARATOR . md5($directory) . '-listeners.php';
    }

    /**
     * Try to load listeners from cache.
     *
     * @param string|null $cacheFile
     * @param string $directory
     * @param bool $refreshCache
     * @return bool True if cache was loaded successfully, false otherwise
     */
    private static function tryLoadFromCache(?string $cacheFile, string $directory, bool $refreshCache): bool
    {
        if ($cacheFile === null || ! file_exists($cacheFile)) {
            return false;
        }

        /** @var mixed $cacheData */
        $cacheData = require $cacheFile;

        if (! is_array($cacheData)) {
            return false;
        }

        /** @var array<string, mixed> $cacheData */

        if (! isset($cacheData['listeners']) || ! is_array($cacheData['listeners'])) {
            return false;
        }

        if ($refreshCache && ! self::isCacheValid($cacheData, $directory)) {
            return false;
        }

        /** @var array<int, array<string, mixed>> $listeners */
        $listeners = $cacheData['listeners'];
        self::registerListenersFromCache($listeners);

        return true;
    }

    /**
     * Check if cache is still valid by comparing modification times.
     *
     * @param array<string, mixed> $cacheData
     * @param string $directory
     * @return bool
     */
    private static function isCacheValid(array $cacheData, string $directory): bool
    {
        if (! isset($cacheData['mtime']) || ! is_int($cacheData['mtime'])) {
            return false;
        }

        $lastModification = self::getLatestModificationTime($directory);

        return $lastModification <= $cacheData['mtime'];
    }

    /**
     * Validate and resolve the directory path.
     *
     * @param string $directory
     * @return string The resolved real path
     * @throws \InvalidArgumentException
     */
    private static function validateAndResolveDirectory(string $directory): string
    {
        if (! is_dir($directory)) {
            throw new \InvalidArgumentException("Directory not found: {$directory}");
        }

        $realDirectory = realpath($directory);
        if ($realDirectory === false) {
            throw new \InvalidArgumentException("Cannot resolve real path for directory: {$directory}");
        }

        return $realDirectory;
    }

    /**
     * Scan directory and discover all listeners.
     *
     * @param string $realDirectory
     * @return array{listeners: array<int, array<string, mixed>>, mtime: int}
     */
    private static function scanAndDiscoverListeners(string $realDirectory): array
    {
        /** @var array<int, array<string, mixed>> $discoveredListeners */
        $discoveredListeners = [];
        $latestMtime = 0;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realDirectory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $latestMtime = max($latestMtime, $file->getMTime());

            $className = self::detectClassNameFromFile($filePath);

            if ($className !== null && self::fileContainsClass($filePath, $className)) {
                if (class_exists($className, true)) {
                    self::registerClass($className, $discoveredListeners, $filePath);
                }
            } else {
                self::loadAndRegisterFunctions($filePath, $discoveredListeners);
            }
        }

        return [
            'listeners' => $discoveredListeners,
            'mtime' => $latestMtime,
        ];
    }

    /**
     * Detect class name from file by parsing namespace and class declarations.
     *
     * @param string $filePath
     * @return string|null
     */
    private static function detectClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $namespace = '';
        $className = '';

        if (preg_match('/^namespace\s+([^;]+);/m', $content, $matches) === 1) {
            $namespace = $matches[1];
        }

        if (preg_match('/^(abstract\s+|final\s+)?class\s+(\w+)/m', $content, $matches) === 1) {
            $className = $matches[2];
        }

        if ($className === '') {
            return null;
        }

        return $namespace !== '' ? $namespace . '\\' . $className : $className;
    }

    /**
     * Write discovered listeners to cache file if enabled.
     *
     * @param string|null $cacheFile
     * @param array{listeners: array<int, array<string, mixed>>, mtime: int} $discoveredData
     * @return void
     */
    private static function writeCacheIfEnabled(?string $cacheFile, array $discoveredData): void
    {
        if ($cacheFile === null) {
            return;
        }

        $exported = var_export($discoveredData, true);
        $exported = str_replace(['array (', ')'], ['[', ']'], $exported);
        $cacheContent = "<?php\n\nreturn " . $exported . ";\n";

        file_put_contents($cacheFile, $cacheContent, LOCK_EX);
    }

    private static function fileContainsClass(string $filePath, string $expectedClassName): bool
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        $lastBackslashPos = strrpos($expectedClassName, '\\');
        $shortClassName = $lastBackslashPos !== false
            ? substr($expectedClassName, $lastBackslashPos + 1)
            : $expectedClassName;
        $hasClass = preg_match('/^(abstract\s+|final\s+)?class\s+' . preg_quote($shortClassName, '/') . '\s/m', $content) === 1;

        return $hasClass;
    }

    /**
     * @param array<int, array<string, mixed>> $discoveredListeners
     */
    private static function loadAndRegisterFunctions(string $filePath, array &$discoveredListeners): void
    {
        $isAlreadyLoaded = in_array($filePath, self::$loadedFiles, true);

        $functionsBefore = get_defined_functions()['user'];

        if (! $isAlreadyLoaded) {
            require_once $filePath;
            self::$loadedFiles[] = $filePath;
        }

        $functionsAfter = get_defined_functions()['user'];
        $newFunctions = array_diff($functionsAfter, $functionsBefore);

        if ($isAlreadyLoaded || count($newFunctions) === 0) {
            $fileFunctions = self::findFunctionsInFile($filePath);

            if (count($fileFunctions) > 0) {
                $newFunctions = $fileFunctions;
            }
        }

        foreach ($newFunctions as $functionName) {
            if (is_string($functionName)) {
                self::registerFunction($functionName, $discoveredListeners, $filePath);
            }
        }
    }

    /**
     * Find functions defined in a specific file.
     *
     * @param string $filePath
     * @return array<int, string>
     */
    private static function findFunctionsInFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $namespace = '';
        if (preg_match('/^namespace\s+([^;]+);/m', $content, $matches) === 1) {
            $namespace = $matches[1];
        }

        preg_match_all('/^function\s+(\w+)\s*\(/m', $content, $matches);
        $functionNames = [];

        foreach ($matches[1] as $functionName) {
            $fullName = $namespace !== '' ? $namespace . '\\' . $functionName : $functionName;
            if (function_exists($fullName)) {
                $functionNames[] = $fullName;
            }
        }

        return $functionNames;
    }

    /**
     * Helper to add a listener to the discovered list.
     *
     * @param array<int, array<string, mixed>> $discoveredListeners
     * @param ListenTo|ListenOnce $listener
     * @param string|array<int, string> $callable
     */
    private static function addDiscoveredListener(
        array &$discoveredListeners,
        object $listener,
        string|array $callable,
        string $filePath,
        bool $once
    ): void {
        $discoveredListeners[] = [
            'event' => $listener->event instanceof \BackedEnum ? $listener->event->value : $listener->event,
            'callable' => $callable,
            'priority' => $listener->priority,
            'once' => $once,
            'file' => $filePath,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $discoveredListeners
     */
    private static function registerFunction(string $functionName, array &$discoveredListeners, string $filePath): void
    {
        if (in_array($functionName, self::$registeredFunctions, true)) {
            return;
        }

        try {
            $reflection = new ReflectionFunction($functionName);
            $hasAttributes = false;

            $attributeTypes = [
                ListenTo::class => false,
                ListenOnce::class => true,
            ];

            foreach ($attributeTypes as $attributeClass => $isOnce) {
                foreach ($reflection->getAttributes($attributeClass) as $attribute) {
                    /** @var ListenTo|ListenOnce $listener */
                    $listener = $attribute->newInstance();

                    if (function_exists($functionName)) {
                        self::addDiscoveredListener(
                            $discoveredListeners,
                            $listener,
                            $functionName,
                            $filePath,
                            $isOnce
                        );
                        $hasAttributes = true;
                    }
                }
            }

            if ($hasAttributes) {
                self::$registeredFunctions[] = $functionName;
            }
        } catch (\Throwable) {
        }
    }

    /**
     * Resolve a class instance using the container if available, or create a new instance.
     *
     * @param class-string $className
     * @return object
     */
    private static function resolveInstance(string $className): object
    {
        if (self::$container !== null && self::$container->has($className)) {
            /** @var mixed $instance */
            $instance = self::$container->get($className);

            if (\is_object($instance)) {
                return $instance;
            }
        }

        return new $className();
    }

    /**
     * @param class-string $className
     * @param array<int, array<string, mixed>> $discoveredListeners
     */
    private static function registerClass(string $className, array &$discoveredListeners, string $filePath): void
    {
        try {
            $reflection = new ReflectionClass($className);
            self::registerClassListeners($reflection, $discoveredListeners, $filePath);
            self::registerMethodListeners($reflection, $discoveredListeners, $filePath);
        } catch (\ReflectionException) {
        }
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param array<int, array<string, mixed>> $discoveredListeners
     */
    private static function registerClassListeners(ReflectionClass $reflection, array &$discoveredListeners, string $filePath): void
    {
        if (
            $reflection->getAttributes(ListenTo::class) === [] &&
            $reflection->getAttributes(ListenOnce::class) === []
        ) {
            return;
        }

        $instance = self::resolveInstance($reflection->getName());

        $attributeTypes = [
            ListenTo::class => false,
            ListenOnce::class => true,
        ];

        foreach ($attributeTypes as $attributeClass => $isOnce) {
            foreach ($reflection->getAttributes($attributeClass) as $attribute) {
                /** @var ListenTo|ListenOnce $listener */
                $listener = $attribute->newInstance();
                $method = $listener->method;

                if (! method_exists($instance, $method)) {
                    throw new \RuntimeException("Method {$method} does not exist on {$reflection->getName()}");
                }

                if (is_callable([$instance, $method])) {
                    self::addDiscoveredListener(
                        $discoveredListeners,
                        $listener,
                        [$reflection->getName(), $method],
                        $filePath,
                        $isOnce
                    );
                }
            }
        }
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param array<int, array<string, mixed>> $discoveredListeners
     */
    private static function registerMethodListeners(ReflectionClass $reflection, array &$discoveredListeners, string $filePath): void
    {
        $instance = null;

        $attributeTypes = [
            ListenTo::class => false,
            ListenOnce::class => true,
        ];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $hasListenTo = $method->getAttributes(ListenTo::class) !== [];
            $hasListenOnce = $method->getAttributes(ListenOnce::class) !== [];

            if (! $hasListenTo && ! $hasListenOnce) {
                continue;
            }

            if ($instance === null) {
                $instance = self::resolveInstance($reflection->getName());
            }

            foreach ($attributeTypes as $attributeClass => $isOnce) {
                foreach ($method->getAttributes($attributeClass) as $attribute) {
                    /** @var ListenTo|ListenOnce $listener */
                    $listener = $attribute->newInstance();

                    if (is_callable([$instance, $method->getName()])) {
                        self::addDiscoveredListener(
                            $discoveredListeners,
                            $listener,
                            [$reflection->getName(), $method->getName()],
                            $filePath,
                            $isOnce
                        );
                    }
                }
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $listeners
     */
    private static function registerListenersFromCache(array $listeners): void
    {
        $includedFiles = [];

        foreach ($listeners as $listener) {
            if (! \is_array($listener)) {
                continue;
            }

            $file = $listener['file'] ?? null;
            if (\is_string($file) && ! isset($includedFiles[$file])) {
                require_once $file;
                $includedFiles[$file] = true;
            }

            $callable = $listener['callable'] ?? null;
            $event = $listener['event'] ?? null;
            $priority = $listener['priority'] ?? null;
            $once = $listener['once'] ?? false;

            if ($callable === null || $event === null || $priority === null) {
                continue;
            }

            if (! \is_int($priority)) {
                continue;
            }

            if (! \is_string($event) && ! $event instanceof \BackedEnum) {
                continue;
            }

            // Resolve class instances using container if available
            if (\is_array($callable) && is_string($callable[0] ?? null) && class_exists($callable[0])) {
                $instance = self::resolveInstance($callable[0]);
                $callable = [$instance, $callable[1]];
            }

            if (! is_callable($callable)) {
                continue;
            }

            if ($once === true) {
                Event::once($event, $callable, $priority);
            } else {
                Event::on($event, $callable, $priority);
            }
        }
    }

    private static function getLatestModificationTime(string $directory): int
    {
        $latestMtime = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $latestMtime = max($latestMtime, $file->getMTime());
            }
        }

        return $latestMtime;
    }

    public static function reset(): void
    {
        self::$discoveredPaths = [];
        self::$registeredFunctions = [];
        self::$loadedFiles = [];
        self::$container = null;
    }
}
