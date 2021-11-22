<?php


namespace Lab4u\Mobile;


use Lab4u\Mobile\Abstracts\Method;

class FactoryMethod
{
    protected static $arMethod = [];

    protected static $apiMethod;

    /**
     * Создает экземпляр класса наследника Method.
     *
     * @param $apiMode
     * @return null | Method
     */
    public static function createMethod($apiMode)
    {
        self::$apiMethod = self::prepare($apiMode);

        if (empty(self::$apiMethod)) {
            return null;
        }

        if (self::isMethodExists() === false) {
            return null;
        }

        $className = self::getClassName();

        if (class_exists($className)) {
            return new $className(self::$arMethod[$apiMode]);
        }

        return null;
    }

    protected static function prepare($apiMode)
    {
        if(!is_string($apiMode) || empty($apiMode)) {
            return '';
        }

        return $apiMode;
    }

    public static function isMethodExists()
    {
        if (empty(self::$arMethod)) {
            self::$arMethod = MethodRepository::getList();
        }

        return array_key_exists(self::$apiMethod, self::$arMethod);
    }

    protected static function getClassName()
    {
        if (empty(self::$arMethod)) {
            self::$arMethod = MethodRepository::getList();
        }

        return '\\Lab4u\\Mobile\\' . ucfirst(strtolower(self::$arMethod[self::$apiMethod]['ACCESS'])) . 'Method';
    }
}