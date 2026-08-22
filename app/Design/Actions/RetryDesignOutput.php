<?php

namespace App\Design\Actions;

use App\Enums\DesignOutputStatus;
use App\Models\DesignOutput;
use LogicException;

class RetryDesignOutput
{
    public function handle(DesignOutput $output): DesignOutput
    {
        if ($output->status !== DesignOutputStatus::FAILED || $output->media_asset_id !== null) {
            throw new LogicException('Only a failed Design output without canonical media can be retried.');
        }

        $output->forceFill(['status' => DesignOutputStatus::PENDING, 'failure_code' => null, 'rendered_at' => null])->save();

        return $output->fresh();
    }
}
