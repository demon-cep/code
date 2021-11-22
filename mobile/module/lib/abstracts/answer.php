<?php


namespace Lab4u\Mobile\Abstracts;

use Lab4u\Mobile\Method\ApiMethod;
use Lab4u\Mobile\Tools\ApiHelper;

abstract class Answer
{
    const STATUS_ERROR = 'ERROR';
    const STATUS_SUCCESS = 'SUCCESS';

    /**@var Method $method */
    protected $method;

    /**@var array $arAnswer */
    protected $arAnswer;

    public function __construct($method)
    {
        $this->method = $method;
    }

    public function send()
    {
        ApiHelper::ReturnAnswerToAPP($this->getResult());
    }

    public function get()
    {
        return $this->arAnswer;
    }

    public function getResult()
    {
        if (!isset($this->method)) {
            return [
                "status" => self::STATUS_ERROR,
                "answer_msg" => "Не найден метод обмена",
                "answer_code" => "unknown_exchange_handler"
            ];
        }

        $class = \Lab4u\Mobile\Method\Resolver::getClass($this->method);

        if ($class == false) {
            return [
                "status" => self::STATUS_ERROR,
                "answer_msg" => "Не найден обработчик обмена",
                "answer_code" => "unknown_exchange_handler"
            ];
        }

        /**@var ApiMethod $class */
        return $class::getResult();
    }
}