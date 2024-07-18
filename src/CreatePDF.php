<?php

namespace AdobePDFServices;

use Requests;
use Requests_Exception_HTTP;

class CreatePDF {

    private $clientId;
    private $clientSecret;

    public function __construct($clientId, $clientSecret) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function convert($file) {

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file);

        // Step 1: Obtain Access Token
        $token = $this->getAccessToken($this->clientId, $this->clientSecret);
        if (!$token) {
            return null;
        }

        // Step 2: Upload Asset
        $uploadResponse = $this->uploadAsset($file, $this->clientId, $token, $mimeType);
        if (!$uploadResponse) {
            return null;
        }

        $assetID = $uploadResponse['assetID'];
        $uploadUri = $uploadResponse['uploadUri'];

        // Step 3: Upload File to Cloud
        $uploadSuccess = $this->uploadFileToCloud($file, $uploadUri, $mimeType);
        if (!$uploadSuccess) {
            return null;
        }

        // Step 4: Create Job
        $jobResponse = $this->createJob($assetID, $this->clientId, $token);
        if (!$jobResponse) {
            return null;
        }

        $jobId = $jobResponse['headers']['x-request-id'];

        // Step 5: Fetch Job Status and Download Asset
        $downloadUri = $this->fetchJobStatus($jobId, $this->clientId, $token);
        if (!$downloadUri) {
            return null;
        }

        return $downloadUri;
    }

    private function getAccessToken($clientId, $clientSecret) {
        $tokenUrl = 'https://pdf-services.adobe.io/token';
        $data = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ];

        try {
            $response = Requests::post($tokenUrl, ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query($data));
            if ($response->success) {
                $responseData = json_decode($response->body, true);
                return $responseData['access_token'] ?? null;
            }
        } catch (Requests_Exception_HTTP $e) {
            error_log('Error fetching access token: ' . $e->getMessage());
        }

        return null;
    }

    private function uploadAsset($file, $clientId, $token, $mimeType) {
        $uploadUrl = 'https://pdf-services.adobe.io/assets';
        $data = [
            'mediaType' => $mimeType
        ];

        try {
            $headers = [
                'X-API-Key' => $clientId,
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ];
            $response = Requests::post($uploadUrl, $headers, json_encode($data));
            if ($response->success) {
                $responseData = json_decode($response->body, true);
                return [
                    'assetID' => $responseData['assetID'] ?? null,
                    'uploadUri' => $responseData['uploadUri'] ?? null
                ];
            }
        } catch (Requests_Exception_HTTP $e) {
            error_log('Error uploading asset: ' . $e->getMessage());
        }

        return null;
    }

    private function uploadFileToCloud($file, $uploadUri, $mimeType) {
        $fileSize = filesize($file);

        try {
            $headers = [
                'Content-Type' => $mimeType,
                'Content-Length' => $fileSize
            ];
            $response = Requests::put($uploadUri, $headers, file_get_contents($file));
            if ($response->success) {
                return true;
            }
        } catch (Requests_Exception_HTTP $e) {
            error_log('Error uploading file to cloud: ' . $e->getMessage());
        }

        return false;
    }

    function createJob($assetID, $clientId, $token) {
        $url = 'https://pdf-services.adobe.io/operation/createpdf';

        $data = [
            'assetID' => $assetID
        ];

        try {
            $headers = [
                'x-api-key' => $clientId,
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ];
            $response = Requests::post($url, $headers, json_encode($data));
            if ($response->success) {
                $headers = $this->httpParseHeaders($response->headers);
                return [
                    'http_status' => $response->status_code,
                    'headers' => $headers,
                    'body' => $response->body
                ];
            }
        } catch (Requests_Exception_HTTP $e) {
            error_log('Error creating job: ' . $e->getMessage());
        }

        return null;
    }

    private function fetchJobStatus($jobId, $clientId, $token) {
        $statusUrl = 'https://pdf-services.adobe.io/operation/createpdf/' . $jobId . '/status';

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'x-api-key' => $clientId
        ];

        do {
            sleep(5); // Wait 5 seconds before checking the status again

            try {
                $response = Requests::get($statusUrl, $headers);
                if ($response->success) {
                    $responseData = json_decode($response->body, true);
                    if ($responseData['status'] === 'done' || $responseData['status'] === 'failed') {
                        return $responseData['asset']['downloadUri'] ?? null;
                    }
                }
            } catch (Requests_Exception_HTTP $e) {
                error_log('Error fetching job status: ' . $e->getMessage());
                return null;
            }

        } while ($responseData['status'] === 'in progress');

        return null;
    }

    function httpParseHeaders($headers_array) {
        $headers = [];
    
        foreach ($headers_array as $key => $value) {
            if (is_string($key)) {
                $headers[$key] = $value;
            }
        }
    
        return $headers;
    }
    
}
?>
