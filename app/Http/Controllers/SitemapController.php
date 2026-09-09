<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\JobPosting;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * XML sitemap of everything currently reachable on the public site --
     * home, job search, categories (with their listing pages), and every
     * publicly-visible job posting. Companies are deliberately excluded
     * for now: the Company public profile page (route ৫) doesn't exist
     * yet, so there is no URL to point to.
     */
    public function index(): Response
    {
        $jobPostings = JobPosting::query()->active()->select('id', 'slug', 'updated_at')->get();
        $categories = Category::query()->select('id', 'slug')->get();

        $xml = view('sitemap', [
            // Passed in as a variable rather than written literally in
            // sitemap.blade.php: this app's Blade compiler mishandles a
            // literal opening/closing PHP tag pair inside a raw-echo tag
            // in the Blade source, so the XML declaration has to arrive
            // as data instead.
            'xmlDeclaration' => '<?xml version="1.0" encoding="UTF-8"?>',
            'jobPostings' => $jobPostings,
            'categories' => $categories,
        ])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
