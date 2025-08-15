```mermaid
flowchart TD
    V0[Visitor Dashboard] --> V1[Request a visitation]
    V1 --> V2[Select Resident]
    V2 --> V3[Pick date/time + notes]
    V3 --> V4[Submit]
    V4 --> V5[Status = PENDING]
    V5 -->|Resident approves| V6[Status = APPROVED]
    V5 -->|Resident declines| V7[Status = DECLINED]
    V6 --> V8[Visitor sees upcoming visits]