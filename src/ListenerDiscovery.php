<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

use Rcalicdan\Event\Attributes\Listener;
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

        $cacheFile = null;
        if ($cachePath !== null) {
            if (!is_dir($cachePath)) {
                @mkdir($cachePath, 0775, true);
            }

            if (!is_dir($cachePath) || !is_writable($cachePath)) {
                throw new \InvalidArgumentException("Cache path is not a writable directory: {$cachePath}");
            }

            $cacheFile = $cachePath . DIRECTORY_SEPARATOR . md5($directory . $namespace) . '-listeners.php';

            if (file_exists($cacheFile)) {
                $cacheData = require $cacheFile;

                $isCacheValid = true;
                if ($debugMode && is_array($cacheData)) {
                    $lastModification = self::getLatestModificationTime($directory);
                    if (!isset($cacheData['mtime']) || !is_int($cacheData['mtime']) || $lastModification > $cacheData['mtime']) {
                        $isCacheValid = false;
                    }
                }

                if ($isCacheValid && is_array($cacheData) && isset($cacheData['listeners']) && is_array($cacheData['listeners'])) {
                    /** @var array<int, array<string, mixed>> $listeners */
                    $listeners = $cacheData['listeners'];
                    self::registerListenersFromCache($listeners);
                    self::$discovered = true;
                    return;
                }
            }
        }

        if (!is_dir($directory)) {
            throw new \InvalidArgumentException("Directory not found: {$directory}");
        }

        $realDirectory = realpath($directory);
        if ($realDirectory === false) {
            throw new \InvalidArgumentException("Cannot resolve real path for directory: {$directory}");
        }

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

            $relativePath = str_replace($realDirectory, '', $filePath);
            $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
            $classPath = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);
            $className = rtrim($namespace, '\\') . '\\' . $classPath;

            if (self::fileContainsClass($filePath, $className)) {
                if (class_exists($className, true)) {
                    self::registerClass($className, $discoveredListeners, $filePath);
                }
            } else {
                self::loadAndRegisterFunctions($filePath, $namespace, $discoveredListeners);
            }
        }

        if ($cacheFile !== null) {
            $exported = var_export([
                'mtime' => $latestMtime,
                'listeners' => $discoveredListeners,
            ], true);

            $exported = str_replace(['array (', ')', '  '], ['[', ']', '  '], $exported);
            $cacheContent = "<?php\n\nreturn " . $exported . ";\n";

            file_put_contents($cacheFile, $cacheContent, LOCK_EX);
        }

        self::registerListenersFromCache($discoveredListeners);

        self::$discovered = true;
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
     * @param array<int, array<string, mixed>> $discoveredListeners
     */
    private static function registerFunction(string $functionName, array &$discoveredListeners, string $filePath): void
    {
        if (in_array($functionName, self::$registeredFunctions, true)) {
            return;
        }

        try {
            $reflection = new ReflectionFunction($functionName);
            $attributes = $reflection->getAttributes(Listener::class);

            if ($attributes === []) return;

            foreach ($attributes as $attribute) {
                /** @var Listener $listener */
                $listener = $attribute->newInstance();

                if (function_exists($functionName)) {
                    $discoveredListeners[] = [
                        'event' => $listener->event instanceof \BackedEnum ? $listener->event->value : $listener->event,
                        'callable' => $functionName,
                        'priority' => $listener->priority,
                        'file' => $filePath,
                    ];
                    self::$registeredFunctions[] = $functionName;
                }
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
        $attributes = $reflection->getAttributes(Listener::class);
        if ($attributes === []) return;

        $instance = $reflection->newInstance();
        foreach ($attributes as $attribute) {
            /** @var Listener $listener */
            $listener = $attribute->newInstance();
            $method = $listener->method;

            if (!method_exists($instance, $method)) {
                throw new \RuntimeException("Method {$method} does not exist on {$reflection->getName()}");
            }

            if (is_callable([$instance, $method])) {
                $discoveredListeners[] = [
                    'event' => $listener->event instanceof \BackedEnum ? $listener->event->value : $listener->event,
                    'callable' => [$reflection->getName(), $method],
                    'priority' => $listener->priority,
                    'file' => $filePath,
                ];
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
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Listener::class);
            if ($attributes === []) continue;

            if ($instance === null) {
                $instance = $reflection->newInstance();
            }

            foreach ($attributes as $attribute) {
                /** @var Listener $listener */
                $listener = $attribute->newInstance();

                if (is_callable([$instance, $method->getName()])) {
                    $discoveredListeners[] = [
                        'event' => $listener->event instanceof \BackedEnum ? $listener->event->value : $listener->event,
                        'callable' => [$reflection->getName(), $method->getName()],
                        'priority' => $listener->priority,
                        'file' => $filePath,
                    ];
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

            Event::on($event, $callable, $priority);
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
