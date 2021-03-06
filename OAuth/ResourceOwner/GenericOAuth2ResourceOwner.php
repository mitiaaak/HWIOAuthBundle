<?php

/*
 * This file is part of the HWIOAuthBundle package.
 *
 * (c) Hardware.Info <opensource@hardware.info>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace HWI\Bundle\OAuthBundle\OAuth\ResourceOwner;

use Buzz\Client\ClientInterface as HttpClientInterface;

use Symfony\Component\Security\Core\Exception\AuthenticationException,
    Symfony\Component\Security\Http\HttpUtils,
    Symfony\Component\HttpFoundation\Request;

/**
 * GenericOAuth2ResourceOwner
 *
 * @author Geoffrey Bachelet <geoffrey.bachelet@gmail.com>
 * @author Alexander <iam.asm89@gmail.com>
 */
class GenericOAuth2ResourceOwner extends AbstractResourceOwner
{
    /**
     * {@inheritDoc}
     */
    public function getUserInformation($accessToken)
    {
        $url = $this->getOption('infos_url');
        $url .= (false !== strpos($url, '?') ? '&' : '?').http_build_query(array(
            'access_token' => $accessToken
        ));

        $content = $this->doGetUserInformationRequest($url)->getContent();

        $response = $this->getUserResponse();
        $response->setResponse($content);
        $response->setResourceOwner($this);
        $response->setAccessToken($accessToken);

        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function getAuthorizationUrl($redirectUri, array $extraParameters = array())
    {
        $parameters = array_merge($extraParameters, array(
            'response_type' => 'code',
            'client_id'     => $this->getOption('client_id'),
            'scope'         => $this->getOption('scope'),
            'redirect_uri'  => $redirectUri,
        ));

        return $this->getOption('authorization_url').'?'.http_build_query($parameters);
    }

    /**
     * Retrieve an access token for a given code.
     *
     * @param Request $request         The request object from where the code is going to extracted
     * @param mixed   $redirectUri     The uri to redirect the client back to
     * @param array   $extraParameters An array of parameters to add to the url
     *
     * @return array Array containing the access token and it's 'expires_in' value,
     *               along with any other parameters returned from the authentication
     *               provider.
     *
     * @throws AuthenticationException If an OAuth error occurred or no access token is found
     */
    public function getAccessToken(Request $request, $redirectUri, array $extraParameters = array())
    {
        $parameters = array_merge($extraParameters, array(
            'code'          => $request->query->get('code'),
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->getOption('client_id'),
            'client_secret' => $this->getOption('client_secret'),
            'redirect_uri'  => $redirectUri,
        ));

        $response = $this->doGetTokenRequest($this->getOption('access_token_url'), $parameters);
        $response = $this->getResponseContent($response);

        if (isset($response['error'])) {
            throw new AuthenticationException(sprintf('OAuth error: "%s"', $response['error']));
        }

        if (!isset($response['access_token'])) {
            throw new AuthenticationException('Not a valid access token.');
        }

        return $response;
    }

    /**
     * Refresh an access token using a refresh token.
     *
     * @param  string $refreshToken    Refresh token
     * @param  array  $extraParameters An array of parameters to add to the url
     *
     * @return array Array containing the access token and it's 'expires_in' value,
     *               along with any other parameters returned from the authentication
     *               provider.
     *
     * @throws AuthenticationException If an OAuth error occurred or no access token is found
     */
    public function refreshAccessToken($refreshToken, array $extraParameters = array())
    {
        $parameters = array_merge($extraParameters, array(
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
            'client_id'     => $this->getOption('client_id'),
            'client_secret' => $this->getOption('client_secret'),
        ));

        $response = $this->doGetTokenRequest($this->getOption('access_token_url'), $parameters);
        $response = $this->getResponseContent($response);

        if (isset($response['error'])) {
            throw new AuthenticationException(sprintf('OAuth error: "%s"', $response['error']));
        }

        if (!isset($response['access_token'])) {
            throw new AuthenticationException('Not a valid access token.');
        }

        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function handles(Request $request)
    {
        return $request->query->has('code');
    }

    /**
     * {@inheritDoc}
     */
    protected function doGetTokenRequest($url, array $parameters = array())
    {
        return $this->httpRequest($url, http_build_query($parameters));
    }

    /**
     * {@inheritDoc}
     */
    protected function doGetUserInformationRequest($url, array $parameters = array())
    {
        return $this->httpRequest($url);
    }
}
