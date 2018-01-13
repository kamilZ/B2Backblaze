<?php

namespace B2Backblaze;

use Buzz\Browser;
use Buzz\Client\Curl;

/**
 * B2Client.
 *
 * @author Kamil Zabdyr <kamilzabdyr@gmail.com>
 */
class B2API
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
        $this->url = 'https://api.backblazeb2.com/b2api/v1/';
        $this->accountId = $account_id;
        $this->credentials = base64_encode($account_id.':'.$application_key);
        $this->timeout = $timeout;
    }

    /**
     * b2_authorize_account
     * Used to log in to the B2 API. Returns an authorization token that can be used for account-level operations, and a URL that should be used as the base URL for subsequent API calls.
     *
     * @return B2Response Example:
     *                    {
     *                     "accountId": "YOUR_ACCOUNT_ID",
     *                     "apiUrl": "https://api900.backblazeb2.com",
     *                     "authorizationToken": "2_20150807002553_443e98bf57f978fa58c284f8_24d25d99772e3ba927778b39c9b0198f412d2163_acct",
     *                     "downloadUrl": "https://f900.backblazeb2.com",
     *                     "minimumPartSize": 100000000
     *                    }
     *                    downloadUrl: The base URL to use for downloading files.
     *                    authorizationToken: An authorization token to use with all calls, other than b2_authorize_account, that need an Authorization header.
     */
    public function b2AuthorizeAccount()
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $response = $browser->get($this->url.'b2_authorize_account', array(
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
            'Authorization' => 'Basic '.$this->credentials,
        ));

        return new B2Response($response);
    }



    /**
     * b2_cancel_large_file
     * Cancels the upload of a large file, and deletes all of the parts that have been uploaded.
     * This will return an error if there is no active upload with the given file ID.
     *
     * @param $URL
     * @param $token
     * @param $fileId
     *
     * @return B2Response Example:
     *                    {
     *                      "accountId": "YOUR_ACCOUNT_ID",
     *                      "bucketId": "4a48fe8875c6214145260818",
     *                      "fileId": "4_za71f544e781e6891531b001a_f200ec353a2184825_d20160409_m004829_c000_v0001016_t0028",
     *                      "fileName": "bigfile.dat"
     *                    }
     *
     * @throws B2Exception When token is null
     */
    public function b2CancelLargeFile($URL, $token, $fileId)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'fileId' => $fileId,
        );
        $response = $browser->post($URL.'/b2api/v1/b2_cancel_large_file', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
    }


    /**
     * Gets an URL to use for uploading files.
     * When you upload a file to B2, you must call b2_get_upload_url first to get the URL for uploading.
     * Then, you use b2_upload_file on this URL to upload your file.
     *
     * @param $URL
     * @param $token
     * @param $backedId
     *
     * @return B2Response Example:
     *                    {
     *                    "bucketId" : "4a48fe8875c6214145260818",
     *                    "uploadUrl" : "https://pod-000-1005-03.backblaze.com/b2api/v1/b2_upload_file?cvt=c001_v0001005_t0027&bucket=4a48fe8875c6214145260818",
     *                    "authorizationToken" : "2_20151009170037_f504a0f39a0f4e657337e624_9754dde94359bd7b8f1445c8f4cc1a231a33f714_upld"
     *                    }
     *
     * @throws B2Exception When token is null
     */
    public function b2GetUploadURL($URL, $token, $backedId)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'bucketId' => $backedId,
        );
        $response = $browser->post($URL.'/b2api/v1/b2_get_upload_url', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
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
     * @param String[] $lifecycleRules The initial list (a JSON array) of lifecycle rules for this bucket.
     *
     * @return B2Response Example:
     *                    {
     *                      "accountId" : "010203040506",
     *                      "bucketId" : "4a48fe8875c6214145260818",
     *                      "bucketInfo" : {},
     *                      "bucketName" : "any-name-you-pick",
     *                      "bucketType" : "allPrivate",
     *                      "lifecycleRules" : []
     *                    }
     *
     * @throws B2Exception
     */
    public function b2CreateBucket($URL, $token, $name, $public = false, $lifecycleRules = null)
    {
        if (count_chars($name) < 5 && count_chars($name) > 50) {
            throw new B2Exception('Invalid bucket name');
        }
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'accountId' => $this->accountId,
            'bucketName' => $name,
            'bucketType' => $public ? 'allPublic' : 'allPrivate',
        );
        if($lifecycleRules != null){
            $payload['lifecycleRules'] = $lifecycleRules;
        }
        $response = $browser->post($URL.'/b2api/v1/b2_create_bucket', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
    }

    /**
     * Deletes the bucket specified. Only buckets that contain no version of any files can be deleted.
     *
     * @param String $URL      Obtained from b2AuthorizeAccount call
     * @param String $token    Obtained from b2AuthorizeAccount call
     * @param String $bucketId The ID of the bucket you want to delete
     *
     * @return B2Response Example:
     *                    {
     *                    "bucketId" : "4a48fe8875c6214145260818",
     *                    "accountId" : "010203040506",
     *                    "bucketName" : "any_name_you_pick",
     *                    "bucketType" : "allPrivate"
     *                    }
     *
     * @throws B2Exception
     */
    public function b2DeleteBucket($URL, $token, $bucketId)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'accountId' => $this->accountId,
            'bucketId' => $bucketId,
        );
        $response = $browser->post($URL.'/b2api/v1/b2_delete_bucket', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
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
     * @return B2Response Example:
     *                    {
     *                    "fileId" : "4_h4a48fe8875c6214145260818_f000000000000472a_d20140104_m032022_c001_v0000123_t0104",
     *                    "fileName" : "typing_test.txt"
     *                    }
     *
     * @throws B2Exception
     */
    public function b2DeleteFileVersion($URL, $token, $fileId, $fileName)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'fileId' => $fileId,
            'fileName' => $fileName,
        );
        $response = $browser->post($URL.'/b2api/v1/b2_delete_file_version', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
    }

    /**
     * Downloads one file from B2.
     * If the version you delete is the latest version, and there are older versions,
     * then the most recent older version will become the current version,
     * and be the one that you'll get when downloading by name.
     *
     * @param String $downloadURL  Obtained from b2GetUploadURL call
     * @param String $fileId       The ID of the file you want to delete
     * @param bool   $download     Return URL or download directly
     * @param bool   $metadataOnly TRUE for headers array, FALSE for content too
     *
     * @return B2Response
     *
     * @throws B2Exception
     */
    public function b2DownloadFileById($downloadURL, $fileId, $download = false, $metadataOnly = false)
    {
        $uri = $downloadURL.'/b2api/v1/b2_download_file_by_id?fileId='.urlencode($fileId);
        if (!$download) {
            return $uri;
        }
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $response = $metadataOnly ? $browser->head($uri) : $browser->get($uri);

        return new B2Response($response, false);
    }

    /**
     * Downloads one file by providing the name of the bucket and the name of the file.
     *
     *
     * @param String $downloadURL  Obtained from b2AuthorizeAccount call
     * @param string $bucketName   The bucket name of file
     * @param string $fileName     The name of the file, in percent-encoded UTF-8
     * @param string $token        Can be null if your bucket is public otherwise An upload authorization token from authorization request
     * @param bool   $metadataOnly True for headers array, False for content too
     *
     * @return B2Response
     *
     * @throws B2Exception
     */
    public function b2DownloadFileByName($downloadURL, $bucketName, $fileName, $token = null, $metadataOnly = false)
    {
        $uri = $downloadURL.'/file/'.$bucketName.'/'.$fileName;
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
        $response = $metadataOnly ? $browser->head($uri, $headers) : $browser->get($uri, $headers);

        return new B2Response($response, false);
    }



    /**
     * b2_get_download_authorization
     * Used to generate an authorization token that can be used to download files with the specified prefix from a
     * private B2 bucket. Returns an authorization token that can be passed to b2_download_file_by_name
     * in the Authorization header or as an Authorization parameter.
     *
     * @param String $URL      Obtained from b2AuthorizeAccount call
     * @param String $token    Obtained from b2AuthorizeAccount call
     * @param String $bucketId The ID of the bucket you want to get the authorization
     * @param String $fileNamePrefix The file name prefix of files the download authorization will allow
     * @param int $validDurationInSeconds The number of seconds the authorization is valid for
     *
     * @return B2Response Example:
     *                    {
     *                      "authorizationToken": "3_20160803004041_53982a92f631a8c7303e3266_d940c7f5ee17cd1de3758aaacf1024188bc0cd0b_000_20160804004041_0006_dnld",
     *                      "bucketId": "a71f544e781e6891531b001a",
     *                      "fileNamePrefix": "public"
     *                    }
     * @throws B2Exception
     */
    public function b2GetDownloadAuthorization($URL, $token, $bucketId, $fileNamePrefix, $validDurationInSeconds = 86400)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'bucketId' => $bucketId,
            'fileNamePrefix' => $fileNamePrefix,
            'validDurationInSeconds' => $validDurationInSeconds,
        );
        $response = $browser->post($URL.'/b2api/v1/b2_get_download_authorization', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
    }

    /**
     * b2_finish_large_file
     * Converts the parts that have been uploaded into a single B2 file.
     *
     * @param String $URL      Obtained from b2AuthorizeAccount call
     * @param String $token    Obtained from b2AuthorizeAccount call
     * @param String $fileId   The ID returned by b2_start_large_file.
     * @param String[] $partSha1Array  A JSON array of hex SHA1 checksums of the parts of the large file. This is a double-check that the right parts were uploaded in the right order, and that none were missed. Note that the part numbers start at 1, and the SHA1 of the part 1 is the first string in the array, at index 0.
     *                                 Example:  ["<sha1_of_first_part>","<sha1_of_second_part>","<sha1_of_third_part>"]
     * @return B2Response Example:
     *                    {
     *                      "accountId": "YOUR_ACOUNT_ID",
     *                      "action": "upload",
     *                      "bucketId": "e73ede9c9c8412db49f60715",
     *                      "contentLength": 208158542,
     *                      "contentSha1": "none",
     *                      "contentType": "b2/x-auto",
     *                      "fileId": "4_ze73ede9c9c8412db49f60715_f100b4e93fbae6252_d20150824_m224353_c900_v8881000_t0001",
     *                      "fileInfo": {},
     *                      "fileName": "bigfile.dat",
     *                      "uploadTimestamp": 1460162909000
     *                    }
     * @throws B2Exception
     */
    public function b2FinishLargeFile($URL, $token, $fileId, $partSha1Array)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'fileId' => $fileId,
            'partSha1Array' => $partSha1Array
        );
        $response = $browser->post($URL.'/b2api/v1/b2_finish_large_file', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
    }


    /**
     * Gets information about one file stored in B2.
     *
     * @param String $URL    Obtained from b2_authorize_account call
     * @param string $token  Obtained from b2_authorize_account call
     * @param string $fileId The ID of the file, in percent-encoded UTF-8
     *
     * @return B2Response
     *                    {
     *                    "accountId": "7eecc42b9675",
     *                    "bucketId": "e73ede9c9c8412db49f60715",
     *                    "contentLength": 122573,
     *                    "contentSha1": "a01a21253a07fb08a354acd30f3a6f32abb76821",
     *                    "contentType": "image/jpeg",
     *                    "fileId": "4_ze73ede9c9c8412db49f60715_f100b4e93fbae6252_d20150824_m224353_c900_v8881000_t0001",
     *                    "fileInfo": {},
     *                    "fileName": "akitty.jpg"
     *                    }
     *
     * @throws B2Exception
     */
    public function b2GetFileInfo($URL, $token, $fileId)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'fileId' => $fileId,
        );
        $response = $browser->post($URL.'/b2api/v1/b2_get_file_info', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
    }

    /**
     * b2_get_upload_part_url
     * Gets an URL to use for uploading parts of a large file.
     *
     * When you upload part of a large file to B2, you must call b2_get_upload_part_url first to get the URL for
     * uploading. Then, you use b2_upload_part on this URL to upload your data.
     *
     * An uploadUrl and upload authorizationToken are valid for 24 hours or until the endpoint rejects an upload,
     * see b2_upload_file. You can upload as many files to this URL as you need. To achieve faster upload speeds,
     * request multiple uploadUrls and upload your files to these different endpoints in parallel.
     *
     *
     * @param String $URL      Obtained from b2_authorize_account call
     * @param string $token    Obtained from b2_authorize_account call
     * @param string $fileId   Obtained from b2_start_large_file
     *
     * @return B2Response
     *                    {
     *                      "authorizationToken": "3_20160409004829_42b8f80ba60fb4323dcaad98_ec81302316fccc2260201cbf17813247f312cf3b_000_uplg",
     *                      "fileId": "4_ze73ede9c9c8412db49f60715_f100b4e93fbae6252_d20150824_m224353_c900_v8881000_t0001",818_f000000000000472a_d20140104_m032022_c001_v0000123_t0104",
     *                      "uploadUrl": "https://pod-000-1016-09.backblaze.com/b2api/v1/b2_upload_part/4_ze73ede9c9c8412db49f60715_f100b4e93fbae6252_d20150824_m224353_c900_v8881000_t0001/0037"
     *                    }
     *
     * @throws B2Exception
     */
    public function b2GetUploadPartURL($URL, $token, $fileId)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'fileId' => $fileId
        );
        $response = $browser->post($URL.'/b2api/v1/b2_get_upload_part_url', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
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
     * @return B2Response
     *                    {
     *                    "action" : "hide",
     *                    "fileId" : "4_h4a48fe8875c6214145260818_f000000000000472a_d20140104_m032022_c001_v0000123_t0104",
     *                    "fileName" : "typing_test.txt",
     *                    "uploadTimestamp" : 1437815673000
     *                    }
     *
     * @throws B2Exception
     */
    public function b2HideFile($URL, $token, $bucketId, $fileName)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'bucketId' => $bucketId,
            'fileName' => $fileName,
        );
        $response = $browser->post($URL.'/b2api/v1/b2_hide_file', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
    }

    /**
     * Lists buckets associated with an account, in alphabetical order by bucket ID.
     *
     * @param String $URL   Obtained from b2_authorize_account call
     * @param string $token Obtained from b2_authorize_account call
     *
     * @return B2Response {
     *                    "buckets": [
     *                    {
     *                      "accountId": "30f20426f0b1",
     *                      "bucketId": "4a48fe8875c6214145260818",
     *                      "bucketInfo": {},
     *                      "bucketName" : "Kitten-Videos",
     *                      "bucketType": "allPrivate",
     *                      "lifecycleRules": []
     *                    },
     *                     (...)
     *                    ]}
     *
     * @throws B2Exception
     */
    public function b2ListBuckets($URL, $token)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'accountId' => $this->accountId,
        );
        $response = $browser->post($URL.'/b2api/v1/b2_list_buckets', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
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
     * @param string      $prefix        Files returned will be limited to those with the given prefix. Defaults to the empty string, which matches all files.
     * @param string      $delimiter     Files returned will be limited to those within the top folder, or any one subfolder. Defaults to NULL. Folder names will also be returned. The delimiter character will be used to "break" file names into folders.
     *
     * @return B2Response {
     *                    "files": [
     *                    {
     *                    "action": "upload",
     *                    "contentLength": 6,
     *                    "fileId": "4_z27c88f1d182b150646ff0b16_f1004ba650fe24e6b_d20150809_m012853_c100_v0009990_t0000",
     *                    "fileName": "files/hello.txt",
     *                    "size": 6,
     *                    "uploadTimestamp": 1439083733000
     *                    },
     *                    {
     *                    "action": "upload",
     *                    "fileId": "4_z27c88f1d182b150646ff0b16_f1004ba650fe24e6c_d20150809_m012854_c100_v0009990_t0000",
     *                    "fileName": "files/world.txt",
     *                    "contentLength": 6,
     *                    "size": 6,
     *                    "uploadTimestamp": 1439083734000
     *                    }
     *                    ],
     *                    "nextFileName": null
     *                    }
     *                    nextFileName: What to pass in to startFileName for the next search.
     *
     * @throws B2Exception
     */
    public function b2ListFileNames($URL, $token, $bucketId, $startFileName = null, $maxFileCount = 100, $prefix = null, $delimiter = null)
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
        if(!is_null($prefix)){
            $payload["prefix"] = $prefix;
        }
        if(!is_null($delimiter)){
            $payload["delimiter"] = $delimiter;
        }
        $response = $browser->post($URL.'/b2api/v1/b2_list_file_names', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
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
     * @param string      $prefix        Files returned will be limited to those with the given prefix. Defaults to the empty string, which matches all files.
     * @param string      $delimiter     Files returned will be limited to those within the top folder, or any one subfolder. Defaults to NULL. Folder names will also be returned. The delimiter character will be used to "break" file names into folders.
     *
     * @return B2Response {
     *                    "files": [
     *                      {
     *                          "action": "upload",
     *                          "fileId": "4_z27c88f1d182b150646ff0b16_f100920ddab886245_d20150809_m232316_c100_v0009990_t0003",
     *                          "fileName": "files/hello.txt",
     *                          "contentLength": 6,
     *                          "size": 6,
     *                          "uploadTimestamp": 1439162596000
     *                      },
     *                      {
     *                          "action": "hide",
     *                          "fileId": "4_z27c88f1d182b150646ff0b16_f100920ddab886247_d20150809_m232323_c100_v0009990_t0005",
     *                          "fileName": "files/world.txt",
     *                          "contentLength": 6,
     *                          "size": 0,
     *                          "uploadTimestamp": 1439162603000
     *                      },
     *                      {
     *                          "action": "upload",
     *                          "fileId": "4_z27c88f1d182b150646ff0b16_f100920ddab886246_d20150809_m232316_c100_v0009990_t0003",
     *                          "fileName": "files/world.txt",
     *                          "contentLength": 6,
     *                          "size": 6,
     *                          "uploadTimestamp": 1439162596000
     *                      }
     *                    ],
     *                    "nextFileId": "4_z27c88f1d182b150646ff0b16_f100920ddab886247_d20150809_m232316_c100_v0009990_t0003",
     *                    "nextFileName": "files/world.txt"
     *                }
     *
     * nextFileId: What to pass in to startFileId for the next search.
     * nextFileName: What to pass in to startFileName for the next search.
     *
     * @throws B2Exception
     */
    public function b2ListFileVersions($URL, $token, $bucketId, $startFileId = null, $startFileName = null, $maxFileCount = 100, $prefix = null, $delimiter = null)
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
        if(!is_null($prefix)){
            $payload["prefix"] = $prefix;
        }
        if(!is_null($delimiter)){
            $payload["delimiter"] = $delimiter;
        }
        $response = $browser->post($URL.'/b2api/v1/b2_list_file_versions', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
    }


    /**
     * b2_list_parts
     *
     * Lists the parts that have been uploaded for a large file that has not been finished yet.
     *
     *
     * @param String      $URL              Obtained from b2_authorize_account call
     * @param string      $token            Obtained from b2_authorize_account call
     * @param string      $fileId           The ID returned by b2_start_large_file. This is the file whose parts will be listed.
     * @param null|String $startPartNumber  The first part to return. If there is a part with this number, it will be returned as the first in the list. If not, the returned list will start with the first part number after this one.
     * @param int         $maxPartCount     The maximum number of parts to return from this call. The default value is 100, and the maximum allowed is 1000.
     *
     * @return B2Response {
     *                      "nextPartNumber": null,
     *                      "parts": [
     *                          {
     *                              "contentLength": 100000000,
     *                              "contentSha1": "062685a84ab248d2488f02f6b01b948de2514ad8",
     *                              "fileId": "4_ze73ede9c9c8412db49f60715_f100b4e93fbae6252_d20150824_m224353_c900_v8881000_t0001",
     *                              "partNumber": 1,
     *                              "uploadTimestamp": 1462212185000
     *                          },
     *                          {
     *                              "contentLength": 100000000,
     *                              "contentSha1": "cf634751c3d9f6a15344f23cbf13f3fc9542addf",
     *                              "fileId": "4_ze73ede9c9c8412db49f60715_f100b4e93fbae6252_d20150824_m224353_c900_v8881000_t0001",
     *                              "partNumber": 2,
     *                              "uploadTimestamp": 1462212296000
     *                          },
     *                          {
     *                              "contentLength": 8158554,
     *                              "contentSha1": "00ad164147cbbd60aedb2b04ff66b0f74f962753",
     *                              "fileId": "4_ze73ede9c9c8412db49f60715_f100b4e93fbae6252_d20150824_m224353_c900_v8881000_t0001",
     *                              "partNumber": 3,
     *                              "uploadTimestamp": 1462212327000
     *                          }
     *                      ]
     *                   }
     * @throws B2Exception
     */
    public function b2ListParts($URL, $token, $fileId, $startPartNumber = null, $maxPartCount = 100)
    {
        if ($maxPartCount > 1000) {
            throw new B2Exception('The maximum allowed is 1000');
        }
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'fileId' => $fileId,
            'maxPartCount' => $maxPartCount
        );
        if (!is_null($startPartNumber)) {
            $payload['startPartNumber'] = $startPartNumber;
        }
        $response = $browser->post($URL.'/b2api/v1/b2_list_parts', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
    }

    /**
     * b2_list_unfinished_large_files
     *
     * Lists information about large file uploads that have been started, but have not been finished or canceled.
     *
     *
     * @param String      $URL              Obtained from b2_authorize_account call
     * @param string      $token            Obtained from b2_authorize_account call
     * @param string      $bucketId         The bucket to look for file names in.
     * @param null|String $startFileId      The first upload to return. If there is an upload with this ID, it will be returned in the list. If not, the first upload after this the first one after this ID.
     * @param int         $maxFileCount     The maximum number of files to return from this call. The default value is 100, and the maximum allowed is 100.
     *
     * @return B2Response {
     *                      "files": [
     *                          {
     *                          "accountId": "7eecc42b9675",
     *                          "bucketId": "e73ede9c9c8412db49f60715",
     *                          "contentType": "application/octet-stream",
     *                          "fileId": "4_ze73ede9c9c8412db49f60715_f100b4e93fbae6252_d20150824_m224353_c900_v8881000_t0001",
     *                          "fileInfo": {},
     *                          "fileName": "bigfile.dat",
     *                          "uploadTimestamp": 1462212184000
     *                          }
     *                      ],
     *                      "nextFileId": null
     *                  }
     * @throws B2Exception
     */
    public function b2ListUnfinishedLargeFiles($URL, $token, $bucketId, $startFileId = null, $maxFileCount = 100)
    {
        if ($maxFileCount > 100) {
            throw new B2Exception('The maximum allowed is 100');
        }
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'fileId' => $bucketId,
            'maxFileCount' => $maxFileCount,
        );
        if (!is_null($startFileId)) {
            $payload['startFileId'] = $startFileId;
        }
        $response = $browser->post($URL.'/b2api/v1/b2_list_unfinished_large_files', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
    }


    /**
     * b2_start_large_file
     *
     * Prepares for uploading the parts of a large file.
     *
     *
     * @param String      $URL              Obtained from b2_authorize_account call
     * @param string      $token            Obtained from b2_authorize_account call
     * @param string      $bucketId         The ID of the bucket that the file will go in.
     * @param string      $fileName         The name of the file
     * @param string      $contentType      The MIME type of the content of the file, which will be returned in the Content-Type header when downloading the file
     * @param null|String fileInfo          A JSON object holding the name/value pairs for the custom file info. Example: { "src_last_modified_millis" : "1452802803026", "large_file_sha1" : "a3195dc1e7b46a2ff5da4b3c179175b75671e80d", "color": "blue" }
     *
     * @return B2Response {
     *                       "accountId": "YOUR_ACCOUNT_ID",
     *                       "bucketId": "e73ede9c9c8412db49f60715",
     *                       "contentType": "b2/x-auto",
     *                       "fileId": "4_za71f544e781e6891531b001a_f200ec353a2184825_d20160409_m004829_c000_v0001016_t0028",
     *                       "fileInfo": {},
     *                       "fileName": "bigfile.dat",
     *                       "uploadTimestamp": 1460162909000
     *                  }
     * @throws B2Exception
     */
    public function b2StartLargeFile($URL, $token, $bucketId, $fileName, $contentType = "b2/x-auto", $fileInfo = null)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'buckedId' => $bucketId,
            'fileName' => $fileName,
            'contentType' => $contentType,
        );
        if (!is_null($fileInfo)) {
            $payload['fileInfo'] = $fileInfo;
        }
        $response = $browser->post($URL.'/b2api/v1/b2_start_large_file', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
    }



    /**
     * @param string $file         Raw file content
     * @param string $uploadURL    Use the b2GetUploadURL operation to get this URL for uploading files
     * @param string $token        An upload authorization token, from b2_get_upload_url
     * @param string $fileName     The name of the file, in percent-encoded UTF-8
     * @param float  $lastModified The value should be a base 10 number which represents a UTC time
     * @param string $contentType  The MIME type of the content of the file, which will be returned in the Content-Type header when downloading the file. Use the Content-Type b2/x-auto to automatically set the stored Content-Type post upload. In the case where a file extension is absent or the lookup fails, the Content-Type is set to application/octet-stream.
     * @param array  $params       Up to 10 of these headers may be present. The * part of the header name is replace with the name of a custom field in the file information stored with the file, and the value is an arbitrary UTF-8 string, percent-encoded. The same info headers sent with the upload will be returned with the download.
     *
     * @return B2Response Example:
     *                    {
     *                    "field": "123123123123",
     *                    "fileName": "pedro.jpg",
     *                    "accountId": "YOUR_ACCOUNT_ID",
     *                    "bucketId" : "4a48fe8875c6214145260818",
     *                    "contentLength": "100024", #in bytes
     *                    "contentSha1": "f12311231a3312312312123123123123",
     *                    "contentType": "image/JPG",
     *                    "fileInfo": [{ ... }] #
     *                    }
     *
     * @throws B2Exception
     */
    public function b2UploadFile($file, $uploadURL, $token, $fileName, $lastModified = null, $contentType = 'b2/x-auto', $params = array())
    {
        $curl = $this->prepareCurl();
        $request = new Browser($curl);

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

        return new B2Response($response);
    }




    /**
     * b2_upload_part
     * Uploads one part of a large file to B2, using an file ID obtained from b2_start_large_file.
     *
     * @param string $uploadURL
     * @param string $token
     * @param string $filePath
     * @param int $minimumPartSize The SHA1 checksum of the this part of the file. B2 will check this when the part is uploaded, to make sure that the data arrived correctly.
     *
     * @return B2Response[] Example:
     *          Part 1:
     *          {
     *              "contentLength": 100000000,
     *              "contentSha1": "062685a84ab248d2488f02f6b01b948de2514ad8",
     *              "fileId": "4_ze73ede9c9c8412db49f60715_f100b4e93fbae6252_d20150824_m224353_c900_v8881000_t0001",
     *              "partNumber": 1
     *          }
     *          Part 2:
     *          {
     *              "contentLength": 100000000,
     *              "contentSha1": "cf634751c3d9f6a15344f23cbf13f3fc9542addf",
     *              "fileId": "4_ze73ede9c9c8412db49f60715_f100b4e93fbae6252_d20150824_m224353_c900_v8881000_t0001",
     *              "partNumber": 2
     *          }
     *          Part 3:
     *              {
     *              "contentLength": 8158542,
     *              "contentSha1": "4546018a346df683acc9a3367977de4cc8024965",
     *              "fileId": "4_ze73ede9c9c8412db49f60715_f100b4e93fbae6252_d20150824_m224353_c900_v8881000_t0001",
     *              "partNumber": 3
     *          }
     *
     * @throws B2Exception
     */
    public function b2UploadPart($uploadURL, $token, $filePath, $minimumPartSize = 100000000)
    {
        $sha1s          = [];
        $responses      = [];
        $totalBytesSent = 0;
        $i              = 1; // current part

        $bytesSentForPart = $minimumPartSize;
        $fileSize = filesize($filePath);
        $file = fopen($filePath, "r");
        while($totalBytesSent < $fileSize) {
            // Determine the number of bytes to send based on the minimum part size
            if (($fileSize - $totalBytesSent) < $minimumPartSize) {
                $bytesSentForPart = ($fileSize - $totalBytesSent);
            }
            // Get a sha1 of the part we are going to send
            fseek($file, $totalBytesSent);
            $data = fread($file, $bytesSentForPart);
            array_push($sha1s, sha1($data));
            fseek($file, $totalBytesSent);

            $curl = $this->prepareCurl();
            $request = new Browser($curl);

            $headers = array(
                'Accept' => 'application/json',
                'Authorization' => $token,
                'Content-Length' => $bytesSentForPart,
                'X-Bz-Part-Number' => $i,
                'X-Bz-Content-Sha1' => $sha1s[$i - 1],
            );
            $response = $request->post($uploadURL, $headers, $data);
            $responses[] = new B2Response($response);
            // Prepare for the next iteration of the loop
            $i++;
            $totalBytesSent = $bytesSentForPart + $totalBytesSent;
        }
        fclose($file);
        return $responses;
    }

    /**
     * Update an existing bucket.
     *
     * Modifies the bucketType of an existing bucket. Can be used to allow everyone to download the contents
     * of the bucket without providing any authorization,
     * or to prevent anyone from downloading the contents of the bucket without providing a bucket auth token.
     *
     * @param String $URL                   Obtained from b2_authorize_account call
     * @param string $token                 Obtained from b2_authorize_account call
     * @param string $bucketId              The ID of the bucket
     * @param bool   $public                TRUE for public, FALSE for private.
     * @param null|String[] $bucketInfo     User-defined information to be stored with the bucket: a JSON object mapping names to values.
     * @param null|String[] $lifecycleRules The list of lifecycle rules for this bucket. Structure defined below
     * @param null|String $ifRevisionIs     When set, the update will only happen if the revision number stored in the B2 service matches the one passed in.
     *
     * @return B2Response
     *                    {
     *                      "accountId" : "010203040506",
     *                      "bucketId" : "4a48fe8875c6214145260818",
     *                      "bucketInfo" : {},
     *                      "bucketName" : "any-name-you-pick",
     *                      "bucketType" : "allPrivate",
     *                      "lifecycleRules" : []
     *                    }
     *
     * @throws B2Exception
     */
    public function b2UpdateBucket($URL, $token, $bucketId, $public, $bucketInfo = null, $lifecycleRules = null,  $ifRevisionIs = null)
    {
        $curl = $this->prepareCurl();
        $browser = new Browser($curl);
        $payload = array(
            'accountId' => $this->accountId,
            'bucketId' => $bucketId,
            'bucketType' => $public ? 'allPublic' : 'allPrivate',
        );
        if(!is_null($bucketInfo)){
            $payload["bucketInfo"] = $bucketInfo;
        }
        if(!is_null($lifecycleRules)){
            $payload["lifecycleRules"] = $lifecycleRules;
        }
        if(!is_null($ifRevisionIs)){
            $payload["ifRevisionIs"] = $ifRevisionIs;
        }
        $response = $browser->post($URL.'/b2api/v1/b2_update_bucket', $this->getHeaders($token), json_encode($payload));

        return new B2Response($response);
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
