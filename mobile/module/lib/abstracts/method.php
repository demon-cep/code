<?php


namespace Lab4u\Mobile\Abstracts;


use Lab4u\Mobile\Answer\AccessDenyAnswer;
use Lab4u\Mobile\Answer\AuthRefreshTokenAnswer;
use Lab4u\Mobile\Answer\FreeAccessAnswer;
use Lab4u\Mobile\Answer\NeedRefreshTokenAnswer;
use Lab4u\Mobile\Answer\PrivateAccessAnswer;
use Lab4u\Mobile\Answer\TokenNotPassedAnswer;
use Lab4u\Mobile\JWT;
use Lab4u\Mobile\PrivateMethod;
use Lab4u\Mobile\Token;

abstract class Method
{
    const ACCESS = 'PRIVATE';

    const API_VERSION = '1.0';

    public $id;

    protected $method;
    protected $name;
    protected $access;
    protected $arGroups;

    public function __construct(array $arFields)
    {
        $this->method = $arFields['METHOD'];
        $this->name = $arFields['NAME'];
        $this->access = $arFields['ACCESS'];

        $this->arGroups = $arFields['ACCESS_GROUPS'];
    }

    public function getFields()
    {
        return [
            'METHOD' => $this->method,
            'VERSION' => self::API_VERSION,
            'NAME' => $this->name,
            'ACCESS' => static::ACCESS
        ];
    }

    public function isValid()
    {
        return !empty($this->method) && !empty($this->name);
    }

    public function isValidOnUpdate()
    {
        return !empty($this->method) && !empty($this->name);
    }

    public function setMethod($value)
    {
        $this->method = strval($value);
        return $this;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function setName($value)
    {
        $this->name = strval($value);
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * Полный список груп для доступа. Не переданные группы будут удалены.
     *
     * @param array $arGroups
     * @return $this
     */
    public function setAccessGroups(array $arGroups)
    {
        $this->arGroups = array_unique(array_filter($arGroups));
        return $this;
    }

    public function getAccessGroups()
    {
        return (empty($this->arGroups)) ? [] : $this->arGroups;
    }

    public function execute()
    {
        if (!$this->isNeedAuth()) {
            return new FreeAccessAnswer($this);
        }

        /*
        if ($USER->IsAuthorized() && $this->checkAccess()) {
            return new PrivateAccessAnswer($this);
        }

        if ($USER->IsAuthorized() && !$this->checkAccess()) {
            return new AccessDenyAnswer($this);
        }
        */

        $authToken = Token::getToken();

        if (is_null($authToken)) {
            return new TokenNotPassedAnswer($this);
        }

        try {
            $refreshToken = JWT::decode($authToken, JWT::JWT_SECRET_SERVER_KEY);
        } catch (\Exception $e) {
            return new AuthRefreshTokenAnswer($this);
        }

        if (time() > $refreshToken->EXPIRE_DATE) {
            return new NeedRefreshTokenAnswer($this);
        }

        if (!Token::authorize($refreshToken)) {
            return new AccessDenyAnswer($this);
        }

        if (!$this->checkAccess()) {
            return new AccessDenyAnswer($this);
        }

        return new PrivateAccessAnswer($this);
    }

    public function checkAccess()
    {
        global $USER;

        $arUserGroups = $USER->GetUserGroupArray();

        foreach ($arUserGroups as $iUserGroup) {
            if (in_array($iUserGroup, $this->getAccessGroups())) {
                return true;
            }
        }

        return false;
    }

    public function isNeedAuth()
    {
        return $this->access == PrivateMethod::ACCESS;
    }

}