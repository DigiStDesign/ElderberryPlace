```mermaid
flowchart TD
    A[Staff logs in] --> B{Credentials valid?}
    B -->|No| C[Show error]
    B -->|Yes| D[Staff Dashboard]
    D --> E[View My Roster]
