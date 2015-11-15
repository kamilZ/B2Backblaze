<?php

namespace B2Backblaze;

use Buzz\Message\Response;

/**
 * B2Response.
 *
 * @author Kamil Zabdyr <kamilzabdyr@gmail.com>
 */
class B2Response
{
    protected $response;
    protected $data;

    /**
     * @param Response $response
     * @param bool $decode
     */
    public function __construct(Response $response, $decode = true)
    {
        $this->response = $response;
        if ($decode) {
            $this->data = json_decode($this->response->getContent(), true);
        } else {
            $this->data = null;
        }
    }

    /**
     * Returns the decoded response.
     *
     * @return array|null
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Returns the value of key in data array.
     *
     * @param String $key
     *
     * @return null|mixed|array
     */
    public function get($key)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        return null;
    }

    /**
     * Returns the decoded response.
     *
     * @return mixed
     */
    public function getRawContent()
    {
        return $this->response->getContent();
    }

    /**
     * Returns the value of a header.
     *
     * @param String $key
     *
     * @return array|null|string
     */
    public function getHeader($key)
    {
        return $this->response->getHeader($key);
    }

    /**
     * Returns the response headers.
     *
     * @return array|null
     */
    public function getHeaders()
    {
        return $this->response->getHeaders();
    }

    /**
     * Returns true if the response status code is 200.
     *
     * @param bool $errorCheck
     *
     * @return bool
     */
    public function isOk($errorCheck = true)
    {
        return $this->response->isOk() && ($errorCheck ? (!array_key_exists('code', $this->data)) : true);
    }
}
