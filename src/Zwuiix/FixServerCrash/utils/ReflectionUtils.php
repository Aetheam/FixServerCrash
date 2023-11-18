<?php

namespace Zwuiix\FixServerCrash\utils;

final class ReflectionUtils
{
    private static array $propCache = [];
    private static array $methCache = [];

    /**
     * @param string $className
     * @param object $instance
     * @param string $propertyName
     * @param $value
     * @throws \ReflectionException
     */
    public static function setProperty(string $className, object $instance, string $propertyName, $value): void
    {
        if (!isset(self::$propCache[$k = "$className - $propertyName"])) {
            $refClass = new \ReflectionClass($className);
            $refProp = $refClass->getProperty($propertyName);
            $refProp->setAccessible(true);
        } else {
            $refProp = self::$propCache[$k];
        }
        $refProp->setValue($instance, $value);
    }

    /**
     * @param string $className
     * @param object $instance
     * @param string $propertyName
     * @return mixed
     * @throws \ReflectionException
     */
    public static function getProperty(string $className, object $instance, string $propertyName): mixed
    {
        if (!isset(self::$propCache[$k = "$className - $propertyName"])) {
            $refClass = new \ReflectionClass($className);
            $refProp = $refClass->getProperty($propertyName);
            $refProp->setAccessible(true);
        } else {
            $refProp = self::$propCache[$k];
        }
        return $refProp->getValue($instance);
    }

    /**
     * @param string $className
     * @param string $methodName
     * @param mixed ...$args
     * @return mixed
     * @throws \ReflectionException
     */
    public static function invokeStatic(string $className, string $methodName, ...$args): mixed
    {
        if (!isset(self::$methCache[$k = "$className - $methodName"])) {
            $refClass = new \ReflectionClass($className);
            $refMeth = $refClass->getMethod($methodName);
            $refMeth->setAccessible(true);
        } else {
            $refMeth = self::$methCache[$k];
        }
        return $refMeth->invoke(null, ...$args);
    }

    /**
     * @param string $className
     * @param object $instance
     * @param string $methodName
     * @param mixed ...$args
     * @return mixed
     * @throws \ReflectionException
     */
    public static function invoke(string $className, object $instance, string $methodName, ...$args): mixed
    {
        if (!isset(self::$methCache[$k = "$className - $methodName"])) {
            $refClass = new \ReflectionClass($className);
            $refMeth = $refClass->getMethod($methodName);
            $refMeth->setAccessible(true);
        } else {
            $refMeth = self::$methCache[$k];
        }
        return $refMeth->invoke($instance, ...$args);
    }
}