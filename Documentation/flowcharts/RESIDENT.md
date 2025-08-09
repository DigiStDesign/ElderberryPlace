```mermaid
flowchart TD
    R1[Resident Login] --> R2[Dashboard]
    R2 --> R3[View Today's Schedule]
    R2 --> R4[View Upcoming Visits]
    R4 --> R5{Respond to visit?}
    R5 -->|Yes| R6[Accept/Decline visit]
    R5 -->|No| R2
    R2 --> R7[Book a Service]
    R7 --> R8[Select Required Service From List]
    R8 --> R9[View List of Upcoming Sessions]
    R9 --> R10[Book Session]
    R10 --> R2
    R2 --> RX[Logout]