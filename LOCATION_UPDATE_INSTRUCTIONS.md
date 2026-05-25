# 📍 Complete Location Update API - All-in-One System

## 🚀 Overview

The location update API now supports **simultaneous updates** to **Country, State, City, and LGA** in a single API call. Admins can update any combination of location levels with full Google API integration and boost configuration.

---

## 🔗 API Endpoint

```
POST /api/v1/admin/locations/{lga}/update
```

### 📋 Required Headers
```json
{
    "Content-Type": "application/json",
    "Authorization": "Bearer {admin_token}"
}
```

---

## 📝 Complete Update Request Structure

### 🌍 **Full Update Example** - Update All Location Levels

```json
{
    "country": {
        "name": "United States",
        "iso_code": "US",
        "is_active": true,
        "sort_order": 1,
        "boundary_geojson": {
            "type": "Polygon",
            "coordinates": [[[...]]]
        }
    },
    "state": {
        "name": "California",
        "slug": "california",
        "is_active": true,
        "boost_pricing_multiplier": 1.5,
        "boundary_geojson": {
            "type": "Polygon",
            "coordinates": [[[...]]]
        }
    },
    "city": {
        "name": "San Francisco",
        "latitude": "37.7749",
        "longitude": "-122.4194",
        "slug": "san-francisco",
        "boost_active": true,
        "vendor_count": 150,
        "boundary_geojson": {
            "type": "Polygon",
            "coordinates": [[[...]]]
        }
    },
    "lga": {
        "name": "San Francisco Bay Area",
        "slug": "san-francisco-bay-area",
        "latitude": "37.7749295",
        "longitude": "-122.4194155",
        "google_place_id": "ChIJr7rOjF1O5ARMxjP_qtW8sE",
        "google_resource_name": "places/ChIJr7rOjF1O5ARMxjP_qtW8sE",
        "viewport": {
            "north": 37.7849,
            "south": 37.7649,
            "east": -122.4094,
            "west": -122.4294
        },
        "address_components_json": [
            {
                "long_name": "San Francisco",
                "short_name": "SF",
                "types": ["locality", "political"]
            }
        ],
        "formatted_address": "San Francisco Bay Area, California, USA",
        "display_name": "SF Bay Area",
        "short_name": "SF Bay",
        "full_address": "San Francisco Bay Area, California, USA",
        "boundary_geojson": {
            "type": "Polygon",
            "coordinates": [[[...]]]
        },
        "postal_code": "94102",
        "administrative_area_level_1": "California",
        "administrative_area_level_2": "San Francisco County",
        "locality": "San Francisco",
        "sublocality": "Mission District",
        "route": "Market Street",
        "street_number": "123",
        "boost_slots": ["top_1", "top_5", "top_10"],
        "boost_price": "15000.50",
        "boost_base_price": "5000.00",
        "boost_active": true,
        "boost_enabled": true,
        "vendor_count": 250,
        "total_vendor_count": 300,
        "active_vendor_count": 200,
        "average_rating": "4.5",
        "review_count": 1500
    },
    "map_pick": {
        "placeId": "ChIJr7rOjF1O5ARMxjP_qtW8sE",
        "resourceName": "places/ChIJr7rOjF1O5ARMxjP_qtW8sE",
        "lat": "37.7749295",
        "lng": "-122.4194155",
        "formattedAddress": "San Francisco Bay Area, California, USA",
        "viewport": {
            "north": 37.7849,
            "south": 37.7649,
            "east": -122.4094,
            "west": -122.4294
        },
        "addressComponents": [
            {
                "long_name": "San Francisco",
                "short_name": "SF",
                "types": ["locality", "political"]
            }
        ],
        "country": "United States",
        "state": "California",
        "lga": "San Francisco Bay Area",
        "administrativeAreaLevel1": "California",
        "administrativeAreaLevel2": "San Francisco County",
        "locality": "San Francisco",
        "sublocality": "Mission District",
        "route": "Market Street",
        "street_number": "123",
        "postal_code": "94102"
    },
    "boost_config": {
        "enabled": true,
        "tiers": [
            {
                "key": "top_1",
                "label": "Top-1 Premium",
                "total_slots": 1,
                "price_amount": "5000.00"
            },
            {
                "key": "top_5",
                "label": "Top-5 Standard",
                "total_slots": 5,
                "price_amount": "2500.00"
            },
            {
                "key": "top_10",
                "label": "Top-10 Basic",
                "total_slots": 10,
                "price_amount": "1000.00"
            }
        ],
        "durations": [
            {
                "days": 7,
                "enabled": true,
                "price_amount": "500.00"
            },
            {
                "days": 14,
                "enabled": true,
                "price_amount": "900.00"
            },
            {
                "days": 30,
                "enabled": true,
                "price_amount": "1800.00"
            }
        ]
    }
}
```

---

## 🎯 **Partial Update Examples**

### 🇺🇸 **Update Country Only**
```json
{
    "country": {
        "name": "United States of America",
        "iso_code": "US",
        "is_active": true,
        "sort_order": 1
    }
}
```

### 🏛️ **Update State Only**
```json
{
    "state": {
        "name": "California State",
        "slug": "california-state",
        "is_active": true,
        "boost_pricing_multiplier": 2.0
    }
}
```

### 🏙️ **Update City Only**
```json
{
    "city": {
        "name": "San Francisco City",
        "latitude": "37.7749",
        "longitude": "-122.4194",
        "boost_active": true,
        "vendor_count": 200
    }
}
```

### 📍 **Update LGA Only**
```json
{
    "lga": {
        "name": "Updated Bay Area",
        "latitude": "37.7749295",
        "longitude": "-122.4194155",
        "boost_active": true,
        "boost_price": "20000.00"
    }
}
```

### 💰 **Update Boost Configuration Only**
```json
{
    "boost_config": {
        "enabled": true,
        "tiers": [
            {
                "key": "premium",
                "label": "Premium Tier",
                "total_slots": 3,
                "price_amount": "7500.00"
            }
        ],
        "durations": [
            {
                "days": 21,
                "enabled": true,
                "price_amount": "1500.00"
            }
        ]
    }
}
```

---

## ✅ **Response Format**

```json
{
    "success": true,
    "message": "Location updated successfully.",
    "data": {
        "country": {
            "id": 1,
            "name": "United States of America",
            "iso_code": "US",
            "is_active": true,
            "sort_order": 1,
            "latitude": "37.7749295",
            "longitude": "-122.4194155",
            "formatted_address": "San Francisco Bay Area, California, USA",
            "display_name": "United States of America",
            "short_name": "USA",
            "boundary_geojson": {...}
        },
        "state": {
            "id": 1,
            "name": "California State",
            "slug": "california-state",
            "is_active": true,
            "boost_pricing_multiplier": "2.0",
            "latitude": "37.7749295",
            "longitude": "-122.4194155",
            "boost_base_price": "7500.00",
            "boost_tiers": [...],
            "boost_durations": [...],
            "boost_enabled": true
        },
        "city": {
            "id": 1,
            "name": "San Francisco City",
            "latitude": "37.7749",
            "longitude": "-122.4194",
            "boost_base_price": "7500.00",
            "boost_active": true,
            "boost_enabled": true,
            "vendor_count": 200
        },
        "lga": {
            "id": 1,
            "name": "Updated Bay Area",
            "slug": "updated-bay-area",
            "latitude": "37.7749295",
            "longitude": "-122.4194155",
            "formatted_address": "San Francisco Bay Area, California, USA",
            "boost_price": "20000.00",
            "boost_base_price": "7500.00",
            "boost_active": true,
            "boost_enabled": true,
            "boost_stats": {
                "total_slots": 3,
                "slots_sold": 0,
                "slots_remaining": 3,
                "active_boosts": 0,
                "expired_boosts": 0
            },
            "vendor_count": 250,
            "average_rating": "4.5",
            "review_count": 1500
        }
    }
}
```

---

## 📋 **Validation Rules**

### 🌍 Country Fields
- `name`: string, max 120 characters
- `iso_code`: string, exactly 2 characters
- `is_active`: boolean
- `sort_order`: integer, min 0

### 🏛️ State Fields
- `name`: string, max 120 characters
- `slug`: string, max 140 characters
- `is_active`: boolean
- `boost_pricing_multiplier`: numeric, min 0

### 🏙️ City Fields
- `name`: string, max 120 characters
- `latitude`: numeric, between -90 and 90
- `longitude`: numeric, between -180 and 180
- `slug`: string, max 140 characters
- `boost_active`: boolean
- `vendor_count`: integer, min 0

### 📍 LGA Fields
- `name`: string, max 120 characters
- `slug`: string, max 140 characters
- `latitude`: numeric, between -90 and 90
- `longitude`: numeric, between -180 and 180
- `boost_active`: boolean
- `boost_enabled`: boolean
- All Google API fields supported

### 💰 Boost Configuration
- `boost_config.enabled`: boolean
- `boost_config.tiers.*.key`: string, max 30 chars
- `boost_config.tiers.*.label`: string, max 60 chars
- `boost_config.tiers.*.total_slots`: integer, min 0
- `boost_config.tiers.*.price_amount`: numeric, min 0
- `boost_config.durations.*.days`: integer, in [7, 14, 30]
- `boost_config.durations.*.enabled`: boolean
- `boost_config.durations.*.price_amount`: numeric, min 0

---

## 🔧 **Key Features**

### ✅ **Smart Updates**
- **Partial Updates**: Only include fields you want to change
- **Auto-Slug Generation**: Automatically updates slugs when names change
- **Relationship Preservation**: Maintains all existing relationships
- **Transaction Safety**: All updates wrapped in database transactions

### 🌍 **Google API Integration**
- **Complete Address Components**: Full support for all Google Places data
- **Precise Coordinates**: Unlimited precision for lat/lng
- **Viewport Support**: Map boundary information
- **Address Formatting**: Properly formatted addresses

### 💰 **Boost System**
- **Tier Management**: Create and manage boost tiers
- **Duration Control**: Set boost durations and pricing
- **Statistics Tracking**: Automatic calculation of boost metrics
- **Multi-Level Support**: Boost config applies to all location levels

### 🔄 **Automatic Updates**
- **State LGA Count**: Automatically updates when LGAs change
- **Relationship Refresh**: Returns fresh data after updates
- **Slug Updates**: Auto-generates slugs for name changes
- **Boost Stats**: Recalculates statistics on pricing changes

---

## 🚀 **Best Practices**

### ✅ **Recommended Usage**
1. **📋 Include All Data**: Send complete data for best results
2. **🌍 Use Map Data**: Always include `map_pick` for Google API integration
3. **💰 Update Boost**: Include `boost_config` for pricing changes
4. **🔄 Verify Response**: Check all fields updated correctly
5. **📊 Monitor Stats**: Review boost_stats after changes

### ⚠️ **Important Notes**
1. **🔐 Admin Required**: All endpoints need admin authentication
2. **🆔 LGA ID**: Use existing LGA ID in URL path
3. **🔄 Transaction Safe**: All changes are atomic
4. **📝 Validation**: All inputs validated before processing
5. **🌐 Google API**: Map data enhances location accuracy

---

## 🎯 **Use Cases**

### 🏢 **Business Updates**
- Update business locations with new coordinates
- Adjust boost pricing for market changes
- Modify vendor counts and ratings
- Update address information

### 🗺️ **Geographic Updates**
- Correct location coordinates
- Update administrative boundaries
- Add new address components
- Improve map accuracy

### 💰 **Pricing Updates**
- Adjust boost tier pricing
- Modify duration options
- Update multipliers
- Enable/disable boost features

### 📊 **Data Management**
- Bulk updates to location hierarchy
- Synchronize with external systems
- Maintain data consistency
- Update display names and formatting

---

## 🎉 **Summary**

The all-in-one location update API provides **complete control** over the entire location hierarchy with **single API calls**. Admins can now efficiently manage:

- 🌍 **Countries** - Basic info and boundaries
- 🏛️ **States** - Regional settings and boost multipliers  
- 🏙️ **Cities** - Urban locations and vendor data
- 📍 **LGAs** - Detailed local government areas
- 💰 **Boost System** - Complete pricing and tier management
- 🌍 **Google API** - Full address and coordinate integration

This system provides **maximum flexibility** while maintaining **data integrity** and **transaction safety**! 🚀
