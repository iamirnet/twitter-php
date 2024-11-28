<?php


namespace iAmirNet\Twitter\OAuth;

class Token
{
    // access tokens and request tokens
    public $key;
    public $secret;


    /**
     * key = the token
     * secret = the token secret
     */
    public function __construct(string $key, string $secret)
    {
        $this->key = $key;
        $this->secret = $secret;
    }


    /**
     * generates the basic string serialization of a token that a server
     * would respond to request_token and access_token calls with
     */
    public function to_string(): string
    {
        return 'oauth_token=' .
            Util::urlencode_rfc3986($this->key) .
            '&oauth_token_secret=' .
            Util::urlencode_rfc3986($this->secret);
    }


    public function __toString(): string
    {
        return $this->to_string();
    }
}
