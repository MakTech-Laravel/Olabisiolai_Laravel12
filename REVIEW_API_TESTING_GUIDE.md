# Review API Testing Guide

Complete guide for testing all review-related APIs with JSON examples and expected responses.

---

## 📋 Prerequisites

### Authentication

- Admin token required for admin endpoints
- Optional for public endpoints (for anonymous reviews)
- Use Bearer token in header: `Authorization: Bearer {token}`

### Base URLs

- **Public**: `http://localhost:8000/api/v1/reviews`
- **Admin**: `http://localhost:8000/api/v1/admin/reviews`

### Test User/Admin

Create test data first:

```bash
# Create test business
php artisan tinker
> $business = \App\Models\BusinessInfo::factory()->create();
> $business->id  // Remember this ID

# Create test user (optional)
> $user = \App\Models\User::factory()->create(['role' => 'user']);

# Create test admin
> $admin = \App\Models\User::factory()->create(['role' => 'admin']);
```

---

## 🔓 PUBLIC ENDPOINTS

### 1️⃣ GET `/api/v1/reviews` - List Approved Reviews

**Description**: Get approved reviews for a specific business

**Method**: GET

**Headers**:

```json
{
  "Content-Type": "application/json"
}
```

**Query Parameters**:

```json
{
  "business_id": 1,
  "rating": 4,
  "per_page": 15,
  "page": 1
}
```

**Required**: `business_id`
**Optional**: `rating`, `per_page`, `page`

**Example Request**:

```bash
curl -X GET "http://localhost:8000/api/v1/reviews?business_id=1&rating=5&per_page=10" \
  -H "Content-Type: application/json"
```

**Success Response (200)**:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "display_name": "Anonymous",
      "full_name": "Anonymous",
      "is_anonymous": true,
      "rating": 5,
      "rating_label": "★★★★★",
      "review_text": "Excellent service! Highly recommended.",
      "is_approved": true,
      "is_flagged": false,
      "flag_reason": null,
      "user": null,
      "business_info": {
        "id": 1,
        "business_name": "John's Services",
        "vendor": {
          "id": 1,
          "name": "John Vendor",
          "email": "vendor@example.com",
          "phone": "+234801234567",
          "role": "vendor"
        },
        "category": {
          "id": 1,
          "name": "Services"
        }
      },
      "images": [
        {
          "id": 1,
          "image_path": "review-images/image1.jpg",
          "original_filename": "review1.jpg",
          "mime_type": "image/jpeg",
          "file_size": 102400,
          "created_at": "06 May 2026, 12:30 PM"
        }
      ],
      "created_at": "06 May 2026, 12:30 PM",
      "updated_at": "06 May 2026, 12:30 PM"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 45
  }
}
```

---

### 2️⃣ POST `/api/v1/reviews/store` - Submit New Review

**Description**: Submit an anonymous or authenticated review for a business

**Method**: POST

**Headers**:

```json
{
  "Content-Type": "multipart/form-data"
}
```

#### Option A: Anonymous Review

**Body (Form Data)**:

```json
{
  "business_id": 1,
  "full_name": "Ahmed Hassan",
  "is_anonymous": true,
  "rating": 5,
  "review_text": "Excellent service! Highly recommended. The team was professional and efficient.",
  "images": ["image1.jpg", "image2.jpg"]
}
```

#### Option B: Authenticated Review

**Headers** (with auth):

```json
{
  "Authorization": "Bearer {user_token}",
  "Content-Type": "multipart/form-data"
}
```

**Body (Form Data)**:

```json
{
  "business_id": 1,
  "full_name": "Ahmed Hassan",
  "is_anonymous": false,
  "rating": 4,
  "review_text": "Great service with good communication. Minor delay in delivery.",
  "images": ["image1.jpg", "image2.jpg"]
}
```

**Validation Rules**:

- `business_id`: required, integer, exists in business_info
- `full_name`: required, string, max 255
- `is_anonymous`: boolean, default false
- `rating`: required, integer, min 1, max 5
- `review_text`: required, string, min 10, max 2000
- `images`: nullable, array, min 2, max 10 images
- Each image: required, file, image type, JPEG/PNG/WebP, max 5MB

**Example cURL (Anonymous)**:

```bash
curl -X POST "http://localhost:8000/api/v1/reviews" \
  -F "business_id=1" \
  -F "full_name=Ahmed Hassan" \
  -F "is_anonymous=true" \
  -F "rating=5" \
  -F "review_text=Excellent service! Highly recommended." \
  -F "images=@review1.jpg" \
  -F "images=@review2.jpg"
```

**Success Response (201)**:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "display_name": "Anonymous",
    "full_name": "Anonymous",
    "is_anonymous": true,
    "rating": 5,
    "rating_label": "★★★★★",
    "review_text": "Excellent service! Highly recommended.",
    "is_approved": true,
    "is_flagged": false,
    "flag_reason": null,
    "user": null,
    "business_info": {
      "id": 1,
      "business_name": "John's Services"
    },
    "images": [
      {
        "id": 1,
        "image_path": "review-images/image1.jpg",
        "original_filename": "review1.jpg",
        "mime_type": "image/jpeg",
        "file_size": 102400,
        "created_at": "06 May 2026, 12:30 PM"
      },
      {
        "id": 2,
        "image_path": "review-images/image2.jpg",
        "original_filename": "review2.jpg",
        "mime_type": "image/jpeg",
        "file_size": 95200,
        "created_at": "06 May 2026, 12:30 PM"
      }
    ],
    "created_at": "06 May 2026, 12:30 PM",
    "updated_at": "06 May 2026, 12:30 PM"
  },
  "message": "Review submitted successfully"
}
```

**Validation Error Response (422)**:

```json
{
  "success": false,
  "message": "The rating field must be an integer.",
  "data": {
    "errors": {
      "rating": ["The rating field must be an integer."],
      "review_text": ["The review text field is required."]
    }
  }
}
```

---

## 🔐 ADMIN ENDPOINTS

### 3️⃣ POST `/api/v1/admin/reviews` - List All Reviews (Admin)

**Description**: List all reviews with filtering and search

**Method**: POST

**Headers**:

```json
{
  "Authorization": "Bearer {admin_token}",
  "Content-Type": "application/json"
}
```

**Body (JSON)**:

```json
{
  "business_id": 1,
  "is_approved": true,
  "is_flagged": false,
  "rating": 5,
  "search": "excellent",
  "per_page": 15,
  "page": 1
}
```

**All Parameters Optional**: If not provided, shows all reviews

**Example Request**:

```bash
curl -X POST "http://localhost:8000/api/v1/admin/reviews" \
  -H "Authorization: Bearer admin_token" \
  -H "Content-Type: application/json" \
  -d '{
    "business_id": 1,
    "is_approved": true,
    "is_flagged": false,
    "per_page": 10,
    "page": 1
  }'
```

**Success Response (200)**:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "display_name": "Anonymous",
      "full_name": "Anonymous",
      "is_anonymous": true,
      "rating": 5,
      "rating_label": "★★★★★",
      "review_text": "Excellent service!",
      "is_approved": true,
      "is_flagged": false,
      "flag_reason": null,
      "user": null,
      "business_info": {...},
      "images": [...],
      "created_at": "06 May 2026, 12:30 PM"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 10,
    "total": 45
  }
}
```

---

### 4️⃣ POST `/api/v1/admin/reviews/{review}/view` - View Specific Review

**Description**: Get detailed info about a specific review

**Method**: POST

**Headers**:

```json
{
  "Authorization": "Bearer {admin_token}",
  "Content-Type": "application/json"
}
```

**URL Parameter**: `{review}` = Review ID

**Example Request**:

```bash
curl -X POST "http://localhost:8000/api/v1/admin/reviews/1/view" \
  -H "Authorization: Bearer admin_token"
```

**Success Response (200)**:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "display_name": "Ahmed Hassan",
    "full_name": "Ahmed Hassan",
    "is_anonymous": false,
    "rating": 5,
    "rating_label": "★★★★★",
    "review_text": "Excellent service with great attention to detail.",
    "is_approved": true,
    "is_flagged": false,
    "flag_reason": null,
    "user": {
      "id": 5,
      "first_name": "Ahmed",
      "last_name": "Hassan",
      "name": "Ahmed Hassan",
      "email": "ahmed@example.com",
      "phone": "+234801234567"
    },
    "business_info": {
      "id": 1,
      "business_name": "John's Services",
      "vendor": {...},
      "category": {...}
    },
    "images": [
      {
        "id": 1,
        "image_path": "review-images/image1.jpg",
        "original_filename": "review1.jpg",
        "mime_type": "image/jpeg",
        "file_size": 102400,
        "created_at": "06 May 2026, 12:30 PM"
      }
    ],
    "created_at": "06 May 2026, 12:30 PM",
    "updated_at": "06 May 2026, 12:30 PM"
  }
}
```

---

### 5️⃣ POST `/api/v1/admin/reviews/{review}/update` - Approve/Flag Review

**Description**: Update review approval status (single boolean logic: `is_approved = true` = approved, `is_approved = false` = flagged)

**Method**: POST

**Headers**:

```json
{
  "Authorization": "Bearer {admin_token}",
  "Content-Type": "application/json"
}
```

**URL Parameter**: `{review}` = Review ID

**Body (JSON)**:

#### Option A: Approve Review

```json
{
  "is_approved": true
}
```

#### Option B: Flag Review

```json
{
  "is_approved": false,
  "flag_reason": "Inappropriate language and offensive content"
}
```

#### Option C: Unflag Review (Same as Approve)

```json
{
  "is_approved": true
}
```

**Validation**:

- `is_approved`: boolean (optional)
- `flag_reason`: required if is_approved=false, string, max 1000

**Example Request (Approve)**:

```bash
curl -X POST "http://localhost:8000/api/v1/admin/reviews/1/update" \
  -H "Authorization: Bearer admin_token" \
  -H "Content-Type: application/json" \
  -d '{
    "is_approved": true
  }'
```

**Example Request (Flag)**:

```bash
curl -X POST "http://localhost:8000/api/v1/admin/reviews/1/update" \
  -H "Authorization: Bearer admin_token" \
  -H "Content-Type: application/json" \
  -d '{
    "is_approved": false,
    "flag_reason": "Spam content"
  }'
```

**Success Response (200)**:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "display_name": "Ahmed Hassan",
    "full_name": "Ahmed Hassan",
    "is_anonymous": false,
    "rating": 5,
    "rating_label": "★★★★★",
    "review_text": "Excellent service!",
    "is_approved": true,
    "is_flagged": false,
    "flag_reason": null,
    "user": {...},
    "business_info": {...},
    "images": [...],
    "created_at": "06 May 2026, 12:30 PM",
    "updated_at": "06 May 2026, 01:30 PM"
  },
  "message": "Review updated successfully"
}
```

---

### 6️⃣ POST `/api/v1/admin/reviews/{review}/delete` - Delete Review

**Description**: Delete a specific review and its images

**Method**: POST

**Headers**:

```json
{
  "Authorization": "Bearer {admin_token}",
  "Content-Type": "application/json"
}
```

**URL Parameter**: `{review}` = Review ID

**Example Request**:

```bash
curl -X POST "http://localhost:8000/api/v1/admin/reviews/1/delete" \
  -H "Authorization: Bearer admin_token"
```

**Success Response (200)**:

```json
{
  "success": true,
  "message": "Review deleted successfully"
}
```

---

### 7️⃣ POST `/api/v1/admin/reviews/bulk-approve` - Bulk Approve Reviews

**Description**: Approve multiple reviews at once

**Method**: POST

**Headers**:

```json
{
  "Authorization": "Bearer {admin_token}",
  "Content-Type": "application/json"
}
```

**Body (JSON)**:

```json
{
  "review_ids": [1, 2, 3, 4],
  "business_id": 1
}
```

**Validation**:

- `review_ids`: required, array of integers, must exist in reviews table
- `business_id`: optional (for filtering/context)

**Example Request**:

```bash
curl -X POST "http://localhost:8000/api/v1/admin/reviews/bulk-approve" \
  -H "Authorization: Bearer admin_token" \
  -H "Content-Type: application/json" \
  -d '{
    "review_ids": [1, 2, 3]
  }'
```

**Success Response (200)**:

```json
{
  "success": true,
  "message": "3 reviews approved successfully"
}
```

---

### 8️⃣ POST `/api/v1/admin/reviews/bulk-flag` - Bulk Flag Reviews

**Description**: Flag multiple reviews at once

**Method**: POST

**Headers**:

```json
{
  "Authorization": "Bearer {admin_token}",
  "Content-Type": "application/json"
}
```

**Body (JSON)**:

```json
{
  "review_ids": [1, 2, 3],
  "flag_reason": "Inappropriate content detected",
  "business_id": 1
}
```

**Validation**:

- `review_ids`: required, array of integers
- `flag_reason`: required, string, max 1000
- `business_id`: optional

**Example Request**:

```bash
curl -X POST "http://localhost:8000/api/v1/admin/reviews/bulk-flag" \
  -H "Authorization: Bearer admin_token" \
  -H "Content-Type: application/json" \
  -d '{
    "review_ids": [1, 2],
    "flag_reason": "Spam content"
  }'
```

**Success Response (200)**:

```json
{
  "success": true,
  "message": "2 reviews flagged successfully"
}
```

---

### 9️⃣ POST `/api/v1/admin/reviews/statistics` - Get Review Statistics

**Description**: Get overall review statistics and metrics

**Method**: POST

**Headers**:

```json
{
  "Authorization": "Bearer {admin_token}",
  "Content-Type": "application/json"
}
```

**Example Request**:

```bash
curl -X POST "http://localhost:8000/api/v1/admin/reviews/statistics" \
  -H "Authorization: Bearer admin_token"
```

**Success Response (200)**:

```json
{
  "success": true,
  "data": {
    "total_reviews": 245,
    "approved_reviews": 220,
    "flagged_reviews": 25,
    "average_rating": 4.32,
    "rating_distribution": {
      "5_star": 120,
      "4_star": 85,
      "3_star": 25,
      "2_star": 10,
      "1_star": 5
    }
  }
}
```

---

## 📝 Running Tests in Terminal

### Run All Review Tests

```bash
php artisan test tests/Feature/ReviewTest.php --compact
```

### Run Specific Test

```bash
php artisan test tests/Feature/ReviewTest.php --filter=test_public_can_submit_review_without_user_id_when_anonymous
```

### Run with Detailed Output

```bash
php artisan test tests/Feature/ReviewTest.php
```

---

## 🧪 Postman/Thunder Client Collection

### Import as Environment Variables

```json
{
  "base_url": "http://localhost:8000",
  "admin_token": "your_admin_token_here",
  "user_token": "your_user_token_here",
  "business_id": 1,
  "review_id": 1
}
```

### Quick Test Sequence

1. ✅ Create business (factory)
2. ✅ POST /api/v1/reviews (anonymous)
3. ✅ GET /api/v1/reviews?business_id=X
4. ✅ POST /api/v1/admin/reviews (list all)
5. ✅ POST /api/v1/admin/reviews/{id}/view
6. ✅ POST /api/v1/admin/reviews/{id}/update (approve)
7. ✅ POST /api/v1/admin/reviews/statistics

---

## ❌ Common Error Responses

### 400 - Bad Request

```json
{
  "success": false,
  "message": "Invalid business_id format",
  "data": null
}
```

### 401 - Unauthorized

```json
{
  "success": false,
  "message": "Unauthenticated",
  "data": null
}
```

### 403 - Forbidden (Non-Admin)

```json
{
  "success": false,
  "message": "This action is unauthorized.",
  "data": null
}
```

### 404 - Not Found

```json
{
  "success": false,
  "message": "Review not found",
  "data": null
}
```

### 422 - Validation Error

```json
{
  "success": false,
  "message": "The rating field must be between 1 and 5.",
  "data": {
    "errors": {
      "rating": ["The rating field must be between 1 and 5."]
    }
  }
}
```

### 500 - Server Error

```json
{
  "success": false,
  "message": "Internal server error",
  "data": null
}
```

---

## 💡 Tips for Testing

1. **Always provide business_id**: Required for all review operations
2. **Image formats**: Only JPEG, PNG, WebP (2-10 images)
3. **Anonymous reviews**: user_id will be null in database, "Anonymous" shown in response
4. **Authenticated reviews**: User's actual name shown if is_anonymous=false
5. **Search**: Works on full_name, review_text, and business_name
6. **Filtering**: Combine filters for precise results (business_id + is_approved + rating)
7. **Approval status**: Default is approved (`is_approved=true`) on submission
8. **Flag reason**: Only needed when `is_approved=false`
9. **Single Boolean Logic**: `is_approved=false` represents a flagged review. `is_flagged` is still returned in API for backward compatibility as `!is_approved`.

---

**Last Updated**: May 6, 2026
**API Version**: v1
**Database**: MySQL
