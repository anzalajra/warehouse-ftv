<?php

namespace App\Filament\Pages;

use App\Models\Delivery;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Jadwal Pengiriman — operational delivery board (MVP, no map).
 *
 * Lists surat jalan (Delivery rows) scheduled on a chosen day, grouped by the
 * assigned driver. Each card can be re-assigned (driver / escort / scheduled
 * time / address) and have its status advanced inline. Follows the custom
 * page + Blade + Livewire-method pattern used by Reports.php.
 */
class DeliverySchedule extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Jadwal Pengiriman';

    protected static ?string $title = 'Jadwal Pengiriman';

    protected static string|UnitEnum|null $navigationGroup = 'Rentals';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.delivery-schedule';

    /** Selected day (Y-m-d). */
    #[Url]
    public string $date = '';

    /** out | in | all — which delivery direction to show. */
    #[Url]
    public string $direction = 'all';

    // --- Inline assignment editor state -------------------------------------

    public ?int $editingId = null;

    public ?int $editDriverId = null;

    public ?int $editEscortId = null;

    public ?string $editScheduledAt = null;

    public ?string $editAddress = null;

    /** Per-request cache so deliveryGroups() isn't re-queried by summary(). */
    protected ?Collection $groupsCache = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'staff']) ?? false;
    }

    public function mount(): void
    {
        if (! $this->date) {
            $this->date = now()->format('Y-m-d');
        }
    }

    public function goToday(): void
    {
        $this->date = now()->format('Y-m-d');
    }

    public function shiftDay(int $days): void
    {
        $this->date = Carbon::parse($this->date)->addDays($days)->format('Y-m-d');
    }

    /**
     * Users eligible to be a driver or escort: any admin/staff/driver role.
     *
     * @return array<int, string>
     */
    public function assignableUsers(): array
    {
        // Only query roles that actually exist — the optional "driver" role may not
        // be seeded, and Spatie's role() scope throws on an unknown role name.
        $roles = \Spatie\Permission\Models\Role::whereIn('name', ['super_admin', 'admin', 'staff', 'driver'])
            ->pluck('name')
            ->all();

        if (empty($roles)) {
            return [];
        }

        return User::role($roles)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Deliveries on the selected day, grouped by driver (unassigned last).
     *
     * @return Collection<string, Collection<int, Delivery>>
     */
    public function deliveryGroups(): Collection
    {
        if ($this->groupsCache !== null) {
            return $this->groupsCache;
        }

        $query = Delivery::query()
            ->whereDate('scheduled_at', $this->date)
            ->whereNotIn('status', [Delivery::STATUS_CANCELLED])
            ->with(['rental.user', 'driver', 'escort', 'items'])
            ->orderBy('driver_id')
            ->orderBy('sort_order')
            ->orderBy('scheduled_at');

        if ($this->direction !== 'all') {
            $query->where('type', $this->direction === 'out' ? Delivery::TYPE_OUT : Delivery::TYPE_IN);
        }

        return $this->groupsCache = $query->get()
            ->groupBy(fn (Delivery $d): string => $d->driver?->name ?? '— Belum ada driver —');
    }

    /** @return array{total:int, assigned:int, unassigned:int, completed:int} */
    public function summary(): array
    {
        $all = $this->deliveryGroups()->flatten();

        return [
            'total' => $all->count(),
            'assigned' => $all->whereNotNull('driver_id')->count(),
            'unassigned' => $all->whereNull('driver_id')->count(),
            'completed' => $all->where('status', Delivery::STATUS_COMPLETED)->count(),
        ];
    }

    // --- Actions -------------------------------------------------------------

    public function openAssign(int $deliveryId): void
    {
        $delivery = Delivery::find($deliveryId);
        if (! $delivery) {
            return;
        }

        $this->editingId = $delivery->id;
        $this->editDriverId = $delivery->driver_id;
        $this->editEscortId = $delivery->escort_id;
        $this->editScheduledAt = $delivery->scheduled_at?->format('Y-m-d\TH:i');
        $this->editAddress = $delivery->address;
    }

    public function closeAssign(): void
    {
        $this->reset(['editingId', 'editDriverId', 'editEscortId', 'editScheduledAt', 'editAddress']);
    }

    public function saveAssign(): void
    {
        $delivery = Delivery::find($this->editingId);
        if (! $delivery) {
            $this->closeAssign();

            return;
        }

        $delivery->update([
            'driver_id' => $this->editDriverId ?: null,
            'escort_id' => $this->editEscortId ?: null,
            'scheduled_at' => $this->editScheduledAt ?: $delivery->scheduled_at,
            'address' => $this->editAddress,
        ]);

        Notification::make()
            ->title('Penugasan pengiriman disimpan')
            ->success()
            ->send();

        $this->closeAssign();
    }

    /** Advance a delivery's status inline (draft → pending → completed). */
    public function setStatus(int $deliveryId, string $status): void
    {
        if (! array_key_exists($status, Delivery::getStatusOptions())) {
            return;
        }

        $delivery = Delivery::find($deliveryId);
        if (! $delivery) {
            return;
        }

        $delivery->update(['status' => $status]);

        Notification::make()
            ->title('Status pengiriman diperbarui')
            ->success()
            ->send();
    }
}
