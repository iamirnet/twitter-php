<?php

namespace iAmirNet\Twitter\OAuth;

/**
 * The HMAC-SHA1 signature method uses the HMAC-SHA1 signature algorithm as defined in [RFC2104]
 * where the Signature Base String is the text and the key is the concatenated values (each first
 * encoded per Parameter Encoding) of the Consumer Secret and Token Secret, separated by an '&'
 * character (ASCII code 38) even if empty.
 *   - Chapter 9.2 ("HMAC-SHA1")
 */
class SignatureMethod_HMAC_SHA1 extends SignatureMethod
{
    public function get_name(): string
    {
        return 'HMAC-SHA1';
    }


    public function build_signature(Request $request, Consumer $consumer, ?Token $token): string
    {
        $base_string = $request->get_signature_base_string();
        $request->base_string = $base_string;

        $key_parts = [
            $consumer->secret,
            $token ? $token->secret : '',
        ];

        $key_parts = Util::urlencode_rfc3986($key_parts);
        $key = implode('&', $key_parts);

        return base64_encode(hash_hmac('sha1', $base_string, $key, true));
    }
}
