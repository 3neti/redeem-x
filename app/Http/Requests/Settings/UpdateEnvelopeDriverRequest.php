<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEnvelopeDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add policy check if needed
    }

    public function rules(): array
    {
        return [
            // Basic Info (id and version come from route, not editable)
            'title' => ['required', 'string', 'min:3', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'domain' => ['nullable', 'string', 'max:50'],
            'issuer_type' => ['nullable', 'string', 'max:50'],

            // Option to save as new version
            'save_as_new_version' => ['boolean'],
            'new_version' => ['required_if:save_as_new_version,true', 'nullable', 'string', 'regex:/^\d+\.\d+\.\d+$/'],

            // Payload Schema
            'payload_schema' => ['nullable', 'array'],
            'payload_schema.type' => ['required_with:payload_schema', 'string', 'in:object'],
            'payload_schema.properties' => ['nullable', 'array'],
            'payload_schema.required' => ['nullable', 'array'],

            // Documents
            'documents' => ['nullable', 'array'],
            'documents.*.type' => ['required', 'string', 'max:50'],
            'documents.*.title' => ['required', 'string', 'max:100'],
            'documents.*.allowed_mimes' => ['required', 'array', 'min:1'],
            'documents.*.allowed_mimes.*' => ['string'],
            'documents.*.max_size_mb' => ['required', 'integer', 'min:1', 'max:50'],
            'documents.*.multiple' => ['boolean'],

            // Checklist
            'checklist' => ['nullable', 'array'],
            'checklist.*.key' => ['required', 'string', 'max:50'],
            'checklist.*.label' => ['required', 'string', 'max:200'],
            'checklist.*.kind' => ['required', 'string', 'in:document,payload_field,signal,attestation'],
            'checklist.*.doc_type' => ['required_if:checklist.*.kind,document', 'nullable', 'string'],
            'checklist.*.payload_pointer' => ['required_if:checklist.*.kind,payload_field', 'nullable', 'string'],
            'checklist.*.signal_key' => ['required_if:checklist.*.kind,signal', 'nullable', 'string'],
            'checklist.*.required' => ['boolean'],
            'checklist.*.review' => ['string', 'in:none,optional,required'],

            // Signals
            'signals' => ['nullable', 'array'],
            'signals.*.key' => ['required', 'string', 'max:50'],
            'signals.*.type' => ['required', 'string', 'in:boolean,string'],
            'signals.*.source' => ['required', 'string', 'in:host,integration'],
            'signals.*.default' => ['nullable'],
            'signals.*.required' => ['boolean'],
            'signals.*.signal_category' => ['string', 'in:decision,integration'],
            'signals.*.system_settable' => ['boolean'],

            // Gates
            'gates' => ['nullable', 'array'],
            'gates.*.key' => ['required', 'string', 'max:50'],
            'gates.*.rule' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_version.regex' => 'Version must be in semantic format (e.g., 1.0.0).',
            'documents.*.type.required' => 'Each document type must have a unique type identifier.',
            'checklist.*.kind.in' => 'Checklist item kind must be: document, payload_field, signal, or attestation.',
        ];
    }
}
