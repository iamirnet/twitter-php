<?php

namespace iAmirNet\Twitter\OAuth;

use Exception;

class Request
{
    // for debug purposes
    public $base_string;
    public static $version = '1.0';
    public static $POST_INPUT = 'php://input';
    protected $parameters;
    protected $http_method;
    protected $http_url;


    public function __construct(string $http_method, string $http_url, array $parameters = null)
    {
        $parameters = $parameters ?: [];
        $parameters = array_merge(Util::parse_parameters((string) parse_url($http_url, PHP_URL_QUERY)), $parameters);
        $this->parameters = $parameters;
        $this->http_method = $http_method;
        $this->http_url = $http_url;
    }


    /**
     * attempt to build up a request from what was passed to the server
     */
    public static function from_request(string $http_method = null, string $http_url = null, array $parameters = null): self
    {
        $scheme = (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on')
            ? 'http'
            : 'https';
        $http_url = ($http_url) ? $http_url : $scheme .
            '://' . $_SERVER['HTTP_HOST'] .
            ':' .
            $_SERVER['SERVER_PORT'] .
            $_SERVER['REQUEST_URI'];
        $http_method = ($http_method) ? $http_method : $_SERVER['REQUEST_METHOD'];

        // We weren't handed any parameters, so let's find the ones relevant to
        // this request.
        // If you run XML-RPC or similar you should use this to provide your own
        // parsed parameter-list
        if (!$parameters) {
            // Find request headers
            $request_headers = Util::get_headers();

            // Parse the query-string to find GET parameters
            $parameters = Util::parse_parameters($_SERVER['QUERY_STRING']);

            // It's a POST request of the proper content-type, so parse POST
            // parameters and add those overriding any duplicates from GET
            if ($http_method == 'POST'
                && isset($request_headers['Content-Type'])
                && strstr($request_headers['Content-Type'], 'application/x-www-form-urlencoded')
            ) {
                $post_data = Util::parse_parameters(
                    file_get_contents(self::$POST_INPUT)
                );
                $parameters = array_merge($parameters, $post_data);
            }

            // We have a Authorization-header with OAuth data. Parse the header
            // and add those overriding any duplicates from GET or POST
            if (isset($request_headers['Authorization']) && substr($request_headers['Authorization'], 0, 6) == 'OAuth ') {
                $header_parameters = Util::split_header(
                    $request_headers['Authorization']
                );
                $parameters = array_merge($parameters, $header_parameters);
            }
        }

        return new self($http_method, $http_url, $parameters);
    }


    /**
     * pretty much a helper function to set up the request
     */
    public static function from_consumer_and_token(Consumer $consumer, ?Token $token, string $http_method, string $http_url, array $parameters = null): self
    {
        $parameters = $parameters ?: [];
        $defaults = [
            'oauth_version' => self::$version,
            'oauth_nonce' => self::generate_nonce(),
            'oauth_timestamp' => self::generate_timestamp(),
            'oauth_consumer_key' => $consumer->key,
        ];
        if ($token) {
            $defaults['oauth_token'] = $token->key;
        }

        $parameters = array_merge($defaults, $parameters);

        return new self($http_method, $http_url, $parameters);
    }


    public function set_parameter(string $name, $value, bool $allow_duplicates = true): void
    {
        if ($allow_duplicates && isset($this->parameters[$name])) {
            // We have already added parameter(s) with this name, so add to the list
            if (is_scalar($this->parameters[$name])) {
                // This is the first duplicate, so transform scalar (string)
                // into an array so we can add the duplicates
                $this->parameters[$name] = [$this->parameters[$name]];
            }

            $this->parameters[$name][] = $value;
        } else {
            $this->parameters[$name] = $value;
        }
    }


    public function get_parameter(string $name)
    {
        return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
    }


    public function get_parameters(): array
    {
        return $this->parameters;
    }


    public function unset_parameter(string $name): void
    {
        unset($this->parameters[$name]);
    }


    /**
     * The request parameters, sorted and concatenated into a normalized string.
     */
    public function get_signable_parameters(): string
    {
        // Grab all parameters
        $params = $this->parameters;

        // Remove oauth_signature if present
        // Ref: Spec: 9.1.1 ("The oauth_signature parameter MUST be excluded.")
        if (isset($params['oauth_signature'])) {
            unset($params['oauth_signature']);
        }

        return Util::build_http_query($params);
    }


    /**
     * Returns the base string of this request
     *
     * The base string defined as the method, the url
     * and the parameters (normalized), each urlencoded
     * and the concated with &.
     */
    public function get_signature_base_string(): string
    {
        $parts = [
            $this->get_normalized_http_method(),
            $this->get_normalized_http_url(),
            $this->get_signable_parameters(),
        ];

        $parts = Util::urlencode_rfc3986($parts);

        return implode('&', $parts);
    }


    /**
     * just uppercases the http method
     */
    public function get_normalized_http_method(): string
    {
        return strtoupper($this->http_method);
    }


    /**
     * parses the url and rebuilds it to be
     * scheme://host/path
     */
    public function get_normalized_http_url(): string
    {
        $parts = parse_url($this->http_url);

        $scheme = (isset($parts['scheme'])) ? $parts['scheme'] : 'http';
        $port = (isset($parts['port'])) ? $parts['port'] : (($scheme == 'https') ? '443' : '80');
        $host = (isset($parts['host'])) ? $parts['host'] : '';
        $path = (isset($parts['path'])) ? $parts['path'] : '';

        if (($scheme == 'https' && $port != '443')
            || ($scheme == 'http' && $port != '80')) {
            $host = "$host:$port";
        }
        return "$scheme://$host$path";
    }


    /**
     * builds a url usable for a GET request
     */
    public function to_url(): string
    {
        $post_data = $this->to_postdata();
        $out = $this->get_normalized_http_url();
        if ($post_data) {
            $out .= '?' . $post_data;
        }
        return $out;
    }


    /**
     * builds the data one would send in a POST request
     */
    public function to_postdata(): string
    {
        return Util::build_http_query($this->parameters);
    }


    /**
     * builds the Authorization: header
     */
    public function to_header(string $realm = null): string
    {
        $first = true;
        if ($realm) {
            $out = 'Authorization: OAuth realm="' . Util::urlencode_rfc3986($realm) . '"';
            $first = false;
        } else {
            $out = 'Authorization: OAuth';
        }

        $total = [];
        foreach ($this->parameters as $k => $v) {
            if (substr($k, 0, 5) != 'oauth') {
                continue;
            }
            if (is_array($v)) {
                throw new Exception('Arrays not supported in headers');
            }
            $out .= $first ? ' ' : ',';
            $out .= Util::urlencode_rfc3986($k) . '="' . Util::urlencode_rfc3986($v) . '"';
            $first = false;
        }
        return $out;
    }


    public function __toString(): string
    {
        return $this->to_url();
    }


    public function sign_request(SignatureMethod $signature_method, Consumer $consumer, ?Token $token)
    {
        $this->set_parameter(
            'oauth_signature_method',
            $signature_method->get_name(),
            false
        );
        $signature = $this->build_signature($signature_method, $consumer, $token);
        $this->set_parameter('oauth_signature', $signature, false);
    }


    public function build_signature(SignatureMethod $signature_method, Consumer $consumer, ?Token $token)
    {
        $signature = $signature_method->build_signature($this, $consumer, $token);
        return $signature;
    }


    /**
     * util function: current timestamp
     */
    private static function generate_timestamp(): int
    {
        return time();
    }


    /**
     * util function: current nonce
     */
    private static function generate_nonce(): string
    {
        $mt = microtime();
        $rand = mt_rand();

        return md5($mt . $rand); // md5s look nicer than numbers
    }
}
