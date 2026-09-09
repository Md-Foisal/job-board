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
            'jobPostings' => $jobPostings,
            'categories' => $categories,
        ])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
