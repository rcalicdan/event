<?php

declare(strict_types=1);

namespace Rcalicdan\Event;

use Rcalicdan\Event\Attributes\Listener;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

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
            $attributes = $reflection->getAttributes(Listener::class);

            if ($attributes === []) {
                return;
            }

            $instance = new $className();

            foreach ($attributes as $attribute) {
                /** @var Listener $listener */
                $listener = $attribute->newInstance();

                $method = $listener->method;

                if (!method_exists($instance, $method)) {
                    throw new \RuntimeException(
                        "Method {$method} does not exist on {$className}"
                    );
                }

                $callable = [$instance, $method];
                
                if (is_callable($callable)) {
                    Event::on($listener->event, $callable);
                }
            }
        } catch (\ReflectionException $e) {
            // Skip classes that can't be reflected
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
