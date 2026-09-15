{!! $xmlDeclaration !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('home') }}</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ route('jobs.index') }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    @foreach (['about', 'privacy', 'terms'] as $staticPage)
    <url>
        <loc>{{ route($staticPage) }}</loc>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>
    @endforeach
    @foreach ($categories as $category)
    <url>
        <loc>{{ route('categories.show', $category) }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach
    @foreach ($companies as $company)
    <url>
        <loc>{{ route('companies.show', $company) }}</loc>
        <lastmod>{{ $company->updated_at->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.5</priority>
    </url>
    @endforeach
    @foreach ($jobPostings as $jobPosting)
    <url>
        <loc>{{ route('jobs.show', $jobPosting) }}</loc>
        <lastmod>{{ $jobPosting->updated_at->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
    @endforeach
</urlset>
