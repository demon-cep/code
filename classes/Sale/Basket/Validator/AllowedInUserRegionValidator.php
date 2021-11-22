<?php

namespace Lab4u\Sale\Basket\Validator;

use Lab4u\Sale\Basket;
use Lab4u\User\User;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);


class AllowedInUserRegionValidator implements Basket\Validator\AnalysisValidatorInterface
{

    /**
     * @var User
     */
    private $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * @inheritDoc
     */
    public function validate(Basket\AnalysisItemInterface $basketItem): void
    {
        $userRegionId = $this->user->getRegionId();

        $arForbiddenRegionIds = $basketItem->getExcludeRegionIds();

        if (is_array($arForbiddenRegionIds) && in_array($userRegionId, $arForbiddenRegionIds)) {
            throw new Basket\BasketException(Loc::getMessage(
                'ALLOWED_IN_USER_REGION_VALIDATOR_EXCEPTION_MESSAGE',
                ['#NAME#' => $basketItem->getName()]
            ));
        }
    }
}