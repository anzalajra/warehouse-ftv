<?php

namespace App\Filament\Resources\Rentals\Schemas;

use App\Models\User;
use App\Models\ProductUnit;
use App\Models\Rental;
use App\Models\RentalItem;
use Filament\Schemas\Components\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Carbon\Carbon;

class RentalForm
{
    // ── Cached data to avoid repeated queries during a single request ──
    private static ?array $cachedProductOptions = null;
    private static ?array $cachedProductList = null;
    private static ?array $cachedProductVariationMap = null;

    /**
     * Get product options (composite ID => label) — cached per request
     */
    private static function getProductOptionsWithVariations(): array
    {
        if (self::$cachedProductOptions !== null) {
            return self::$cachedProductOptions;
        }

        $products = \App\Models\Product::with('variations')
            ->where('is_active', true)
            ->select('id', 'name')
            ->get();

        $options = [];
        foreach ($products as $product) {
            if ($product->variations->isNotEmpty()) {
                foreach ($product->variations as $variation) {
                    $options["{$product->id}:{$variation->id}"] = "{$product->name} ({$variation->name})";
                }
            } else {
                $options[$product->id] = $product->name;
            }
        }

        self::$cachedProductOptions = $options;
        return $options;
    }

    /**
     * Get simple product list (id => name) — cached per request
     */
    private static function getProductList(): array
    {
        if (self::$cachedProductList !== null) {
            return self::$cachedProductList;
        }

        self::$cachedProductList = \App\Models\Product::where('is_active', true)
            ->pluck('name', 'id')
            ->toArray();

        return self::$cachedProductList;
    }

    /**
     * Get product variation map (product_id => [variation_id => name]) — cached
     */
    private static function getProductVariationMap(): array
    {
        if (self::$cachedProductVariationMap !== null) {
            return self::$cachedProductVariationMap;
        }

        $variations = \App\Models\ProductVariation::select('id', 'product_id', 'name')->get();
        $map = [];
        foreach ($variations as $v) {
            $map[$v->product_id][$v->id] = $v->name;
        }

        self::$cachedProductVariationMap = $map;
        return $map;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('id')->dehydrated(false),
                TextInput::make('rental_code')
                    ->label('Rental Code')
                    ->default('AUTO')
                    ->disabled()
                    ->dehydrated(false),

                Select::make('user_id')
                    ->label('Customer')
                    ->options(User::pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->disabled(fn ($record) => $record && in_array($record->status, [Rental::STATUS_ACTIVE, Rental::STATUS_LATE_RETURN])),

                DateTimePicker::make('start_date')
                    ->label('Start Date & Time')
                    ->required()
                    ->native(false)
                    ->default(now())
                    ->seconds(false)
                    ->live()
                    ->disabled(fn ($record) => $record && in_array($record->status, [Rental::STATUS_ACTIVE, Rental::STATUS_LATE_RETURN]))
                    ->afterStateUpdated(fn ($state, callable $get, callable $set) => self::calculateDuration($get, $set)),

                DateTimePicker::make('end_date')
                    ->label('End Date & Time')
                    ->required()
                    ->native(false)
                    ->default(now()->addDays(1))
                    ->seconds(false)
                    ->live()
                    ->disabled(fn ($record) => $record && in_array($record->status, [Rental::STATUS_ACTIVE, Rental::STATUS_LATE_RETURN]))
                    ->afterStateUpdated(fn ($state, callable $get, callable $set) => self::calculateDuration($get, $set))
                    ->helperText(function (callable $get) {
                        $startDate = $get('start_date');
                        $endDate = $get('end_date');
                        if ($startDate && $endDate) {
                            $start = Carbon::parse($startDate);
                            $end = Carbon::parse($endDate);
                            $totalHours = (int) $start->diffInHours($end);
                            $days = (int) floor($totalHours / 24);
                            $hours = $totalHours % 24;
                            if ($days > 0 && $hours > 0) return "📅 Durasi: {$days} hari {$hours} jam";
                            elseif ($days > 0) return "📅 Durasi: {$days} hari";
                            else return "📅 Durasi: {$hours} jam";
                        }
                        return null;
                    }),

                Select::make('status')
                    ->options(Rental::getStatusOptions())
                    ->required()
                    ->default('quotation')
                    ->disabled(fn ($record) => $record && (!$record->canBeEdited() || in_array($record->status, [Rental::STATUS_ACTIVE, Rental::STATUS_LATE_RETURN]))),

                // ── Grouped Rental Items Repeater ──
                Repeater::make('grouped_items')
                    ->label('Rental Items')
                    ->columns(12)
                    ->addable(false)
                    ->reorderable(false)
                    ->disabled(fn ($record) => $record && in_array($record->status, [Rental::STATUS_ACTIVE, Rental::STATUS_LATE_RETURN]))
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->options(fn () => self::getProductOptionsWithVariations())
                            ->searchable()
                            ->preload()
                            ->dehydrated(true)
                            ->columnSpan(4),

                        TextInput::make('quantity')
                            ->label('Qty')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->columnSpan(2)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $old, callable $get, callable $set) {
                                self::handleQuantityChange((int) $state, (int) $old, $get, $set);
                            })
                            ->suffixAction(
                                Action::make('manage_row_units')
                                    ->icon('heroicon-m-queue-list')
                                    ->color('gray')
                                    ->tooltip('Kelola Unit')
                                    ->modalHeading('Kelola Unit')
                                    ->modalWidth('md')
                                    ->form(function (callable $get): array {
                                        $quantity = (int) ($get('quantity') ?? 1);
                                        $compositeId = $get('product_id');
                                        $startDate = $get('../../start_date');
                                        $endDate = $get('../../end_date');
                                        $currentRentalId = $get('../../id');

                                        $productId = $compositeId;
                                        $variationId = null;
                                        if ($compositeId && str_contains((string) $compositeId, ':')) {
                                            [$productId, $variationId] = explode(':', $compositeId);
                                        }

                                        $options = self::getAvailableUnits($startDate, $endDate, $currentRentalId, $productId, $variationId);

                                        $fields = [];
                                        for ($i = 0; $i < $quantity; $i++) {
                                            $fields[] = Select::make("unit_{$i}")
                                                ->label("Unit #" . ($i + 1))
                                                ->options($options)
                                                ->searchable()
                                                ->required();
                                        }
                                        return $fields;
                                    })
                                    ->fillForm(function (callable $get): array {
                                        $unitIds = json_decode($get('unit_ids') ?? '[]', true);
                                        $fill = [];
                                        foreach ($unitIds as $i => $uid) {
                                            $fill["unit_{$i}"] = $uid;
                                        }
                                        return $fill;
                                    })
                                    ->action(function (array $data, callable $get, callable $set) {
                                        $unitIds = [];
                                        foreach ($data as $key => $val) {
                                            if (str_starts_with($key, 'unit_') && $val) {
                                                $unitIds[] = (int) $val;
                                            }
                                        }
                                        $set('unit_ids', json_encode(array_values(array_unique($unitIds))));

                                        \Filament\Notifications\Notification::make()
                                            ->title('Unit diperbarui')
                                            ->success()
                                            ->send();
                                    })
                            ),

                        TextInput::make('daily_rate')
                            ->label('Price')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->default(0)
                            ->columnSpan(2)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (callable $get, callable $set) => self::calculateGroupedLineTotal($get, $set)),

                        Hidden::make('days')->default(1),
                        Hidden::make('unit_ids')->default('[]'),

                        TextInput::make('discount')
                            ->label('Disc %')
                            ->numeric()
                            ->default(0)
                            ->columnSpan(1)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (callable $get, callable $set) => self::calculateGroupedLineTotal($get, $set)),

                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->prefix('Rp')
                            ->readOnly()
                            ->dehydrated(true)
                            ->default(0)
                            ->columnSpan(3),
                    ])
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->live()
                    ->afterStateUpdated(fn (callable $get, callable $set) => self::calculateTotals($get, $set)),

                // ── Add Product Action ──
                Actions::make([
                    Action::make('add_item')
                        ->label('Add Product')
                        ->icon('heroicon-m-plus')
                        ->button()
                        ->visible(fn ($record) => !$record || !in_array($record->status, [Rental::STATUS_ACTIVE, Rental::STATUS_LATE_RETURN]))
                        ->form(function () {
                            // Pre-load variation map once
                            $variationMap = self::getProductVariationMap();

                            return [
                                Select::make('product_id')
                                    ->label('Product')
                                    ->options(fn () => self::getProductList())
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (callable $set) => $set('product_variation_id', null)),

                                Select::make('product_variation_id')
                                    ->label('Variation')
                                    ->options(function (callable $get) use ($variationMap) {
                                        $productId = $get('product_id');
                                        if (!$productId) return [];
                                        return $variationMap[$productId] ?? [];
                                    })
                                    ->visible(function (callable $get) use ($variationMap) {
                                        $productId = $get('product_id');
                                        return $productId && !empty($variationMap[$productId] ?? []);
                                    })
                                    ->required(function (callable $get) use ($variationMap) {
                                        $productId = $get('product_id');
                                        return $productId && !empty($variationMap[$productId] ?? []);
                                    })
                                    ->live(),

                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required()
                                    ->visible(fn (callable $get) => (bool) $get('product_id'))
                                    ->helperText(function (callable $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) return '';
                                        $query = ProductUnit::where('product_id', $productId)
                                            ->whereNotIn('status', [ProductUnit::STATUS_MAINTENANCE, ProductUnit::STATUS_RETIRED]);
                                        if ($get('product_variation_id')) {
                                            $query->where('product_variation_id', $get('product_variation_id'));
                                        }
                                        return "Total unit tersedia: {$query->count()}";
                                    }),
                            ];
                        })
                        ->action(function (array $data, callable $get, callable $set) {
                            self::handleAddProduct($data, $get, $set);
                        }),
                ]),

                // ── Total Section ──
                \Filament\Schemas\Components\Section::make('Total')
                    ->schema([
                        Hidden::make('is_taxable')
                            ->default(fn () => filter_var(\App\Models\Setting::get('is_taxable', true), FILTER_VALIDATE_BOOLEAN))
                            ->dehydrated(),
                        Hidden::make('price_includes_tax')
                            ->default(fn () => filter_var(\App\Models\Setting::get('price_includes_tax', false), FILTER_VALIDATE_BOOLEAN))
                            ->dehydrated(),

                        TextInput::make('subtotal')
                            ->label('Untaxed Amount (Subtotal)')
                            ->numeric()->prefix('Rp')->default(0)->disabled()->dehydrated(true)->columnSpan(2),

                        Hidden::make('discount_type')->default('fixed'),
                        TextInput::make('discount')
                            ->label('Manual Discount')->numeric()->default(0)->live(onBlur: true)
                            ->prefix(fn (callable $get) => $get('discount_type') === 'percent' ? '%' : 'Rp')
                            ->suffixAction(
                                Action::make('toggle_discount_type')
                                    ->icon(fn (callable $get) => $get('discount_type') === 'percent' ? 'heroicon-m-currency-dollar' : 'heroicon-m-receipt-percent')
                                    ->tooltip(fn (callable $get) => $get('discount_type') === 'percent' ? 'Ganti ke Fixed (Rp)' : 'Ganti ke Persen (%)')
                                    ->color('gray')
                                    ->action(function (callable $get, callable $set) {
                                        $set('discount_type', $get('discount_type') === 'percent' ? 'fixed' : 'percent');
                                        self::calculateTotals($get, $set);
                                    })
                            )
                            ->afterStateUpdated(fn (callable $get, callable $set) => self::calculateTotals($get, $set)),

                        TextInput::make('daily_discount_amount')
                            ->label('Diskon Promo Harian')
                            ->numeric()->prefix('Rp')->default(0)->disabled()->dehydrated(false)
                            ->visible(fn ($record) => $record && $record->daily_discount_amount > 0),
                            
                        TextInput::make('date_promotion_amount')
                            ->label('Diskon Promo Tanggal')
                            ->numeric()->prefix('Rp')->default(0)->disabled()->dehydrated(false)
                            ->visible(fn ($record) => $record && $record->date_promotion_amount > 0),

                        TextInput::make('tax_base')
                            ->label('Dasar Pengenaan Pajak (DPP)')
                            ->numeric()->prefix('Rp')->default(0)->readOnly()->dehydrated()
                            ->visible(fn (callable $get) => $get('is_taxable'))->columnSpan(1),
                        TextInput::make('ppn_amount')
                            ->label('PPN (11%)')
                            ->numeric()->prefix('Rp')->default(0)->readOnly()->dehydrated()
                            ->visible(fn (callable $get) => $get('is_taxable'))->columnSpan(1),

                        TextInput::make('total')
                            ->label('Total')->numeric()->prefix('Rp')->default(0)->disabled()->dehydrated(true)->columnSpan(2),
                    ])
                    ->columnSpanFull(),

                // ── Deposit & Down Payment ──
                \Filament\Schemas\Components\Section::make('Deposit & Down Payment')
                    ->schema([
                        Hidden::make('deposit_type')->default('fixed'),
                        TextInput::make('deposit')
                            ->label('Security Deposit')->helperText('Required deposit amount/rate')
                            ->numeric()->default(0)->live(onBlur: true)
                            ->prefix(fn (callable $get) => $get('deposit_type') === 'percent' ? '%' : 'Rp')
                            ->suffixAction(
                                Action::make('toggle_deposit_type')
                                    ->icon(fn (callable $get) => $get('deposit_type') === 'percent' ? 'heroicon-m-currency-dollar' : 'heroicon-m-receipt-percent')
                                    ->tooltip(fn (callable $get) => $get('deposit_type') === 'percent' ? 'Ganti ke Fixed (Rp)' : 'Ganti ke Persen (%)')
                                    ->color('gray')
                                    ->action(function (callable $get, callable $set) {
                                        $set('deposit_type', $get('deposit_type') === 'percent' ? 'fixed' : 'percent');
                                        self::calculateTotals($get, $set);
                                    })
                            )
                            ->afterStateUpdated(fn (callable $get, callable $set) => self::calculateTotals($get, $set)),

                        TextInput::make('down_payment_amount')
                            ->label('Down Payment (DP)')->numeric()->prefix('Rp')->default(0),
                    ])
                    ->columnSpanFull(),

                // Hidden fields — managed by other system code
                Hidden::make('late_fee')->default(0)->dehydrated(),
                Hidden::make('down_payment_status')->default('pending')->dehydrated(),
                Hidden::make('ppn_rate')->default(0),

                Textarea::make('notes')->label('Notes')->rows(3)->columnSpanFull(),
            ]);
    }

    // ═══════════════════════════════════════════════
    //  ADD PRODUCT (from modal)
    // ═══════════════════════════════════════════════
    public static function handleAddProduct(array $data, callable $get, callable $set): void
    {
        $items = $get('grouped_items') ?? [];
        $pId = $data['product_id'];
        $vId = $data['product_variation_id'] ?? null;
        $quantity = (int) ($data['quantity'] ?? 1);
        $compositeId = $vId ? "{$pId}:{$vId}" : (string) $pId;

        // Calculate days
        $startDate = $get('start_date');
        $endDate = $get('end_date');
        $days = 1;
        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $days = max(1, (int) ceil($start->diffInHours($end) / 24));
        }

        // Get daily rate — single query
        $dailyRate = 0;
        if ($vId) {
            $dailyRate = \App\Models\ProductVariation::where('id', $vId)->value('daily_rate') ?? 0;
        } else {
            $dailyRate = \App\Models\Product::where('id', $pId)->value('daily_rate') ?? 0;
        }

        // Collect all already-used unit IDs
        $allUsedUnitIds = [];
        foreach ($items as $item) {
            $allUsedUnitIds = array_merge($allUsedUnitIds, json_decode($item['unit_ids'] ?? '[]', true));
        }

        // Find available units (optimized - single query)
        $currentRentalId = $get('id');
        $available = self::getAvailableUnitsOptimized($startDate, $endDate, $currentRentalId, $pId, $vId, $allUsedUnitIds);

        if ($available->count() < $quantity) {
            \Filament\Notifications\Notification::make()
                ->title('Unit Tidak Cukup')
                ->body("Hanya tersedia {$available->count()} unit untuk tanggal yang dipilih.")
                ->danger()->send();
            return;
        }

        $unitsToAdd = $available->take($quantity);
        $unitIds = $unitsToAdd->pluck('id')->values()->toArray();

        // Check if same product+variation already exists → merge
        $existingKey = null;
        foreach ($items as $key => $item) {
            if (($item['product_id'] ?? null) == $compositeId) {
                $existingKey = $key;
                break;
            }
        }

        if ($existingKey !== null) {
            $existing = $items[$existingKey];
            $existingUnitIds = json_decode($existing['unit_ids'] ?? '[]', true);
            $merged = array_merge($existingUnitIds, $unitIds);
            $newQty = count($merged);

            $items[$existingKey]['quantity'] = $newQty;
            $items[$existingKey]['unit_ids'] = json_encode($merged);

            $disc = (float) ($existing['discount'] ?? 0);
            $gross = $dailyRate * $days * $newQty;
            $items[$existingKey]['subtotal'] = max(0, $gross - ($gross * $disc / 100));
        } else {
            $gross = $dailyRate * $days * $quantity;
            $uuid = (string) \Illuminate\Support\Str::uuid();
            $items[$uuid] = [
                'product_id' => $compositeId,
                'quantity' => $quantity,
                'unit_ids' => json_encode($unitIds),
                'daily_rate' => $dailyRate,
                'days' => $days,
                'discount' => 0,
                'subtotal' => $gross,
            ];
        }

        $set('grouped_items', $items);
        self::calculateTotals($get, $set);

        $unitNames = $unitsToAdd->map(fn ($u) => $u->serial_number)->join(', ');
        \Filament\Notifications\Notification::make()
            ->title("{$quantity} unit ditambahkan")
            ->body("Unit: {$unitNames}")
            ->success()->send();
    }

    // ═══════════════════════════════════════════════
    //  QUANTITY CHANGE HANDLER
    // ═══════════════════════════════════════════════
    public static function handleQuantityChange(int $newQty, int $oldQty, callable $get, callable $set): void
    {
        if ($newQty === $oldQty || $newQty < 1) return;

        $unitIds = json_decode($get('unit_ids') ?? '[]', true);
        $compositeId = $get('product_id');
        $productId = $compositeId;
        $variationId = null;
        if ($compositeId && str_contains((string) $compositeId, ':')) {
            [$productId, $variationId] = explode(':', $compositeId);
        }

        if ($newQty > $oldQty) {
            $needed = $newQty - $oldQty;
            $startDate = $get('../../start_date');
            $endDate = $get('../../end_date');
            $currentRentalId = $get('../../id');

            // Collect all used unit IDs
            $allItems = $get('../../grouped_items') ?? [];
            $allUsedUnitIds = [];
            foreach ($allItems as $item) {
                $allUsedUnitIds = array_merge($allUsedUnitIds, json_decode($item['unit_ids'] ?? '[]', true));
            }

            $available = self::getAvailableUnitsOptimized($startDate, $endDate, $currentRentalId, $productId, $variationId, $allUsedUnitIds);

            if ($available->count() < $needed) {
                \Filament\Notifications\Notification::make()
                    ->title('Unit Tidak Cukup')
                    ->body("Hanya tersedia {$available->count()} unit tambahan.")
                    ->danger()->send();
                $set('quantity', $oldQty);
                return;
            }

            $newUnits = $available->take($needed);
            $unitIds = array_merge($unitIds, $newUnits->pluck('id')->toArray());
        } else {
            $unitIds = array_slice($unitIds, 0, $newQty);
        }

        $set('unit_ids', json_encode($unitIds));
        self::calculateGroupedLineTotal($get, $set);
    }

    // ═══════════════════════════════════════════════
    //  AVAILABLE UNITS — OPTIMIZED (DB-level filtering)
    // ═══════════════════════════════════════════════

    /**
     * Get IDs of units that are booked during the given date range.
     * Single query instead of N+1 isAvailable() calls.
     */
    private static function getBookedUnitIds($startDate, $endDate, $excludeRentalId = null): array
    {
        if (!$startDate || !$endDate) return [];

        $activeStatuses = [
            Rental::STATUS_QUOTATION,
            Rental::STATUS_CONFIRMED,
            Rental::STATUS_ACTIVE,
            Rental::STATUS_LATE_PICKUP,
            Rental::STATUS_LATE_RETURN,
        ];

        // 1. Units directly rented in overlapping period
        $directlyBooked = RentalItem::query()
            ->when($excludeRentalId, fn ($q) => $q->where('rental_id', '!=', $excludeRentalId))
            ->whereHas('rental', function ($query) use ($startDate, $endDate, $activeStatuses) {
                $query->whereIn('status', $activeStatuses)
                    ->where('start_date', '<', $endDate)
                    ->where('end_date', '>', $startDate);
            })
            ->pluck('product_unit_id')
            ->toArray();

        // 2. Units that are components of booked bundles (parent is rented → child unavailable)
        $componentBooked = \App\Models\UnitKit::whereNotNull('linked_unit_id')
            ->whereIn('unit_id', function ($query) use ($directlyBooked) {
                // Parent units that are booked
                $query->select('unit_id')
                    ->from('unit_kits')
                    ->whereIn('unit_id', $directlyBooked);
            })
            ->pluck('linked_unit_id')
            ->toArray();

        // 3. Bundle units whose components are booked
        $bundleBooked = \App\Models\UnitKit::whereNotNull('linked_unit_id')
            ->whereIn('linked_unit_id', $directlyBooked)
            ->pluck('unit_id')
            ->toArray();

        return array_values(array_unique(array_merge($directlyBooked, $componentBooked, $bundleBooked)));
    }

    /**
     * Get available units filtered at DB level — much faster than per-unit isAvailable()
     */
    public static function getAvailableUnitsOptimized($startDate, $endDate, $currentRentalId = null, $productId = null, $variationId = null, array $excludeUnitIds = [])
    {
        $bookedIds = self::getBookedUnitIds($startDate, $endDate, $currentRentalId);
        $allExcluded = array_merge($bookedIds, $excludeUnitIds);

        return ProductUnit::query()
            ->select('id', 'serial_number', 'product_id', 'product_variation_id', 'status')
            ->whereNotIn('status', [ProductUnit::STATUS_MAINTENANCE, ProductUnit::STATUS_RETIRED])
            ->whereNotIn('condition', ['broken', 'lost'])
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->when($variationId, fn ($q) => $q->where('product_variation_id', $variationId))
            ->when(!empty($allExcluded), fn ($q) => $q->whereNotIn('id', $allExcluded))
            ->get();
    }

    /**
     * Get available units as Select options array
     */
    public static function getAvailableUnits($startDate, $endDate, $currentRentalId = null, $productId = null, $variationId = null): array
    {
        $units = self::getAvailableUnitsOptimized($startDate, $endDate, $currentRentalId, $productId, $variationId);

        return $units->mapWithKeys(fn ($unit) => [$unit->id => $unit->serial_number])
            ->toArray();
    }

    // ═══════════════════════════════════════════════
    //  CALCULATIONS
    // ═══════════════════════════════════════════════
    public static function calculateGroupedLineTotal(callable $get, callable $set): void
    {
        $dailyRate = (float) ($get('daily_rate') ?? 0);
        $days = (int) ($get('days') ?? 1);
        $quantity = (int) ($get('quantity') ?? 1);
        $discountPercent = (float) ($get('discount') ?? 0);

        $gross = $dailyRate * $days * $quantity;
        $subtotal = max(0, $gross - ($gross * $discountPercent / 100));
        $set('subtotal', $subtotal);

        self::calculateTotals($get, $set);
    }

    public static function calculateDuration(callable $get, callable $set): void
    {
        $startDate = $get('start_date');
        $endDate = $get('end_date');
        if (!$startDate || !$endDate) return;

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $days = max(1, (int) ceil($start->diffInHours($end) / 24));

        $items = $get('grouped_items') ?? [];
        foreach ($items as $key => $item) {
            $set("grouped_items.{$key}.days", $days);
            $dailyRate = (float) ($item['daily_rate'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 1);
            $disc = (float) ($item['discount'] ?? 0);
            $gross = $dailyRate * $days * $quantity;
            $set("grouped_items.{$key}.subtotal", max(0, $gross - ($gross * $disc / 100)));
        }
        self::calculateTotals($get, $set);
    }

    public static function calculateTotals(callable $get, callable $set): void
    {
        $items = $get('grouped_items');
        $isInside = false;
        if ($items === null) {
            $items = $get('../../grouped_items');
            $isInside = true;
        }
        $items = $items ?? [];

        $grossSubtotal = 0;
        foreach ($items as $item) {
            $grossSubtotal += (float) ($item['subtotal'] ?? 0);
        }

        $getValue = fn ($field) => $isInside ? $get("../../{$field}") : $get($field);
        $setVal = fn ($f, $v) => $isInside ? $set("../../{$f}", $v) : $set($f, $v);

        $isTaxable = (bool) $getValue('is_taxable');
        $priceIncludesTax = (bool) $getValue('price_includes_tax');
        $discountType = $getValue('discount_type') ?? 'fixed';
        $discountValue = (float) ($getValue('discount') ?? 0);
        $dailyDiscountAmount = (float) ($getValue('daily_discount_amount') ?? 0);
        $datePromotionAmount = (float) ($getValue('date_promotion_amount') ?? 0);
        $depositType = $getValue('deposit_type') ?? 'fixed';
        $depositValue = (float) ($getValue('deposit') ?? 0);
        $lateFee = (float) ($getValue('late_fee') ?? 0);

        $setVal('subtotal', $grossSubtotal);

        $discountAmount = $discountType === 'percent'
            ? $grossSubtotal * ($discountValue / 100)
            : $discountValue;
            
        $totalDiscount = $discountAmount + $dailyDiscountAmount + $datePromotionAmount;
        $netSubtotal = max(0, $grossSubtotal - $totalDiscount);

        $taxEnabled = filter_var(\App\Models\Setting::get('tax_enabled', true), FILTER_VALIDATE_BOOLEAN);
        $isPkp = filter_var(\App\Models\Setting::get('is_pkp', false), FILTER_VALIDATE_BOOLEAN);
        $ppnRate = (float) \App\Models\Setting::get('ppn_rate', 11);

        $taxableAmount = $netSubtotal + $lateFee;
        $taxBase = $taxableAmount;
        $ppnAmount = 0;

        if ($taxEnabled && $isPkp && $isTaxable) {
            if ($priceIncludesTax) {
                $taxBase = $taxableAmount / (1 + ($ppnRate / 100));
            }
            $ppnAmount = $taxBase * ($ppnRate / 100);
        } else {
            $ppnRate = 0;
        }

        $payableAmount = $priceIncludesTax ? $taxableAmount : ($taxBase + $ppnAmount);
        $depositAmount = $depositType === 'percent' ? $grossSubtotal * ($depositValue / 100) : $depositValue;
        $total = $payableAmount + $depositAmount;

        $setVal('tax_base', round($taxBase, 2));
        $setVal('ppn_amount', round($ppnAmount, 2));
        $setVal('ppn_rate', $ppnRate);
        $setVal('total', round($total, 2));
    }

    // ═══════════════════════════════════════════════
    //  SYNC RENTAL ITEMS (DB) — called from pages
    // ═══════════════════════════════════════════════
    public static function syncRentalItems(Rental $rental, array $groupedItems): void
    {
        $existingItems = $rental->items()->whereNull('parent_item_id')->with('productUnit')->get();
        $processedIds = [];
        $newlyCreatedItems = [];

        // Disable RentalItem events during bulk sync to prevent cascading
        // refreshStatus/linking queries on every single create/update.
        // We'll do a single batch refresh at the end.
        \App\Models\RentalItem::withoutEvents(function () use ($rental, $groupedItems, $existingItems, &$processedIds, &$newlyCreatedItems) {
            // Reset ghost-slot tracker per row (don't reuse ghosts across different products).
            $existingGhosts = $existingItems->whereNull('product_unit_id')->groupBy(function ($it) {
                $key = $it->product_id;
                if ($it->product_variation_id) {
                    $key .= ':' . $it->product_variation_id;
                }
                return (string) $key;
            });
            // Convert to a mutable structure keyed by composite_id => array of items.
            $ghostPool = [];
            foreach ($existingGhosts as $compositeKey => $items) {
                $ghostPool[$compositeKey] = $items->all();
            }

            foreach ($groupedItems as $sortOrder => $group) {
                $unitIds = json_decode($group['unit_ids'] ?? '[]', true) ?: [];
                $days = (int) ($group['days'] ?? 1);
                $dailyRate = (float) ($group['daily_rate'] ?? 0);
                $discount = (float) ($group['discount'] ?? 0);
                $quantity = (int) ($group['quantity'] ?? count($unitIds));

                $gross = $dailyRate * $days;
                $perUnitSubtotal = max(0, $gross - ($gross * $discount / 100));

                // Derive product_id / variation_id from composite or from any of the unit_ids.
                $compositeId = (string) ($group['product_id'] ?? '');
                $rowProductId = null;
                $rowVariationId = null;
                if (str_contains($compositeId, ':')) {
                    [$rowProductId, $rowVariationId] = array_map('intval', explode(':', $compositeId, 2));
                } elseif ($compositeId !== '') {
                    $rowProductId = (int) $compositeId;
                }
                // Fallback: look up via first unit_id when composite isn't usable.
                if (!$rowProductId && !empty($unitIds)) {
                    $u = \App\Models\ProductUnit::find($unitIds[0]);
                    if ($u) {
                        $rowProductId = $u->product_id;
                        $rowVariationId = $u->product_variation_id;
                    }
                }

                // 1) Persist real (unit-bound) slots.
                foreach ($unitIds as $unitId) {
                    $existing = $existingItems->where('product_unit_id', $unitId)->first();

                    if ($existing) {
                        $existing->update([
                            'product_id' => $rowProductId,
                            'product_variation_id' => $rowVariationId,
                            'daily_rate' => $dailyRate,
                            'days' => $days,
                            'discount' => $discount,
                            'subtotal' => $perUnitSubtotal,
                            'sort_order' => $sortOrder,
                        ]);
                        $processedIds[] = $existing->id;
                    } else {
                        $newItem = $rental->items()->create([
                            'product_unit_id' => $unitId,
                            'product_id' => $rowProductId,
                            'product_variation_id' => $rowVariationId,
                            'daily_rate' => $dailyRate,
                            'days' => $days,
                            'discount' => $discount,
                            'subtotal' => $perUnitSubtotal,
                            'sort_order' => $sortOrder,
                        ]);
                        $processedIds[] = $newItem->id;
                        $newlyCreatedItems[] = $newItem;
                    }
                }

                // 2) Persist ghost slots (intent-to-rent without an assigned serial yet).
                $ghostCount = max(0, $quantity - count($unitIds));
                if ($ghostCount > 0 && $rowProductId) {
                    $compositeKey = $rowVariationId ? "{$rowProductId}:{$rowVariationId}" : (string) $rowProductId;
                    $availableGhosts = $ghostPool[$compositeKey] ?? [];

                    for ($i = 0; $i < $ghostCount; $i++) {
                        if (!empty($availableGhosts)) {
                            $reused = array_shift($availableGhosts);
                            $reused->update([
                                'daily_rate' => $dailyRate,
                                'days' => $days,
                                'discount' => $discount,
                                'subtotal' => $perUnitSubtotal,
                                'sort_order' => $sortOrder,
                            ]);
                            $processedIds[] = $reused->id;
                        } else {
                            $newGhost = $rental->items()->create([
                                'product_unit_id' => null,
                                'product_id' => $rowProductId,
                                'product_variation_id' => $rowVariationId,
                                'daily_rate' => $dailyRate,
                                'days' => $days,
                                'discount' => $discount,
                                'subtotal' => $perUnitSubtotal,
                                'sort_order' => $sortOrder,
                            ]);
                            $processedIds[] = $newGhost->id;
                        }
                    }
                    $ghostPool[$compositeKey] = $availableGhosts;
                }
            }

            // Delete items no longer present (real + leftover ghosts).
            $toDelete = $existingItems->pluck('id')->diff($processedIds)->toArray();
            if (!empty($toDelete)) {
                $rental->items()->whereIn('parent_item_id', $toDelete)->delete();
                $rental->items()->whereIn('id', $toDelete)->delete();
            }
        });

        // Attach kits for newly created items (was in RentalItem::created event).
        // Skip ghosts (no product_unit_id → no kits to attach).
        foreach ($newlyCreatedItems as $newItem) {
            if ($newItem->product_unit_id) {
                $newItem->attachKitsFromUnit();
            }
        }

        // Single batch refresh of all unit statuses instead of per-item
        $rental->unsetRelation('items');
        $rental->refreshUnitStatuses();
    }

    // ═══════════════════════════════════════════════
    //  GROUP ITEMS FOR FORM (hydration helper)
    // ═══════════════════════════════════════════════
    public static function groupItemsForForm($items): array
    {
        $grouped = [];

        // Respect the manual drag-and-drop ordering persisted in sort_order.
        // Fall back to id for rows predating the column (sort_order default 0).
        $items = collect($items)->sortBy([
            ['sort_order', 'asc'],
            ['id', 'asc'],
        ])->values();

        foreach ($items as $item) {
            if ($item->parent_item_id) continue;

            $unit = $item->productUnit;

            // Determine product / variation IDs:
            //   - real items (unit-bound) use the unit's product info
            //   - ghost items (product_unit_id IS NULL) use their own product_id / product_variation_id columns
            if ($unit) {
                $productId = $unit->product_id;
                $variationId = $unit->product_variation_id;
            } else {
                $productId = $item->product_id;
                $variationId = $item->product_variation_id;
                if (!$productId) continue; // truly orphaned row — skip
            }

            $compositeId = $variationId
                ? "{$productId}:{$variationId}"
                : (string) $productId;

            if (!isset($grouped[$compositeId])) {
                $grouped[$compositeId] = [
                    'product_id' => $compositeId,
                    'quantity' => 0,
                    'unit_ids' => [],
                    'daily_rate' => $item->daily_rate,
                    'days' => $item->days,
                    'discount' => $item->discount ?? 0,
                    'subtotal' => 0,
                ];
            }

            $grouped[$compositeId]['quantity']++;
            if ($unit) {
                $grouped[$compositeId]['unit_ids'][] = $unit->id;
            }
            $grouped[$compositeId]['subtotal'] += (float) $item->subtotal;
        }

        foreach ($grouped as &$g) {
            $g['unit_ids'] = json_encode($g['unit_ids']);
        }

        return array_values($grouped);
    }
}
