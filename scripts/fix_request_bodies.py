#!/usr/bin/env python3
"""
Fix Generate Voucher request bodies to match each folder's test scenario.
"""

import json
from pathlib import Path

# Define request body per folder (only the parts that differ)
FOLDER_BODIES = {
    "01 - Simplest Voucher (₱100)": {
        "amount": 100,
        "count": 1
    },
    "02 - Basic Settings - Bulk (₱1000 for 10 vouchers)": {
        "amount": 100,
        "count": 10,
        "prefix": "PROMO",
        "mask": "***-***"
    },
    "03 - Input Fields - Email (₱100 + ₱2.20)": {
        "amount": 100,
        "count": 1,
        "input_fields": ["email"]
    },
    "03 - Input Fields - Mobile (₱100 + ₱2.30)": {
        "amount": 100,
        "count": 1,
        "input_fields": ["mobile"]
    },
    "03 - Input Fields - Name (₱100 + ₱2.40)": {
        "amount": 100,
        "count": 1,
        "input_fields": ["name"]
    },
    "03 - Input Fields - Location (₱100 + ₱3.00)": {
        "amount": 100,
        "count": 1,
        "input_fields": ["location"]
    },
    "03 - Input Fields - Signature (₱100 + ₱2.80)": {
        "amount": 100,
        "count": 1,
        "input_fields": ["signature"]
    },
    "03 - Input Fields - Selfie (₱100 + ₱4.00)": {
        "amount": 100,
        "count": 1,
        "input_fields": ["selfie"]
    },
    "04 - Input Fields - Basic KYC (₱100 + ₱6.90)": {
        "amount": 100,
        "count": 1,
        "input_fields": ["email", "mobile", "name"]
    },
    "04 - Input Fields - Identity Verification (₱100 + ₱7.50)": {
        "amount": 100,
        "count": 1,
        "input_fields": ["email", "mobile", "selfie"]
    },
    "04 - Input Fields - Digital Signature (₱100 + ₱7.30)": {
        "amount": 100,
        "count": 1,
        "input_fields": ["email", "name", "signature"]
    },
    "04 - Input Fields - Full Profile (₱100 + ₱12.20)": {
        "amount": 100,
        "count": 1,
        "input_fields": ["email", "mobile", "name", "address", "birth_date"]
    },
    "05 - Feedback - Email (₱100 + ₱1.00)": {
        "amount": 100,
        "count": 1,
        "feedback_email": "feedback@example.com"
    },
    "05 - Feedback - Mobile (₱100 + ₱1.80)": {
        "amount": 100,
        "count": 1,
        "feedback_mobile": "+639171234567"
    },
    "05 - Feedback - Webhook (₱100 + ₱1.90)": {
        "amount": 100,
        "count": 1,
        "feedback_webhook": "https://webhook.site/test"
    },
    "05 - Feedback - Email + Mobile + Webhook (₱100 + ₱4.70)": {
        "amount": 100,
        "count": 1,
        "feedback_email": "feedback@example.com",
        "feedback_mobile": "+639171234567",
        "feedback_webhook": "https://webhook.site/test"
    },
    "06 - Cash Validation - Secret (₱100 + ₱1.20)": {
        "amount": 100,
        "count": 1,
        "validation_secret": "SECRET123"
    },
    "06 - Cash Validation - Mobile (₱100 + ₱1.30)": {
        "amount": 100,
        "count": 1,
        "validation_mobile": "+639171234567"
    },
    "06 - Cash Validation - Both (₱100 + ₱2.50)": {
        "amount": 100,
        "count": 1,
        "validation_secret": "SECRET123",
        "validation_mobile": "+639171234567"
    },
    "07 - Settlement Rail - INSTAPAY / Absorb": {
        "amount": 100,
        "count": 1,
        "settlement_rail": "INSTAPAY",
        "fee_strategy": "absorb"
    },
    "07 - Settlement Rail - INSTAPAY / Include": {
        "amount": 100,
        "count": 1,
        "settlement_rail": "INSTAPAY",
        "fee_strategy": "include"
    },
    "07 - Settlement Rail - PESONET / Absorb": {
        "amount": 100,
        "count": 1,
        "settlement_rail": "PESONET",
        "fee_strategy": "absorb"
    },
    "08 - Rider - Message (₱100 + ₱2.00)": {
        "amount": 100,
        "count": 1,
        "rider_message": "Thank you for redeeming!"
    },
    "08 - Rider - Url (₱100 + ₱2.10)": {
        "amount": 100,
        "count": 1,
        "rider_url": "https://example.com/thankyou"
    },
    "08 - Rider - Full (₱100 + ₱4.10)": {
        "amount": 100,
        "count": 1,
        "rider_message": "Thank you!",
        "rider_url": "https://example.com/thankyou"
    },
    "09 - Validation - Location (₱100 + ₱3.00)": {
        "amount": 100,
        "count": 1,
        "validation_location": "14.5995,120.9842",
        "validation_radius": 100
    },
    "09 - Validation - Time (₱100 + ₱2.50)": {
        "amount": 100,
        "count": 1,
        "starts_at": "2025-01-01 00:00:00",
        "expires_at": "2025-12-31 23:59:59"
    },
    "11 - Complex Scenario (₱572.50 total)": {
        "amount": 572.50,
        "count": 1,
        "input_fields": ["email", "mobile", "name", "location", "signature"],
        "feedback_email": "feedback@example.com",
        "feedback_mobile": "+639171234567",
        "validation_secret": "COMPLEX123",
        "settlement_rail": "INSTAPAY",
        "rider_message": "Complex scenario test"
    }
}

def fix_generate_voucher_body(folder):
    """Update the Generate Voucher request body."""
    
    folder_name = folder.get('name', '')
    body_config = FOLDER_BODIES.get(folder_name)
    
    if not body_config:
        return False
    
    for request in folder.get('item', []):
        if request['name'] == 'Generate Voucher':
            if 'body' in request['request'] and request['request']['body']['mode'] == 'raw':
                # Convert body config to JSON string
                body_json = json.dumps(body_config, indent=2)
                request['request']['body']['raw'] = body_json
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
        if folder_name in FOLDER_BODIES:
            if fix_generate_voucher_body(folder):
                body_config = FOLDER_BODIES[folder_name]
                keys = ', '.join(body_config.keys())
                print(f"  ✏️  {folder_name}: {keys}")
                folders_updated += 1
    
    print(f"💾 Writing updated collection...")
    with open(collection_path, 'w', encoding='utf-8') as f:
        json.dump(collection, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Fixed {folders_updated} folders")
    print(f"")
    print(f"Each folder now has the correct request body for its test scenario")

if __name__ == '__main__':
    main()
