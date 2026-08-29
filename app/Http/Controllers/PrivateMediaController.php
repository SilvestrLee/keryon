<?php

namespace App\Http\Controllers;

use App\Media\PrivateMediaDelivery;
use App\Models\MediaAsset;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateMediaController extends Controller
{
    public function __invoke(string $asset, PrivateMediaDelivery $delivery): StreamedResponse
    {
        $record = MediaAsset::query()->where('uuid', $asset)->firstOrFail();

        return $delivery->response($record);
    }
}
