<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

use Rcalicdan\Event\Attributes\Listener;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;

class ListenerDiscovery
{
    private static bool $discovered = false;

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

                if (class_exists($className, true)) {
                    self::registerClass($className);
                }
            }
        }

        self::$discovered = true;
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
    }
}