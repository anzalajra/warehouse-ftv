<?php

namespace App\Filament\Clusters\Settings;

use BackedEnum;
use Filament\Clusters\Cluster;

class SettingsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 1000;

    /**
     * Hidden from the sidebar navigation — Settings is reached via the floating
     * profile capsule's gear button (and the cluster's own sub-navigation once
     * inside any settings page). Pages stay accessible by URL.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
