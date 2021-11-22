<?php

namespace Lab4u\Mobile\Answer;


use Lab4u\Mobile\Abstracts\Answer;

class TokenNotPassedAnswer extends Answer
{

    public function getResult()
    {
        \CHTTP::SetStatus("400 Bad Request");

        return [
            "status" => "ERROR",
            "answer_msg" => "Токен не передан",
            "answer_code" => "auth_refresh_token",
            "data" => null
        ];
    }
}