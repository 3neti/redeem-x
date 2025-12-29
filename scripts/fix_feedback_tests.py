#!/usr/bin/env python3
"""
Fix feedback validation tests in Get Voucher Details requests.

Each folder should test for its specific feedback channels.
"""

import json
from pathlib import Path

# Define expected feedback per folder
FOLDER_FEEDBACK = {
    "01 - Simplest Voucher (₱100)": {"email": None, "mobile": None, "webhook": None},
    "02 - Basic Settings - Bulk (₱1000 for 10 vouchers)": {"email": None, "mobile": None, "webhook": None},
    "03 - Input Fields - Email (₱100 + ₱2.20)": {"email": None, "mobile": None, "webhook": None},
    "03 - Input Fields - Mobile (₱100 + ₱2.30)": {"email": None, "mobile": None, "webhook": None},
    "03 - Input Fields - Name (₱100 + ₱2.40)": {"email": None, "mobile": None, "webhook": None},
    "03 - Input Fields - Location (₱100 + ₱3.00)": {"email": None, "mobile": None, "webhook": None},
    "03 - Input Fields - Signature (₱100 + ₱2.80)": {"email": None, "mobile": None, "webhook": None},
    "03 - Input Fields - Selfie (₱100 + ₱4.00)": {"email": None, "mobile": None, "webhook": None},
    "04 - Input Fields - Basic KYC (₱100 + ₱6.90)": {"email": None, "mobile": None, "webhook": None},
    "04 - Input Fields - Identity Verification (₱100 + ₱7.50)": {"email": None, "mobile": None, "webhook": None},
    "04 - Input Fields - Digital Signature (₱100 + ₱7.30)": {"email": None, "mobile": None, "webhook": None},
    "04 - Input Fields - Full Profile (₱100 + ₱12.20)": {"email": None, "mobile": None, "webhook": None},
    "05 - Feedback - Email (₱100 + ₱1.00)": {"email": "feedback@example.com", "mobile": None, "webhook": None},
    "05 - Feedback - Mobile (₱100 + ₱1.80)": {"email": None, "mobile": "+639171234567", "webhook": None},
    "05 - Feedback - Webhook (₱100 + ₱1.90)": {"email": None, "mobile": None, "webhook": "https://webhook.site/test"},
    "05 - Feedback - Email + Mobile + Webhook (₱100 + ₱4.70)": {"email": "feedback@example.com", "mobile": "+639171234567", "webhook": "https://webhook.site/test"},
    "06 - Cash Validation - Secret (₱100 + ₱1.20)": {"email": None, "mobile": None, "webhook": None},
    "06 - Cash Validation - Mobile (₱100 + ₱1.30)": {"email": None, "mobile": None, "webhook": None},
    "06 - Cash Validation - Both (₱100 + ₱2.50)": {"email": None, "mobile": None, "webhook": None},
    "07 - Settlement Rail - INSTAPAY / Absorb": {"email": None, "mobile": None, "webhook": None},
    "07 - Settlement Rail - INSTAPAY / Include": {"email": None, "mobile": None, "webhook": None},
    "07 - Settlement Rail - PESONET / Absorb": {"email": None, "mobile": None, "webhook": None},
    "08 - Rider - Message (₱100 + ₱2.00)": {"email": None, "mobile": None, "webhook": None},
    "08 - Rider - Url (₱100 + ₱2.10)": {"email": None, "mobile": None, "webhook": None},
    "08 - Rider - Full (₱100 + ₱4.10)": {"email": None, "mobile": None, "webhook": None},
    "09 - Validation - Location (₱100 + ₱3.00)": {"email": None, "mobile": None, "webhook": None},
    "09 - Validation - Time (₱100 + ₱2.50)": {"email": None, "mobile": None, "webhook": None},
    "11 - Complex Scenario (₱572.50 total)": {"email": "feedback@example.com", "mobile": "+639171234567", "webhook": None},
}

def generate_feedback_tests(feedback):
    """Generate test code for feedback validation."""
    tests = []
    
    # Email feedback test
    if feedback["email"] is None:
        tests.extend([
            'pm.test("No email feedback", function () {',
            '    pm.expect(voucher.instructions.feedback.email).to.be.null;',
            '});',
            ''
        ])
    else:
        tests.extend([
            'pm.test("Email feedback configured", function () {',
            f'    pm.expect(voucher.instructions.feedback.email).to.equal("{feedback["email"]}");',
            '});',
            ''
        ])
    
    # Mobile feedback test
    if feedback["mobile"] is None:
        tests.extend([
            'pm.test("No mobile feedback", function () {',
            '    pm.expect(voucher.instructions.feedback.mobile).to.be.null;',
            '});',
            ''
        ])
    else:
        tests.extend([
            'pm.test("Mobile feedback configured", function () {',
            f'    pm.expect(voucher.instructions.feedback.mobile).to.equal("{feedback["mobile"]}");',
            '});',
            ''
        ])
    
    # Webhook feedback test
    if feedback["webhook"] is None:
        tests.extend([
            'pm.test("No webhook feedback", function () {',
            '    pm.expect(voucher.instructions.feedback.webhook).to.be.null;',
            '});',
            ''
        ])
    else:
        tests.extend([
            'pm.test("Webhook feedback configured", function () {',
            f'    pm.expect(voucher.instructions.feedback.webhook).to.equal("{feedback["webhook"]}");',
            '});',
            ''
        ])
    
    return tests

def fix_voucher_details_feedback_tests(folder):
    """Fix the feedback tests in Get Voucher Details request."""
    
    folder_name = folder.get('name', '')
    expected_feedback = FOLDER_FEEDBACK.get(folder_name)
    
    if expected_feedback is None:
        return False
    
    for request in folder.get('item', []):
        if request['name'] == 'Get Voucher Details':
            for event in request.get('event', []):
                if event.get('listen') == 'test':
                    script_lines = event['script']['exec']
                    
                    new_lines = []
                    skip_feedback_section = False
                    feedback_section_found = False
                    
                    for i, line in enumerate(script_lines):
                        # Find the start of feedback section
                        if '// Feedback channels' in line:
                            feedback_section_found = True
                            skip_feedback_section = True
                            new_lines.append(line)
                            new_lines.extend(generate_feedback_tests(expected_feedback))
                            continue
                        
                        # Skip until we find the next section (// Rider)
                        if skip_feedback_section:
                            if '// Rider' in line:
                                skip_feedback_section = False
                                new_lines.append(line)
                            continue
                        
                        new_lines.append(line)
                    
                    event['script']['exec'] = new_lines
                    return feedback_section_found
    return False

def main():
    collection_path = Path(__file__).parent.parent / 'docs' / 'postman' / 'redeem-x-e2e-generation-billing.postman_collection.json'
    
    print(f"📖 Reading collection: {collection_path}")
    with open(collection_path, 'r', encoding='utf-8') as f:
        collection = json.load(f)
    
    folders_updated = 0
    for folder in collection.get('item', []):
        folder_name = folder.get('name', '')
        if folder_name in FOLDER_FEEDBACK:
            if fix_voucher_details_feedback_tests(folder):
                feedback = FOLDER_FEEDBACK[folder_name]
                channels = []
                if feedback["email"]: channels.append("email")
                if feedback["mobile"]: channels.append("mobile")
                if feedback["webhook"]: channels.append("webhook")
                channels_str = ', '.join(channels) if channels else 'none'
                print(f"  ✏️  {folder_name}: [{channels_str}]")
                folders_updated += 1
    
    print(f"💾 Writing updated collection...")
    with open(collection_path, 'w', encoding='utf-8') as f:
        json.dump(collection, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Fixed {folders_updated} folders")
    print(f"")
    print(f"Each folder now tests for its specific feedback channels")

if __name__ == '__main__':
    main()
