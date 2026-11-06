<?php

namespace App\Services\Discovery;

use Illuminate\Database\Eloquent\Builder;

/**
 * A search area: a point and a radius (docs/MAPS_AND_LOCATION.md).
 *
 * In SQL, a bounding box first narrows the rows through the index on
 * (status, latitude, longitude). The circle and the sort order then use the
 * equirectangular approximation: plain arithmetic that runs identically on
 * MySQL, MariaDB and SQLite (which has no trigonometric functions by
 * default), and is accurate to well under 1% at the radiuses allowed here.
 * Distances shown to customers are computed exactly in PHP (haversine).
 *
 * Numbers are written into the SQL as formatted floats, not bindings: they
 * are typed floats, so they cannot carry SQL, and SQLite would compare a
 * bound value as text.
 */
final readonly class GeoCircle
{
    private const KM_PER_DEGREE = 111.32;

    private const EARTH_RADIUS_KM = 6371.0088;

    public function __construct(
        public float $latitude,
        public float $longitude,
        public float $radiusKm,
    ) {}

    /**
     * Great-circle distance in kilometres.
     */
    public static function haversineKm(float $fromLat, float $fromLon, float $toLat, float $toLon): float
    {
        $latitudes = deg2rad($toLat - $fromLat);
        $longitudes = deg2rad($toLon - $fromLon);
        $a = sin($latitudes / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($longitudes / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }

    public function distanceTo(float $latitude, float $longitude): float
    {
        return self::haversineKm($this->latitude, $this->longitude, $latitude, $longitude);
    }

    /**
     * Limits a query on `branches` to the circle.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $branches
     */
    public function constrain(Builder $branches): void
    {
        $latitudeDelta = $this->radiusKm / self::KM_PER_DEGREE;

        $branches
            ->whereNotNull('branches.latitude')
            ->whereNotNull('branches.longitude')
            ->whereBetween('branches.latitude', [$this->latitude - $latitudeDelta, $this->latitude + $latitudeDelta]);

        $this->constrainLongitude($branches);

        $branches->whereRaw($this->squaredDistanceSql().' <= '.self::number($this->radiusKm ** 2));
    }

    /**
     * Squared distance in km² from this point to `branches`, for sorting.
     */
    public function squaredDistanceSql(): string
    {
        $latitude = self::number($this->latitude);
        $longitude = self::number($this->longitude);
        $degree = self::number(self::KM_PER_DEGREE);
        $longitudeDegree = self::number(self::KM_PER_DEGREE * $this->longitudeScale());

        $north = "((branches.latitude - {$latitude}) * {$degree})";
        // The short way around: 179° and -179° are 2° apart.
        $difference = "abs(branches.longitude - {$longitude})";
        $east = "((case when {$difference} > 180 then 360 - {$difference} else {$difference} end) * {$longitudeDegree})";

        return "({$north} * {$north} + {$east} * {$east})";
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $branches
     */
    private function constrainLongitude(Builder $branches): void
    {
        $scale = $this->longitudeScale();
        // Near the poles every longitude is close: the latitude box is enough.
        if ($scale < 0.01) {
            return;
        }

        $delta = $this->radiusKm / (self::KM_PER_DEGREE * $scale);
        $west = $this->longitude - $delta;
        $east = $this->longitude + $delta;

        if ($west >= -180 && $east <= 180) {
            $branches->whereBetween('branches.longitude', [$west, $east]);

            return;
        }

        // The box crosses the 180th meridian.
        $branches->where(function (Builder $wrapped) use ($west, $east): void {
            $wrapped->where('branches.longitude', '>=', $west < -180 ? $west + 360 : $west)
                ->orWhere('branches.longitude', '<=', $east > 180 ? $east - 360 : $east);
        });
    }

    private function longitudeScale(): float
    {
        return cos(deg2rad($this->latitude));
    }

    private static function number(float $value): string
    {
        return sprintf('%.7F', $value);
    }
}
