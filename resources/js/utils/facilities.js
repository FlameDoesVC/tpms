// Labels and glyphs for the facility/amenity slugs stored on hotels and room
// types. The slugs themselves are the closed set in app/Support/FacilityCatalog.php,
// which rejects anything not listed there - keep the two in step.
//
// The same arrays drive the admin editors' checkbox grids, so a facility only
// has to be added in one place per side.

export const HOTEL_FACILITIES = [
    { slug: 'wifi', label: 'Free Wi-Fi', icon: 'wifi' },
    { slug: 'pool', label: 'Swimming pool', icon: 'waves' },
    { slug: 'spa', label: 'Spa', icon: 'leaf' },
    { slug: 'gym', label: 'Fitness centre', icon: 'dumbbell' },
    { slug: 'restaurant', label: 'Restaurant', icon: 'dining' },
    { slug: 'bar', label: 'Bar', icon: 'cocktail' },
    { slug: 'beach_access', label: 'Beach access', icon: 'umbrella' },
    { slug: 'airport_shuttle', label: 'Airport shuttle', icon: 'car' },
    { slug: 'room_service', label: 'Room service', icon: 'dining' },
    { slug: 'laundry', label: 'Laundry', icon: 'inbox' },
    { slug: 'parking', label: 'Parking', icon: 'parking' },
    { slug: 'air_conditioning', label: 'Air conditioning', icon: 'snowflake' },
    { slug: 'kids_club', label: 'Kids club', icon: 'users' },
    { slug: 'dive_center', label: 'Dive centre', icon: 'anchor' },
    { slug: 'water_sports', label: 'Water sports', icon: 'waves' },
];

export const ROOM_AMENITIES = [
    { slug: 'sea_view', label: 'Sea view', icon: 'waves' },
    { slug: 'balcony', label: 'Balcony', icon: 'umbrella' },
    { slug: 'minibar', label: 'Minibar', icon: 'cocktail' },
    { slug: 'safe', label: 'In-room safe', icon: 'shield' },
    { slug: 'tv', label: 'Television', icon: 'tv' },
    { slug: 'coffee_maker', label: 'Coffee maker', icon: 'dining' },
    { slug: 'bathtub', label: 'Bathtub', icon: 'waves' },
    { slug: 'king_bed', label: 'King bed', icon: 'bed' },
    { slug: 'twin_beds', label: 'Twin beds', icon: 'bed' },
    { slug: 'air_conditioning', label: 'Air conditioning', icon: 'snowflake' },
    { slug: 'wifi', label: 'Free Wi-Fi', icon: 'wifi' },
];

const BY_SLUG = new Map(
    [...HOTEL_FACILITIES, ...ROOM_AMENITIES].map((entry) => [entry.slug, entry])
);

/**
 * Label and icon for a slug. A slug the frontend doesn't know about still
 * renders as something readable rather than a blank chip - the backend catalog
 * can gain an entry before this file does.
 */
export function facilityMeta(slug) {
    return (
        BY_SLUG.get(slug) ?? {
            slug,
            label: String(slug).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
            icon: 'check',
        }
    );
}
