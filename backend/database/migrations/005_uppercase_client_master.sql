UPDATE clients SET
    client_code=UPPER(client_code),
    legal_name=UPPER(legal_name),
    display_name=UPPER(display_name),
    gstin=UPPER(gstin),
    pan=UPPER(pan),
    notes=UPPER(notes);

UPDATE client_locations SET
    location_code=UPPER(location_code),
    location_name=UPPER(location_name),
    address_line_1=UPPER(address_line_1),
    address_line_2=UPPER(address_line_2),
    landmark=UPPER(landmark),
    city=UPPER(city),
    district=UPPER(district),
    state_name=UPPER(state_name),
    gstin=UPPER(gstin);

UPDATE client_contacts SET
    full_name=UPPER(full_name),
    designation=UPPER(designation);
