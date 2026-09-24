<?php

namespace App\Models;

use App\Services\IdentifierNormalizer;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'phone', 'email', 'address', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class)->orderBy('registration_plate');
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function deletedVehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class)->onlyTrashed()->orderBy('registration_plate');
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderBy('scheduled_at');
    }

    /**
     * @return HasMany<Job, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class)->latest();
    }

    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $escaped = addcslashes($term, '%_\\');
        $plate = IdentifierNormalizer::normalize($term);

        $query->where(function (Builder $query) use ($escaped, $plate) {
            $query->where('name', 'like', "%{$escaped}%")
                ->orWhere('phone', 'like', "%{$escaped}%")
                ->orWhereHas('vehicles', fn (Builder $vehicles) => $vehicles
                    ->whereRaw("CONCAT(make, ' ', model) like ?", ["%{$escaped}%"])
                    ->orWhere('vin', 'like', "%{$escaped}%")
                    ->when($plate !== '', fn (Builder $vehicles) => $vehicles->orWhereRaw(
                        "REPLACE(REPLACE(registration_plate, '-', ''), ' ', '') like ?",
                        ["%{$plate}%"],
                    )));
        });
    }
}
