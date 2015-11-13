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
    protected $accountId;
    protected $timeout;

    /**
     * @param String $account_id      The B2 account id for the account
     * @param String $application_key B2 application key for the account.
     * @param int    $timeout         Curl timeout.
     */
    public function __construct($account_id, $application_key, $timeout = 2000)
    {
        $this->url = 'https://api.backblaze.com/b2api/v1/';
        $this->accountId = $account_id;
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
     * Creates a new bucket. A bucket belongs to the account used to create it.
     * Buckets can be named. The name must be globally unique. No account can use a bucket with the same name.
     * Buckets are assigned a unique bucketId which is used when uploading, downloading, or deleting files.
     *
     * @param String $URL    Obtained from b2AuthorizeAccount call
     * @param String $token  Obtained from b2AuthorizeAccount call
     * @param String $name   6 char min, 50 char max: letters, digits, - and _
     * @param bool   $public
     *
     * @return array Example:
     *               {
     *               "bucketId" : "4a48fe8875c6214145260818",
     *               "accountId" : "010203040506",
     *               "bucketName" : "any_name_you_pick",
     *               "bucketType" : "allPrivate"
     *               }
     *
     * @throws B2Exception
     */
    public function b2CreateBucket($URL, $token, $name, $public = false)
    {
        if (count_chars($name) < 5 && count_chars($name) > 50) {
            throw new B2Exception('Invalid bucket name');
        }
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = json_encode(array(
            'accountId' => $this->accountId,
            'bucketName' => $name,
            'bucketType' => $public ? 'allPublic' : 'allPrivate',
        ));
        $response = $browser->post($URL.'/b2api/v1/b2_create_bucket', $this->getHeaders($token), $payload);

        return json_decode($response->getContent(), true);
    }

    /**
     * Deletes the bucket specified. Only buckets that contain no version of any files can be deleted.
     *
     * @param String $URL      Obtained from b2AuthorizeAccount call
     * @param String $token    Obtained from b2AuthorizeAccount call
     * @param String $bucketId The ID of the bucket you want to delete
     *
     * @return array Example:
     *               {
     *               "bucketId" : "4a48fe8875c6214145260818",
     *               "accountId" : "010203040506",
     *               "bucketName" : "any_name_you_pick",
     *               "bucketType" : "allPrivate"
     *               }
     *
     * @throws B2Exception
     */
    public function b2DeleteBucket($URL, $token, $bucketId)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = json_encode(array(
            'accountId' => $this->accountId,
            'bucketId' => $bucketId,
        ));
        $response = $browser->post($URL.'/b2api/v1/b2_delete_bucket', $this->getHeaders($token), $payload);

        return json_decode($response->getContent(), true);
    }

    /**
     * Deletes one version of a file from B2.
     * If the version you delete is the latest version, and there are older versions,
     * then the most recent older version will become the current version,
     * and be the one that you'll get when downloading by name.
     *
     * @param String $URL      Obtained from b2AuthorizeAccount call
     * @param String $token    Obtained from b2AuthorizeAccount call
     * @param String $fileId   The ID of the file you want to delete
     * @param String $fileName The file name of the file you want to delete
     *
     * @return array Example:
     *               {
     *               "fileId" : "4_h4a48fe8875c6214145260818_f000000000000472a_d20140104_m032022_c001_v0000123_t0104",
     *               "fileName" : "typing_test.txt"
     *               }
     *
     * @throws B2Exception
     */
    public function b2DeleteFileVersion($URL, $token, $fileId, $fileName)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = json_encode(array(
            'fileId' => $fileId,
            'fileName' => $fileName,
        ));
        $response = $browser->post($URL.'/b2api/v1/b2_delete_file_version', $this->getHeaders($token), $payload);

        return json_decode($response->getContent(), true);
    }

    /**
     * Downloads one file from B2.
     * If the version you delete is the latest version, and there are older versions,
     * then the most recent older version will become the current version,
     * and be the one that you'll get when downloading by name.
     *
     * @param String $downloadURL Obtained from b2GetUploadURL call
     * @param String $fileId      The ID of the file you want to delete
     * @param bool   $download    Return URL or download directly
     *
     * @return mixed|String
     *
     * @throws B2Exception
     */
    public function b2DownloadFileById($downloadURL, $fileId, $download = false)
    {
        $url = $downloadURL.'/b2api/v1/b2_download_file_by_id?fileId='.urlencode($fileId);
        if (!$download) {
            return $url;
        }
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);

        return $browser->get($url)->getContent();
    }

    /**
     * Downloads one file by providing the name of the bucket and the name of the file.
     *
     *
     * @param String $downloadURL Obtained from b2GetUploadURL call
     * @param string $bucketName  The bucket name of file
     * @param string $fileName    The name of the file, in percent-encoded UTF-8
     * @param string $token       Can be null if your bucket is public otherwise An upload authorization token from authorization request
     *
     * @return mixed
     *
     * @throws B2Exception
     */
    public function b2DownloadFileByName($downloadURL, $bucketName, $fileName, $token = null)
    {
        $uri = $downloadURL.'/file/'.$bucketName.'/'.urlencode($fileName);
        $curl = $this->prepareCurl();

        if (is_null($token)) {
            $curl->setVerifyPeer(false); // ensure it.
        }
        $browser = new Browser($curl);
        if (is_null($token)) {
            $headers = [];
        } else {
            $headers = $this->getHeaders($token);
        }
        $response = $browser->get($uri, $headers);

        return $response->getContent();
    }

    /**
     * Gets information about one file stored in B2.
     *
     * @param String $URL    Obtained from b2_authorize_account call
     * @param string $token  Obtained from b2_authorize_account call
     * @param string $fileId The ID of the file, in percent-encoded UTF-8
     *
     * @return array
     *               {
     *               "accountId": "7eecc42b9675",
     *               "bucketId": "e73ede9c9c8412db49f60715",
     *               "contentLength": 122573,
     *               "contentSha1": "a01a21253a07fb08a354acd30f3a6f32abb76821",
     *               "contentType": "image/jpeg",
     *               "fileId": "4_ze73ede9c9c8412db49f60715_f100b4e93fbae6252_d20150824_m224353_c900_v8881000_t0001",
     *               "fileInfo": {},
     *               "fileName": "akitty.jpg"
     *               }
     *
     * @throws B2Exception
     */
    public function b2GetFileInfo($URL, $token, $fileId)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = json_encode(array(
            'fileId' => $fileId,
        ));
        $response = $browser->post($URL.'/b2api/v1/b2_get_file_info', $this->getHeaders($token), $payload);

        return $response->getContent();
    }

    /**
     * Hides a file so that downloading by name will not find the file,
     * but previous versions of the file are still stored.
     * See File Versions about what it means to hide a file.
     *
     * @param String $URL      Obtained from b2_authorize_account call
     * @param string $token    Obtained from b2_authorize_account call
     * @param string $bucketId The ID of the bucket
     * @param string $fileName The name of the file, in percent-encoded UTF-8
     *
     * @return array
     *               {
     *               "action" : "hide",
     *               "fileId" : "4_h4a48fe8875c6214145260818_f000000000000472a_d20140104_m032022_c001_v0000123_t0104",
     *               "fileName" : "typing_test.txt",
     *               "uploadTimestamp" : 1437815673000
     *               }
     *
     * @throws B2Exception
     */
    public function b2HideFile($URL, $token, $bucketId, $fileName)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = json_encode(array(
            'bucketId' => $bucketId,
            'fileName' => $fileName,
        ));
        $response = $browser->post($URL.'/b2api/v1/b2_hide_file', $this->getHeaders($token), $payload);

        return $response->getContent();
    }

    /**
     * Lists buckets associated with an account, in alphabetical order by bucket ID.
     *
     * @param String $URL   Obtained from b2_authorize_account call
     * @param string $token Obtained from b2_authorize_account call
     *
     * @return array {
     *               "buckets": [
     *               {
     *               "bucketId": "4a48fe8875c6214145260818",
     *               "accountId": "30f20426f0b1",
     *               "bucketName" : "Kitten Videos",
     *               "bucketType": "allPrivate"
     *               },
     *               {
     *               "bucketId" : "5b232e8875c6214145260818",
     *               "accountId": "30f20426f0b1",
     *               "bucketName": "Puppy Videos",
     *               "bucketType": "allPublic"
     *               } (...) ]
     *               }
     *
     * @throws B2Exception
     */
    public function b2ListBuckets($URL, $token)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = json_encode(array(
            'accountId' => $this->accountId,
        ));
        $response = $browser->post($URL.'/b2api/v1/b2_list_buckets', $this->getHeaders($token), $payload);

        return $response->getContent();
    }

    /**
     * Lists the names of all files in a bucket, starting at a given name.
     * This call returns at most 1000 file names, but it can be called repeatedly
     * to scan through all of the file names in a bucket. Each time you call,
     * it returns an "endFileName" that can be used as the starting point for the next call.
     *
     *
     * @param String      $URL           Obtained from b2_authorize_account call
     * @param string      $token         Obtained from b2_authorize_account call
     * @param string      $bucketId      The ID of the bucket
     * @param null|String $startFileName The first file name to return.
     * @param int         $maxFileCount  The maximum number of files to return from this call. The default value is 100, and the maximum allowed is 1000.
     *
     * @return array {
     *               "files": [
     *               {
     *               "action": "upload",
     *               "fileId": "4_z27c88f1d182b150646ff0b16_f1004ba650fe24e6b_d20150809_m012853_c100_v0009990_t0000",
     *               "fileName": "files/hello.txt",
     *               "size": 6,
     *               "uploadTimestamp": 1439083733000
     *               },
     *               {
     *               "action": "upload",
     *               "fileId": "4_z27c88f1d182b150646ff0b16_f1004ba650fe24e6c_d20150809_m012854_c100_v0009990_t0000",
     *               "fileName": "files/world.txt",
     *               "size": 6,
     *               "uploadTimestamp": 1439083734000
     *               }
     *               ],
     *               "nextFileName": null
     *               }
     *               nextFileName: What to pass in to startFileName for the next search.
     *
     * @throws B2Exception
     */
    public function b2ListFileNames($URL, $token, $bucketId, $startFileName = null, $maxFileCount = 100)
    {
        if ($maxFileCount > 1000) {
            throw new B2Exception('The maximum allowed is 1000');
        }
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'bucketId' => $bucketId,
            'maxFileCount' => $maxFileCount,
        );
        if (!is_null($startFileName)) {
            $payload['startFileName'] = $startFileName;
        }
        $response = $browser->post($URL.'/b2api/v1/b2_list_file_names', $this->getHeaders($token), json_encode($payload));

        return $response->getContent();
    }

    /**
     * Lists all of the versions of all of the files contained in one bucket,
     * in alphabetical order by file name, and by reverse of date/time uploaded
     * for versions of files with the same name.
     *
     *
     * @param String      $URL           Obtained from b2_authorize_account call
     * @param string      $token         Obtained from b2_authorize_account call
     * @param string      $bucketId      The ID of the bucket
     * @param null|String $startFileId   The first file id to return.
     * @param null|String $startFileName The first file name to return.
     * @param int         $maxFileCount  The maximum number of files to return from this call. The default value is 100, and the maximum allowed is 1000.
     *
     * @return array {
     *               "files": [
     *               {
     *               "action": "upload",
     *               "fileId": "4_z27c88f1d182b150646ff0b16_f100920ddab886245_d20150809_m232316_c100_v0009990_t0003",
     *               "fileName": "files/hello.txt",
     *               "size": 6,
     *               "uploadTimestamp": 1439162596000
     *               },
     *               {
     *               "action": "hide",
     *               "fileId": "4_z27c88f1d182b150646ff0b16_f100920ddab886247_d20150809_m232323_c100_v0009990_t0005",
     *               "fileName": "files/world.txt",
     *               "size": 0,
     *               "uploadTimestamp": 1439162603000
     *               },
     *               {
     *               "action": "upload",
     *               "fileId": "4_z27c88f1d182b150646ff0b16_f100920ddab886246_d20150809_m232316_c100_v0009990_t0003",
     *               "fileName": "files/world.txt",
     *               "size": 6,
     *               "uploadTimestamp": 1439162596000
     *               }
     *               ],
     *               "nextFileId": "4_z27c88f1d182b150646ff0b16_f100920ddab886247_d20150809_m232316_c100_v0009990_t0003",
     *               "nextFileName": "files/world.txt"
     *               }
     *
     * nextFileId: What to pass in to startFileId for the next search.
     * nextFileName: What to pass in to startFileName for the next search.
     *
     * @throws B2Exception
     */
    public function b2ListFileVersions($URL, $token, $bucketId, $startFileId = null, $startFileName = null, $maxFileCount = 100)
    {
        if ($maxFileCount > 1000) {
            throw new B2Exception('The maximum allowed is 1000');
        }
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'bucketId' => $bucketId,
            'maxFileCount' => $maxFileCount,
        );
        if (!is_null($startFileId)) {
            $payload['startFileId'] = $startFileId;
        }
        if (!is_null($startFileName)) {
            $payload['startFileName'] = $startFileName;
        }
        $response = $browser->post($URL.'/b2api/v1/b2_list_file_versions', $this->getHeaders($token), json_encode($payload));

        return $response->getContent();
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

    /**
     * Update an existing bucket.
     *
     * Modifies the bucketType of an existing bucket. Can be used to allow everyone to download the contents
     * of the bucket without providing any authorization,
     * or to prevent anyone from downloading the contents of the bucket without providing a bucket auth token.
     *
     * @param String $URL      Obtained from b2_authorize_account call
     * @param string $token    Obtained from b2_authorize_account call
     * @param string $bucketId The ID of the bucket
     * @param bool   $public   TRUE for public, FALSE for private.
     *
     * @return array
     *               {
     *               "bucketId" : "4a48fe8875c6214145260818",
     *               "accountId" : "30f20426f0b1",
     *               "bucketName" : "Kitten Videos",
     *               "bucketType" : "allPrivate"
     *               }
     *
     * @throws B2Exception
     */
    public function b2UpdateBucket($URL, $token, $bucketId, $public)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = json_encode(array(
            'accountId' => $this->accountId,
            'bucketId' => $bucketId,
            'bucketType' => $public ? 'allPublic' : 'allPrivate',
        ));
        $response = $browser->post($URL.'/b2api/v1/b2_update_bucket', $this->getHeaders($token), $payload);

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
