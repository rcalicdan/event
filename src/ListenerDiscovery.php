<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

use Rcalicdan\Event\Attributes\ListenTo;
use Rcalicdan\Event\Attributes\ListenOnce;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;
use SplFileInfo;

class ListenerDiscovery
{
    private static bool $discovered = false;

    /** @var array<int, string> */
    private static array $registeredFunctions = [];

    /** @var array<int, string> */
    private static array $loadedFiles = [];

    /**
     * Discover and register all listeners in a directory.
     *
     * @param string $directory The directory to scan for listeners.
     * @param string $namespace The namespace of the listeners.
     * @param bool|null $failFast Optional. If true, exceptions will be thrown. If false, resilient mode. If null, uses env config.
     * @param string|null $cachePath Optional. The absolute path to a writable directory to store the cache file. If null, caching is disabled.
     * @param bool $debugMode Optional. If true and caching is enabled, the cache is invalidated if listener files change. Set to false in production.
     */
    public static function discover(
        string $directory,
        string $namespace,
        ?bool $failFast = null,
        ?string $cachePath = null,
        bool $debugMode = false
    ): void {
        if (self::$discovered) {
            return;
        }

        if ($failFast !== null) {
            Event::setThrowOnListenerError($failFast);
        }

        $cacheFile = self::prepareCacheFile($cachePath, $directory, $namespace);

        if (self::tryLoadFromCache($cacheFile, $directory, $debugMode)) {
            self::$discovered = true;
            return;
        }

        $realDirectory = self::validateAndResolveDirectory($directory);
        $discoveredData = self::scanAndDiscoverListeners($realDirectory, $namespace);

        self::writeCacheIfEnabled($cacheFile, $discoveredData);
        self::registerListenersFromCache($discoveredData['listeners']);

        self::$discovered = true;
    }

    /**
     * Prepare the cache file path if caching is enabled.
     *
     * @param string|null $cachePath
     * @param string $directory
     * @param string $namespace
     * @return string|null The cache file path or null if caching is disabled
     * @throws \InvalidArgumentException
     */
    private static function prepareCacheFile(?string $cachePath, string $directory, string $namespace): ?string
    {
        if ($cachePath === null) {
            return null;
        }

        if (!is_dir($cachePath)) {
            @mkdir($cachePath, 0775, true);
        }

        if (!is_dir($cachePath) || !is_writable($cachePath)) {
            throw new \InvalidArgumentException("Cache path is not a writable directory: {$cachePath}");
        }

        return $cachePath . DIRECTORY_SEPARATOR . md5($directory . $namespace) . '-listeners.php';
    }

    /**
     * Try to load listeners from cache.
     *
     * @param string|null $cacheFile
     * @param string $directory
     * @param bool $debugMode
     * @return bool True if cache was loaded successfully, false otherwise
     */
    private static function tryLoadFromCache(?string $cacheFile, string $directory, bool $debugMode): bool
    {
        if ($cacheFile === null || !file_exists($cacheFile)) {
            return false;
        }

        /** @var mixed $cacheData */
        $cacheData = require $cacheFile;

        if (!is_array($cacheData)) {
            return false;
        }

        /** @var array<string, mixed> $cacheData */

        if (!isset($cacheData['listeners']) || !is_array($cacheData['listeners'])) {
            return false;
        }

        if ($debugMode && !self::isCacheValid($cacheData, $directory)) {
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
        if (!isset($cacheData['mtime']) || !is_int($cacheData['mtime'])) {
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
        if (!is_dir($directory)) {
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
     * @param string $namespace
     * @return array{listeners: array<int, array<string, mixed>>, mtime: int}
     */
    private static function scanAndDiscoverListeners(string $realDirectory, string $namespace): array
    {
        /** @var array<int, array<string, mixed>> $discoveredListeners */
        $discoveredListeners = [];
        $latestMtime = 0;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realDirectory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $latestMtime = max($latestMtime, $file->getMTime());

            $className = self::buildClassName($realDirectory, $filePath, $namespace);

            if (self::fileContainsClass($filePath, $className)) {
                if (class_exists($className, true)) {
                    self::registerClass($className, $discoveredListeners, $filePath);
                }
            } else {
                self::loadAndRegisterFunctions($filePath, $namespace, $discoveredListeners);
            }
        }

        return [
            'listeners' => $discoveredListeners,
            'mtime' => $latestMtime,
        ];
    }

    /**
     * Build the fully qualified class name from file path.
     *
     * @param string $realDirectory
     * @param string $filePath
     * @param string $namespace
     * @return string
     */
    private static function buildClassName(string $realDirectory, string $filePath, string $namespace): string
    {
        $relativePath = str_replace($realDirectory, '', $filePath);
        $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
        $classPath = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);

        return rtrim($namespace, '\\') . '\\' . $classPath;
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
    private static function loadAndRegisterFunctions(string $filePath, string $namespace, array &$discoveredListeners): void
    {
        $isAlreadyLoaded = in_array($filePath, self::$loadedFiles, true);

        $functionsBefore = get_defined_functions()['user'];

        if (!$isAlreadyLoaded) {
            require_once $filePath;
            self::$loadedFiles[] = $filePath;
        }

        $functionsAfter = get_defined_functions()['user'];
        $newFunctions = array_diff($functionsAfter, $functionsBefore);

        if ($isAlreadyLoaded && count($newFunctions) === 0) {
            $newFunctions = self::findFunctionsInNamespace($namespace);
        }

        foreach ($newFunctions as $functionName) {
            if (is_string($functionName)) {
                self::registerFunction($functionName, $discoveredListeners, $filePath);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private static function findFunctionsInNamespace(string $namespace): array
    {
        $allFunctions = get_defined_functions()['user'];
        $namespace = strtolower(rtrim($namespace, '\\') . '\\');

        $matchingFunctions = [];
        foreach ($allFunctions as $functionName) {
            $lowerFunctionName = strtolower($functionName);
            if (str_starts_with($lowerFunctionName, $namespace)) {
                $matchingFunctions[] = $functionName;
            }
        }

        return $matchingFunctions;
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

        $instance = $reflection->newInstance();

        $attributeTypes = [
            ListenTo::class => false,
            ListenOnce::class => true,
        ];

        foreach ($attributeTypes as $attributeClass => $isOnce) {
            foreach ($reflection->getAttributes($attributeClass) as $attribute) {
                /** @var ListenTo|ListenOnce $listener */
                $listener = $attribute->newInstance();
                $method = $listener->method;

                if (!method_exists($instance, $method)) {
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

            if (!$hasListenTo && !$hasListenOnce) {
                continue;
            }

            if ($instance === null) {
                $instance = $reflection->newInstance();
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
            if (!is_array($listener)) {
                continue;
            }

            $file = $listener['file'] ?? null;
            if (is_string($file) && !isset($includedFiles[$file])) {
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

            if (!is_int($priority)) {
                continue;
            }

            if (!is_string($event) && !$event instanceof \BackedEnum) {
                continue;
            }

            if (is_array($callable) && is_string($callable[0] ?? null) && class_exists($callable[0])) {
                $callable = [new $callable[0](), $callable[1]];
            }

            if (!is_callable($callable)) {
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
        self::$discovered = false;
        self::$registeredFunctions = [];
    }
}