<?php

namespace Botble\Base\Supports;

use BaseHelper;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

class MarketplaceService
{
    protected string $url;

    protected ?string $token;

    protected string $publishedPath;

    protected string $productId;

    protected string $licenseUrl;

    protected string $licenseApiKey;

    public function __construct(string $url = null, string $token = null)
    {
        $core = BaseHelper::getFileData(core_path('core.json'));

        $this->url = $url ?? $core['marketplaceUrl'];

        $this->token = $token ?? $core['marketplaceToken'];

        $this->publishedPath = storage_path('app/marketplace/');

        $this->productId = $core['productId'];

        $this->licenseUrl = $core['apiUrl'];

        $this->licenseApiKey = $core['apiKey'];
    }

    public function callApi(string $method, string $path, array $request = [])
    {
        if (! config('core.base.general.enable_marketplace_feature')) {
            abort(404);
        }

        try {
            $response = $this->request()->{$method}($this->url . $path, $request);

            if ($response->status() !== 200) {
                $body = json_decode($response->body(), true);

                return $this->responseReturn(
                    Arr::get($body, 'message') ?: trans('packages/plugin-management::marketplace.api_connect_error'),
                    true,
                    [],
                    $response->getStatusCode()
                );
            }

            return $response;
        } catch (Throwable $e) {
            report($e);

            return $this->responseReturn(trans('packages/plugin-management::marketplace.api_connect_error'), true);
        }
    }

    protected function request(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Token ' . $this->token,
        ]);
    }

    public function beginInstall(string $id, string $type, string $name): bool
    {
        $core = new Core();

        $data = $this->callApi(
            'post',
            '/products/' . $id . '/download',
            [
                'site_url' => rtrim(url('')),
                'product_id' => $this->productId,
                'license_url' => $this->licenseUrl,
                'license_api_key' => $this->licenseApiKey,
                'license_file' => $core->checkLocalLicenseExist() ? file_get_contents(
                    $core->getLicenseFilePath()
                ) : null,
            ]
        );

        if ($data->getStatusCode() != 200) {
            $content = json_decode($data->getContent(), true);

            return $this->responseReturn(Arr::get($content, 'message') ?: $data, true);
        }

        File::ensureDirectoryExists($this->publishedPath . $id);

        $destination = $this->publishedPath . $id . '/' . $name . '.zip';

        File::cleanDirectory($this->publishedPath . $id);

        File::put($destination, $data);

        $this->extractFile($id, $name);
        $this->copyToPath($id, $type, $name);

        return true;
    }

    protected function extractFile(string $id, string $name): string
    {
        $destination = $this->publishedPath . $id . '/' . $name . '.zip';
        $pathTo = $this->publishedPath . $id;

        $zipper = new Zipper();

        if (! $zipper->extract($destination, $pathTo)) {
            return $this->responseReturn(trans('packages/plugin-management::marketplace.unzip_failed'), true);
        }

        File::delete($destination);

        return $pathTo;
    }

    protected function copyToPath(string $id, string $type, string $name): string
    {
        $pathTemp = $this->publishedPath . $id;
        $path = ($type == 'plugin' ? plugin_path() : theme_path()) . DIRECTORY_SEPARATOR . $name;

        if (File::isDirectory($pathTemp)) {
            File::copyDirectory($pathTemp, $path);
            File::deleteDirectory($pathTemp);
        }

        return $path;
    }

    protected function responseReturn(
        string $message,
        bool $error = false,
        array $data = [],
        int $statusCode = 200
    ): JsonResponse {
        return response()->json([
            'error' => $error,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }
}
