<?php

namespace Lab4u\Mobile\Tools;

use Bitrix\Main;
use Lab4u\Request\Session;

class ApiHelper
{

    public static function ReturnAnswerToWeb($DATA)
    {
        global $APPLICATION;
        header('Content-Type: application/json; charset=utf-8');
        $APPLICATION->RestartBuffer();
        if (!isset($DATA["data"])) {
            $DATA["data"] = "";
        }

        if (!isset($DATA["status"]) || !isset($DATA["status_msg"]) || !isset($DATA["status_code"])) {
            print Main\Web\Json::encode(
                array(
                    "status" => "ERROR",
                    "status_msg" => "Ошибка формата данных",
                    "status_code" => "answer_format_error",
                    "sessid" => bitrix_sessid(),
                    "data_type" => "string",
                    "data" => ""
                ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
            exit;
        }

        print Main\Web\Json::encode(
            array(
                "status" => $DATA["status"],
                "status_msg" => $DATA["status_msg"],
                "status_code" => $DATA["status_code"],
                "sessid" => bitrix_sessid(),
                "data_type" => gettype($DATA["data"]),
                "data" => $DATA["data"]
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
        exit;
    }

    public static function ReturnAnswerToAPP($DATA)
    {
        global $APPLICATION;

        $response = Main\Context::getCurrent()->getResponse();

        try {
            $response->addHeader(Session::MOBILE_NAME, session_id());
        } catch (Main\ArgumentNullException $e) {
        } catch (Main\ArgumentOutOfRangeException $e) {
        }

        header('Content-Type: application/json; charset=utf-8');


        if (API_DEBUG != 'Y') {
            $APPLICATION->RestartBuffer();
        }

        if (!isset($DATA["status"]) || !isset($DATA["answer_msg"]) || !isset($DATA["answer_code"])) {
            print Main\Web\Json::encode(
                array(
                    "status" => "ERROR",
                    "answer_msg" => "Ошибка формата данных",
                    "answer_code" => "answer_format_error",
                    "api_mode" => '',
                    "api_version" => '1.0',
                    "sessid" => bitrix_sessid(),
                    "data_type" => "string",
                    "data" => ""
                ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
            exit;
        }

        print Main\Web\Json::encode(
            array(
                "status" => $DATA["status"],
                "answer_msg" => $DATA["answer_msg"],
                "answer_code" => $DATA["answer_code"],
                "api_mode" => '',
                "api_version" => '1.0',
                "sessid" => bitrix_sessid(),
                "data_type" => gettype($DATA["data"]),
                "data" => $DATA["data"]
            ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
        exit;
    }

    public static function ClearResult(&$arFields)
    {
        foreach ($arFields as $key => $value) {
            if (0 === strpos($key, '~')) {
                unset($arFields[$key]);
            } else {
                if (true == is_array($value)) {
                    self::ClearResult($arFields[$key]);
                }
            }
        }
    }
}