<?php

namespace App\Http\Controllers;

use App\Models\OrganizationCommunicationAsset;
use App\Organizations\Communications\OrganizationCommunicationAssetDelivery;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationCommunicationAssetController extends Controller
{
    public function __invoke(string $asset, OrganizationCommunicationAssetDelivery $delivery): StreamedResponse
    {
        $record = OrganizationCommunicationAsset::query()->where('uuid', $asset)->firstOrFail();

        return $delivery->response($record);
    }
}
