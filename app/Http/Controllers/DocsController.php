<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class DocsController extends Controller
{
    private const DOCS_PATH = 'docs';

    private const AVAILABLE_DOCS = [
        'bank-integration' => [
            'title' => 'Bank Integration Guide',
            'file' => 'BANK_INTEGRATION_GUIDE.md',
            'description' => 'Complete API integration guide for bank partners',
        ],
        'security' => [
            'title' => 'Security Specification',
            'file' => 'SECURITY_SPECIFICATION.md',
            'description' => 'Bank-grade security controls and compliance',
        ],
        'data-retention' => [
            'title' => 'Data Retention Policy',
            'file' => 'DATA_RETENTION_POLICY.md',
            'description' => 'GDPR/BSP compliance and privacy policy',
        ],
    ];

    public function index()
    {
        return Inertia::render('docs/Index', [
            'documents' => collect(self::AVAILABLE_DOCS)->map(function ($doc, $slug) {
                return [
                    'slug' => $slug,
                    'title' => $doc['title'],
                    'description' => $doc['description'],
                ];
            })->values(),
        ]);
    }

    public function show(string $slug)
    {
        if (! isset(self::AVAILABLE_DOCS[$slug])) {
            abort(404, 'Documentation not found');
        }

        $doc = self::AVAILABLE_DOCS[$slug];
        $filePath = base_path(self::DOCS_PATH.'/'.$doc['file']);

        if (! File::exists($filePath)) {
            abort(404, 'Documentation file not found');
        }

        $markdown = File::get($filePath);

        return Inertia::render('docs/Show', [
            'slug' => $slug,
            'title' => $doc['title'],
            'description' => $doc['description'],
            'content' => $markdown,
            'documents' => collect(self::AVAILABLE_DOCS)->map(function ($d, $s) {
                return [
                    'slug' => $s,
                    'title' => $d['title'],
                    'description' => $d['description'],
                ];
            })->values(),
        ]);
    }
}
