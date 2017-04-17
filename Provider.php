<?php

namespace SocialiteProviders\Twitch;

use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\InvalidStateException;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use GuzzleHttp\ClientInterface;

class Provider extends AbstractProvider implements ProviderInterface
{
  /**
   * Unique Provider Identifier.
   */
  const IDENTIFIER = 'TWITCH';

  /**
   * {@inheritdoc}
   */
  protected $scopes = ['user_read'];

  /**
   * {@inherticdoc}.
   */
  protected $scopeSeparator = ' ';

  /**
   * {@inheritdoc}
   */
  protected function getAuthUrl($state)
  {
    return $this->buildAuthUrlFromBase(
      'https://api.twitch.tv/kraken/oauth2/authorize', $state
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getTokenUrl()
  {
    return 'https://api.twitch.tv/kraken/oauth2/token';
  }

  /**
   * {@inheritdoc}
   */
  protected function getUserByToken($token)
  {
    $response = $this->getHttpClient()->get(
      'https://api.twitch.tv/kraken/user', [
      'headers' => [
        'Accept' => 'application/vnd.twitchtv.v3+json',
        'Authorization' => 'OAuth ' . $token
      ],
    ]);
    return json_decode($response->getBody()->getContents(), true);
  }

  /**
   * {@inheritdoc}
   */
  protected function mapUserToObject(array $user)
  {
    return (new User())->setRaw($user)->map([
      'id' => $user['_id'], 'nickname' => $user['display_name'],
      'name' => $user['name'], 'email' => array_get($user, 'email'),
      'avatar' => $user['logo'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function getTokenFields($code)
  {
    return array_merge(parent::getTokenFields($code), [
      'grant_type' => 'authorization_code',
    ]);
  }

  /**
   * Get the access token for the given code.
   *
   * @param  string $code
   * @return string
   */
  public function getAccessToken($code)
  {
    $postKey = (version_compare(ClientInterface::VERSION, '6') === 1) ? 'form_params' : 'body';
    $response = $this->getHttpClient()->post($this->getTokenUrl(), [
      'headers' => ['Accept' => 'application/json'],
      $postKey => $this->getTokenFields($code),
    ]);
    return json_decode($response->getBody()->getContents(), true);
  }

  /**
   * @return \SocialiteProviders\Manager\OAuth2\User
   */
  public function user()
  {
    if ($this->hasInvalidState()) {
      throw new InvalidStateException();
    }
    $response = $this->getAccessToken($this->getCode());
    $user = $this->mapUserToObject($this->getUserByToken(
      $token = $this->parseAccessToken($response)
    ));
    $this->credentialsResponseBody = $response;

    if ($user instanceof User) {
      $user->setAccessTokenResponseBody($this->credentialsResponseBody);
    }

    // @note: twitch doesn't use expiresIn 04/2017
    return $user->setToken($token)->setRefreshToken($this->parseRefreshToken($response));
  }
}