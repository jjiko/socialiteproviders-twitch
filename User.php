<?php

namespace SocialiteProviders\Twitch;

use SocialiteProviders\Manager\OAuth2\User as BaseUser;

class User extends BaseUser
{
  public $refreshToken;

  public function setRefreshToken($refreshToken)
  {
    $this->refreshToken = $refreshToken;

    return $this;
  }
}
