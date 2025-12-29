#!/usr/bin/env python3
"""
Fix cash validation tests in Get Voucher Details requests.

Each folder should test for its specific cash validation rules.
"""

import json
from pathlib import Path

# Define expected cash validation per folder
FOLDER_CASH_VALIDATION = {
    "01 - Simplest Voucher (₱100)": {"secret": None, "mobile": None},
    "02 - Basic Settings - Bulk (₱1000 for 10 vouchers)": {"secret": None, "mobile": None},
    "03 - Input Fields - Email (₱100 + ₱2.20)": {"secret": None, "mobile": None},
    "03 - Input Fields - Mobile (₱100 + ₱2.30)": {"secret": None, "mobile": None},
    "03 - Input Fields - Name (₱100 + ₱2.40)": {"secret": None, "mobile": None},
    "03 - Input Fields - Location (₱100 + ₱3.00)": {"secret": None, "mobile": None},
    "03 - Input Fields - Signature (₱100 + ₱2.80)": {"secret": None, "mobile": None},
    "03 - Input Fields - Selfie (₱100 + ₱4.00)": {"secret": None, "mobile": None},
    "04 - Input Fields - Basic KYC (₱100 + ₱6.90)": {"secret": None, "mobile": None},
    "04 - Input Fields - Identity Verification (₱100 + ₱7.50)": {"secret": None, "mobile": None},
    "04 - Input Fields - Digital Signature (₱100 + ₱7.30)": {"secret": None, "mobile": None},
    "04 - Input Fields - Full Profile (₱100 + ₱12.20)": {"secret": None, "mobile": None},
    "05 - Feedback - Email (₱100 + ₱1.00)": {"secret": None, "mobile": None},
    "05 - Feedback - Mobile (₱100 + ₱1.80)": {"secret": None, "mobile": None},
    "05 - Feedback - Webhook (₱100 + ₱1.90)": {"secret": None, "mobile": None},
    "05 - Feedback - Email + Mobile + Webhook (₱100 + ₱4.70)": {"secret": None, "mobile": None},
    "06 - Cash Validation - Secret (₱100 + ₱1.20)": {"secret": "SECRET123", "mobile": None},
    "06 - Cash Validation - Mobile (₱100 + ₱1.30)": {"secret": None, "mobile": "+639171234567"},
    "06 - Cash Validation - Both (₱100 + ₱2.50)": {"secret": "SECRET123", "mobile": "+639171234567"},
    "07 - Settlement Rail - INSTAPAY / Absorb": {"secret": None, "mobile": None},
    "07 - Settlement Rail - INSTAPAY / Include": {"secret": None, "mobile": None},
    "07 - Settlement Rail - PESONET / Absorb": {"secret": None, "mobile": None},
    "08 - Rider - Message (₱100 + ₱2.00)": {"secret": None, "mobile": None},
    "08 - Rider - Url (₱100 + ₱2.10)": {"secret": None, "mobile": None},
    "08 - Rider - Full (₱100 + ₱4.10)": {"secret": None, "mobile": None},
    "09 - Validation - Location (₱100 + ₱3.00)": {"secret": None, "mobile": None},
    "09 - Validation - Time (₱100 + ₱2.50)": {"secret": None, "mobile": None},
    "11 - Complex Scenario (₱572.50 total)": {"secret": "COMPLEX123", "mobile": None},
}

def generate_cash_validation_test(validation):
    """Generate test code for cash validation."""
    if validation["secret"] is None and validation["mobile"] is None:
        # No validation
        return [
            'pm.test("Cash validation empty (no validation)", function () {',
            '    pm.expect(voucher.instructions.cash.validation.secret).to.be.null;',
            '    pm.expect(voucher.instructions.cash.validation.mobile).to.be.null;',
            '    pm.expect(voucher.instructions.cash.validation.location).to.be.null;',
            '    pm.expect(voucher.instructions.cash.validation.radius).to.be.null;',
            '    pm.expect(voucher.instructions.cash.validation.country).to.equal(\'PH\');',
            '});',
            ''
        ]
    else:
        tests = []
        
        # Secret validation
        if validation["secret"]:
            tests.extend([
                'pm.test("Cash validation secret configured", function () {',
                f'    pm.expect(voucher.instructions.cash.validation.secret).to.equal("{validation["secret"]}");',
                '});',
                ''
            ])
        else:
            tests.extend([
                'pm.test("No secret validation", function () {',
                '    pm.expect(voucher.instructions.cash.validation.secret).to.be.null;',
                '});',
                ''
            ])
        
        # Mobile validation
        if validation["mobile"]:
            tests.extend([
                'pm.test("Cash validation mobile configured", function () {',
                f'    pm.expect(voucher.instructions.cash.validation.mobile).to.equal("{validation["mobile"]}");',
                '});',
                ''
            ])
        else:
            tests.extend([
                'pm.test("No mobile validation", function () {',
                '    pm.expect(voucher.instructions.cash.validation.mobile).to.be.null;',
                '});',
                ''
            ])
        
        # Common validations
        tests.extend([
            'pm.test("Other cash validations empty", function () {',
            '    pm.expect(voucher.instructions.cash.validation.location).to.be.null;',
            '    pm.expect(voucher.instructions.cash.validation.radius).to.be.null;',
            '    pm.expect(voucher.instructions.cash.validation.country).to.equal(\'PH\');',
            '});',
            ''
        ])
        
        return tests

def fix_voucher_details_cash_validation_tests(folder):
    """Fix the cash validation tests in Get Voucher Details request."""
    
    folder_name = folder.get('name', '')
    expected_validation = FOLDER_CASH_VALIDATION.get(folder_name)
    
    if expected_validation is None:
        return False
    
    for request in folder.get('item', []):
        if request['name'] == 'Get Voucher Details':
            for event in request.get('event', []):
                if event.get('listen') == 'test':
                    script_lines = event['script']['exec']
                    
                    new_lines = []
                    skip_validation_section = False
                    validation_section_found = False
                    
                    for i, line in enumerate(script_lines):
                        # Find "Cash validation empty" test
                        if 'pm.test("Cash validation empty' in line:
                            validation_section_found = True
                            skip_validation_section = True
                            new_lines.extend(generate_cash_validation_test(expected_validation))
                            continue
                        
                        # Skip until closing brace
                        if skip_validation_section:
                            if line.strip() == '});':
                                skip_validation_section = False
                            continue
                        
                        new_lines.append(line)
                    
                    event['script']['exec'] = new_lines
                    return validation_section_found
    return False

def main():
    collection_path = Path(__file__).parent.parent / 'docs' / 'postman' / 'redeem-x-e2e-generation-billing.postman_collection.json'
    
    print(f"📖 Reading collection: {collection_path}")
    with open(collection_path, 'r', encoding='utf-8') as f:
        collection = json.load(f)
    
    folders_updated = 0
    for folder in collection.get('item', []):
        folder_name = folder.get('name', '')
        if folder_name in FOLDER_CASH_VALIDATION:
            if fix_voucher_details_cash_validation_tests(folder):
                validation = FOLDER_CASH_VALIDATION[folder_name]
                validations = []
                if validation["secret"]: validations.append(f"secret={validation['secret']}")
                if validation["mobile"]: validations.append(f"mobile={validation['mobile']}")
                validations_str = ', '.join(validations) if validations else 'none'
                print(f"  ✏️  {folder_name}: [{validations_str}]")
                folders_updated += 1
    
    print(f"💾 Writing updated collection...")
    with open(collection_path, 'w', encoding='utf-8') as f:
        json.dump(collection, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Fixed {folders_updated} folders")
    print(f"")
    print(f"Each folder now tests for its specific cash validation rules")

if __name__ == '__main__':
    main()
