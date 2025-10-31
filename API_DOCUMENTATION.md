# Crave Kitchen Backend API Documentation

## Base URL

```
http://localhost:8000/api
```

## Authentication

The API uses Laravel Sanctum for authentication. Most endpoints require a Bearer token in the Authorization header.

### Headers

```
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}
```

---

## Authentication Endpoints

### 1. Login

**POST** `/auth/login`

**Description:** Authenticate admin user and receive access token.

**Request Body:**

```json
{
    "email": "admin@example.com",
    "password": "A1000B2000"
}
```

**Response (200):**

```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "Admin User",
            "email": "admin@example.com"
        },
        "token": "1|9BuPuM8HQ9yFie2ndjAOxDzbBuMJneTkF524phBB22016bd4"
    }
}
```

**Error Response (422):**

```json
{
    "message": "The provided credentials are incorrect.",
    "errors": {
        "email": ["The provided credentials are incorrect."]
    }
}
```

### 2. Get Current User

**GET** `/auth/me`

**Headers:** `Authorization: Bearer {token}`

**Response (200):**

```json
{
    "success": true,
    "data": {
        "user": {
            "id": 1,
            "name": "Admin User",
            "email": "admin@example.com"
        }
    }
}
```

### 3. Logout

**POST** `/auth/logout`

**Headers:** `Authorization: Bearer {token}`

**Response (200):**

```json
{
    "success": true,
    "message": "Logout successful"
}
```

### 4. Legacy User Route

**GET** `/user`

**Headers:** `Authorization: Bearer {token}`

**Response (200):**

```json
{
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "email_verified_at": null,
    "created_at": "2025-10-13T10:00:00.000000Z",
    "updated_at": "2025-10-13T10:00:00.000000Z"
}
```

---

## Customer Management

### 1. Get All Customers

**GET** `/admin/customers`

**Query Parameters:**

-   `per_page` (int, default: 15) - Items per page
-   `search` (string) - Search in name, email, phone, address
-   `has_orders` (boolean) - Filter customers with/without orders
-   `name` (string) - Filter by name
-   `email` (string) - Filter by email
-   `phone` (string) - Filter by phone
-   `address` (string) - Filter by address
-   `min_orders` (int) - Minimum number of orders
-   `max_orders` (int) - Maximum number of orders
-   `min_spent` (decimal) - Minimum amount spent
-   `max_spent` (decimal) - Maximum amount spent
-   `payment_status` (string) - Filter by payment status
-   `order_status` (string) - Filter by order status
-   `created_from` (date) - Created after date
-   `created_to` (date) - Created before date
-   `sort_by` (string) - Sort field (name, email, created_at, total_orders, total_spent)
-   `sort_order` (string) - Sort direction (asc, desc)

**Response (200):**

```json
{
    "success": true,
    "message": "Customers retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "John Doe",
            "email": "john.doe@example.com",
            "phone": "+1234567890",
            "address": "123 Main Street, City, State 12345",
            "total_orders": 5,
            "total_spent": "125.50",
            "created_at": "2025-10-01T10:00:00.000000Z",
            "updated_at": "2025-10-13T10:00:00.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "per_page": 15,
        "to": 1,
        "total": 1
    }
}
```

### 2. Get Single Customer

**GET** `/admin/customers/{id}`

**Response (200):**

```json
{
    "success": true,
    "message": "Customer retrieved successfully",
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john.doe@example.com",
        "phone": "+1234567890",
        "address": "123 Main Street, City, State 12345",
        "total_orders": 5,
        "total_spent": "125.50",
        "created_at": "2025-10-01T10:00:00.000000Z",
        "updated_at": "2025-10-13T10:00:00.000000Z",
        "orders": [
            {
                "id": 1,
                "order_number": "ORD-001",
                "status": "completed",
                "total_amount": "25.50",
                "payment_status": "paid",
                "created_at": "2025-10-13T09:00:00.000000Z"
            }
        ]
    }
}
```

### 3. Create Customer

**POST** `/admin/customers`

**Request Body:**

```json
{
    "name": "John Doe",
    "email": "john.doe@example.com",
    "phone": "+1234567890",
    "address": "123 Main Street, City, State 12345"
}
```

**Response (201):**

```json
{
    "success": true,
    "message": "Customer created successfully",
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john.doe@example.com",
        "phone": "+1234567890",
        "address": "123 Main Street, City, State 12345",
        "total_orders": 0,
        "total_spent": "0.00",
        "created_at": "2025-10-13T10:00:00.000000Z",
        "updated_at": "2025-10-13T10:00:00.000000Z"
    }
}
```

### 4. Update Customer

**PUT** `/admin/customers/{id}`

**Request Body:**

```json
{
    "name": "John Smith",
    "email": "john.smith@example.com",
    "phone": "+1234567891",
    "address": "456 Oak Avenue, City, State 12345"
}
```

**Response (200):**

```json
{
    "success": true,
    "message": "Customer updated successfully",
    "data": {
        "id": 1,
        "name": "John Smith",
        "email": "john.smith@example.com",
        "phone": "+1234567891",
        "address": "456 Oak Avenue, City, State 12345",
        "total_orders": 5,
        "total_spent": "125.50",
        "created_at": "2025-10-01T10:00:00.000000Z",
        "updated_at": "2025-10-13T10:30:00.000000Z"
    }
}
```

### 5. Delete Customer

**DELETE** `/admin/customers/{id}`

**Response (200):**

```json
{
    "success": true,
    "message": "Customer deleted successfully"
}
```

### 6. Soft Delete Customer

**DELETE** `/admin/customers/{id}/soft-delete`

**Response (200):**

```json
{
    "success": true,
    "message": "Customer soft deleted successfully"
}
```

### 7. Get Customer Statistics

**GET** `/admin/customers/{id}/statistics`

**Response (200):**

```json
{
    "success": true,
    "data": {
        "customer": {
            "id": 1,
            "name": "John Doe"
        },
        "statistics": {
            "total_orders": 5,
            "completed_orders": 4,
            "pending_orders": 1,
            "cancelled_orders": 0,
            "total_spent": "125.50",
            "average_order_value": "25.10",
            "first_order_date": "2025-09-01T10:00:00.000000Z",
            "last_order_date": "2025-10-13T09:00:00.000000Z",
            "favorite_items": [
                {
                    "item_name": "Chicken Rice",
                    "order_count": 3,
                    "total_quantity": 5
                }
            ]
        }
    }
}
```

### 8. Search Customers

**GET** `/admin/search/customers`

**Query Parameters:**

-   `query` (string) - Search term
-   `has_orders` (boolean) - Filter customers with/without orders
-   `min_spent` (decimal) - Minimum amount spent
-   `max_spent` (decimal) - Maximum amount spent

**Response (200):** Same as Get All Customers

---

## Item Management

### 1. Get All Items

**GET** `/admin/items`

**Query Parameters:**

-   `per_page` (int, default: 15) - Items per page
-   `search` (string) - Search in name, description, category
-   `category` (string) - Filter by category
-   `is_active` (boolean) - Filter active/inactive items
-   `min_price` (decimal) - Minimum price
-   `max_price` (decimal) - Maximum price
-   `min_stock` (int) - Minimum stock quantity
-   `max_stock` (int) - Maximum stock quantity
-   `sort_by` (string) - Sort field (name, price, category, stock_quantity, created_at)
-   `sort_order` (string) - Sort direction (asc, desc)

**Response (200):**

```json
{
    "success": true,
    "message": "Items retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "Chicken Rice",
            "description": "Delicious steamed chicken with fragrant rice",
            "price": "12.50",
            "category": "Main Course",
            "stock_quantity": 50,
            "is_active": true,
            "is_available": true,
            "is_low_stock": false,
            "created_at": "2025-10-01T10:00:00.000000Z",
            "updated_at": "2025-10-13T10:00:00.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "per_page": 15,
        "to": 1,
        "total": 1
    }
}
```

### 2. Get Single Item

**GET** `/admin/items/{id}`

**Response (200):**

```json
{
    "success": true,
    "message": "Item retrieved successfully",
    "data": {
        "id": 1,
        "name": "Chicken Rice",
        "description": "Delicious steamed chicken with fragrant rice",
        "price": "12.50",
        "category": "Main Course",
        "stock_quantity": 50,
        "is_active": true,
        "is_available": true,
        "is_low_stock": false,
        "created_at": "2025-10-01T10:00:00.000000Z",
        "updated_at": "2025-10-13T10:00:00.000000Z",
        "order_items": [
            {
                "id": 1,
                "order_id": 1,
                "quantity": 2,
                "unit_price": "12.50",
                "total_price": "25.00"
            }
        ]
    }
}
```

### 3. Create Item

**POST** `/admin/items`

**Request Body:**

```json
{
    "name": "Chicken Rice",
    "description": "Delicious steamed chicken with fragrant rice",
    "price": 12.5,
    "category": "Main Course",
    "stock_quantity": 50,
    "is_active": true
}
```

**Response (201):**

```json
{
    "success": true,
    "message": "Item created successfully",
    "data": {
        "id": 1,
        "name": "Chicken Rice",
        "description": "Delicious steamed chicken with fragrant rice",
        "price": "12.50",
        "category": "Main Course",
        "stock_quantity": 50,
        "is_active": true,
        "is_available": true,
        "is_low_stock": false,
        "created_at": "2025-10-13T10:00:00.000000Z",
        "updated_at": "2025-10-13T10:00:00.000000Z"
    }
}
```

### 4. Update Item

**PUT** `/admin/items/{id}`

**Request Body:**

```json
{
    "name": "Premium Chicken Rice",
    "description": "Premium steamed chicken with fragrant rice and vegetables",
    "price": 15.0,
    "category": "Main Course",
    "stock_quantity": 30,
    "is_active": true
}
```

**Response (200):** Same structure as Create Item response

### 5. Delete Item

**DELETE** `/admin/items/{id}`

**Response (200):**

```json
{
    "success": true,
    "message": "Item deleted successfully"
}
```

### 6. Get Item Categories

**GET** `/admin/items-categories`

**Response (200):**

```json
{
    "success": true,
    "data": [
        {
            "category": "Main Course",
            "count": 15
        },
        {
            "category": "Appetizer",
            "count": 8
        },
        {
            "category": "Dessert",
            "count": 5
        },
        {
            "category": "Beverage",
            "count": 12
        }
    ]
}
```

### 7. Update Item Stock

**PUT** `/admin/items/{id}/stock`

**Request Body:**

```json
{
    "stock_quantity": 25,
    "operation": "set"
}
```

**Available operations:** `set`, `add`, `subtract`

**Response (200):**

```json
{
    "success": true,
    "message": "Stock updated successfully",
    "data": {
        "id": 1,
        "name": "Chicken Rice",
        "previous_stock": 50,
        "new_stock": 25,
        "operation": "set"
    }
}
```

### 8. Check Item Availability

**POST** `/admin/items/check-availability`

**Request Body:**

```json
{
    "items": [
        { "id": 1, "quantity": 2 },
        { "id": 2, "quantity": 1 }
    ]
}
```

**Response (200):**

```json
{
    "success": true,
    "data": {
        "available": true,
        "items": [
            {
                "item_id": 1,
                "name": "Chicken Rice",
                "requested_quantity": 2,
                "available_quantity": 50,
                "available": true
            },
            {
                "item_id": 2,
                "name": "Fried Rice",
                "requested_quantity": 1,
                "available_quantity": 0,
                "available": false
            }
        ]
    }
}
```

### 9. Get Low Stock Items

**GET** `/admin/items-low-stock`

**Query Parameters:**

-   `threshold` (int, default: 10) - Stock threshold

**Response (200):**

```json
{
    "success": true,
    "data": [
        {
            "id": 2,
            "name": "Fried Rice",
            "stock_quantity": 5,
            "threshold": 10,
            "category": "Main Course"
        }
    ]
}
```

### 10. Search Items

**GET** `/admin/search/items`

**Query Parameters:**

-   `query` (string) - Search term
-   `category` (string) - Filter by category
-   `min_price` (decimal) - Minimum price
-   `max_price` (decimal) - Maximum price

**Response (200):** Same as Get All Items

---

## Order Management

### 1. Get All Orders

**GET** `/admin/orders`

**Query Parameters:**

-   `per_page` (int, default: 15) - Items per page
-   `search` (string) - Search in order number, customer name
-   `status` (string) - Filter by status (pending, processing, completed, cancelled)
-   `payment_status` (string) - Filter by payment status (unpaid, partial, paid, refunded)
-   `customer_id` (int) - Filter by customer
-   `min_amount` (decimal) - Minimum total amount
-   `max_amount` (decimal) - Maximum total amount
-   `from_date` (date) - Orders from date
-   `to_date` (date) - Orders to date
-   `sort_by` (string) - Sort field (order_number, total_amount, created_at, status)
-   `sort_order` (string) - Sort direction (asc, desc)

**Response (200):**

```json
{
    "success": true,
    "message": "Orders retrieved successfully",
    "data": [
        {
            "id": 1,
            "order_number": "ORD-001",
            "customer": {
                "id": 1,
                "name": "John Doe",
                "email": "john.doe@example.com"
            },
            "status": "completed",
            "subtotal": "33.00",
            "discount_type": "percentage",
            "discount_value": "10.00",
            "total_amount": "29.70",
            "payment_status": "paid",
            "notes": "Customer requested extra rice",
            "created_at": "2025-10-13T09:00:00.000000Z",
            "updated_at": "2025-10-13T10:00:00.000000Z",
            "items_count": 2,
            "payments_sum": "29.70"
        }
    ],
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "per_page": 15,
        "to": 1,
        "total": 1
    }
}
```

### 2. Get Single Order

**GET** `/admin/orders/{id}`

**Response (200):**

```json
{
    "success": true,
    "message": "Order retrieved successfully",
    "data": {
        "id": 1,
        "order_number": "ORD-001",
        "customer": {
            "id": 1,
            "name": "John Doe",
            "email": "john.doe@example.com",
            "phone": "+1234567890"
        },
        "status": "completed",
        "subtotal": "33.00",
        "discount_type": "percentage",
        "discount_value": "10.00",
        "total_amount": "29.70",
        "payment_status": "paid",
        "notes": "Customer requested extra rice",
        "created_at": "2025-10-13T09:00:00.000000Z",
        "updated_at": "2025-10-13T10:00:00.000000Z",
        "order_items": [
            {
                "id": 1,
                "item": {
                    "id": 1,
                    "name": "Chicken Rice",
                    "category": "Main Course"
                },
                "quantity": 2,
                "unit_price": "12.50",
                "total_price": "25.00"
            },
            {
                "id": 2,
                "item": {
                    "id": 2,
                    "name": "Fried Rice",
                    "category": "Main Course"
                },
                "quantity": 1,
                "unit_price": "8.00",
                "total_price": "8.00"
            }
        ],
        "payments": [
            {
                "id": 1,
                "amount": "29.70",
                "payment_method": "cash",
                "payment_date": "2025-10-13T10:00:00.000000Z",
                "notes": "Full payment received"
            }
        ]
    }
}
```

### 3. Create Order

**POST** `/admin/orders`

**Request Body:**

```json
{
    "customer_id": 1,
    "items": [
        {
            "item_id": 1,
            "quantity": 2,
            "unit_price": 12.5
        },
        {
            "item_id": 2,
            "quantity": 1,
            "unit_price": 8.0
        }
    ],
    "discount_type": "percentage",
    "discount_value": 10,
    "notes": "Customer requested extra rice"
}
```

**Response (201):**

```json
{
    "success": true,
    "message": "Order created successfully",
    "data": {
        "id": 1,
        "order_number": "ORD-001",
        "customer": {
            "id": 1,
            "name": "John Doe",
            "email": "john.doe@example.com"
        },
        "status": "pending",
        "subtotal": "33.00",
        "discount_type": "percentage",
        "discount_value": "10.00",
        "total_amount": "29.70",
        "payment_status": "unpaid",
        "notes": "Customer requested extra rice",
        "created_at": "2025-10-13T10:00:00.000000Z",
        "updated_at": "2025-10-13T10:00:00.000000Z",
        "order_items": [
            {
                "id": 1,
                "item": {
                    "id": 1,
                    "name": "Chicken Rice"
                },
                "quantity": 2,
                "unit_price": "12.50",
                "total_price": "25.00"
            },
            {
                "id": 2,
                "item": {
                    "id": 2,
                    "name": "Fried Rice"
                },
                "quantity": 1,
                "unit_price": "8.00",
                "total_price": "8.00"
            }
        ]
    }
}
```

### 4. Update Order

**PUT** `/admin/orders/{id}`

**Request Body:** Same as Create Order

**Response (200):** Same structure as Create Order response

### 5. Delete Order

**DELETE** `/admin/orders/{id}`

**Response (200):**

```json
{
    "success": true,
    "message": "Order deleted successfully"
}
```

### 6. Get Orders Summary

**GET** `/admin/orders-summary`

**Query Parameters:**

-   `period` (string) - Summary period (day, week, month, year)
-   `status` (string) - Filter by status
-   `from_date` (date) - Summary from date
-   `to_date` (date) - Summary to date

**Response (200):**

```json
{
    "success": true,
    "data": {
        "total_orders": 150,
        "total_revenue": "4500.00",
        "average_order_value": "30.00",
        "status_breakdown": {
            "pending": 10,
            "processing": 5,
            "completed": 130,
            "cancelled": 5
        },
        "payment_status_breakdown": {
            "unpaid": 8,
            "partial": 7,
            "paid": 130,
            "refunded": 5
        },
        "daily_summary": [
            {
                "date": "2025-10-13",
                "orders_count": 15,
                "revenue": "450.00"
            }
        ]
    }
}
```

### 7. Update Order Status

**PUT** `/admin/orders/{id}/status`

**Request Body:**

```json
{
    "status": "completed",
    "notes": "Order delivered successfully"
}
```

**Response (200):**

```json
{
    "success": true,
    "message": "Order status updated successfully",
    "data": {
        "id": 1,
        "previous_status": "processing",
        "new_status": "completed",
        "notes": "Order delivered successfully"
    }
}
```

### 8. Update Order Payment Status

**PUT** `/admin/orders/{id}/payment-status`

**Request Body:**

```json
{
    "payment_status": "paid",
    "notes": "Payment received in full"
}
```

**Response (200):**

```json
{
    "success": true,
    "message": "Order payment status updated successfully",
    "data": {
        "id": 1,
        "previous_payment_status": "partial",
        "new_payment_status": "paid",
        "notes": "Payment received in full"
    }
}
```

### 9. Search Orders

**GET** `/admin/search/orders`

**Query Parameters:**

-   `query` (string) - Search term
-   `customer_id` (int) - Filter by customer
-   `status` (string) - Filter by status
-   `from_date` (date) - Orders from date
-   `to_date` (date) - Orders to date

**Response (200):** Same as Get All Orders

---

## Payment Management

### 1. Get Order Payments

**GET** `/admin/orders/{order}/payments`

**Response (200):**

```json
{
    "success": true,
    "message": "Payments retrieved successfully",
    "data": [
        {
            "id": 1,
            "order_id": 1,
            "amount": "25.00",
            "payment_method": "cash",
            "notes": "Partial payment received",
            "payment_date": "2025-10-13T10:30:00.000000Z",
            "created_at": "2025-10-13T10:30:00.000000Z",
            "updated_at": "2025-10-13T10:30:00.000000Z"
        }
    ]
}
```

### 2. Get Single Payment

**GET** `/admin/orders/{order}/payments/{payment}`

**Response (200):**

```json
{
    "success": true,
    "message": "Payment retrieved successfully",
    "data": {
        "id": 1,
        "order": {
            "id": 1,
            "order_number": "ORD-001",
            "customer_name": "John Doe"
        },
        "amount": "25.00",
        "payment_method": "cash",
        "notes": "Partial payment received",
        "payment_date": "2025-10-13T10:30:00.000000Z",
        "created_at": "2025-10-13T10:30:00.000000Z",
        "updated_at": "2025-10-13T10:30:00.000000Z"
    }
}
```

### 3. Create Payment

**POST** `/admin/orders/{order}/payments`

**Request Body:**

```json
{
    "amount": 25.0,
    "payment_method": "cash",
    "notes": "Partial payment received",
    "payment_date": "2025-10-13T10:30:00Z"
}
```

**Response (201):**

```json
{
    "success": true,
    "message": "Payment created successfully",
    "data": {
        "id": 1,
        "order_id": 1,
        "amount": "25.00",
        "payment_method": "cash",
        "notes": "Partial payment received",
        "payment_date": "2025-10-13T10:30:00.000000Z",
        "created_at": "2025-10-13T10:30:00.000000Z",
        "updated_at": "2025-10-13T10:30:00.000000Z"
    }
}
```

### 4. Update Payment

**PUT** `/admin/orders/{order}/payments/{payment}`

**Request Body:**

```json
{
    "amount": 30.0,
    "payment_method": "card",
    "notes": "Updated payment amount",
    "payment_date": "2025-10-13T10:30:00Z"
}
```

**Response (200):** Same structure as Create Payment response

### 5. Delete Payment

**DELETE** `/admin/orders/{order}/payments/{payment}`

**Response (200):**

```json
{
    "success": true,
    "message": "Payment deleted successfully"
}
```

### 6. Get Payment Summary for Order

**GET** `/admin/orders/{order}/payments/summary`

**Response (200):**

```json
{
    "success": true,
    "data": {
        "order": {
            "id": 1,
            "order_number": "ORD-001",
            "total_amount": "29.70"
        },
        "payment_summary": {
            "total_paid": "25.00",
            "remaining_balance": "4.70",
            "payment_count": 1,
            "payment_methods": [
                {
                    "method": "cash",
                    "amount": "25.00",
                    "count": 1
                }
            ]
        }
    }
}
```

---

## Dashboard

### 1. Get Dashboard Data

**GET** `/admin/dashboard`

**Response (200):**

```json
{
    "success": true,
    "data": {
        "summary": {
            "total_customers": 45,
            "total_items": 28,
            "total_orders": 150,
            "total_revenue": "4500.00",
            "pending_orders": 10,
            "low_stock_items": 3
        },
        "recent_orders": [
            {
                "id": 1,
                "order_number": "ORD-001",
                "customer_name": "John Doe",
                "total_amount": "29.70",
                "status": "completed",
                "created_at": "2025-10-13T09:00:00.000000Z"
            }
        ],
        "top_items": [
            {
                "id": 1,
                "name": "Chicken Rice",
                "category": "Main Course",
                "orders_count": 45,
                "revenue": "562.50"
            }
        ],
        "revenue_chart": [
            {
                "date": "2025-10-01",
                "revenue": "150.00"
            },
            {
                "date": "2025-10-02",
                "revenue": "200.00"
            }
        ]
    }
}
```

### 2. Get Recent Activity

**GET** `/admin/dashboard/recent-activity`

**Query Parameters:**

-   `limit` (int, default: 10) - Number of activities to return

**Response (200):**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "type": "order_created",
            "title": "New Order Created",
            "description": "Order ORD-001 created by John Doe",
            "data": {
                "order_id": 1,
                "order_number": "ORD-001",
                "customer_name": "John Doe",
                "total_amount": "29.70"
            },
            "created_at": "2025-10-13T10:00:00.000000Z"
        },
        {
            "id": 2,
            "type": "payment_received",
            "title": "Payment Received",
            "description": "Payment of $25.00 received for Order ORD-001",
            "data": {
                "payment_id": 1,
                "order_number": "ORD-001",
                "amount": "25.00",
                "payment_method": "cash"
            },
            "created_at": "2025-10-13T10:30:00.000000Z"
        }
    ]
}
```

---

## Search

### 1. Global Search

**GET** `/admin/search/global`

**Query Parameters:**

-   `query` (string) - Search term
-   `limit` (int, default: 20) - Maximum results per type

**Response (200):**

```json
{
    "success": true,
    "data": {
        "customers": [
            {
                "id": 1,
                "name": "John Doe",
                "email": "john.doe@example.com",
                "type": "customer"
            }
        ],
        "items": [
            {
                "id": 1,
                "name": "Chicken Rice",
                "category": "Main Course",
                "price": "12.50",
                "type": "item"
            }
        ],
        "orders": [
            {
                "id": 1,
                "order_number": "ORD-001",
                "customer_name": "John Doe",
                "total_amount": "29.70",
                "type": "order"
            }
        ]
    }
}
```

### 2. Search Suggestions

**GET** `/admin/search/suggestions`

**Query Parameters:**

-   `query` (string) - Search term
-   `type` (string) - Suggestion type (items, customers, orders, all)

**Response (200):**

```json
{
    "success": true,
    "data": [
        {
            "text": "Chicken Rice",
            "type": "item",
            "id": 1
        },
        {
            "text": "John Doe",
            "type": "customer",
            "id": 1
        },
        {
            "text": "ORD-001",
            "type": "order",
            "id": 1
        }
    ]
}
```

---

## Error Logging

### 1. Log Error (Authenticated)

**POST** `/admin/errors`

**Request Body:**

```json
{
    "message": "Database connection failed",
    "level": "error",
    "context": {
        "user_id": 1,
        "action": "create_order",
        "trace": "Stack trace here..."
    }
}
```

**Response (201):**

```json
{
    "success": true,
    "message": "Error logged successfully",
    "data": {
        "id": 1,
        "message": "Database connection failed",
        "level": "error",
        "context": {
            "user_id": 1,
            "action": "create_order",
            "trace": "Stack trace here..."
        },
        "created_at": "2025-10-13T10:00:00.000000Z"
    }
}
```

### 2. Log Error Batch (Authenticated)

**POST** `/admin/errors/batch`

**Request Body:**

```json
{
    "errors": [
        {
            "message": "Validation failed",
            "level": "warning",
            "context": { "field": "email" }
        },
        {
            "message": "API timeout",
            "level": "error",
            "context": { "endpoint": "/api/orders" }
        }
    ]
}
```

**Response (201):**

```json
{
    "success": true,
    "message": "Errors logged successfully",
    "data": {
        "logged_count": 2,
        "errors": [
            {
                "id": 1,
                "message": "Validation failed",
                "level": "warning"
            },
            {
                "id": 2,
                "message": "API timeout",
                "level": "error"
            }
        ]
    }
}
```

### 3. Get Error Statistics

**GET** `/admin/errors/stats`

**Query Parameters:**

-   `period` (string) - Period (day, week, month, year)
-   `level` (string) - Error level filter

**Response (200):**

```json
{
    "success": true,
    "data": {
        "total_errors": 150,
        "level_breakdown": {
            "error": 45,
            "warning": 75,
            "info": 30
        },
        "daily_breakdown": [
            {
                "date": "2025-10-13",
                "error_count": 5,
                "warning_count": 10,
                "info_count": 3
            }
        ],
        "top_errors": [
            {
                "message": "Database connection failed",
                "count": 15,
                "level": "error"
            }
        ]
    }
}
```

### 4. Log Public Error (Unauthenticated)

**POST** `/errors/public`

**Request Body:**

```json
{
    "message": "Frontend JavaScript error",
    "level": "error",
    "context": {
        "url": "/dashboard",
        "browser": "Chrome 118.0.0.0",
        "error": "TypeError: Cannot read property 'length' of undefined"
    }
}
```

**Response (201):**

```json
{
    "success": true,
    "message": "Error logged successfully"
}
```

### 5. Health Check

**GET** `/errors/health`

**Response (200):**

```json
{
    "success": true,
    "message": "Error logging service is healthy",
    "data": {
        "status": "ok",
        "timestamp": "2025-10-13T10:00:00.000000Z"
    }
}
```

---

## Error Responses

### Validation Errors (422)

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password field is required."]
    }
}
```

### Unauthorized (401)

```json
{
    "message": "Unauthenticated."
}
```

### Forbidden (403)

```json
{
    "message": "This action is unauthorized."
}
```

### Not Found (404)

```json
{
    "success": false,
    "message": "Resource not found"
}
```

### Server Error (500)

```json
{
    "success": false,
    "message": "Internal server error occurred"
}
```

---

## Status Codes

-   **200** - Success
-   **201** - Created
-   **400** - Bad Request
-   **401** - Unauthorized
-   **403** - Forbidden
-   **404** - Not Found
-   **422** - Validation Error
-   **500** - Internal Server Error

---

## Data Types

### Order Status

-   `pending` - Order created but not processed
-   `processing` - Order is being prepared
-   `completed` - Order is finished and delivered
-   `cancelled` - Order was cancelled

### Payment Status

-   `unpaid` - No payments received
-   `partial` - Some payments received but incomplete
-   `paid` - Fully paid
-   `refunded` - Payment was refunded

### Payment Methods

-   `cash` - Cash payment
-   `card` - Credit/Debit card
-   `bank_transfer` - Bank transfer
-   `digital_wallet` - Digital wallet (e.g., PayPal, Apple Pay)

### Discount Types

-   `percentage` - Percentage discount (e.g., 10%)
-   `fixed` - Fixed amount discount (e.g., $5.00)

### Error Levels

-   `error` - Critical errors
-   `warning` - Warning messages
-   `info` - Informational messages
-   `debug` - Debug information

---

## Rate Limiting

The API implements rate limiting to prevent abuse:

-   **Authentication endpoints**: 5 requests per minute
-   **General API endpoints**: 60 requests per minute
-   **Search endpoints**: 30 requests per minute

Rate limit headers are included in responses:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1697198400
```

---

## Pagination

List endpoints support pagination with the following parameters:

-   `page` (int) - Page number (default: 1)
-   `per_page` (int) - Items per page (default: 15, max: 100)

Pagination metadata is included in responses:

```json
{
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 5,
        "per_page": 15,
        "to": 15,
        "total": 75
    },
    "links": {
        "first": "http://localhost:8000/api/admin/customers?page=1",
        "last": "http://localhost:8000/api/admin/customers?page=5",
        "prev": null,
        "next": "http://localhost:8000/api/admin/customers?page=2"
    }
}
```

---

## Sorting and Filtering

Most list endpoints support sorting and filtering:

### Sorting

-   `sort_by` - Field to sort by
-   `sort_order` - Sort direction (`asc` or `desc`)

### Common Filters

-   `search` - General search across relevant fields
-   Date ranges: `from_date`, `to_date`, `created_from`, `created_to`
-   Numeric ranges: `min_price`, `max_price`, `min_amount`, `max_amount`
-   Boolean filters: `is_active`, `has_orders`
-   Status filters: `status`, `payment_status`

---

## Testing

Use the provided `http_calls.http` file with VS Code REST Client extension to test all endpoints. Make sure to:

1. Start your Laravel development server: `php artisan serve`
2. Update the `@token` variable with a valid authentication token
3. Ensure your database is migrated and seeded with test data

---

## Additional Notes

-   All timestamps are in UTC and follow ISO 8601 format
-   Decimal values are returned as strings to maintain precision
-   Soft deleted records are excluded from default queries unless specifically requested
-   The API follows RESTful conventions where applicable
-   All request and response bodies use JSON format
-   Error messages are designed to be user-friendly and actionable
