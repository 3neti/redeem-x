#!/usr/bin/env python3
"""
Generate Postman test folders for voucher generation billing tests.

This script programmatically creates test folders by:
1. Cloning the baseline folder structure
2. Modifying the "Generate Voucher" request body
3. Updating fee-related assertions
4. Adding instruction-specific validations
"""

import json
import copy
import sys
from pathlib import Path

# Fee structure (from InstructionItems database)
FEES = {
    'inputs.fields.email': 2.20,
    'inputs.fields.mobile': 2.30,
    'inputs.fields.name': 2.40,
    'inputs.fields.address': 2.50,
    'inputs.fields.birth_date': 2.60,
    'inputs.fields.gross_monthly_income': 2.70,
    'inputs.fields.signature': 2.80,
    'inputs.fields.location': 3.00,
    'inputs.fields.reference_code': 2.50,
    'inputs.fields.otp': 4.00,
    'inputs.fields.selfie': 4.00,
    'feedback.email': 1.00,
    'feedback.mobile': 1.80,
    'feedback.webhook': 1.90,
    'cash.validation.secret': 1.20,
    'cash.validation.mobile': 1.30,
    'validation.location': 3.00,
    'validation.time': 2.50,
    'rider.message': 2.00,
    'rider.url': 2.10,
}

def load_collection(filepath):
    """Load Postman collection from JSON file."""
    with open(filepath, 'r') as f:
        return json.load(f)

def save_collection(collection, filepath):
    """Save Postman collection to JSON file."""
    with open(filepath, 'w') as f:
        json.dump(collection, f, indent=2, ensure_ascii=False)
    print(f"✓ Saved collection to {filepath}")

def clone_baseline(collection):
    """Clone the baseline folder (01 - Simplest Voucher)."""
    baseline = collection['item'][0]
    return copy.deepcopy(baseline)

def create_input_field_single_folder(baseline, field_name, field_label):
    """
    Create a folder for testing a single input field.
    
    Args:
        baseline: Baseline folder to clone
        field_name: Field name (e.g., 'email')
        field_label: Display label (e.g., 'Email')
    """
    folder = copy.deepcopy(baseline)
    fee = FEES[f'inputs.fields.{field_name}']
    
    # Update folder metadata
    folder['name'] = f"03 - Input Fields - {field_label} (₱100 + ₱{fee:.2f})"
    folder['description'] = f"Test voucher with single input field: {field_label}. Expected: User -₱{100 + fee:.2f} (₱100 escrow + ₱{fee:.2f} fee), Products +₱{fee:.2f}"
    
    # Find and update "Generate Voucher" request (index 2)
    gen_request = folder['item'][2]
    
    # Update request body to include input field
    gen_request['request']['body']['raw'] = json.dumps({
        "amount": 100,
        "count": 1,
        "input_fields": [field_name]
    }, indent=2)
    
    # Update assertions in "Get Balance (After)" (index 3)
    balance_after = folder['item'][3]
    test_script = balance_after['event'][0]['script']['exec']
    
    # Replace "Simplest voucher has zero fees" with actual fee assertion
    for i, line in enumerate(test_script):
        if 'Simplest voucher has zero fees' in line:
            test_script[i] = f'pm.test("{field_label} field fee charged", function () {{'
            test_script[i+1] = f'    // {field_label} field costs ₱{fee:.2f}'
            test_script[i+2] = f'    pm.expect(feeAmount).to.be.closeTo({fee}, 0.5);'
        elif 'simplest voucher = no fees' in line:
            test_script[i] = f'    console.log(\'    - Fees: ₱\' + feeAmount.toFixed(2) + \' ({field_label} field)\');'
    
    # Update assertions in "Get System Balances (After)" (index 5)
    system_after = folder['item'][5]
    test_script2 = system_after['event'][0]['script']['exec']
    
    for i, line in enumerate(test_script2):
        if 'Products unchanged (no fees for simplest voucher)' in line:
            test_script2[i] = f'pm.test("Products received {field_label} field fee", function () {{'
            test_script2[i+1] = f'    pm.expect(productsIncrease).to.be.closeTo({fee}, 0.5);'
        elif 'Products increase matches fee (both zero)' in line:
            test_script2[i] = f'pm.test("Products increase matches {field_label} fee", function () {{'
            test_script2[i+3] = f'    pm.expect(productsIncrease).to.be.closeTo({fee}, 0.5);'
            test_script2[i+4] = f'    pm.expect(actualFee).to.be.closeTo({fee}, 0.5);'
    
    # Add instruction validation in "Get Voucher Details" (index 4)
    voucher_details = folder['item'][4]
    test_script3 = voucher_details['event'][0]['script']['exec']
    
    # Insert before "Processed status" test
    insert_pos = None
    for i, line in enumerate(test_script3):
        if 'Processed status' in line:
            insert_pos = i
            break
    
    if insert_pos:
        new_tests = [
            '',
            f'// {field_label} field validation',
            f'pm.test("{field_label} field present in inputs", function () {{',
            '    const fields = voucher.instructions.inputs.fields;',
            '    pm.expect(fields).to.be.an(\'array\');',
            f'    pm.expect(fields).to.include(\'{field_name}\');',
            '    pm.expect(fields.length).to.equal(1);',
            f'    console.log(\'✓ {field_label} field configured\');',
            '});',
        ]
        test_script3[insert_pos:insert_pos] = new_tests
    
    return folder

def create_input_fields_combo_folder(baseline, combo_name, fields, total_fee):
    """Create folder for testing multiple input fields together."""
    folder = copy.deepcopy(baseline)
    
    folder['name'] = f"04 - Input Fields - {combo_name} (₱100 + ₱{total_fee:.2f})"
    folder['description'] = f"Test voucher with {combo_name}: {', '.join(fields)}. Expected: User -₱{100 + total_fee:.2f}, Products +₱{total_fee:.2f}"
    
    # Update request body
    folder['item'][2]['request']['body']['raw'] = json.dumps({
        "amount": 100,
        "count": 1,
        "input_fields": fields
    }, indent=2)
    
    # Update assertions
    balance_after = folder['item'][3]['event'][0]['script']['exec']
    for i, line in enumerate(balance_after):
        if 'Simplest voucher has zero fees' in line:
            balance_after[i] = f'pm.test("{combo_name} fees charged", function () {{'
            balance_after[i+1] = f'    // {combo_name}: ₱{total_fee:.2f}'
            balance_after[i+2] = f'    pm.expect(feeAmount).to.be.closeTo({total_fee}, 0.5);'
        elif 'simplest voucher = no fees' in line:
            balance_after[i] = f'    console.log(\'    - Fees: ₱\' + feeAmount.toFixed(2) + \' ({combo_name})\');'
    
    system_after = folder['item'][5]['event'][0]['script']['exec']
    for i, line in enumerate(system_after):
        if 'Products unchanged' in line:
            system_after[i] = f'pm.test("Products received {combo_name} fees", function () {{'
            system_after[i+1] = f'    pm.expect(productsIncrease).to.be.closeTo({total_fee}, 0.5);'
        elif 'Products increase matches fee (both zero)' in line:
            system_after[i] = f'pm.test("Products match {combo_name} fees", function () {{'
            system_after[i+3] = f'    pm.expect(productsIncrease).to.be.closeTo({total_fee}, 0.5);'
            system_after[i+4] = f'    pm.expect(actualFee).to.be.closeTo({total_fee}, 0.5);'
    
    return folder

def create_feedback_folder(baseline, channels, total_fee):
    """Create folder for testing feedback channels."""
    folder = copy.deepcopy(baseline)
    channel_names = [ch.replace('feedback_', '').title() for ch in channels]
    name = ' + '.join(channel_names) if len(channels) > 1 else channel_names[0]
    
    folder['name'] = f"05 - Feedback - {name} (₱100 + ₱{total_fee:.2f})"
    folder['description'] = f"Test voucher with feedback channels: {name}. Expected: User -₱{100 + total_fee:.2f}, Products +₱{total_fee:.2f}"
    
    body = {"amount": 100, "count": 1}
    if 'feedback_email' in channels:
        body['feedback_email'] = 'test@example.com'
    if 'feedback_mobile' in channels:
        body['feedback_mobile'] = '+639171234567'
    if 'feedback_webhook' in channels:
        body['feedback_webhook'] = 'https://webhook.site/test'
    
    folder['item'][2]['request']['body']['raw'] = json.dumps(body, indent=2)
    
    # Update assertions similar to input fields
    balance_after = folder['item'][3]['event'][0]['script']['exec']
    for i, line in enumerate(balance_after):
        if 'Simplest voucher has zero fees' in line:
            balance_after[i] = f'pm.test("{name} feedback fees", function () {{'
            balance_after[i+1] = f'    // {name}: ₱{total_fee:.2f}'
            balance_after[i+2] = f'    pm.expect(feeAmount).to.be.closeTo({total_fee}, 0.5);'
        elif 'simplest voucher = no fees' in line:
            balance_after[i] = f'    console.log(\'    - Fees: ₱\' + feeAmount.toFixed(2) + \' (feedback)\');'
    
    return folder

def create_basic_settings_folder(baseline):
    """Create folder for basic settings (bulk generation)."""
    folder = copy.deepcopy(baseline)
    
    folder['name'] = "02 - Basic Settings - Bulk (₱1000 for 10 vouchers)"
    folder['description'] = "Test bulk voucher generation with custom settings. Expected: User -₱1000 (₱100×10), Products ±0"
    
    folder['item'][2]['request']['body']['raw'] = json.dumps({
        "amount": 100,
        "count": 10,
        "prefix": "PROMO",
        "mask": "***-***",  # 6 asterisks (max allowed)
        "ttl_days": 7
    }, indent=2)
    
    # Update assertions for bulk (10 vouchers)
    balance_after = folder['item'][3]['event'][0]['script']['exec']
    for i, line in enumerate(balance_after):
        if 'Deduction' in line and 'voucher amount' in line:
            # Baseline has 3 lines: test(...) {, assertion, }); - we need to replace all 3
            balance_after[i] = 'pm.test("Deduction equals 10 vouchers escrow", function () {'
            balance_after[i+1] = '    // 10 vouchers × ₱100 = ₱1000'
            # Insert new line for assertion, shift everything down
            balance_after.insert(i+2, '    pm.expect(deducted).to.be.closeTo(1000, 0.5);')
            # Now i+3 has the old '});' which we keep
    
    return folder

def create_cash_validation_folder(baseline, validation_type):
    """Create folder for cash validation (secret, mobile, both)."""
    folder = copy.deepcopy(baseline)
    
    validation_configs = {
        'secret': {
            'fee': 1.20,
            'body': {'amount': 100, 'count': 1, 'validation_secret': 'TEST1234'},
            'desc': 'secret code validation'
        },
        'mobile': {
            'fee': 1.30,
            'body': {'amount': 100, 'count': 1, 'validation_mobile': '+639171234567'},
            'desc': 'mobile number validation'
        },
        'both': {
            'fee': 2.50,
            'body': {'amount': 100, 'count': 1, 'validation_secret': 'TEST1234', 'validation_mobile': '+639171234567'},
            'desc': 'secret + mobile validation'
        }
    }
    
    config = validation_configs[validation_type]
    fee = config['fee']
    
    folder['name'] = f"06 - Cash Validation - {validation_type.title()} (₱100 + ₱{fee:.2f})"
    folder['description'] = f"Test voucher with {config['desc']}. Expected: User -₱{100 + fee:.2f}, Products +₱{fee:.2f}"
    
    folder['item'][2]['request']['body']['raw'] = json.dumps(config['body'], indent=2)
    
    # Update assertions
    balance_after = folder['item'][3]['event'][0]['script']['exec']
    for i, line in enumerate(balance_after):
        if 'Simplest voucher has zero fees' in line:
            balance_after[i] = f'pm.test("Cash validation fee charged", function () {{'
            balance_after[i+1] = f'    // {validation_type.title()} validation: ₱{fee:.2f}'
            balance_after[i+2] = f'    pm.expect(feeAmount).to.be.closeTo({fee}, 0.5);'
        elif 'simplest voucher = no fees' in line:
            balance_after[i] = f'    console.log(\'    - Fees: ₱\' + feeAmount.toFixed(2) + \' (validation)\');'
    
    return folder

def create_settlement_rail_folder(baseline, rail, fee_strategy):
    """Create folder for settlement rail & fee strategy tests."""
    folder = copy.deepcopy(baseline)
    
    folder['name'] = f"07 - Settlement Rail - {rail} / {fee_strategy.title()}"
    folder['description'] = f"Test voucher with {rail} rail and {fee_strategy} fee strategy. No instruction fees, tests disbursement configuration."
    
    folder['item'][2]['request']['body']['raw'] = json.dumps({
        'amount': 100,
        'count': 1,
        'settlement_rail': rail.lower(),
        'fee_strategy': fee_strategy
    }, indent=2)
    
    # No fee changes (settlement rail doesn't add instruction fees)
    # Just verify the configuration is stored
    voucher_details = folder['item'][4]['event'][0]['script']['exec']
    insert_pos = None
    for i, line in enumerate(voucher_details):
        if 'Processed status' in line:
            insert_pos = i
            break
    
    if insert_pos:
        new_tests = [
            '',
            '// Settlement rail validation',
            f'pm.test("Settlement rail is {rail}", function () {{',
            '    const cash = voucher.instructions.cash;',
            f'    pm.expect(cash.meta.settlement_rail).to.equal(\'{rail.lower()}\');',
            f'    console.log(\'✓ Settlement rail: {rail}\');',
            '});',
            '',
            f'pm.test("Fee strategy is {fee_strategy}", function () {{',
            '    const cash = voucher.instructions.cash;',
            f'    pm.expect(cash.meta.fee_strategy).to.equal(\'{fee_strategy}\');',
            f'    console.log(\'✓ Fee strategy: {fee_strategy}\');',
            '});',
        ]
        voucher_details[insert_pos:insert_pos] = new_tests
    
    return folder

def create_rider_folder(baseline, rider_type):
    """Create folder for rider information tests."""
    folder = copy.deepcopy(baseline)
    
    rider_configs = {
        'message': {
            'fee': 2.00,
            'body': {'amount': 100, 'count': 1, 'rider_message': 'Thank you for redeeming!'},
            'desc': 'message only'
        },
        'url': {
            'fee': 2.10,
            'body': {'amount': 100, 'count': 1, 'rider_url': 'https://example.com/promo'},
            'desc': 'URL only'
        },
        'full': {
            'fee': 4.10,
            'body': {
                'amount': 100,
                'count': 1,
                'rider_message': 'Thank you!',
                'rider_url': 'https://example.com/promo',
                'rider_timeout': 5,
                'rider_splash': True
            },
            'desc': 'full rider with timeout/splash'
        }
    }
    
    config = rider_configs[rider_type]
    fee = config['fee']
    
    folder['name'] = f"08 - Rider - {rider_type.title()} (₱100 + ₱{fee:.2f})"
    folder['description'] = f"Test voucher with rider {config['desc']}. Expected: User -₱{100 + fee:.2f}, Products +₱{fee:.2f}"
    
    folder['item'][2]['request']['body']['raw'] = json.dumps(config['body'], indent=2)
    
    # Update assertions
    balance_after = folder['item'][3]['event'][0]['script']['exec']
    for i, line in enumerate(balance_after):
        if 'Simplest voucher has zero fees' in line:
            balance_after[i] = f'pm.test("Rider fee charged", function () {{'
            balance_after[i+1] = f'    // {rider_type.title()} rider: ₱{fee:.2f}'
            balance_after[i+2] = f'    pm.expect(feeAmount).to.be.closeTo({fee}, 0.5);'
        elif 'simplest voucher = no fees' in line:
            balance_after[i] = f'    console.log(\'    - Fees: ₱\' + feeAmount.toFixed(2) + \' (rider)\');'
    
    return folder

def create_validation_folder(baseline, validation_type):
    """Create folder for location/time validation tests."""
    folder = copy.deepcopy(baseline)
    
    validation_configs = {
        'location': {
            'fee': 3.00,
            'body': {
                'amount': 100,
                'count': 1,
                'validation_location': {
                    'latitude': 14.5995,
                    'longitude': 120.9842,
                    'radius': 500,
                    'on_failure': 'reject'
                }
            },
            'desc': 'GPS-based location validation'
        },
        'time': {
            'fee': 2.50,
            'body': {
                'amount': 100,
                'count': 1,
                'validation_time': {
                    'window': {'start': '09:00', 'end': '17:00'},
                    'limit_minutes': 30
                }
            },
            'desc': 'time window and duration validation'
        }
    }
    
    config = validation_configs[validation_type]
    fee = config['fee']
    
    folder['name'] = f"09 - Validation - {validation_type.title()} (₱100 + ₱{fee:.2f})"
    folder['description'] = f"Test voucher with {config['desc']}. Expected: User -₱{100 + fee:.2f}, Products +₱{fee:.2f}"
    
    folder['item'][2]['request']['body']['raw'] = json.dumps(config['body'], indent=2)
    
    # Update assertions
    balance_after = folder['item'][3]['event'][0]['script']['exec']
    for i, line in enumerate(balance_after):
        if 'Simplest voucher has zero fees' in line:
            balance_after[i] = f'pm.test("{validation_type.title()} validation fee", function () {{'
            balance_after[i+1] = f'    // {validation_type.title()}: ₱{fee:.2f}'
            balance_after[i+2] = f'    pm.expect(feeAmount).to.be.closeTo({fee}, 0.5);'
        elif 'simplest voucher = no fees' in line:
            balance_after[i] = f'    console.log(\'    - Fees: ₱\' + feeAmount.toFixed(2) + \' (validation)\');'
    
    return folder

def create_complex_scenario_folder(baseline):
    """Create comprehensive test with multiple features."""
    folder = copy.deepcopy(baseline)
    
    # Calculate total: 5 vouchers × (₱100 + ₱14.50 fees) = ₱572.50
    # Fees: email 2.20 + mobile 2.30 + name 2.40 + location 3.00 + feedback_email 1.00 + feedback_mobile 1.80 + rider 2.00 = ₱14.70
    escrow = 500  # 5 × ₱100
    fees = 72.50  # 5 × ₱14.50
    total = escrow + fees
    
    folder['name'] = f"11 - Complex Scenario (₱{total:.2f} total)"
    folder['description'] = f"Comprehensive test: 5 vouchers with multiple features. Expected: User -₱{total:.2f} (₱{escrow} escrow + ₱{fees:.2f} fees)"
    
    folder['item'][2]['request']['body']['raw'] = json.dumps({
        'amount': 100,
        'count': 5,
        'prefix': 'PROMO',
        'mask': '****-****',
        'ttl_days': 7,
        'input_fields': ['email', 'mobile', 'name', 'location'],
        'feedback_email': 'test@example.com',
        'feedback_mobile': '+639171234567',
        'rider_message': 'Thank you for participating!'
    }, indent=2)
    
    # Update assertions for bulk with fees
    balance_after = folder['item'][3]['event'][0]['script']['exec']
    for i, line in enumerate(balance_after):
        if 'Deduction equals voucher amount' in line:
            balance_after[i] = f'pm.test("Total deduction correct", function () {{'
            balance_after[i+1] = f'    // 5 vouchers × (₱100 + ₱14.50) = ₱{total:.2f}'
            balance_after[i+2] = f'    pm.expect(deducted).to.be.closeTo({total}, 1.0);'
        elif 'Simplest voucher has zero fees' in line:
            balance_after[i] = f'pm.test("Complex scenario fees", function () {{'
            balance_after[i+1] = f'    // Total fees: ₱{fees:.2f}'
            balance_after[i+2] = f'    pm.expect(feeAmount).to.be.closeTo({fees}, 1.0);'
        elif 'simplest voucher = no fees' in line:
            balance_after[i] = f'    console.log(\'    - Fees: ₱\' + feeAmount.toFixed(2) + \' (complex)\');'
    
    system_after = folder['item'][5]['event'][0]['script']['exec']
    for i, line in enumerate(system_after):
        if 'Products unchanged' in line:
            system_after[i] = f'pm.test("Products received all fees", function () {{'
            system_after[i+1] = f'    pm.expect(productsIncrease).to.be.closeTo({fees}, 1.0);'
    
    return folder

def main():
    collection_path = Path('docs/postman/redeem-x-e2e-generation-billing.postman_collection.json')
    
    if not collection_path.exists():
        print(f"❌ Collection not found: {collection_path}")
        sys.exit(1)
    
    collection = load_collection(collection_path)
    print(f"✓ Loaded collection: {collection['info']['name']}")
    print(f"  Current folders: {len(collection['item'])}")
    
    baseline = clone_baseline(collection)
    print(f"✓ Cloned baseline: {baseline['name']}\n")
    
    folders_created = []
    
    # Batch 5: Cash validation
    print("📦 Batch 5: Cash Validation...")
    validation_types = [('secret', 1.20), ('mobile', 1.30), ('both', 2.50)]
    for val_type, fee in validation_types:
        folder = create_cash_validation_folder(baseline, val_type)
        collection['item'].append(folder)
        folders_created.append(folder['name'])
        print(f"  ✓ {val_type.title()} (₱{fee:.2f})")
    
    # Batch 6: Settlement rail & fee strategy
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
    
    # Batch 7: Rider information
    print("\n📦 Batch 7: Rider Information...")
    rider_types = [('message', 2.00), ('url', 2.10), ('full', 4.10)]
    for rider_type, fee in rider_types:
        folder = create_rider_folder(baseline, rider_type)
        collection['item'].append(folder)
        folders_created.append(folder['name'])
        print(f"  ✓ {rider_type.title()} (₱{fee:.2f})")
    
    # Batch 8: Location/time validation
    print("\n📦 Batch 8: Location & Time Validation...")
    validation_tests = [('location', 3.00), ('time', 2.50)]
    for val_type, fee in validation_tests:
        folder = create_validation_folder(baseline, val_type)
        collection['item'].append(folder)
        folders_created.append(folder['name'])
        print(f"  ✓ {val_type.title()} (₱{fee:.2f})")
    
    # Batch 9: Complex scenario
    print("\n📦 Batch 9: Complex Scenario...")
    folder = create_complex_scenario_folder(baseline)
    collection['item'].append(folder)
    folders_created.append(folder['name'])
    print(f"  ✓ Complex (₱572.50 total)")
    
    # Save
    save_collection(collection, collection_path)
    print(f"\n✅ Success! Created {len(folders_created)} new folders")
    print(f"   Total folders: {len(collection['item'])}")
    print("\n📋 Folders created:")
    for name in folders_created:
        print(f"   • {name}")

if __name__ == '__main__':
    main()
