# Vendor Review API Testing Guide

This guide provides comprehensive testing examples for the Vendor Review Dashboard API endpoints with reply functionality.

## Prerequisites

- Vendor user account with associated business
- Authentication token (Bearer token)
- Existing reviews for the vendor's business

## Base URL
```
http://localhost:8000/api/v1/vendor
```

## Authentication Headers
```http
Authorization: Bearer {vendor_token}
Content-Type: application/json
Accept: application/json
```

## API Endpoints

### 1. Get All Reviews for Vendor's Business

**Endpoint:** `GET /reviews`

**Query Parameters:**
- `per_page` (optional): Number of reviews per page (1-100, default: 15)
- `rating` (optional): Filter by rating (1-5)
- `has_reply` (optional): Filter reviews with/without replies (true/false)
- `search` (optional): Search in reviewer name and review text

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/vendor/reviews?per_page=10&rating=5&has_reply=false" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

**Example Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "reviewer_name": "John Doe",
            "is_anonymous": false,
            "rating": 5,
            "review_text": "Excellent service! Highly recommended.",
            "is_approved": true,
            "business": {
                "id": 1,
                "business_name": "Tech Solutions Inc."
            },
            "images": [
                {
                    "id": 1,
                    "url": "http://localhost:8000/storage/review-images/image1.jpg",
                    "original_filename": "review_photo.jpg",
                    "mime_type": "image/jpeg",
                    "file_size": 245760
                }
            ],
            "replies": [],
            "created_at": "07-05-2026"
        }
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 10,
        "total": 25
    }
}
```

### 2. Get Review Statistics

**Endpoint:** `GET /reviews/statistics`

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/vendor/reviews/statistics" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

**Example Response:**
```json
{
    "success": true,
    "data": {
        "total_reviews": 25,
        "average_rating": 4.2,
        "replied_reviews": 18,
        "unreplied_reviews": 7,
        "rating_distribution": {
            "5_star": 12,
            "4_star": 8,
            "3_star": 3,
            "2_star": 1,
            "1_star": 1
        }
    }
}
```

### 3. Get Single Review with Replies

**Endpoint:** `GET /reviews/{review_id}`

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/vendor/reviews/1" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

**Example Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "reviewer_name": "John Doe",
        "is_anonymous": false,
        "rating": 5,
        "review_text": "Excellent service! Highly recommended.",
        "is_approved": true,
        "business": {
            "id": 1,
            "business_name": "Tech Solutions Inc."
        },
        "images": [],
        "replies": [
            {
                "id": 1,
                "review_id": 1,
                "reply_text": "Thank you for your kind words! We appreciate your feedback.",
                "vendor": {
                    "id": 5,
                    "first_name": "Vendor",
                    "last_name": "User",
                    "email": "vendor@example.com",
                    "full_name": "Vendor User"
                },
                "created_at": "2026-05-07T12:30:00.000000Z",
                "updated_at": "2026-05-07T12:30:00.000000Z"
            }
        ],
        "created_at": "07-05-2026"
    }
}
```

### 4. Add Reply to Review

**Endpoint:** `POST /reviews/{review_id}/reply`

**Request Body:**
```json
{
    "reply_text": "Thank you for your feedback! We're glad you had a great experience."
}
```

**Example Request:**
```bash
curl -X POST "http://localhost:8000/api/v1/vendor/reviews/1/reply" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "reply_text": "Thank you for your feedback! We'\''re glad you had a great experience."
  }'
```

**Example Response:**
```json
{
    "success": true,
    "data": {
        "id": 2,
        "review_id": 1,
        "reply_text": "Thank you for your feedback! We're glad you had a great experience.",
        "vendor": {
            "id": 5,
            "first_name": "Vendor",
            "last_name": "User",
            "email": "vendor@example.com",
            "full_name": "Vendor User"
        },
        "created_at": "2026-05-07T13:15:00.000000Z",
        "updated_at": "2026-05-07T13:15:00.000000Z"
    },
    "message": "Reply added successfully"
}
```

### 5. Update Reply

**Endpoint:** `PUT /reviews/replies/{reply_id}`

**Request Body:**
```json
{
    "reply_text": "Updated reply text with more details."
}
```

**Example Request:**
```bash
curl -X PUT "http://localhost:8000/api/v1/vendor/reviews/replies/2" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "reply_text": "Updated reply text with more details."
  }'
```

**Example Response:**
```json
{
    "success": true,
    "data": {
        "id": 2,
        "review_id": 1,
        "reply_text": "Updated reply text with more details.",
        "vendor": {
            "id": 5,
            "first_name": "Vendor",
            "last_name": "User",
            "email": "vendor@example.com",
            "full_name": "Vendor User"
        },
        "created_at": "2026-05-07T13:15:00.000000Z",
        "updated_at": "2026-05-07T14:20:00.000000Z"
    },
    "message": "Reply updated successfully"
}
```

### 6. Delete Reply

**Endpoint:** `DELETE /reviews/replies/{reply_id}`

**Example Request:**
```bash
curl -X DELETE "http://localhost:8000/api/v1/vendor/reviews/replies/2" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

**Example Response:**
```json
{
    "success": true,
    "message": "Reply deleted successfully"
}
```

### 7. Get Replies for Specific Review

**Endpoint:** `GET /reviews/{review_id}/replies`

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/vendor/reviews/1/replies" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

**Example Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "review_id": 1,
            "reply_text": "Thank you for your kind words! We appreciate your feedback.",
            "vendor": {
                "id": 5,
                "first_name": "Vendor",
                "last_name": "User",
                "email": "vendor@example.com",
                "full_name": "Vendor User"
            },
            "created_at": "2026-05-07T12:30:00.000000Z",
            "updated_at": "2026-05-07T12:30:00.000000Z"
        }
    ]
}
```

## Testing Scenarios

### Scenario 1: Complete Review Management Workflow

1. **Get all reviews:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/vendor/reviews" \
     -H "Authorization: Bearer {token}"
   ```

2. **Check statistics:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/vendor/reviews/statistics" \
     -H "Authorization: Bearer {token}"
   ```

3. **Reply to an unanswered review:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/vendor/reviews/3/reply" \
     -H "Authorization: Bearer {token}" \
     -H "Content-Type: application/json" \
     -d '{"reply_text": "Thank you for your valuable feedback!"}'
   ```

4. **Verify reply was added:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/vendor/reviews/3" \
     -H "Authorization: Bearer {token}"
   ```

### Scenario 2: Filter and Search

1. **Get 5-star reviews only:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/vendor/reviews?rating=5" \
     -H "Authorization: Bearer {token}"
   ```

2. **Get reviews without replies:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/vendor/reviews?has_reply=false" \
     -H "Authorization: Bearer {token}"
   ```

3. **Search reviews:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/vendor/reviews?search=excellent" \
     -H "Authorization: Bearer {token}"
   ```

## Error Responses

### Unauthorized (403)
```json
{
    "success": false,
    "message": "Unauthorized to view this review"
}
```

### Validation Error (422)
```json
{
    "message": "The reply text field is required.",
    "errors": {
        "reply_text": [
            "The reply text field is required."
        ]
    }
}
```

### Not Found (404)
```json
{
    "success": false,
    "message": "Business not found for this vendor"
}
```

## Postman Collection

You can import these endpoints into Postman using the following collection JSON:

```json
{
    "info": {
        "name": "Vendor Review Dashboard API",
        "description": "API endpoints for vendor review management with reply functionality"
    },
    "variable": [
        {
            "key": "base_url",
            "value": "http://localhost:8000/api/v1/vendor"
        },
        {
            "key": "token",
            "value": "your_vendor_token_here"
        }
    ],
    "item": [
        {
            "name": "Reviews",
            "item": [
                {
                    "name": "Get All Reviews",
                    "request": {
                        "method": "GET",
                        "header": [
                            {
                                "key": "Authorization",
                                "value": "Bearer {{token}}"
                            }
                        ],
                        "url": {
                            "raw": "{{base_url}}/reviews",
                            "host": ["{{base_url}}"],
                            "path": ["reviews"]
                        }
                    }
                },
                {
                    "name": "Get Statistics",
                    "request": {
                        "method": "GET",
                        "header": [
                            {
                                "key": "Authorization",
                                "value": "Bearer {{token}}"
                            }
                        ],
                        "url": {
                            "raw": "{{base_url}}/reviews/statistics",
                            "host": ["{{base_url}}"],
                            "path": ["reviews", "statistics"]
                        }
                    }
                },
                {
                    "name": "Add Reply",
                    "request": {
                        "method": "POST",
                        "header": [
                            {
                                "key": "Authorization",
                                "value": "Bearer {{token}}"
                            },
                            {
                                "key": "Content-Type",
                                "value": "application/json"
                            }
                        ],
                        "body": {
                            "mode": "raw",
                            "raw": "{\n    \"reply_text\": \"Thank you for your feedback!\"\n}"
                        },
                        "url": {
                            "raw": "{{base_url}}/reviews/1/reply",
                            "host": ["{{base_url}}"],
                            "path": ["reviews", "1", "reply"]
                        }
                    }
                }
            ]
        }
    ]
}
```

## Notes

- All endpoints require vendor authentication
- Vendors can only view and reply to reviews for their own business
- Reply text has a maximum length of 1000 characters
- Reviews are returned in reverse chronological order (newest first)
- Replies are returned in chronological order (oldest first)
- All timestamps are in UTC timezone
