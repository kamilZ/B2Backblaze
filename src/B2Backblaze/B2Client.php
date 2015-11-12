<?php

namespace B2Backblaze;

use Buzz\Browser;
use Buzz\Client\Curl;

/**
 * B2Client.
 *
 * @author Kamil Zabdyr <kamilzabdyr@gmail.com>
 */
class B2Client
{
    protected $url;
    protected $credentials;
    protected $timeout;

    /**
     * @param String $account_id      The B2 account id for the account
     * @param String $application_key B2 application key for the account.
     * @param int    $timeout         Curl timeout.
     */
    public function __construct($account_id, $application_key, $timeout = 2000)
    {
        $this->url = 'https://api.backblaze.com/b2api/v1/';
        $this->credentials = base64_encode($account_id.':'.$application_key);
        $this->timeout = $timeout;
    }

    /**
     * b2_authorize_account
     * Used to log in to the B2 API. Returns an authorization token that can be used for account-level operations, and a URL that should be used as the base URL for subsequent API calls.
     *
     * @return array Example:
     *               {
     *               "accountId": "YOUR_ACCOUNT_ID",
     *               "apiUrl": "https://api900.backblaze.com",
     *               "authorizationToken": "2_20150807002553_443e98bf57f978fa58c284f8_24d25d99772e3ba927778b39c9b0198f412d2163_acct",
     *               "downloadUrl": "https://f900.backblaze.com"
     *               }
     *               downloadUrl: The base URL to use for downloading files.
     *               authorizationToken: An authorization token to use with all calls, other than b2_authorize_account, that need an Authorization header.
     */
    public function b2AuthorizeAccount()
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
//        $payload = json_encode($payload);
        $response = $browser->get($this->url.'b2_authorize_account', array(
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
            'Authorization' => 'Basic '.$this->credentials,
        ));

        return json_decode($response->getContent(), true);
    }

    /**
     * @param $URL
     * @param $token
     * @param $backedId
     *
     * @return array Example:
     *               {
     *               "bucketId" : "4a48fe8875c6214145260818",
     *               "uploadUrl" : "https://pod-000-1005-03.backblaze.com/b2api/v1/b2_upload_file?cvt=c001_v0001005_t0027&bucket=4a48fe8875c6214145260818",
     *               "authorizationToken" : "2_20151009170037_f504a0f39a0f4e657337e624_9754dde94359bd7b8f1445c8f4cc1a231a33f714_upld"
     *               }
     *
     * @throws B2Exception When token is null
     */
    public function b2GetUploadURL($URL, $token, $backedId)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = json_encode(array(
            'bucketId' => $backedId,
        ));
        $response = $browser->post($URL.'/b2api/v1/b2_get_upload_url', $this->getHeaders($token), $payload);

        return json_decode($response->getContent(), true);
    }

    /**
     * @param string $path         File Path
     * @param string $uploadURL    Use the b2GetUploadURL operation to get this URL for uploading files
     * @param string $token        An upload authorization token, from b2_get_upload_url
     * @param string $fileName     The name of the file, in percent-encoded UTF-8
     * @param float  $lastModified The value should be a base 10 number which represents a UTC time
     * @param string $contentType  The MIME type of the content of the file, which will be returned in the Content-Type header when downloading the file. Use the Content-Type b2/x-auto to automatically set the stored Content-Type post upload. In the case where a file extension is absent or the lookup fails, the Content-Type is set to application/octet-stream.
     * @param array  $params       Up to 10 of these headers may be present. The * part of the header name is replace with the name of a custom field in the file information stored with the file, and the value is an arbitrary UTF-8 string, percent-encoded. The same info headers sent with the upload will be returned with the download.
     *
     * @return array Example:
     *               {
     *               "field": "123123123123",
     *               "fileName": "pedro.jpg",
     *               "accountId": "YOUR_ACCOUNT_ID",
     *               "bucketId" : "4a48fe8875c6214145260818",
     *               "contentLength": "100024", #in bytes
     *               "contentSha1": "f12311231a3312312312123123123123",
     *               "contentType": "image/JPG",
     *               "fileInfo": [{ ... }] #
     *               }
     *
     * @throws B2Exception
     *
     */
    public function b2UploadFile($path, $uploadURL, $token, $fileName, $lastModified = null, $contentType = 'b2/x-auto', $params = array())
    {
        $curl = $this->prepareCurl();
        $request = new Browser($curl);
        $file = file_get_contents($path);

        $headers = array(
            'Accept' => 'application/json',
            'Content-type' => $contentType,
            'Authorization' => $token,
            'X-Bz-File-Name' => urlencode($fileName),
            'X-Bz-Content-Sha1' => sha1($file),
        );
        if (!is_null($lastModified)) {
            $headers['X-Bz-Info-src_last_modified_millis'] = $lastModified;
        }
        if (count($params) > 0) {
            if (count($params) > 10) {
                throw new B2Exception('To many params (10 max)');
            }
            foreach ($params as $key => $value) {
                $headers['X-Bz-Info-'.$key] = $value;
            }
        }
        $response = $request->post($uploadURL, $headers, $file);

        return json_decode($response->getContent(), true);
    }

    private function getHeaders($token)
    {
        if (is_null($token)) {
            throw new B2Exception('Token can not be null');
        }

        return array(
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
            'Authorization' => $token,
        );
    }

    /**
     * Resolve curl configuration.
     *
     * @return Curl
     */
    private function prepareCurl()
    {
        $curl = new Curl();
        $curl->setOption(CURLOPT_USERAGENT, 'B2BackblazeClient');
        $curl->setVerifyPeer(false);
        $curl->setTimeout($this->timeout);

        return $curl;
    }
}
