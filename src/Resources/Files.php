<?php

namespace Dcblogdev\Dropbox\Resources;

use Dcblogdev\Dropbox\Dropbox;
use GuzzleHttp\Client;
use Exception;

use Illuminate\Support\Facades\Auth;
use function PHPUnit\Framework\throwException;
use function trigger_error;

class Files extends Dropbox
{

    public function __construct(string $accessToken = '')
    {
        parent::__construct();

        $this->accessToken = $accessToken;
    }


    public function listContents($path = '', $recursive = false, $includeDeleted = false)
    {
        $pathRequest = $this->forceStartingSlash($path);

        return $this->post('files/list_folder', [
            'path' => $path == '' ? '' : $pathRequest,
            'recursive' => $recursive,
            'include_deleted' => $includeDeleted,
        ]);
    }

    public function listContentsContinue($cursor = '')
    {
        return $this->post('files/list_folder/continue', [
            'cursor' => $cursor
        ]);
    }

    public function move($fromPath, $toPath, $autoRename = false, $allowOwnershipTransfer = false)
    {
        $this->post('files/move_v2', [
            "from_path" => $fromPath,
            "to_path" => $toPath,
            "autorename" => $autoRename,
            "allow_ownership_transfer" => $allowOwnershipTransfer
        ]);
    }

    public function delete($path)
    {
        $path = $this->forceStartingSlash($path);

        return $this->post('files/delete_v2', [
            'path' => $path
        ]);
    }

    public function createFolder($path)
    {
        $path = $this->forceStartingSlash($path);

        return $this->post('files/create_folder', [
            'path' => $path
        ]);
    }

    public function search($query)
    {
        return $this->post('files/search', [
            'path' => '',
            'query' => $query,
            'start' => 0,
            'max_results' => 1000,
            'mode' => 'filename'
        ]);
    }

    public function upload($path, $uploadPath, $mode = 'add')
    {
        if ($uploadPath == '') {
            throw new Exception('File is required');
        }

        $path = ($path !== '') ? $this->forceStartingSlash($path) : '';
        $contents = $this->getContents($uploadPath);
        $filename = $this->getFilenameFromPath($uploadPath);
        $path = $path . $filename;

        try {

            $ch = curl_init('https://content.dropboxapi.com/2/files/upload');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->getAccessToken(),
                'Content-Type: application/octet-stream',
                'Dropbox-API-Arg: ' .
                json_encode([
                                "path" => $path,
                                "mode" => $mode,
                                "autorename" => true,
                                "mute" => false
                            ])
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $contents);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            return $response;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function download($path, $destFolder = '')
    {
        $path = $this->forceStartingSlash($path);

        try {
            $client = new Client;

            $response = $client->post("https://content.dropboxapi.com/2/files/download", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Dropbox-API-Arg' => json_encode([
                                                         'path' => $path
                                                     ])
                ]
            ]);

            $header = json_decode($response->getHeader('Dropbox-Api-Result')[0], true);

            $body = $response->getBody()->getContents();

            if (empty($destFolder)) {
                $destFolder = 'dropbox-temp';

                if ( ! is_dir($destFolder)) {
                    mkdir($destFolder);
                }
            }

            file_put_contents($destFolder . $header['name'], $body);

            return response()->download($destFolder . $header['name'], $header['name'])->deleteFileAfterSend();

        } catch (ClientException $e) {
            throw new Exception($e->getResponse()->getBody()->getContents());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getContentsFile($path)
    {
        $path = $this->forceStartingSlash($path);

        try {
            $client = new Client;

            $response = $client->post("https://content.dropboxapi.com/2/files/download", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Dropbox-API-Arg' => json_encode(['path' => $path])
                ]
            ]);

            return $response->getBody()->getContents();

        } catch (ClientException $e) {
            throw new Exception($e->getResponse()->getBody()->getContents());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getMetadataByPath($path, $includeDeleted = false)
    {
        $path = $this->forceStartingSlash($path);

        return $this->getMetadataById($path);
    }


    /**
     * @param $target string that can be an id or a path
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getMetadataById($target, $includeDeleted = false)
    {
        try {

            $client = new Client;

            $body = ['path' => $target, 'include_deleted' => $includeDeleted];

            $response = $client->post("https://api.dropboxapi.com/2/files/get_metadata", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type: application/json',
                ],
                'json' => $body
            ]);

            return $response->getBody()->getContents();

        } catch (ClientException $e) {
            throw new Exception($e->getResponse()->getBody()->getContents());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }


    /**
     * @param $target string that can be an id or a path
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getListRevisions($target)
    {
        try {

            $client = new Client;

            $body = ['path' => $target];

            $response = $client->post("https://api.dropboxapi.com/2/files/list_revisions", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type: application/json',
                ],
                'json' => $body
            ]);

            return $response->getBody()->getContents();

        } catch (ClientException $e) {
            throw new Exception($e->getResponse()->getBody()->getContents());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    protected function getFilenameFromPath($filePath)
    {
        $parts = explode('/', $filePath);
        $filename = end($parts);

        return $this->forceStartingSlash($filename);
    }

    protected function getContents($filePath)
    {
        return file_get_contents($filePath);
    }
}