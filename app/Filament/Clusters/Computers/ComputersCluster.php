<?php

namespace App\Filament\Clusters\Computers;

use BackedEnum;
use Filament\Clusters\Cluster;

class ComputersCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationLabel = 'Computer Booking';

    protected static ?string $clusterBreadcrumb = 'Computer Booking';
}
