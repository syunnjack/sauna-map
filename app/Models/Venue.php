<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    protected $fillable = [
        'name',
        'description',
        'facility_type',
        'area',
        'address',
        'phone',
        'website',
        'opening_hours',
        'lat',
        'lng',
        'congestion_reports',
        'average_congestion',
        'likes_count',
        'source',
        'source_ref',
    ];

    protected function casts(): array
    {
        return [
            'congestion_reports' => 'array',
            'average_congestion' => 'float',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }
}
