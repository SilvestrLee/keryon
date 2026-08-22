<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

/**
 * K-CHURCHWEB-001C §12/§37 — the single "Website" sidebar destination.
 * Every Website Management page/resource declares
 * `protected static ?string $cluster = Website::class;` to join it,
 * giving Overview/Pages/Church Information/Brand/Theme one shared nav
 * entry with Filament's own sub-navigation between them — never nine
 * unrelated top-level items (§37 explicitly rejects that).
 */
class Website extends Cluster
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?int $navigationSort = 2;
}
