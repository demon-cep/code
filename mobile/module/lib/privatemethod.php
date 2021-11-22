<?php


namespace Lab4u\Mobile;


use Lab4u\Mobile\Abstracts\Method;

class PrivateMethod extends Method
{
    const ACCESS = 'PRIVATE';

    const DEFAULT_GROUPS = [
        1, // Администраторы
    ];

    public function __construct(array $arFields)
    {
        parent::__construct($arFields);
    }
}