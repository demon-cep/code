<?php

namespace Lab4u\Mobile\Answer;


use Lab4u\Mobile\Abstracts\Answer;

class UnknownMethodAnswer extends Answer
{

    public function getResult()
    {
        return [
            "status" => "ERROR",
            "answer_msg" => "Неизвестный вариант обмена",
            "answer_code" => "unknown_exchange",
            'data' => null
        ];
    }
}