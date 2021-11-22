<?php

namespace Lab4u\Mobile\Answer;


use Lab4u\Mobile\Abstracts\Answer;

class AuthRefreshTokenAnswer extends Answer
{

    public function getResult()
    {
        \CHTTP::SetStatus("401 Unauthorized");

        return [
            "status" => "ERROR",
            "answer_msg" => 'Ошибка декодирования токена.',
            "answer_code" => "auth_refresh_token",
            "data" => null
        ];
    }
}