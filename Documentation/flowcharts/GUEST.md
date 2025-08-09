```mermaid
flowchart TD
    A[Guest visits website] --> B[View facility info]
    B -->|Log in| D[Go to login page]
    D -->F[Are credentials valid?]
    F-->|Yes|H[Redirect to Visitor dashboard]
    F-->|No|G[Show error]-->D
    B -->|Register| E[Go to visitor registration page]
    E-->|Valid|I[User registered successfully]-->H
    E-->|Invalid|J[Show error]-->E