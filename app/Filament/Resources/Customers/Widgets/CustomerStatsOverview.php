<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Models\CustomerDocument;
use App\Models\DocumentType;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $base = User::query()->whereNotNull('customer_category_id');

        $total = (clone $base)->count();
        $verified = (clone $base)->where('is_verified', true)->count();

        $requiredTypeIds = DocumentType::where('is_active', true)
            ->where('is_required', true)
            ->pluck('id');

        $pendingApproval = (clone $base)
            ->where('is_verified', false)
            ->whereHas('documents', function ($q) use ($requiredTypeIds) {
                $q->whereIn('document_type_id', $requiredTypeIds)
                    ->where('status', CustomerDocument::STATUS_PENDING);
            })
            ->count();

        $notVerified = (clone $base)
            ->where('is_verified', false)
            ->whereDoesntHave('documents', function ($q) use ($requiredTypeIds) {
                $q->whereIn('document_type_id', $requiredTypeIds);
            })
            ->count();

        $clickable = ['style' => 'cursor: pointer;'];

        return [
            Stat::make('Total Customers', $total)
                ->description('All registered customers')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('gray')
                ->extraAttributes(array_merge($clickable, [
                    'wire:click' => "\$dispatch('filter-customers', { scope: 'all' })",
                ])),

            Stat::make('Pending Approval', $pendingApproval)
                ->description('Uploaded documents waiting verification')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->extraAttributes(array_merge($clickable, [
                    'wire:click' => "\$dispatch('filter-customers', { scope: 'pending' })",
                ])),

            Stat::make('Verified', $verified)
                ->description('Verified customers')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->extraAttributes(array_merge($clickable, [
                    'wire:click' => "\$dispatch('filter-customers', { scope: 'verified' })",
                ])),

            Stat::make('Not Verified', $notVerified)
                ->description('No required documents uploaded')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->extraAttributes(array_merge($clickable, [
                    'wire:click' => "\$dispatch('filter-customers', { scope: 'not_verified' })",
                ])),
        ];
    }
}
