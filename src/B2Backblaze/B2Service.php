<?php

namespace B2Backblaze;

/**
 * B2Client.
 *
 * @author Kamil Zabdyr <kamilzabdyr@gmail.com>
 */
class B2Service
{
    protected $accountId;
    protected $client;
    protected $apiURL;
    protected $downloadURL;
    protected $token;

    /**
     * @param String $account_id      The B2 account id for the account
     * @param String $application_key B2 application key for the account.
     * @param int    $timeout         Curl timeout.
     */
    public function __construct($account_id, $application_key, $timeout = 2000)
    {
        $this->accountId = $account_id;
        $this->client = new B2API($account_id, $application_key, $timeout);
        $this->apiURL = null;
        $this->downloadURL = null;
        $this->token = null;
    }

    /**
     * Authenticate with server.
     *
     * @return bool
     */
    public function authorize()
    {
        $response = $this->client->b2AuthorizeAccount();
        if ($response->isOk()) {
            $this->apiURL = $response->get('apiUrl');
            $this->token = $response->get('authorizationToken');
            $this->downloadURL = $response->get('downloadUrl');

            return true;
        }

        return false;
    }

    /**
     * Returns true if bucket exist.
     *
     * @param $bucketId
     *
     * @return bool
     */
    public function isBucketExist($bucketId)
    {
        $this->ensureAuthorized();
        $response = $this->client->b2ListBuckets($this->apiURL, $this->token);
        if ($response->isOk()) {
            $buckets = $response->get('buckets');
            if (!is_null($buckets)) {
                foreach ($buckets as $bucket) {
                    if ($bucketId == $bucket['bucketId']) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Returns the bucket information array.
     *
     * @param $bucketId
     *
     * @return bool
     */
    public function getBucketById($bucketId)
    {
        $this->ensureAuthorized();
        $response = $this->client->b2ListBuckets($this->apiURL, $this->token);
        if ($response->isOk()) {
            $buckets = $response->get('buckets');
            if (!is_null($buckets)) {
                foreach ($buckets as $bucket) {
                    if ($bucketId == $bucket['bucketId']) {
                        return $bucket;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Returns the file content and file metadata.
     *
     * @param String $bucketName
     * @param String $fileName
     * @param bool   $private
     * @param bool   $metadataOnly
     *
     * @return array|bool
     */
    public function get($bucketName, $fileName, $private = false, $metadataOnly = false)
    {
        $token = null;
        if ($private == true) {
            $this->ensureAuthorized();
            $token = $this->token;
        }
        $response = $this->client->b2DownloadFileByName($this->downloadURL, $bucketName, $fileName, $token, $metadataOnly);
        if ($response->isOk(false)) {
            return array('headers' => $response->getHeaders(), 'content' => $response->getRawContent());
        }

        return false;
    }

    /**
     * Inserts file and returns array of file metadata.
     *
     * @param String $bucketId
     * @param mixed  $file
     * @param String $fileName
     *
     * @return array|bool|null
     *
     * @throws B2Exception
     */
    public function insert($bucketId, $file, $fileName)
    {
        $this->ensureAuthorized();
        $response = $this->client->b2GetUploadURL($this->apiURL, $this->token, $bucketId);
        if ($response->isOk()) {
            $response2 = $this->client->b2UploadFile($file, $response->get('uploadUrl'), $this->token, $fileName);
            if ($response2->isOk()) {
                return $response2->getData();
            }
        }

        return false;
    }

    /**
     * Delete file version.
     *
     * @param String $bucketName
     * @param String $fileName
     *
     * @return bool
     */
    public function delete($bucketName, $fileName)
    {
        $data = $this->get($bucketName, $fileName, false, true);
        if ($data !== false && array_key_exists('X-Bz-File-Id', $data['headers'])) {
            $response = $this->client->b2DeleteFileVersion($this->apiURL, $this->token, $data['X-Bz-File-Id'], $fileName);

            return $response->isOk();
        }

        return false;
    }

    /**
     * Rename file.
     *
     * @param String $bucketName
     * @param String $bucketId       //For feature compatibility
     * @param String $fileName
     * @param String $targetBucketId
     * @param String $newFileName
     *
     * @return bool
     */
    public function rename($bucketName, $bucketId, $fileName, $targetBucketId, $newFileName)
    {
        $data = $this->get($bucketName, $fileName, false, false);
        if (is_array($data) && array_key_exists('X-Bz-File-Id', $data['headers'])) {
            $result = $this->insert($targetBucketId, $data['content'], $newFileName);
            if ($result === false) {
                return false;
            }
            $response = $this->client->b2DeleteFileVersion($this->apiURL, $this->token, $data['headers']['X-Bz-File-Id'], $fileName);

            return $response->isOk();
        }

        return false;
    }

    /**
     * Returns the list of files in bucket.
     *
     * @param String $bucketId
     *
     * @return array
     *
     * @throws B2Exception
     */
    public function all($bucketId)
    {
        $this->ensureAuthorized();
        $list = array();
        $nexFile = null;
        do {
            $response = $this->client->b2ListFileNames($this->apiURL, $this->token, $bucketId, $nexFile, 1000);
            if ($response->isOk()) {
                $files = $response->get('files');
                $nexFile = $response->get('nextFileName');
                if (!is_null($files)) {
                    array_merge($list, $files);
                }
                if (is_null($nexFile)) {
                    return $list;
                }
            }
        } while (true);

        return $list;
    }

    /**
     * Check if the filename exists.
     *
     *
     * @param String $bucketName
     * @param String $fileName
     *
     * @return bool
     */
    public function exists($bucketName, $fileName)
    {
        $this->ensureAuthorized();
        $response = $this->client->b2DownloadFileByName($this->downloadURL, $bucketName, $fileName, $this->token, true);
        if (!$response->isOk()) {
            return false;
        }

        return $response->getHeader('X-Bz-File-Name') == $fileName;
    }

    private function isAuthorized()
    {
        return !is_null($this->token) && !is_null($this->apiURL);
    }

    private function ensureAuthorized()
    {
        if (!$this->isAuthorized()) {
            return $this->authorize();
        }

        return true;
    }
}
