# Location API Documentation

## Base URL
```
/api/v1/admin/locations
```

## Authentication
All endpoints require admin authentication via `adminAuthCheck()` middleware.

---

## 1. Store Location (POST)

**Endpoint:** `POST /api/v1/admin/locations`

### Request Body
```json
{
  "location": {
    "country_name": "Nigeria",
    "country_iso_code": "NG",
    "country_is_active": true,
    "country_sort_order": 0,
    "state_name": "Lagos",
    "state_slug": "lagos",
    "city_name": "Ikeja",
    "lga_name": "Ikeja",
    "lga_slug": "ikeja",
    "vendor_count": 150,
    "latitude": 6.6018,
    "longitude": 3.3515,
    "formatted_address": "Ikeja, Lagos, Nigeria",
    "viewport_north": 6.6518,
    "viewport_south": 6.5518,
    "viewport_east": 3.4015,
    "viewport_west": 3.3015,
    "google_place_id": "ChIJd9jRk5QOxoR2A3-2h9l0k",
    "google_resource_name": "places/ChIJd9jRk5QOxoR2A3-2h9l0k",
    "address_components_json": [
      {
        "long_name": "Ikeja",
        "short_name": "Ikeja",
        "types": ["locality", "political"]
      }
    ]
  },
  "map_pick": {
    "placeId": "ChIJd9jRk5QOxoR2A3-2h9l0k",
    "resourceName": "places/ChIJd9jRk5QOxoR2A3-2h9l0k",
    "formattedAddress": "Ikeja, Lagos, Nigeria",
    "lat": 6.6018,
    "lng": 3.3515,
    "viewport": {
      "north": 6.6518,
      "south": 6.5518,
      "east": 3.4015,
      "west": 3.3015
    },
    "addressComponents": [
      {
        "long_name": "Ikeja",
        "short_name": "Ikeja",
        "types": ["locality", "political"]
      }
    ]
  },
  "boost_config": {
    "enabled": true,
    "tiers": [
      {
        "key": "top_1",
        "label": "Top-1",
        "total_slots": 1,
        "price_amount": 100
      },
      {
        "key": "top_5",
        "label": "Top-5",
        "total_slots": 5,
        "price_amount": 50
      }
    ],
    "durations": [
      {
        "days": 7,
        "enabled": true,
        "price_amount": 100
      },
      {
        "days": 14,
        "enabled": true,
        "price_amount": 180
      },
      {
        "days": 30,
        "enabled": true,
        "price_amount": 300
      }
    ]
  }
}
```

### Success Response (201)
```json
{
  "success": true,
  "message": "Location saved successfully.",
  "data": {
    "id": 1,
    "country_name": "Nigeria",
    "country_iso_code": "NG",
    "country_is_active": true,
    "country_sort_order": 0,
    "state_name": "Lagos",
    "state_slug": "lagos",
    "city_name": "Ikeja",
    "lga_name": "Ikeja",
    "lga_slug": "ikeja",
    "vendor_count": 150,
    "latitude": "6.6018000",
    "longitude": "3.3515000",
    "formatted_address": "Ikeja, Lagos, Nigeria",
    "viewport_north": "6.6518000",
    "viewport_south": "6.5518000",
    "viewport_east": "3.4015000",
    "viewport_west": "3.3015000",
    "google_place_id": "ChIJd9jRk5QOxoR2A3-2h9l0k",
    "google_resource_name": "places/ChIJd9jRk5QOxoR2A3-2h9l0k",
    "address_components_json": [...],
    "created_at": "2026-05-04T15:30:00.000000Z",
    "updated_at": "2026-05-04T15:30:00.000000Z",
    "lga_boost": {
      "id": 1,
      "location_id": 1,
      "enabled": true,
      "tiers": [...],
      "durations": [...],
      "total_slots": 6,
      "slots_sold": 0,
      "slots_remaining": 6,
      "active_boosts": 0,
      "expired_boosts": 0,
      "created_at": "2026-05-04T15:30:00.000000Z",
      "updated_at": "2026-05-04T15:30:00.000000Z"
    }
  }
}
```

---

## 2. List Locations (GET)

**Endpoint:** `GET /api/v1/admin/locations`

### Query Parameters
- `search` (optional, string): Search by LGA name, state name, country name, or formatted address
- `per_page` (optional, integer): Number of results per page (1-100, default: 15)
- `filter_boost` (optional, string): Filter by boost status (`enabled` or `disabled`)

### Example Request
```
GET /api/v1/admin/locations?search=Ikeja&per_page=10&filter_boost=enabled
```

### Success Response (200)
```json
{
  "success": true,
  "message": "Locations retrieved successfully.",
  "data": {
    "filter": {
      "search": "Ikeja",
      "boost": "enabled"
    },
    "count": 1,
    "pagination": {
      "current_page": 1,
      "per_page": 10,
      "last_page": 1,
      "total": 1
    },
    "locations": [
      {
        "id": 1,
        "country_name": "Nigeria",
        "state_name": "Lagos",
        "city_name": "Ikeja",
        "lga_name": "Ikeja",
        "lga_slug": "ikeja",
        "vendor_count": 150,
        "formatted_address": "Ikeja, Lagos, Nigeria",
        "created_at": "2026-05-04T15:30:00.000000Z",
        "lga_boost": {
          "enabled": true,
          "total_slots": 6,
          "slots_remaining": 6,
          "occupancy_percentage": 0.0
        }
      }
    ]
  }
}
```

---

## 3. Update Location (PUT)

**Endpoint:** `PUT /api/v1/admin/locations/{id}`

### Request Body
```json
{
  "location": {
    "country_name": "Nigeria",
    "state_name": "Lagos",
    "city_name": "Ikeja",
    "lga_name": "Ikeja Updated",
    "lga_slug": "ikeja-updated",
    "vendor_count": 200,
    "latitude": 6.6018,
    "longitude": 3.3515,
    "formatted_address": "Ikeja Updated, Lagos, Nigeria"
  },
  "map_pick": {
    "placeId": "ChIJd9jRk5QOxoR2A3-2h9l0k",
    "formattedAddress": "Ikeja Updated, Lagos, Nigeria",
    "lat": 6.6018,
    "lng": 3.3515
  },
  "boost_config": {
    "enabled": true,
    "tiers": [
      {
        "key": "top_1",
        "label": "Top-1",
        "total_slots": 2,
        "price_amount": 120
      }
    ],
    "durations": [
      {
        "days": 7,
        "enabled": true,
        "price_amount": 120
      }
    ]
  }
}
```

### Success Response (200)
```json
{
  "success": true,
  "message": "Location updated successfully.",
  "data": {
    "id": 1,
    "country_name": "Nigeria",
    "state_name": "Lagos",
    "city_name": "Ikeja",
    "lga_name": "Ikeja Updated",
    "lga_slug": "ikeja-updated",
    "vendor_count": 200,
    "updated_at": "2026-05-04T16:00:00.000000Z",
    "lga_boost": {
      "id": 1,
      "enabled": true,
      "tiers": [...],
      "durations": [...],
      "total_slots": 2,
      "slots_sold": 0,
      "slots_remaining": 2,
      "updated_at": "2026-05-04T16:00:00.000000Z"
    }
  }
}
```

---

## 4. Delete Location (DELETE)

**Endpoint:** `DELETE /api/v1/admin/locations/{id}`

### Success Response (200)
```json
{
  "success": true,
  "message": "Location deleted successfully."
}
```

---

## 5. Toggle Boost Status (PUT)

**Endpoint:** `PUT /api/v1/admin/locations/{id}/toggle-boost`

### Request Body
```json
{
  "boost_active": false
}
```

### Success Response (200)
```json
{
  "success": true,
  "message": "Boost status updated successfully.",
  "data": {
    "location_id": 1,
    "boost_active": false
  }
}
```

---

## 6. Get Location Vendors (GET)

**Endpoint:** `GET /api/v1/admin/locations/{id}/vendors`

### Success Response (200)
```json
{
  "success": true,
  "message": "Location vendors retrieved successfully.",
  "data": {
    "location_id": 1,
    "vendors": []
  }
}
```

---

## 7. Sync Location Vendors (PUT)

**Endpoint:** `PUT /api/v1/admin/locations/{id}/sync-vendors`

### Request Body
```json
{
  "vendors": [
    {
      "vendor_id": 1,
      "lat": 6.6018,
      "lng": 3.3515
    },
    {
      "vendor_id": 2,
      "lat": 6.6020,
      "lng": 3.3517
    }
  ]
}
```

### Success Response (200)
```json
{
  "success": true,
  "message": "Location vendors synced successfully."
}
```

---

## Error Responses

### Validation Error (422)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "data": {
    "errors": {
      "location.lga_name": ["The lga name field is required."],
      "location.latitude": ["The latitude must be between -90 and 90."]
    }
  }
}
```

### Unauthorized (401)
```json
{
  "success": false,
  "message": "Admin access required."
}
```

### Not Found (404)
```json
{
  "success": false,
  "message": "Location not found."
}
```

### Server Error (500)
```json
{
  "success": false,
  "message": "Something went wrong. Please try again."
}
```

---

## Notes

1. **Slugs**: Automatically generated from names if not provided
2. **Coordinates**: Must be valid latitude (-90 to 90) and longitude (-180 to 180)
3. **Boost Config**: Optional - if not provided, no boost configuration will be created
4. **Map Pick**: Optional - overrides location coordinates if provided
5. **Pagination**: Supports standard Laravel pagination
6. **Search**: Searches across LGA name, state name, country name, and formatted address
7. **Vendor Sync**: Currently returns empty array - implementation needed for vendor-location relationship
