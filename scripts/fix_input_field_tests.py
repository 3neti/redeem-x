#!/usr/bin/env python3
"""
Fix input field validation tests in Get Voucher Details requests.

Each folder should test for its specific input fields, not "no input fields".
"""

import json
from pathlib import Path

# Define expected input fields per folder
FOLDER_INPUT_FIELDS = {
    "01 - Simplest Voucher (₱100)": [],
    "02 - Basic Settings - Bulk (₱1000 for 10 vouchers)": [],
    "03 - Input Fields - Email (₱100 + ₱2.20)": ["email"],
    "03 - Input Fields - Mobile (₱100 + ₱2.30)": ["mobile"],
    "03 - Input Fields - Name (₱100 + ₱2.40)": ["name"],
    "03 - Input Fields - Location (₱100 + ₱3.00)": ["location"],
    "03 - Input Fields - Signature (₱100 + ₱2.80)": ["signature"],
    "03 - Input Fields - Selfie (₱100 + ₱4.00)": ["selfie"],
    "04 - Input Fields - Basic KYC (₱100 + ₱6.90)": ["email", "mobile", "name"],
    "04 - Input Fields - Identity Verification (₱100 + ₱7.50)": ["email", "mobile", "selfie"],
    "04 - Input Fields - Digital Signature (₱100 + ₱7.30)": ["email", "name", "signature"],
    "04 - Input Fields - Full Profile (₱100 + ₱12.20)": ["email", "mobile", "name", "address", "birth_date"],
    "05 - Feedback - Email (₱100 + ₱1.00)": [],
    "05 - Feedback - Mobile (₱100 + ₱1.80)": [],
    "05 - Feedback - Webhook (₱100 + ₱1.90)": [],
    "05 - Feedback - Email + Mobile + Webhook (₱100 + ₱4.70)": [],
    "06 - Cash Validation - Secret (₱100 + ₱1.20)": [],
    "06 - Cash Validation - Mobile (₱100 + ₱1.30)": [],
    "06 - Cash Validation - Both (₱100 + ₱2.50)": [],
    "07 - Settlement Rail - INSTAPAY / Absorb": [],
    "07 - Settlement Rail - INSTAPAY / Include": [],
    "07 - Settlement Rail - PESONET / Absorb": [],
    "08 - Rider - Message (₱100 + ₱2.00)": [],
    "08 - Rider - Url (₱100 + ₱2.10)": [],
    "08 - Rider - Full (₱100 + ₱4.10)": [],
    "09 - Validation - Location (₱100 + ₱3.00)": [],
    "09 - Validation - Time (₱100 + ₱2.50)": [],
    "11 - Complex Scenario (₱572.50 total)": ["email", "mobile", "name", "location", "signature"],
}

def generate_input_field_test(fields):
    """Generate test code for input field validation."""
    if not fields:
        # No input fields
        return [
            'pm.test("No input fields (simplest)", function () {',
            '    pm.expect(voucher.instructions.inputs.fields).to.be.an(\'array\');',
            '    pm.expect(voucher.instructions.inputs.fields).to.have.lengthOf(0);',
            '});'
        ]
    else:
        # Has input fields
        fields_str = ', '.join([f'"{f}"' for f in fields])
        test_name = f"Input fields: {', '.join(fields)}"
        return [
            f'pm.test("{test_name}", function () {{',
            '    pm.expect(voucher.instructions.inputs.fields).to.be.an(\'array\');',
            f'    pm.expect(voucher.instructions.inputs.fields).to.have.lengthOf({len(fields)});',
            f'    const expectedFields = [{fields_str}];',
            '    expectedFields.forEach(field => {',
            '        pm.expect(voucher.instructions.inputs.fields, `Should include ${field}`).to.include(field);',
            '    });',
            '});'
        ]

def fix_voucher_details_test(folder):
    """Fix the input field test in Get Voucher Details request."""
    
    folder_name = folder.get('name', '')
    expected_fields = FOLDER_INPUT_FIELDS.get(folder_name)
    
    if expected_fields is None:
        return False
    
    for request in folder.get('item', []):
        if request['name'] == 'Get Voucher Details':
            for event in request.get('event', []):
                if event.get('listen') == 'test':
                    script_lines = event['script']['exec']
                    
                    # Find and replace the "No input fields (simplest)" test
                    new_lines = []
                    skip_until_closing = False
                    replaced = False
                    
                    for i, line in enumerate(script_lines):
                        if skip_until_closing:
                            if line.strip() == '});':
                                skip_until_closing = False
                                # Insert new test
                                if not replaced:
                                    new_lines.extend(generate_input_field_test(expected_fields))
                                    replaced = True
                            continue
                        
                        # Find the test to replace
                        if 'pm.test("No input fields (simplest)"' in line or 'pm.test("Input fields:' in line:
                            skip_until_closing = True
                            continue
                        
                        new_lines.append(line)
                    
                    event['script']['exec'] = new_lines
                    return True
    return False

def main():
    collection_path = Path(__file__).parent.parent / 'docs' / 'postman' / 'redeem-x-e2e-generation-billing.postman_collection.json'
    
    print(f"📖 Reading collection: {collection_path}")
    with open(collection_path, 'r', encoding='utf-8') as f:
        collection = json.load(f)
    
    folders_updated = 0
    for folder in collection.get('item', []):
        folder_name = folder.get('name', '')
        if folder_name in FOLDER_INPUT_FIELDS:
            if fix_voucher_details_test(folder):
                fields = FOLDER_INPUT_FIELDS[folder_name]
                fields_str = ', '.join(fields) if fields else 'none'
                print(f"  ✏️  {folder_name}: [{fields_str}]")
                folders_updated += 1
    
    print(f"💾 Writing updated collection...")
    with open(collection_path, 'w', encoding='utf-8') as f:
        json.dump(collection, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Fixed {folders_updated} folders")
    print(f"")
    print(f"Each folder now tests for its specific input fields")

if __name__ == '__main__':
    main()
