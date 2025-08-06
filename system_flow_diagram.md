# School Facility Booking System - System Flow Diagram (SFD)

## Overview
This document presents the comprehensive System Flow Diagram for the School Facility Booking System (SFB), illustrating the complete system architecture, user interactions, data flows, and business processes.

## 1. High-Level System Architecture

```mermaid
graph TB
    subgraph "Client Layer"
        WB[Web Browser]
        MB[Mobile Browser]
    end
    
    subgraph "Presentation Layer"
        UI[User Interface]
        subgraph "Pages"
            LP[Login Page]
            DP[Dashboard]
            BP[Booking Page]
            MP[Management Pages]
            AP[Admin Panel]
        end
    end
    
    subgraph "Application Layer"
        subgraph "Core Components"
            AUTH[Authentication System]
            BM[Booking Manager]
            FM[Facility Manager]
            UM[User Manager]
            NS[Notification System]
        end
        
        subgraph "APIs & Processors"
            PB[process_booking.php]
            PL[process_login.php]
            PP[process_profile.php]
            SE[send_support_email.php]
        end
    end
    
    subgraph "Data Layer"
        DB[(MySQL Database)]
        subgraph "Tables"
            UT[users]
            FT[facilities]
            BT[bookings]
            ST[support_requests]
            BLT[booking_logs]
        end
    end
    
    subgraph "External Services"
        SMTP[SMTP Server]
        EMAIL[Email Service]
    end
    
    WB --> UI
    MB --> UI
    UI --> AUTH
    UI --> BM
    UI --> FM
    UI --> UM
    
    AUTH --> PL
    BM --> PB
    FM --> MP
    UM --> PP
    
    PB --> DB
    PL --> DB
    PP --> DB
    SE --> SMTP
    
    NS --> EMAIL
    SMTP --> EMAIL
    
    DB --> UT
    DB --> FT
    DB --> BT
    DB --> ST
    DB --> BLT
```

## 2. User Role-Based System Flow

```mermaid
graph TD
    START([User Accesses System]) --> LOGIN{Login Required?}
    LOGIN -->|Yes| LOGINPAGE[Login Page]
    LOGIN -->|No| DASHBOARD[Dashboard]
    
    LOGINPAGE --> AUTH{Authentication}
    AUTH -->|Success| ROLE{User Role?}
    AUTH -->|Failure| LOGINPAGE
    
    ROLE -->|Admin| ADMIN_DASH[Admin Dashboard]
    ROLE -->|Faculty| FACULTY_DASH[Faculty Dashboard]
    ROLE -->|Staff| STAFF_DASH[Staff Dashboard]
    
    subgraph "Admin Functions"
        ADMIN_DASH --> MANAGE_USERS[Manage Users]
        ADMIN_DASH --> MANAGE_FACILITIES[Manage Facilities]
        ADMIN_DASH --> APPROVE_BOOKINGS[Approve Bookings]
        ADMIN_DASH --> ANALYTICS[View Analytics]
        ADMIN_DASH --> SUPPORT_MGMT[Support Management]
    end
    
    subgraph "Faculty Functions"
        FACULTY_DASH --> CREATE_BOOKING[Create Booking]
        FACULTY_DASH --> VIEW_BOOKINGS[View My Bookings]
        FACULTY_DASH --> EDIT_PROFILE[Edit Profile]
        FACULTY_DASH --> BOOKING_HISTORY[Booking History]
    end
    
    subgraph "Staff Functions"
        STAFF_DASH --> CREATE_BOOKING_LIMITED[Create Limited Booking]
        STAFF_DASH --> VIEW_BOOKINGS_LIMITED[View My Bookings]
        STAFF_DASH --> EDIT_PROFILE_LIMITED[Edit Profile]
    end
    
    CREATE_BOOKING --> CONFLICT_CHECK{Check Conflicts}
    CREATE_BOOKING_LIMITED --> CONFLICT_CHECK
    
    CONFLICT_CHECK -->|No Conflict| SUBMIT_BOOKING[Submit Booking]
    CONFLICT_CHECK -->|Conflict Found| BOOKING_ERROR[Show Conflict Error]
    
    SUBMIT_BOOKING --> PENDING_STATUS[Booking Status: Pending]
    PENDING_STATUS --> ADMIN_APPROVAL{Admin Approval}
    
    ADMIN_APPROVAL -->|Approved| APPROVED_STATUS[Status: Approved]
    ADMIN_APPROVAL -->|Rejected| REJECTED_STATUS[Status: Rejected]
    
    APPROVED_STATUS --> EMAIL_NOTIFICATION[Send Email Notification]
    REJECTED_STATUS --> EMAIL_NOTIFICATION
    
    EMAIL_NOTIFICATION --> END([Process Complete])
    BOOKING_ERROR --> CREATE_BOOKING
```

## 3. Booking Process Flow

```mermaid
sequenceDiagram
    participant User
    participant UI as User Interface
    participant Auth as Authentication
    participant BookingMgr as Booking Manager
    participant ConflictChk as Conflict Checker
    participant DB as Database
    participant EmailSvc as Email Service
    participant Admin
    
    User->>UI: Access Booking Page
    UI->>Auth: Verify User Session
    Auth-->>UI: User Authenticated
    
    User->>UI: Fill Booking Form
    User->>UI: Submit Booking Request
    
    UI->>BookingMgr: Process Booking Data
    BookingMgr->>ConflictChk: Check for Conflicts
    ConflictChk->>DB: Query Existing Bookings
    DB-->>ConflictChk: Return Booking Data
    
    alt No Conflicts
        ConflictChk-->>BookingMgr: No Conflicts Found
        BookingMgr->>DB: Insert New Booking
        DB-->>BookingMgr: Booking Created (Pending)
        BookingMgr-->>UI: Booking Submitted Successfully
        UI-->>User: Show Success Message
        
        BookingMgr->>EmailSvc: Send Notification to Admin
        EmailSvc->>Admin: Email: New Booking Request
        
        Admin->>UI: Review Booking Request
        Admin->>BookingMgr: Approve/Reject Booking
        BookingMgr->>DB: Update Booking Status
        
        BookingMgr->>EmailSvc: Send Status Update
        EmailSvc->>User: Email: Booking Status Update
    else Conflicts Found
        ConflictChk-->>BookingMgr: Conflicts Detected
        BookingMgr-->>UI: Return Conflict Error
        UI-->>User: Show Conflict Message
    end
```

## 4. Data Flow Diagram

```mermaid
graph LR
    subgraph "Input Sources"
        USER_INPUT[User Input]
        ADMIN_INPUT[Admin Input]
        SYSTEM_INPUT[System Generated]
    end
    
    subgraph "Processing Layer"
        VALIDATION[Input Validation]
        BUSINESS_LOGIC[Business Logic Processing]
        CONFLICT_RESOLUTION[Conflict Resolution]
        NOTIFICATION_PROC[Notification Processing]
    end
    
    subgraph "Data Storage"
        USER_DATA[(User Data)]
        FACILITY_DATA[(Facility Data)]
        BOOKING_DATA[(Booking Data)]
        LOG_DATA[(System Logs)]
        EMAIL_QUEUE[(Email Queue)]
    end
    
    subgraph "Output Destinations"
        USER_INTERFACE[User Interface]
        EMAIL_SYSTEM[Email Notifications]
        REPORTS[Reports & Analytics]
        AUDIT_LOGS[Audit Logs]
    end
    
    USER_INPUT --> VALIDATION
    ADMIN_INPUT --> VALIDATION
    SYSTEM_INPUT --> VALIDATION
    
    VALIDATION --> BUSINESS_LOGIC
    BUSINESS_LOGIC --> CONFLICT_RESOLUTION
    BUSINESS_LOGIC --> NOTIFICATION_PROC
    
    BUSINESS_LOGIC --> USER_DATA
    BUSINESS_LOGIC --> FACILITY_DATA
    BUSINESS_LOGIC --> BOOKING_DATA
    BUSINESS_LOGIC --> LOG_DATA
    
    NOTIFICATION_PROC --> EMAIL_QUEUE
    
    USER_DATA --> USER_INTERFACE
    FACILITY_DATA --> USER_INTERFACE
    BOOKING_DATA --> USER_INTERFACE
    BOOKING_DATA --> REPORTS
    
    EMAIL_QUEUE --> EMAIL_SYSTEM
    LOG_DATA --> AUDIT_LOGS
    
    USER_INTERFACE --> USER_INPUT
    REPORTS --> ADMIN_INPUT
```

## 5. Database Entity Relationship Flow

```mermaid
erDiagram
    USERS ||--o{ BOOKINGS : creates
    USERS ||--o{ SUPPORT_REQUESTS : submits
    FACILITIES ||--o{ BOOKINGS : "booked for"
    BOOKINGS ||--o{ BOOKING_LOGS : generates
    BOOKINGS ||--o{ BOOKING_MATERIALS : "may require"
    MATERIALS ||--o{ BOOKING_MATERIALS : "used in"
    
    USERS {
        int id PK
        string username
        string email
        string password_hash
        enum role
        enum status
        datetime created_at
    }
    
    FACILITIES {
        int id PK
        string name
        text description
        int capacity
        string location
        string type
        enum status
    }
    
    BOOKINGS {
        int id PK
        int facility_id FK
        int user_id FK
        datetime start_time
        datetime end_time
        int attendees_count
        text purpose
        enum status
        datetime created_at
    }
    
    SUPPORT_REQUESTS {
        int id PK
        int user_id FK
        string subject
        text message
        enum status
        datetime created_at
    }
    
    BOOKING_LOGS {
        int id PK
        int booking_id FK
        string action
        text details
        datetime created_at
    }
    
    BOOKING_MATERIALS {
        int id PK
        int booking_id FK
        int material_id FK
        int quantity
    }
    
    MATERIALS {
        int id PK
        string name
        text description
        int available_quantity
    }
```

## 6. Security and Authentication Flow

```mermaid
graph TD
    START([User Access]) --> HTTPS_CHECK{HTTPS Connection?}
    HTTPS_CHECK -->|No| REDIRECT_HTTPS[Redirect to HTTPS]
    HTTPS_CHECK -->|Yes| SESSION_CHECK{Valid Session?}
    
    REDIRECT_HTTPS --> SESSION_CHECK
    
    SESSION_CHECK -->|No| LOGIN_FORM[Display Login Form]
    SESSION_CHECK -->|Yes| CSRF_CHECK{Valid CSRF Token?}
    
    LOGIN_FORM --> CREDENTIALS[User Enters Credentials]
    CREDENTIALS --> VALIDATE_CREDS{Validate Credentials}
    
    VALIDATE_CREDS -->|Invalid| LOGIN_ERROR[Show Login Error]
    VALIDATE_CREDS -->|Valid| CREATE_SESSION[Create User Session]
    
    LOGIN_ERROR --> LOGIN_FORM
    CREATE_SESSION --> GENERATE_CSRF[Generate CSRF Token]
    
    CSRF_CHECK -->|Invalid| CSRF_ERROR[CSRF Error]
    CSRF_CHECK -->|Valid| ROLE_CHECK{Check User Role}
    
    CSRF_ERROR --> LOGIN_FORM
    GENERATE_CSRF --> ROLE_CHECK
    
    ROLE_CHECK --> ADMIN_ACCESS[Admin Dashboard]
    ROLE_CHECK --> FACULTY_ACCESS[Faculty Dashboard]
    ROLE_CHECK --> STAFF_ACCESS[Staff Dashboard]
    
    subgraph "Security Measures"
        SQL_INJECTION[SQL Injection Prevention]
        XSS_PROTECTION[XSS Protection]
        INPUT_VALIDATION[Input Validation]
        PASSWORD_HASHING[Password Hashing]
    end
    
    ADMIN_ACCESS --> SQL_INJECTION
    FACULTY_ACCESS --> SQL_INJECTION
    STAFF_ACCESS --> SQL_INJECTION
```

## 7. Email Notification Flow

```mermaid
graph TD
    TRIGGER[Notification Trigger] --> EMAIL_TYPE{Email Type?}
    
    EMAIL_TYPE -->|Booking Status| BOOKING_EMAIL[Booking Status Email]
    EMAIL_TYPE -->|Faculty Approval| APPROVAL_EMAIL[Faculty Approval Email]
    EMAIL_TYPE -->|Support Request| SUPPORT_EMAIL[Support Request Email]
    EMAIL_TYPE -->|Password Reset| RESET_EMAIL[Password Reset Email]
    
    BOOKING_EMAIL --> TEMPLATE_BOOKING[Load Booking Template]
    APPROVAL_EMAIL --> TEMPLATE_APPROVAL[Load Approval Template]
    SUPPORT_EMAIL --> TEMPLATE_SUPPORT[Load Support Template]
    RESET_EMAIL --> TEMPLATE_RESET[Load Reset Template]
    
    TEMPLATE_BOOKING --> POPULATE_DATA[Populate Email Data]
    TEMPLATE_APPROVAL --> POPULATE_DATA
    TEMPLATE_SUPPORT --> POPULATE_DATA
    TEMPLATE_RESET --> POPULATE_DATA
    
    POPULATE_DATA --> SMTP_CONFIG[Configure SMTP]
    SMTP_CONFIG --> SEND_EMAIL[Send Email via PHPMailer]
    
    SEND_EMAIL --> EMAIL_SUCCESS{Email Sent?}
    EMAIL_SUCCESS -->|Yes| LOG_SUCCESS[Log Success]
    EMAIL_SUCCESS -->|No| LOG_ERROR[Log Error]
    
    LOG_SUCCESS --> EMAIL_QUEUE[Update Email Queue]
    LOG_ERROR --> RETRY_QUEUE[Add to Retry Queue]
    
    EMAIL_QUEUE --> END_PROCESS([Process Complete])
    RETRY_QUEUE --> END_PROCESS
```

## 8. System Integration Points

```mermaid
graph TB
    subgraph "Internal Components"
        CORE[Core Application]
        AUTH_SYS[Authentication System]
        BOOKING_SYS[Booking System]
        NOTIFICATION_SYS[Notification System]
        ADMIN_SYS[Admin System]
    end
    
    subgraph "External Integrations"
        SMTP_SERVER[SMTP Server]
        EMAIL_PROVIDER[Email Provider]
        DATABASE[MySQL Database]
        WEB_SERVER[Apache/Nginx]
        BROWSER[Web Browser]
    end
    
    subgraph "APIs and Endpoints"
        BOOKING_API[Booking API]
        USER_API[User Management API]
        FACILITY_API[Facility API]
        SUPPORT_API[Support API]
    end
    
    BROWSER <--> WEB_SERVER
    WEB_SERVER <--> CORE
    
    CORE <--> AUTH_SYS
    CORE <--> BOOKING_SYS
    CORE <--> NOTIFICATION_SYS
    CORE <--> ADMIN_SYS
    
    AUTH_SYS <--> DATABASE
    BOOKING_SYS <--> DATABASE
    ADMIN_SYS <--> DATABASE
    
    NOTIFICATION_SYS <--> SMTP_SERVER
    SMTP_SERVER <--> EMAIL_PROVIDER
    
    BOOKING_SYS <--> BOOKING_API
    AUTH_SYS <--> USER_API
    ADMIN_SYS <--> FACILITY_API
    NOTIFICATION_SYS <--> SUPPORT_API
```

## 9. Error Handling and Recovery Flow

```mermaid
graph TD
    ERROR_OCCURRED[Error Occurred] --> ERROR_TYPE{Error Type?}
    
    ERROR_TYPE -->|Database Error| DB_ERROR[Database Connection Error]
    ERROR_TYPE -->|Validation Error| VALIDATION_ERROR[Input Validation Error]
    ERROR_TYPE -->|Authentication Error| AUTH_ERROR[Authentication Error]
    ERROR_TYPE -->|System Error| SYSTEM_ERROR[System/Server Error]
    
    DB_ERROR --> LOG_DB_ERROR[Log Database Error]
    VALIDATION_ERROR --> LOG_VALIDATION[Log Validation Error]
    AUTH_ERROR --> LOG_AUTH_ERROR[Log Auth Error]
    SYSTEM_ERROR --> LOG_SYSTEM_ERROR[Log System Error]
    
    LOG_DB_ERROR --> RETRY_DB{Retry Connection?}
    LOG_VALIDATION --> USER_FEEDBACK[Show User-Friendly Message]
    LOG_AUTH_ERROR --> REDIRECT_LOGIN[Redirect to Login]
    LOG_SYSTEM_ERROR --> ADMIN_NOTIFICATION[Notify Administrator]
    
    RETRY_DB -->|Yes| ATTEMPT_RECONNECT[Attempt Reconnection]
    RETRY_DB -->|No| MAINTENANCE_MODE[Enable Maintenance Mode]
    
    ATTEMPT_RECONNECT --> CONNECTION_SUCCESS{Connection Restored?}
    CONNECTION_SUCCESS -->|Yes| RESUME_OPERATION[Resume Normal Operation]
    CONNECTION_SUCCESS -->|No| MAINTENANCE_MODE
    
    USER_FEEDBACK --> ERROR_RECOVERY[Allow User to Correct Input]
    REDIRECT_LOGIN --> LOGIN_PAGE[Display Login Page]
    ADMIN_NOTIFICATION --> ADMIN_INTERVENTION[Admin Investigates]
    
    ERROR_RECOVERY --> NORMAL_FLOW[Return to Normal Flow]
    LOGIN_PAGE --> NORMAL_FLOW
    MAINTENANCE_MODE --> ADMIN_INTERVENTION
    RESUME_OPERATION --> NORMAL_FLOW
    ADMIN_INTERVENTION --> SYSTEM_REPAIR[System Repair/Update]
    SYSTEM_REPAIR --> NORMAL_FLOW
```

## 10. Performance and Monitoring Flow

```mermaid
graph LR
    subgraph "Monitoring Points"
        PAGE_LOAD[Page Load Time]
        DB_QUERIES[Database Query Performance]
        EMAIL_DELIVERY[Email Delivery Status]
        USER_ACTIVITY[User Activity Tracking]
        SYSTEM_RESOURCES[System Resource Usage]
    end
    
    subgraph "Data Collection"
        PERFORMANCE_LOGS[Performance Logs]
        ERROR_LOGS[Error Logs]
        ACCESS_LOGS[Access Logs]
        BOOKING_METRICS[Booking Metrics]
    end
    
    subgraph "Analysis & Reporting"
        ANALYTICS_ENGINE[Analytics Engine]
        DASHBOARD_METRICS[Dashboard Metrics]
        PERFORMANCE_REPORTS[Performance Reports]
        USAGE_STATISTICS[Usage Statistics]
    end
    
    PAGE_LOAD --> PERFORMANCE_LOGS
    DB_QUERIES --> PERFORMANCE_LOGS
    EMAIL_DELIVERY --> ACCESS_LOGS
    USER_ACTIVITY --> ACCESS_LOGS
    SYSTEM_RESOURCES --> PERFORMANCE_LOGS
    
    PERFORMANCE_LOGS --> ANALYTICS_ENGINE
    ERROR_LOGS --> ANALYTICS_ENGINE
    ACCESS_LOGS --> ANALYTICS_ENGINE
    BOOKING_METRICS --> ANALYTICS_ENGINE
    
    ANALYTICS_ENGINE --> DASHBOARD_METRICS
    ANALYTICS_ENGINE --> PERFORMANCE_REPORTS
    ANALYTICS_ENGINE --> USAGE_STATISTICS
    
    DASHBOARD_METRICS --> ADMIN_DASHBOARD[Admin Dashboard]
    PERFORMANCE_REPORTS --> SYSTEM_OPTIMIZATION[System Optimization]
    USAGE_STATISTICS --> BUSINESS_INSIGHTS[Business Insights]
```

## Conclusion

This System Flow Diagram provides a comprehensive view of the School Facility Booking System architecture, covering:

1. **High-level system architecture** showing all major components
2. **User role-based flows** for different user types (Admin, Faculty, Staff)
3. **Detailed booking process flow** with sequence diagrams
4. **Data flow patterns** throughout the system
5. **Database relationships** and entity interactions
6. **Security and authentication mechanisms**
7. **Email notification workflows**
8. **System integration points**
9. **Error handling and recovery procedures**
10. **Performance monitoring and analytics**

The diagrams use Mermaid syntax for easy rendering and maintenance, providing both technical details for developers and high-level overviews for stakeholders.