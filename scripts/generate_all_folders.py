#!/usr/bin/env python3
"""
Generate ALL Postman test folders for voucher generation billing tests.
This creates all 27 folders (excluding baseline) in one run.
"""

import sys
sys.path.insert(0, 'scripts')
from generate_postman_folders import *

def main():
    collection_path = Path('docs/postman/redeem-x-e2e-generation-billing.postman_collection.json')
    
    if not collection_path.exists():
        print(f"❌ Collection not found: {collection_path}")
        sys.exit(1)
    
    collection = load_collection(collection_path)
    print(f"✓ Loaded collection: {collection['info']['name']}")
    print(f"  Current folders: {len(collection['item'])}\n")
    
    baseline = clone_baseline(collection)
    
    folders_created = []
    
    # BATCH 1: Basic Settings (1 folder)
    print("📦 Batch 1: Basic Settings...")
    folder = create_basic_settings_folder(baseline)
    collection['item'].append(folder)
    folders_created.append(folder['name'])
    print(f"  ✓ Bulk Generation")
    
    # BATCH 2: Single Input Fields (6 folders)
    print("\n📦 Batch 2: Single Input Fields...")
    single_fields = [
        ('email', 'Email', 2.20),
        ('mobile', 'Mobile', 2.30),
        ('name', 'Name', 2.40),
        ('location', 'Location', 3.00),
        ('signature', 'Signature', 2.80),
        ('selfie', 'Selfie', 4.00),
    ]
    for field, label, fee in single_fields:
        folder = create_input_field_single_folder(baseline, field, label)
        collection['item'].append(folder)
        folders_created.append(folder['name'])
        print(f"  ✓ {label} (₱{fee:.2f})")
    
    # BATCH 3: Input Field Combinations (4 folders)
    print("\n📦 Batch 3: Input Field Combinations...")
    combos = [
        ('Basic KYC', ['email', 'mobile', 'name'], 6.90),
        ('Identity Verification', ['name', 'address', 'birth_date'], 7.50),
        ('Digital Signature', ['email', 'mobile', 'signature'], 7.30),
        ('Full Profile', ['email', 'mobile', 'name', 'address', 'birth_date'], 12.20),
    ]
    for name, fields, fee in combos:
        folder = create_input_fields_combo_folder(baseline, name, fields, fee)
        collection['item'].append(folder)
        folders_created.append(folder['name'])
        print(f"  ✓ {name} (₱{fee:.2f})")
    
    # BATCH 4: Feedback Channels (4 folders)
    print("\n📦 Batch 4: Feedback Channels...")
    feedback_tests = [
        (['feedback_email'], 1.00),
        (['feedback_mobile'], 1.80),
        (['feedback_webhook'], 1.90),
        (['feedback_email', 'feedback_mobile', 'feedback_webhook'], 4.70),
    ]
    for channels, fee in feedback_tests:
        folder = create_feedback_folder(baseline, channels, fee)
        collection['item'].append(folder)
        folders_created.append(folder['name'])
        names = [c.replace('feedback_', '').title() for c in channels]
        print(f"  ✓ {'+'.join(names)} (₱{fee:.2f})")
    
    # BATCH 5: Cash Validation (3 folders)
    print("\n📦 Batch 5: Cash Validation...")
    validation_types = [('secret', 1.20), ('mobile', 1.30), ('both', 2.50)]
    for val_type, fee in validation_types:
        folder = create_cash_validation_folder(baseline, val_type)
        collection['item'].append(folder)
        folders_created.append(folder['name'])
        print(f"  ✓ {val_type.title()} (₱{fee:.2f})")
    
    # BATCH 6: Settlement Rail & Fee Strategy (3 folders)
    print("\n📦 Batch 6: Settlement Rail & Fee Strategy...")
    rail_tests = [
        ('INSTAPAY', 'absorb'),
        ('INSTAPAY', 'include'),
        ('PESONET', 'absorb'),
    ]
    for rail, strategy in rail_tests:
        folder = create_settlement_rail_folder(baseline, rail, strategy)
        collection['item'].append(folder)
        folders_created.append(folder['name'])
        print(f"  ✓ {rail} / {strategy}")
    
    # BATCH 7: Rider Information (3 folders)
    print("\n📦 Batch 7: Rider Information...")
    rider_types = [('message', 2.00), ('url', 2.10), ('full', 4.10)]
    for rider_type, fee in rider_types:
        folder = create_rider_folder(baseline, rider_type)
        collection['item'].append(folder)
        folders_created.append(folder['name'])
        print(f"  ✓ {rider_type.title()} (₱{fee:.2f})")
    
    # BATCH 8: Location & Time Validation (2 folders)
    print("\n📦 Batch 8: Location & Time Validation...")
    validation_tests = [('location', 3.00), ('time', 2.50)]
    for val_type, fee in validation_tests:
        folder = create_validation_folder(baseline, val_type)
        collection['item'].append(folder)
        folders_created.append(folder['name'])
        print(f"  ✓ {val_type.title()} (₱{fee:.2f})")
    
    # BATCH 9: Complex Scenario (1 folder)
    print("\n📦 Batch 9: Complex Scenario...")
    folder = create_complex_scenario_folder(baseline)
    collection['item'].append(folder)
    folders_created.append(folder['name'])
    print(f"  ✓ Complex (₱572.50 total)")
    
    # Save
    save_collection(collection, collection_path)
    print(f"\n✅ Success! Created {len(folders_created)} new folders")
    print(f"   Total folders: {len(collection['item'])} (including baseline)")
    print("\n📋 Summary:")
    print(f"   01: Baseline (₱100)")
    print(f"   02: Basic Settings (1 folder)")
    print(f"   03: Single Input Fields (6 folders)")
    print(f"   04: Input Combinations (4 folders)")
    print(f"   05: Feedback Channels (4 folders)")
    print(f"   06: Cash Validation (3 folders)")
    print(f"   07: Settlement Rail (3 folders)")
    print(f"   08: Rider (3 folders)")
    print(f"   09: Location/Time Validation (2 folders)")
    print(f"   11: Complex Scenario (1 folder)")

if __name__ == '__main__':
    main()
