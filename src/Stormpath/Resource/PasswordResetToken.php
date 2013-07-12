<?php

namespace Stormpath\Resource;

use Stormpath\Resource\AbstractResource;
use Stormpath\Service\StormpathService;
use Stormpath\Resource\Account;

class PasswordResetToken extends AbstractResource
{
    /**
     * Login attempts cannot be lazy loaded or loaded directly
     */
    protected $_url = '';

    protected $email;
    protected $account;

    public function setEmail($email)
    {
        $this->_load();
        $this->email = $value;
        return $this;
    }

    public function getEmail()
    {
        $this->_load();
        return $this->email;
    }

    public function setAccount(Account $value)
    {
        $this->_load();
        $this->account = $value;
        return $this;
    }

    public function getAccount()
    {
        $this->_load();
        return $this->account;
    }

    public function exchangeArray($data)
    {
        $this->setHref(isset($data['href']) ? $data['href']: null);
        $this->setType(isset($data['email']) ? $data['email']: null);

        $account = new Account;
        $account->_lazy($this->getResourceManager(), substr($data['account']['href'], strrpos($data['account']['href'], '/') + 1));
        $this->setAccount($account);
    }

    public function getArrayCopy()
    {
        $this->_load();

        return array(
            'href' => $this->getHref(),
            'type' => $this->getType(),
        );
    }
}