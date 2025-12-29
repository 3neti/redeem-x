#!/usr/bin/env python3
"""
Fix rider validation tests in Get Voucher Details requests.
"""

import json
from pathlib import Path

# Define expected rider per folder
FOLDER_RIDER = {
    "01 - Simplest Voucher (₱100)": {"message": None, "url": None, "splash": None},
    "02 - Basic Settings - Bulk (₱1000 for 10 vouchers)": {"message": None, "url": None, "splash": None},
    "03 - Input Fields - Email (₱100 + ₱2.20)": {"message": None, "url": None, "splash": None},
    "03 - Input Fields - Mobile (₱100 + ₱2.30)": {"message": None, "url": None, "splash": None},
    "03 - Input Fields - Name (₱100 + ₱2.40)": {"message": None, "url": None, "splash": None},
    "03 - Input Fields - Location (₱100 + ₱3.00)": {"message": None, "url": None, "splash": None},
    "03 - Input Fields - Signature (₱100 + ₱2.80)": {"message": None, "url": None, "splash": None},
    "03 - Input Fields - Selfie (₱100 + ₱4.00)": {"message": None, "url": None, "splash": None},
    "04 - Input Fields - Basic KYC (₱100 + ₱6.90)": {"message": None, "url": None, "splash": None},
    "04 - Input Fields - Identity Verification (₱100 + ₱7.50)": {"message": None, "url": None, "splash": None},
    "04 - Input Fields - Digital Signature (₱100 + ₱7.30)": {"message": None, "url": None, "splash": None},
    "04 - Input Fields - Full Profile (₱100 + ₱12.20)": {"message": None, "url": None, "splash": None},
    "05 - Feedback - Email (₱100 + ₱1.00)": {"message": None, "url": None, "splash": None},
    "05 - Feedback - Mobile (₱100 + ₱1.80)": {"message": None, "url": None, "splash": None},
    "05 - Feedback - Webhook (₱100 + ₱1.90)": {"message": None, "url": None, "splash": None},
    "05 - Feedback - Email + Mobile + Webhook (₱100 + ₱4.70)": {"message": None, "url": None, "splash": None},
    "06 - Cash Validation - Secret (₱100 + ₱1.20)": {"message": None, "url": None, "splash": None},
    "06 - Cash Validation - Mobile (₱100 + ₱1.30)": {"message": None, "url": None, "splash": None},
    "06 - Cash Validation - Both (₱100 + ₱2.50)": {"message": None, "url": None, "splash": None},
    "07 - Settlement Rail - INSTAPAY / Absorb": {"message": None, "url": None, "splash": None},
    "07 - Settlement Rail - INSTAPAY / Include": {"message": None, "url": None, "splash": None},
    "07 - Settlement Rail - PESONET / Absorb": {"message": None, "url": None, "splash": None},
    "08 - Rider - Message (₱100 + ₱2.00)": {"message": "Thank you for redeeming!", "url": None, "splash": None},
    "08 - Rider - Url (₱100 + ₱2.10)": {"message": None, "url": "https://example.com/thankyou", "splash": None},
    "08 - Rider - Splash (₱100 + ₱2.20)": {"message": None, "url": None, "splash": "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg=="},
    "08 - Rider - Full (₱100 + ₱4.10)": {"message": "Thank you!", "url": "https://example.com/thankyou", "splash": None},
    "09 - Validation - Location (₱100 + ₱3.00)": {"message": None, "url": None, "splash": None},
    "09 - Validation - Time (₱100 + ₱2.50)": {"message": None, "url": None, "splash": None},
    "11 - Complex Scenario (₱572.50 total)": {"message": "Complex scenario test", "url": None, "splash": None},
}

def generate_rider_test(rider):
    """Generate test code for rider validation."""
    tests = []
    
    # Message test
    if rider["message"] is None:
        tests.extend([
            'pm.test("No rider message", function () {',
            '    pm.expect(voucher.instructions.rider.message).to.be.null;',
            '});',
            ''
        ])
    else:
        tests.extend([
            'pm.test("Rider message configured", function () {',
            f'    pm.expect(voucher.instructions.rider.message).to.equal("{rider["message"]}");',
            '});',
            ''
        ])
    
    # URL test
    if rider["url"] is None:
        tests.extend([
            'pm.test("No rider URL", function () {',
            '    pm.expect(voucher.instructions.rider.url).to.be.null;',
            '});',
            ''
        ])
    else:
        tests.extend([
            'pm.test("Rider URL configured", function () {',
            f'    pm.expect(voucher.instructions.rider.url).to.equal("{rider["url"]}");',
            '});',
            ''
        ])
    
    # Splash test
    if rider["splash"] is None:
        tests.extend([
            'pm.test("No rider splash", function () {',
            '    pm.expect(voucher.instructions.rider.splash).to.be.null;',
            '    pm.expect(voucher.instructions.rider.splash_timeout).to.be.null;',
            '});',
            ''
        ])
    else:
        tests.extend([
            'pm.test("Rider splash configured", function () {',
            f'    pm.expect(voucher.instructions.rider.splash).to.equal("{rider["splash"]}");',
            '    pm.expect(voucher.instructions.rider.splash_timeout).to.be.a("number");',
            '});',
            ''
        ])
    
    # Redirect timeout (always null for our tests)
    tests.extend([
        'pm.test("Rider redirect timeout", function () {',
        '    pm.expect(voucher.instructions.rider.redirect_timeout).to.be.null;',
        '});'
    ])
    
    return tests

def fix_voucher_details_rider_tests(folder):
    """Fix the rider tests in Get Voucher Details request."""
    
    folder_name = folder.get('name', '')
    expected_rider = FOLDER_RIDER.get(folder_name)
    
    if expected_rider is None:
        return False
    
    for request in folder.get('item', []):
        if request['name'] == 'Get Voucher Details':
            for event in request.get('event', []):
                if event.get('listen') == 'test':
                    script_lines = event['script']['exec']
                    
                    new_lines = []
                    skip_rider_section = False
                    rider_section_found = False
                    
                    for i, line in enumerate(script_lines):
                        # Find the start of rider section
                        if '// Rider' in line:
                            rider_section_found = True
                            skip_rider_section = True
                            new_lines.append(line)
                            new_lines.extend(generate_rider_test(expected_rider))
                            new_lines.append('')
                            continue
                        
                        # Skip until we find the next section (// Instruction count)
                        if skip_rider_section:
                            if '// Instruction count' in line or '// Metadata' in line:
                                skip_rider_section = False
                                new_lines.append(line)
                            continue
                        
                        new_lines.append(line)
                    
                    event['script']['exec'] = new_lines
                    return rider_section_found
    return False

def main():
    collection_path = Path(__file__).parent.parent / 'docs' / 'postman' / 'redeem-x-e2e-generation-billing.postman_collection.json'
    
    print(f"📖 Reading collection: {collection_path}")
    with open(collection_path, 'r', encoding='utf-8') as f:
        collection = json.load(f)
    
    folders_updated = 0
    for folder in collection.get('item', []):
        folder_name = folder.get('name', '')
        if folder_name in FOLDER_RIDER:
            if fix_voucher_details_rider_tests(folder):
                rider = FOLDER_RIDER[folder_name]
                fields = []
                if rider["message"]: fields.append(f"message")
                if rider["url"]: fields.append(f"url")
                if rider["splash"]: fields.append(f"splash")
                fields_str = ', '.join(fields) if fields else 'none'
                print(f"  ✏️  {folder_name}: [{fields_str}]")
                folders_updated += 1
    
    print(f"💾 Writing updated collection...")
    with open(collection_path, 'w', encoding='utf-8') as f:
        json.dump(collection, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Fixed {folders_updated} folders")
    print(f"")
    print(f"Each folder now tests for its specific rider configuration")

if __name__ == '__main__':
    main()
