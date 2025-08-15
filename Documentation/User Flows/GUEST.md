```mermaid
flowchart TD
    A[Guest visits site] --> B[View facility info]
    B --> C{Choose action}
    C -->|Log in| D[Login page: enter credentials]
    C -->|Register as Visitor| E[Visitor registration form]
    D --> F{Credentials valid?}
    F -->|Yes| G[Redirect to role dashboard]
    F -->|No| H[Show error]
    E --> I[Create account]
    I --> J[Login as Visitor]
    J --> G