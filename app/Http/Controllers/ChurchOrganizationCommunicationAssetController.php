<?php

namespace App\Http\Controllers;

use App\Communications\OrganizationInbox\ChurchOrganizationCommunicationAssetDelivery;
use App\Models\OrganizationCommunicationAsset;
use App\Models\OrganizationCommunicationDelivery;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChurchOrganizationCommunicationAssetController extends Controller
{
    public function __invoke(string $delivery, string $asset, ChurchOrganizationCommunicationAssetDelivery $service): StreamedResponse
    {
        $deliveryRecord = OrganizationCommunicationDelivery::query()->where('uuid', $delivery)->firstOrFail();
        $assetRecord = OrganizationCommunicationAsset::query()->where('uuid', $asset)->firstOrFail();

        return $service->response($deliveryRecord, $assetRecord);
    }
}
