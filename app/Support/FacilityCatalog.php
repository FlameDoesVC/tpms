<?php

namespace App\Support;

/**
 * The closed set of facility/amenity slugs a hotel or room type may advertise.
 *
 * Facilities are stored as a JSON array of slugs rather than a pivot table:
 * they are a fixed editorial vocabulary, never queried relationally, and the
 * frontend needs a label and an icon for each one anyway. Keeping the list here
 * lets validation reject anything the UI cannot render - resources/js/utils/facilities.js
 * mirrors these slugs and must be updated alongside this file.
 */
class FacilityCatalog
{
    /** @var list<string> */
    public const HOTEL_FACILITIES = [
        'wifi',
        'pool',
        'spa',
        'gym',
        'restaurant',
        'bar',
        'beach_access',
        'airport_shuttle',
        'room_service',
        'laundry',
        'parking',
        'air_conditioning',
        'kids_club',
        'dive_center',
        'water_sports',
    ];

    /** @var list<string> */
    public const ROOM_AMENITIES = [
        'sea_view',
        'balcony',
        'minibar',
        'safe',
        'tv',
        'coffee_maker',
        'bathtub',
        'king_bed',
        'twin_beds',
        'air_conditioning',
        'wifi',
    ];

    /** @return list<string> */
    public static function hotelFacilities(): array
    {
        return self::HOTEL_FACILITIES;
    }

    /** @return list<string> */
    public static function roomAmenities(): array
    {
        return self::ROOM_AMENITIES;
    }
}
