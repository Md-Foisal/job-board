<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * XML sitemap of everything reachable on the public site -- home, job
     * search, the static pages, every category listing, every active
     * company profile, and every publicly-visible job posting.
     *
     * Company profiles were excluded while route ৫ did not exist. It
     * does now, so they are listed; suspended companies are left out
     * because their pages are not public.
     */
    public function index(): Response
    {
        $jobPostings = JobPosting::query()->active()->select('id', 'slug', 'updated_at')->get();
        $categories = Category::query()->select('id', 'slug')->get();
        $companies = Company::query()
            ->where('account_status', AccountStatus::Active)
            ->select('id', 'slug', 'updated_at')
            ->get();

        $xml = view('sitemap', [
            // Passed in as a variable rather than written literally in
            // sitemap.blade.php: this app's Blade compiler mishandles a
            // literal opening/closing PHP tag pair inside a raw-echo tag
            // in the Blade source, so the XML declaration has to arrive
            // as data instead.
            'xmlDeclaration' => '<?xml version="1.0" encoding="UTF-8"?>',
            'jobPostings' => $jobPostings,
            'categories' => $categories,
            'companies' => $companies,
        ])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
