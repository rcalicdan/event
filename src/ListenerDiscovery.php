<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

use Rcalicdan\Event\Attributes\Listener;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;

class ListenerDiscovery
{
    private static bool $discovered = false;
    private static array $registeredFunctions = [];
    private static array $loadedFiles = [];

    /**
     * Discover and register all listeners in a directory
     */
    public static function discover(string $directory, string $namespace): void
    {
        if (self::$discovered) {
            return;
        }

        if (!is_dir($directory)) {
            throw new \InvalidArgumentException("Directory not found: {$directory}");
        }

        $realDirectory = realpath($directory);
        if ($realDirectory === false) {
            throw new \InvalidArgumentException("Cannot resolve real path for directory: {$directory}");
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realDirectory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if ($file->isFile() && $file->getExtension() === 'php') {
                $filePath = $file->getRealPath();
                if ($filePath === false) {
                    continue;
                }

                $relativePath = str_replace($realDirectory, '', $filePath);
                $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
                $classPath = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);
                $className = rtrim($namespace, '\\') . '\\' . $classPath;
  
                $functionsBefore = get_defined_functions()['user'];

           
                if (self::fileContainsClass($filePath, $className)) {
                    if (class_exists($className, true)) {
                        self::registerClass($className);
                    }
                } else {
                    self::loadFunctionFile($filePath);
                }

                $functionsAfter = get_defined_functions()['user'];
                $newFunctions = array_diff($functionsAfter, $functionsBefore);
                
                foreach ($newFunctions as $functionName) {
                    self::registerFunction($functionName);
                }
            }
        }

        self::$discovered = true;
    }

    /**
     * Check if a file contains a class definition (simple heuristic)
     */
    private static function fileContainsClass(string $filePath, string $expectedClassName): bool
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        $shortClassName = substr($expectedClassName, strrpos($expectedClassName, '\\') + 1);
        return preg_match('/^(abstract\s+|final\s+)?class\s+' . preg_quote($shortClassName, '/') . '\s/m', $content) === 1;
    }

    /**
     * Load a file containing functions
     */
    private static function loadFunctionFile(string $filePath): void
    {
        if (in_array($filePath, self::$loadedFiles, true)) {
            return;
        }

        require_once $filePath;
        self::$loadedFiles[] = $filePath;
    }

    /**
     * Register a standalone function as a listener
     */
    private static function registerFunction(string $functionName): void
    {
        if (in_array($functionName, self::$registeredFunctions, true)) {
            return;
        }

        try {
            $reflection = new ReflectionFunction($functionName);
            $attributes = $reflection->getAttributes(Listener::class);

            if ($attributes === []) {
                return;
            }

            foreach ($attributes as $attribute) {
                /** @var Listener $listener */
                $listener = $attribute->newInstance();

                if (is_callable($functionName)) {
                    Event::on($listener->event, $functionName);
                    self::$registeredFunctions[] = $functionName;
                }
            }
        } catch (\ReflectionException $e) {
            // Skip functions that can't be reflected
        }
    }

    /**
     * @param class-string $className
     */
    private static function registerClass(string $className): void
    {
        try {
            $reflection = new ReflectionClass($className);
            
            self::registerClassListeners($reflection);
            self::registerMethodListeners($reflection);
            
        } catch (\ReflectionException $e) {
            // Skip classes that can't be reflected
        }
    }

    /**
     * Register class-level listener attributes
     */
    private static function registerClassListeners(ReflectionClass $reflection): void
    {
        $attributes = $reflection->getAttributes(Listener::class);

        if ($attributes === []) {
            return;
        }

        $instance = $reflection->newInstance();

        foreach ($attributes as $attribute) {
            /** @var Listener $listener */
            $listener = $attribute->newInstance();

            $method = $listener->method;

            if (!method_exists($instance, $method)) {
                throw new \RuntimeException(
                    "Method {$method} does not exist on {$reflection->getName()}"
                );
            }

            $callable = [$instance, $method];
            
            if (is_callable($callable)) {
                Event::on($listener->event, $callable);
            }
        }
    }

    /**
     * Register method-level listener attributes
     */
    private static function registerMethodListeners(ReflectionClass $reflection): void
    {
        $instance = null;

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Listener::class);

            if ($attributes === []) {
                continue;
            }

            if ($instance === null) {
                $instance = $reflection->newInstance();
            }

            foreach ($attributes as $attribute) {
                /** @var Listener $listener */
                $listener = $attribute->newInstance();

                $callable = [$instance, $method->getName()];
                
                if (is_callable($callable)) {
                    Event::on($listener->event, $callable);
                }
            }
        }
    }

    /**
     * Reset discovery state (useful for testing)
     */
    public static function reset(): void
    {
        self::$discovered = false;
        self::$registeredFunctions = [];
        self::$loadedFiles = [];
    }
}