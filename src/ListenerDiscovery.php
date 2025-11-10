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

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace($directory, '', $file->getPathname());
                $relativePath = str_replace(['/', '\\'], '\\', $relativePath);
                $relativePath = ltrim($relativePath, '\\');
                $className = $namespace . '\\' . str_replace('.php', '', $relativePath);

                if (class_exists($className)) {
                    self::registerClass($className);
                }
            }
        }

        self::$discovered = true;
    }

    /**
     * Register a specific class if it has Listener attributes
     */
    private static function registerClass(string $className): void
    {
        try {
            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(Listener::class);

            if (empty($attributes)) {
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

                Event::on($listener->event, [$instance, $method]);
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