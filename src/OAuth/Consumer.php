<?php

namespace iAmirNet\Twitter\OAuth;

class Consumer
{
    public $key;
    public $secret;


    public function __construct(string $key, string $secret)
    {
        $this->key = $key;
        $this->secret = $secret;
    }


    public function __toString(): string
    {
        return "OAuthConsumer[key=$this->key,secret=$this->secret]";
    }
}
