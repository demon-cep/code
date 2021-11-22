<?php

namespace Lab4u\Mobile\Answer;


use Lab4u\Mobile\Abstracts\Answer;

class AccessDenyAnswer extends Answer
{

    public function getResult()
    {
        \CHTTP::SetStatus("401 Unauthorized");

        return [
            "status" => "ERROR",
            "answer_msg" => "Доступ к варианту обмена закрыт",
            "answer_code" => "access_deny",
            'data' => null
        ];
    }
}