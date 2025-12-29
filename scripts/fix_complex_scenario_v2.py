#!/usr/bin/env python3
"""
Fix Complex Scenario folder - update test assertions.
"""

import json
from pathlib import Path

COLLECTION_PATH = Path(__file__).parent.parent / "docs/postman/redeem-x-e2e-generation-billing.postman_collection.json"

def main():
    with open(COLLECTION_PATH, 'r') as f:
        collection = json.load(f)
    
    for folder in collection['item']:
        if '11 - Complex Scenario' in folder['name']:
            print(f"Processing: {folder['name']}")
            
            for req in folder['item']:
                # Fix Get User Balance (After) test
                if 'Get User Balance (After)' in req['name']:
                    for event in req.get('event', []):
                        if event.get('listen') == 'test':
                            script_lines = event['script']['exec']
                            
                            # Find and replace specific lines
                            for i in range(len(script_lines)):
                                line = script_lines[i]
                                
                                # Fix fee comment
                                if '// Total fees: ₱72.50' in line:
                                    script_lines[i] = '    // Total fees: ₱93.50 (5 vouchers × ₱18.70 per voucher)'
                                    print(f"  ✓ Updated line {i}: fee comment")
                                
                                # Fix fee assertion
                                elif 'pm.expect(feeAmount).to.be.closeTo(72.5, 1.0);' in line:
                                    script_lines[i] = '    pm.expect(feeAmount).to.be.closeTo(93.5, 1.0);'
                                    print(f"  ✓ Updated line {i}: fee assertion")
                                
                                # Fix total comment
                                elif '// Expected: ₱572.50' in line:
                                    script_lines[i] = '    // Expected: ₱593.50 (₱500 vouchers + ₱93.50 fees)'
                                    print(f"  ✓ Updated line {i}: total comment")
                                
                                # Fix total assertion
                                elif 'pm.expect(deduction).to.be.closeTo(572.5, 1.0);' in line:
                                    script_lines[i] = '    pm.expect(deduction).to.be.closeTo(593.5, 1.0);'
                                    print(f"  ✓ Updated line {i}: total assertion")
                            
                            event['script']['exec'] = script_lines
            
            break
    
    with open(COLLECTION_PATH, 'w') as f:
        json.dump(collection, f, indent=2)
    
    print(f"\n✅ Test assertions updated")

if __name__ == '__main__':
    main()
