<?php


namespace Lab4u\Mobile;


use Lab4u\Mobile\Abstracts\Method;

class FreeMethod extends Method
{
    const ACCESS = 'FREE';

    public function __construct(array $arFields)
    {
        parent::__construct($arFields);
    }
}