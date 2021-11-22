<?php

namespace Lab4u\Mobile\Method\Basket;

use Lab4u\Helpers\StringHelper;
use Lab4u\Mobile\Abstracts\Answer;
use Lab4u\Mobile\Method\ApiMethod;
use Bitrix\Main;
use Bitrix\Sale\DiscountCouponsManager;


class Cancel implements ApiMethod
{

    public static function getResult()
    {
        try {
            $arFields = Main\Web\Json::decode(file_get_contents('php://input'));
        } catch (Main\ArgumentException $exception) {
            return [
                "status" => Answer::STATUS_ERROR,
                "answer_msg" => 'Ошибочный формат запроса.',
                "answer_code" => "error_format"
            ];
        }

        if (empty($arFields)) {
            return [
                "status" => Answer::STATUS_ERROR,
                "answer_msg" => "Не переданы данные.",
                "answer_code" => "empty_options_data"
            ];
        }

        $couponChanged = false;

        $code = StringHelper::safeString($arFields['CODE']);

        if (empty($code)) {
            return [
                "status" => Answer::STATUS_ERROR,
                "answer_msg" => "Не передан код купона.",
                "answer_code" => "empty_coupon_code",
                "data" => [
                    'IS_CANCELED' => false
                ]
            ];
        }

        $couponChanged = DiscountCouponsManager::delete($code) || $couponChanged;

        return [
            "status" => Answer::STATUS_SUCCESS,
            "answer_msg" => 'Отмена купона',
            "answer_code" => 'coupon_cancel',
            "data" => [
                'IS_CANCELED' => $couponChanged
            ]
        ];
    }
}