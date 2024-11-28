<?php

namespace iAmirNet\Twitter\OAuth;

/**
 * A class for implementing a Signature Method
 * See section 9 ("Signing Requests") in the spec
 */
abstract class SignatureMethod
{
    /**
     * Needs to return the name of the Signature Method (ie HMAC-SHA1)
     */
    abstract public function get_name(): string;


    /**
     * Build up the signature
     * NOTE: The output of this function MUST NOT be urlencoded.
     * the encoding is handled in OAuthRequest when the final
     * request is serialized
     */
    abstract public function build_signature(Request $request, Consumer $consumer, ?Token $token): string;


    /**
     * Verifies that a given signature is correct
     */
    public function check_signature(Request $request, Consumer $consumer, Token $token, string $signature): bool
    {
        $built = $this->build_signature($request, $consumer, $token);
        return $built == $signature;
    }
}
