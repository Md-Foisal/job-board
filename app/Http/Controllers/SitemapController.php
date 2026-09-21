<?php

namespace App\Http\Controllers;

use App\Enums\AccountStatus;
use App\Models\Category;
use App\Models\Company;
use App\Models\JobPosting;
use App\Support\PublicCache;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * XML sitemap of everything reachable on the public site -- home, job
     * search, the static pages, every category listing, every active
     * company profile, and every publicly-visible job posting.
     *
     * Companies the public cannot see -- banned, or hidden while reports
     * about them are reviewed -- are left out: their pages answer 404, and
     * listing them would tell anyone reading this file they exist.
     */
    public function index(): Response
    {
        // Crawlers ask for this repeatedly and it lists every public row.
        return response(PublicCache::remember('sitemap', fn () => $this->render()), 200)
            ->header('Content-Type', 'application/xml');
    }

    private function render(): string
    {
        $jobPostings = JobPosting::query()->active()->select('id', 'slug', 'updated_at')->get();
        $categories = Category::query()->select('id', 'slug')->get();
        $companies = Company::query()
            ->where('account_status', AccountStatus::Active)
            ->notHiddenByReports()
            ->select('id', 'slug', 'updated_at')
            ->get();

        return view('sitemap', [
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
    }
}
