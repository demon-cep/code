<?php

namespace Lab4u\Mobile\Answer;


use Lab4u\Mobile\Abstracts\Answer;

class NeedRefreshTokenAnswer extends Answer
{

    public function getResult()
    {
        \CHTTP::SetStatus("426 Upgrade Required");

        return [
            "status" => "ERROR",
            "answer_msg" => "Токен устарел. Нужно его обновить",
            "answer_code" => "need_refresh_token",
            "data" => null
        ];
    }
}