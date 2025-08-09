```mermaid
flowchart TD
    R0[Resident Dashboard] --> R1[View visitation requests for me]
    R1 --> R2{Request status?}
    R2 -->|PENDING| R3[Approve or Decline]
    R2 -->|APPROVED| R4[Option: Cancel]
    R2 -->|DECLINED/CANCELLED| R5[No actions]
    R3 --> R6[Update status -> Notify visitor]
    R4 --> R7[Set status to CANCELLED -> Notify visitor]

    R0 --> R8[Book into a service session]
    R8 --> R9[See available sessions]
    R9 --> R10[Check availability]
    R10 -->|Available| R11[Confirm booking]
    R10 -->|Conflict| R12[Show alternatives]

    R0 --> R13[My Services]
    R13 --> R14[Upcoming / In‑progress / Completed list]
