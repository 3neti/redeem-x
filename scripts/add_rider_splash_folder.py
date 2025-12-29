#!/usr/bin/env python3
"""
Add new Postman folder: 08 - Rider - Splash (₱100 + ₱2.20)
Tests rider splash screen functionality with base64-encoded image.
"""

import json
import sys
from pathlib import Path

COLLECTION_PATH = Path(__file__).parent.parent / "docs/postman/redeem-x-e2e-generation-billing.postman_collection.json"

def main():
    with open(COLLECTION_PATH, 'r') as f:
        collection = json.load(f)
    
    # Use first folder as template
    template_folder = collection['item'][0]
    
    # Create new folder by deep copying template
    import copy
    new_folder = copy.deepcopy(template_folder)
    new_folder['name'] = '08 - Rider - Splash (₱100 + ₱2.20)'
    
    # Update folder description
    new_folder['description'] = 'Tests rider splash screen functionality. Voucher includes custom splash screen displayed after redemption with configurable timeout.'
    
    # Find and update "Generate Voucher" request
    for req in new_folder['item']:
        if 'Generate Voucher' in req['name']:
            # Update request body with rider splash parameters
            body_raw = json.loads(req['request']['body']['raw'])
            
            # Set voucher parameters
            body_raw['voucher_amount'] = 100  # ₱100
            body_raw['voucher_count'] = 1
            
            # Add rider splash configuration
            # Using a minimal base64-encoded 1x1 red pixel PNG for testing
            body_raw['rider_splash'] = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg=='
            body_raw['rider_splash_timeout'] = 5  # 5 seconds display
            
            req['request']['body']['raw'] = json.dumps(body_raw, indent=2)
        
        # Update "Get System Balances (After)" assertions
        elif 'Get System Balances (After)' in req['name']:
            for event in req.get('event', []):
                if event['listen'] == 'test':
                    # Update test script to expect correct totals
                    test_script = event['script']['exec']
                    
                    # Find and update the totals
                    for i, line in enumerate(test_script):
                        if 'const voucherTotal = 100;' in line:
                            test_script[i] = '    const voucherTotal = 100; // ₱100'
                        elif 'const instructionFee = 0;' in line:
                            test_script[i] = '    const instructionFee = 220; // ₱2.20 (rider.splash)'
    
    # Find insertion point (after last "08 - Rider" folder)
    insert_idx = 0
    for i, item in enumerate(collection['item']):
        if item['name'].startswith('08 - Rider'):
            insert_idx = i + 1
    
    # Insert new folder
    collection['item'].insert(insert_idx, new_folder)
    
    # Write back
    with open(COLLECTION_PATH, 'w') as f:
        json.dump(collection, f, indent=2)
    
    print(f"✅ Added folder: {new_folder['name']}")
    print(f"   Inserted at position {insert_idx}")
    print(f"   Total folders now: {len(collection['item'])}")

if __name__ == '__main__':
    main()
