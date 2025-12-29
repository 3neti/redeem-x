#!/usr/bin/env python3
"""
Add error handling to all Generate Voucher test scripts.
This helps debug API error responses.
"""

import json
from pathlib import Path

def main():
    collection_path = Path('docs/postman/redeem-x-e2e-generation-billing.postman_collection.json')
    
    collection = json.load(open(collection_path))
    
    # Add error handling to all Generate Voucher requests
    for folder in collection['item']:
        gen_request = folder['item'][2]  # Generate Voucher is always index 2
        test_event = [e for e in gen_request['event'] if e['listen'] == 'test'][0]
        script = test_event['script']['exec']
        
        # Find where we parse JSON response
        for i, line in enumerate(script):
            if line == 'const jsonData = pm.response.json();':
                # Check if safety check already exists
                if i+1 < len(script) and 'Debug: Log response' in script[i+1]:
                    print(f'  → {folder["name"]}: Already has error handling')
                    break
                
                # Insert safety check after jsonData declaration
                new_lines = [
                    '',
                    '// Debug: Log response structure',
                    'if (!jsonData || !jsonData.data) {',
                    '    console.error("API Error Response:", JSON.stringify(jsonData, null, 2));',
                    '    pm.expect.fail("API returned error response instead of success");',
                    '}'
                ]
                
                for idx, new_line in enumerate(new_lines):
                    script.insert(i + 1 + idx, new_line)
                
                print(f'  ✓ {folder["name"]}: Added error handling')
                break
    
    # Save
    with open(collection_path, 'w') as f:
        json.dump(collection, f, indent=2, ensure_ascii=False)
    
    print(f'\n✅ Updated all {len(collection["item"])} folders')

if __name__ == '__main__':
    main()
