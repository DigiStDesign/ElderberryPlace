```mermaid
flowchart TD
    A0[Admin Dashboard] --> A1[Manage Users]
    A1 --> A2[Add/Edit/Delete Visitors]
    A1 --> A3[Add/Edit/Delete Residents]
    A1 --> A4[Add/Edit/Delete Staff]

    A0 --> A5[Manage Services]
    A5 --> A6[Add/Edit service offerings]
    A5 --> A7[Add/Edit service categories]

    A0 --> A8[Schedule Sessions]
    A8 --> A9[Pick service + staff]
    A9 --> A10[Set date/time/location/notes]
    A10 --> A11[Create session]

    A0 --> A12[Assign Residents]
    A12 --> A13[Select session]
    A13 --> A14[Add resident]
