# Restaurant Queue Management System - System Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                        RESTAURANT QUEUE SYSTEM                          │
│                                                                         │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                           FRONTEND LAYER                                │
│                                                                         │
├─────────────────┬─────────────────────────────┬─────────────────────────┤
│                 │                             │                         │
│  Customer UI    │     Staff UI                │   Queue Display UI      │
│  - Remote Queue │     - Login                 │   - Current Numbers     │
│  - View Status  │     - Onsite Queue          │   - Waiting Times       │
│  - SMS Links    │     - Manage Queue          │   - Public Display      │
│                 │     - Reports & Stats       │                         │
│                 │                             │                         │
└─────────────────┴─────────────────────────────┴─────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                         RESTful API LAYER                                │
│                                                                         │
├─────────────────┬─────────────────────────────┬─────────────────────────┤
│                 │                             │                         │
│  Auth Service   │     Queue Service           │   Notification Service  │
│  - JWT Auth     │     - Resource-based        │   - SMS Integration     │
│  - Role-based   │     - HATEOAS Links         │   - Queue Updates       │
│  - Login/Logout │     - Blacklist Mgmt        │   - Verification Codes  │
│  - Permissions  │     - Statistics            │                         │
│                 │                             │                         │
└─────────────────┴─────────────────────────────┴─────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                                                                         │
│                          DATABASE LAYER                                 │
│                                                                         │
├─────────────────┬─────────────────────────────┬─────────────────────────┤
│                 │                             │                         │
│  User Data      │     Queue Data              │   System Data           │
│  - Employees    │     - Queue Tickets         │   - Queue Status        │
│  - Customers    │     - Table Types           │   - Statistics          │
│  - Blacklist    │     - SMS Logs              │   - Verification Codes  │
│                 │                             │                         │
└─────────────────┴─────────────────────────────┴─────────────────────────┘
```

## Data Flow

1. **Customer Flow (Remote Queue)**:
   - Customer visits website → Enters phone number → Receives SMS with verification code
   - Enters verification code → Creates queue ticket → Receives SMS with queue status and link
   - Opens link to check real-time queue status → Receives notification when their turn is approaching

2. **Customer Flow (Onsite Queue)**:
   - Staff logs in → Creates queue ticket for customer → System sends SMS with queue status and link
   - Customer can view real-time queue status on displays or via SMS link

3. **Staff Flow**:
   - Staff logs in → Views current queue → Calls next customer → Updates ticket status (seated/no-show)
   - Staff can view statistics, manage blacklist, and handle queue operations

4. **System Flow**:
   - System tracks queue status → Updates waiting times → Sends notifications
   - System resets queue numbers daily → Updates statistics → Manages blacklist automatically

## Integration Points

1. **SMS Gateway Integration**:
   - System integrates with SMS provider to send verification codes and queue updates

2. **Authentication System**:
   - JWT-based authentication for staff access with role-based permissions
   - Login and logout functionality with token management

3. **RESTful API**:
   - Resource-oriented design with consistent URL structure
   - HATEOAS links for API discoverability
   - OpenAPI specification for documentation

4. **Database Integration**:
   - MySQL database with optimized schema and indexing for performance

## Security Measures

1. **Authentication**:
   - JWT tokens with expiration
   - Role-based access control
   - Password hashing
   - Secure logout mechanism

2. **Verification**:
   - Phone verification for remote queue
   - Time-limited verification codes
   - Protection against brute force attacks

3. **API Security**:
   - Input validation
   - Rate limiting
   - HTTPS encryption
   - Proper HTTP status codes
   - RESTful resource protection
