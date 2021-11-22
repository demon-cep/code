<?php

namespace Lab4u\Mobile\Method\Basket;

use Lab4u\Base\CouponBase;
use Lab4u\Helpers\StringHelper;
use Lab4u\Mobile\Abstracts\Answer;
use Lab4u\Mobile\Method\ApiMethod;
use Bitrix\Main;


class Apply implements ApiMethod
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

        $code = StringHelper::safeString($arFields['CODE']);

        /** @var \Lab4u\Sale\Coupon\CouponRepositoryInterface $couponRepository */
        $couponRepository = \Lab4u\Core\Container::find(\Lab4u\Sale\Coupon\CouponRepositoryInterface::class);

        try {
            $coupon = $couponRepository->getCoupon($code);
        } catch (\Exception $exception) {
            return [
                "status" => Answer::STATUS_ERROR,
                "answer_msg" => $exception->getMessage(),
                "answer_code" => "error_coupon_apply",
                'data' => [
                    'IS_APPLIED' => false
                ]
            ];
        }

        $isApplied = false;

        if ($coupon instanceof CouponBase) {
            $isApplied = $coupon->apply();
        }

        return [
            "status" => Answer::STATUS_SUCCESS,
            "answer_msg" => 'Применение купона',
            "answer_code" => 'coupon_apply',
            'data' => [
                'IS_APPLIED' => $isApplied
            ]
        ];
    }
}